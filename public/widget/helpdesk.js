(function () {
    const script = document.currentScript;
    const widgetKey = script?.dataset?.key || script?.getAttribute('data-key');

    if (!widgetKey) {
        return;
    }

    const apiBase = (script.src || '').replace(/\/widget\/helpdesk\.js.*$/, '') + '/api/v1/chat';
    const deflectionApiBase = apiBase.replace('/chat', '/deflection');
    const storageKey = 'helpdesk_chat_session';

    const state = {
        open: false,
        online: true,
        greeting: 'Hi! How can we help you today?',
        offlineMessage: 'We are currently offline.',
        deflectionEnabled: false,
        deflectionSessionId: null,
        deflectionQuery: '',
        sessionUuid: null,
        sessionToken: null,
        messages: [],
        lastPoll: null,
        realtime: null,
        ws: null,
        wsReconnectTimer: null,
        pollTimer: null,
        pulse: null,
        mode: 'intro',
        unreadCount: 0,
        lastSeenMessageId: 0,
    };

    const saved = readStorage();
    if (saved?.sessionUuid && saved?.sessionToken) {
        state.sessionUuid = saved.sessionUuid;
        state.sessionToken = saved.sessionToken;
        state.mode = 'chat';
        state.unreadCount = saved.unreadCount ?? 0;
        state.lastSeenMessageId = saved.lastSeenMessageId ?? 0;
    }

    const root = document.createElement('div');
    root.id = 'helpdesk-chat-root';
    document.body.appendChild(root);

    const shadow = root.attachShadow({ mode: 'open' });
    shadow.innerHTML = `
        <style>
            * { box-sizing: border-box; font-family: system-ui, -apple-system, sans-serif; }
            .launcher-wrap {
                position: fixed; right: 20px; bottom: 20px; z-index: 2147483000;
            }
            .launcher {
                width: 56px; height: 56px; border-radius: 9999px; border: none;
                background: #2563eb; color: #fff; font-size: 24px; cursor: pointer;
                box-shadow: 0 10px 25px rgba(37, 99, 235, 0.35);
            }
            .launcher-badge {
                position: absolute; top: -2px; right: -2px;
                min-width: 20px; height: 20px; border-radius: 9999px;
                background: #ef4444; color: #fff; font-size: 11px; font-weight: 700;
                display: none; align-items: center; justify-content: center;
                padding: 0 6px; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(15, 23, 42, 0.2);
            }
            .launcher-badge.visible { display: inline-flex; }
            .panel {
                position: fixed; right: 20px; bottom: 88px; z-index: 2147483000;
                width: 360px; max-width: calc(100vw - 32px); height: 520px; max-height: calc(100vh - 120px);
                background: #fff; border-radius: 16px; border: 1px solid #e2e8f0;
                box-shadow: 0 20px 50px rgba(15, 23, 42, 0.18);
                display: none; flex-direction: column; overflow: hidden;
            }
            .panel.open { display: flex; }
            .header { padding: 16px; background: #2563eb; color: #fff; font-weight: 600; }
            .subheader { padding: 10px 16px; background: #eff6ff; color: #1e40af; font-size: 13px; }
            .messages { flex: 1; overflow-y: auto; padding: 16px; background: #f8fafc; }
            .bubble { max-width: 85%; margin-bottom: 10px; padding: 10px 12px; border-radius: 12px; font-size: 14px; line-height: 1.4; white-space: pre-wrap; word-break: break-word; }
            .bubble-body { white-space: pre-wrap; }
            .bubble.visitor { margin-left: auto; background: #2563eb; color: #fff; border-bottom-right-radius: 4px; }
            .bubble.agent { margin-right: auto; background: #fff; color: #0f172a; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; }
            .meta { font-size: 11px; opacity: 0.7; margin-bottom: 4px; }
            .form { padding: 12px; border-top: 1px solid #e2e8f0; background: #fff; }
            .field { width: 100%; margin-bottom: 8px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; }
            .actions { display: flex; gap: 8px; }
            .btn { flex: 1; border: none; border-radius: 10px; padding: 10px 12px; font-size: 14px; cursor: pointer; }
            .btn-primary { background: #2563eb; color: #fff; }
            .btn-secondary { background: #e2e8f0; color: #334155; }
            .status { padding: 8px 16px; font-size: 12px; color: #64748b; }
        </style>
        <div class="launcher-wrap">
            <button class="launcher" type="button" aria-label="Open chat">💬</button>
            <span class="launcher-badge" aria-live="polite"></span>
        </div>
        <div class="panel">
            <div class="header">Support chat</div>
            <div class="subheader"></div>
            <div class="messages"></div>
            <div class="status"></div>
            <div class="form"></div>
        </div>
    `;

    const launcher = shadow.querySelector('.launcher');
    const launcherBadge = shadow.querySelector('.launcher-badge');
    const panel = shadow.querySelector('.panel');
    const subheader = shadow.querySelector('.subheader');
    const messagesEl = shadow.querySelector('.messages');
    const statusEl = shadow.querySelector('.status');
    const formEl = shadow.querySelector('.form');

    launcher.addEventListener('click', () => {
        state.open = !state.open;
        panel.classList.toggle('open', state.open);
        if (state.open) {
            markWidgetRead();
            bootstrap();
            if (state.mode === 'chat' && state.sessionUuid) {
                startPolling();
            }
        } else {
            stopPolling();
        }
        updateLauncherBadge();
    });

    async function bootstrap() {
        try {
            const config = await api('GET', '/config');
            state.online = config.online;
            state.greeting = config.greeting || state.greeting;
            state.offlineMessage = config.offline_message || state.offlineMessage;
            state.deflectionEnabled = !!config.deflection_enabled;
            subheader.textContent = state.online ? state.greeting : state.offlineMessage;

            if (state.mode === 'chat' && state.sessionUuid && state.sessionToken) {
                renderChatForm();
                await refreshMessages(true);
                connectRealtime(state.realtime);
                startPolling();
            } else if (state.deflectionEnabled && state.mode !== 'chat') {
                state.mode = 'bot';
                renderBotForm();
            } else {
                renderIntroForm();
            }
        } catch (error) {
            statusEl.textContent = error.message || 'Unable to load chat.';
        }
    }

    function renderIntroForm() {
        formEl.innerHTML = `
            <input class="field name" type="text" placeholder="Your name" />
            <input class="field email" type="email" placeholder="Email${state.online ? ' (optional)' : ''}" />
            <textarea class="field message" rows="3" placeholder="How can we help?"></textarea>
            <div class="actions">
                <button class="btn btn-primary start" type="button">Start chat</button>
            </div>
        `;

        formEl.querySelector('.start').addEventListener('click', startChat);
    }

    function renderBotForm() {
        messagesEl.innerHTML = '<div class="bubble agent">Ask a question and I will search our help articles first.</div>';
        formEl.innerHTML = `
            <textarea class="field bot-query" rows="3" placeholder="How can we help?"></textarea>
            <div class="actions">
                <button class="btn btn-primary bot-ask" type="button">Ask</button>
                <button class="btn btn-secondary bot-agent" type="button">Talk to agent</button>
            </div>
        `;
        formEl.querySelector('.bot-ask').addEventListener('click', askBot);
        formEl.querySelector('.bot-agent').addEventListener('click', () => {
            state.mode = 'intro';
            messagesEl.innerHTML = '';
            renderIntroForm();
        });
    }

    async function askBot() {
        const query = formEl.querySelector('.bot-query')?.value || '';
        if (!query.trim()) {
            return;
        }

        state.deflectionQuery = query;
        statusEl.textContent = 'Searching…';

        try {
            const result = await deflectionApi('POST', '/ask', {
                query,
                channel: 'widget',
                session_id: state.deflectionSessionId,
            });

            state.deflectionSessionId = result.session_id;
            state.mode = 'bot-answer';
            messagesEl.innerHTML = `
                <div class="bubble visitor"><div class="meta">You</div>${escapeHtml(query)}</div>
                <div class="bubble agent"><div class="meta">Help bot</div>${escapeHtml(result.answer || '')}</div>
            `;
            formEl.innerHTML = `
                <div class="actions">
                    <button class="btn btn-primary bot-helpful" type="button">Helpful</button>
                    <button class="btn btn-secondary bot-more" type="button">Need more help</button>
                </div>
            `;
            formEl.querySelector('.bot-helpful').addEventListener('click', () => sendBotFeedback(true));
            formEl.querySelector('.bot-more').addEventListener('click', () => sendBotFeedback(false));
            statusEl.textContent = '';
        } catch (error) {
            statusEl.textContent = error.message || 'Could not get an answer.';
        }
    }

    async function sendBotFeedback(helpful) {
        if (state.deflectionSessionId) {
            await deflectionApi('POST', '/feedback', {
                session_id: state.deflectionSessionId,
                channel: 'widget',
                helpful,
            }).catch(() => {});
        }

        if (helpful) {
            messagesEl.innerHTML += '<div class="bubble agent">Glad that helped!</div>';
            formEl.innerHTML = '<button class="btn btn-secondary bot-again" type="button">Ask another question</button>';
            formEl.querySelector('.bot-again').addEventListener('click', () => {
                state.mode = 'bot';
                renderBotForm();
            });
            return;
        }

        state.mode = 'intro';
        messagesEl.innerHTML = '';
        renderIntroForm();
        const messageField = formEl.querySelector('.message');
        if (messageField) {
            messageField.value = state.deflectionQuery;
        }
    }

    function renderChatForm() {
        formEl.innerHTML = `
            <textarea class="field reply" rows="2" placeholder="Type a message..."></textarea>
            <div class="actions">
                <button class="btn btn-primary send" type="button">Send</button>
            </div>
        `;

        const reply = formEl.querySelector('.reply');
        const send = () => sendMessage(reply.value).then(() => { reply.value = ''; });
        formEl.querySelector('.send').addEventListener('click', send);
        reply.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                send();
            }
        });
    }

    function renderOfflineSuccess(result) {
        messagesEl.innerHTML = `<div class="bubble agent">${escapeHtml(result.message || 'Thanks! We will email you soon.')}</div>`;
        formEl.innerHTML = `<div class="status">Ticket ${escapeHtml(result.ticket_number || '')} created.</div>`;
        clearStorage();
    }

    function updateLauncherBadge() {
        if (!launcherBadge) {
            return;
        }

        if (state.unreadCount > 0 && !state.open) {
            launcherBadge.textContent = state.unreadCount > 99 ? '99+' : String(state.unreadCount);
            launcherBadge.classList.add('visible');
        } else {
            launcherBadge.textContent = '';
            launcherBadge.classList.remove('visible');
        }
    }

    function markWidgetRead() {
        state.unreadCount = 0;
        state.lastSeenMessageId = state.messages.reduce((max, message) => Math.max(max, message.id || 0), state.lastSeenMessageId);
        writeStorage();
        updateLauncherBadge();
    }

    function noteIncomingMessage(message) {
        if (!message?.id || state.open || message.author_type !== 'agent') {
            return;
        }

        if (message.id <= state.lastSeenMessageId) {
            return;
        }

        state.unreadCount += 1;
        updateLauncherBadge();
    }

    function renderMessages() {
        messagesEl.innerHTML = state.messages.map((message) => `
            <div class="bubble ${message.author_type === 'visitor' ? 'visitor' : 'agent'}">
                <div class="meta">${escapeHtml(message.author_name || '')}</div>
                <div class="bubble-body">${formatMessageBody(message)}</div>
            </div>
        `).join('');
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    async function startChat() {
        const name = formEl.querySelector('.name')?.value || '';
        const email = formEl.querySelector('.email')?.value || '';
        const message = formEl.querySelector('.message')?.value || '';

        statusEl.textContent = 'Starting chat...';

        try {
            const result = await api('POST', '/sessions', {
                name,
                email,
                message,
                page_url: window.location.href,
                session_uuid: state.sessionUuid,
            });

            if (result.mode === 'offline') {
                renderOfflineSuccess(result);
                statusEl.textContent = '';
                return;
            }

            state.mode = 'chat';
            state.sessionUuid = result.session_uuid;
            state.sessionToken = result.session_token;
            state.messages = result.messages || [];
            state.realtime = result.realtime || null;
            writeStorage();
            renderMessages();
            renderChatForm();
            connectRealtime(state.realtime);
            startPolling();
            statusEl.textContent = '';
        } catch (error) {
            statusEl.textContent = error.message || 'Could not start chat.';
        }
    }

    async function sendMessage(body) {
        if (!body.trim()) {
            return;
        }

        try {
            await api('POST', `/sessions/${state.sessionUuid}/messages`, { body });
            await refreshMessages(true);
        } catch (error) {
            statusEl.textContent = error.message || 'Could not send message.';
        }
    }

    async function refreshMessages(initial) {
        const params = new URLSearchParams();
        if (!initial && state.lastPoll) {
            params.set('since', state.lastPoll);
        }
        if (state.pulse) {
            params.set('pulse', String(state.pulse));
        }

        const suffix = params.toString() ? `?${params.toString()}` : '';
        const result = await api('GET', `/sessions/${state.sessionUuid}/poll${suffix}`);

        if (initial) {
            state.messages = result.messages || [];
            if (state.open) {
                markWidgetRead();
            }
        } else if (result.messages?.length) {
            result.messages.forEach((message) => {
                if (!state.messages.some((item) => item.id === message.id)) {
                    state.messages.push(message);
                    noteIncomingMessage(message);
                }
            });
        }

        state.lastPoll = result.server_time || state.lastPoll;
        state.pulse = result.pulse || state.pulse;

        if (result.realtime) {
            state.realtime = result.realtime;
            connectRealtime(state.realtime);
        }

        renderMessages();
    }

    function appendRealtimeMessage(message) {
        if (!message?.id || state.messages.some((item) => item.id === message.id)) {
            return;
        }

        const normalized = {
            id: message.id,
            body: message.body,
            author_type: message.author_type,
            author_name: message.author_name,
            created_at: message.created_at,
        };
        state.messages = state.messages.concat([normalized]);
        noteIncomingMessage(normalized);
        renderMessages();
    }

    function disconnectRealtime() {
        if (state.wsReconnectTimer) {
            clearTimeout(state.wsReconnectTimer);
            state.wsReconnectTimer = null;
        }

        if (state.ws) {
            state.ws.close();
            state.ws = null;
        }
    }

    function startPolling() {
        stopPolling();

        if (!state.sessionUuid || state.mode !== 'chat') {
            return;
        }

        state.pollTimer = setInterval(() => {
            if (state.open && state.sessionUuid && state.mode === 'chat') {
                refreshMessages(false).catch(() => {});
            }
        }, 4000);
    }

    function stopPolling() {
        if (state.pollTimer) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function connectRealtime(realtime) {
        if (!realtime?.url || !realtime?.channel || !realtime?.token) {
            return;
        }

        disconnectRealtime();

        const url = `${realtime.url}?token=${encodeURIComponent(realtime.token)}`;
        const socket = new WebSocket(url);
        state.ws = socket;

        socket.addEventListener('open', () => {
            socket.send(JSON.stringify({ action: 'subscribe', channel: realtime.channel }));
        });

        socket.addEventListener('message', (event) => {
            let payload;

            try {
                payload = JSON.parse(event.data);
            } catch {
                return;
            }

            if (payload.event === 'message.created' && payload.data?.message) {
                appendRealtimeMessage(payload.data.message);
            }
        });

        socket.addEventListener('close', () => {
            if (state.open && state.realtime) {
                state.wsReconnectTimer = setTimeout(() => connectRealtime(state.realtime), 3000);
            }
        });
    }

    async function api(method, path, body) {
        const headers = {
            Accept: 'application/json',
            'X-Widget-Key': widgetKey,
        };

        if (state.sessionToken) {
            headers['X-Session-Token'] = state.sessionToken;
        }

        const options = { method, headers };

        if (body) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const response = await fetch(apiBase + path, options);
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Request failed';
            throw new Error(message);
        }

        return payload;
    }

    async function deflectionApi(method, path, body) {
        const headers = {
            Accept: 'application/json',
            'X-Widget-Key': widgetKey,
            'Content-Type': 'application/json',
        };

        const response = await fetch(deflectionApiBase + path, {
            method,
            headers,
            body: body ? JSON.stringify(body) : undefined,
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const message = payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Request failed';
            throw new Error(message);
        }

        return payload;
    }

    function readStorage() {
        try {
            return JSON.parse(localStorage.getItem(storageKey) || 'null');
        } catch {
            return null;
        }
    }

    function writeStorage() {
        localStorage.setItem(storageKey, JSON.stringify({
            sessionUuid: state.sessionUuid,
            sessionToken: state.sessionToken,
            unreadCount: state.unreadCount,
            lastSeenMessageId: state.lastSeenMessageId,
        }));
    }

    updateLauncherBadge();

    function clearStorage() {
        localStorage.removeItem(storageKey);
        state.sessionUuid = null;
        state.sessionToken = null;
    }

    function formatMessageBody(message) {
        const body = message.author_type === 'agent'
            ? htmlToPlainText(message.body || '')
            : String(message.body || '');

        return escapeHtml(body);
    }

    function htmlToPlainText(html) {
        const value = String(html || '');

        if (!value.includes('<')) {
            return value;
        }

        const container = document.createElement('div');
        container.innerHTML = value
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>/gi, '\n\n');

        return (container.textContent || container.innerText || '')
            .replace(/\n{3,}/g, '\n\n')
            .trim();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
})();
