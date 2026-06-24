import { createHmac, timingSafeEqual } from 'node:crypto';
import { WebSocketServer } from 'ws';
import Redis from 'ioredis';

const port = Number(process.env.REALTIME_WS_PORT || 8080);
const host = process.env.REALTIME_WS_HOST || '127.0.0.1';
const redisPrefix = process.env.REALTIME_REDIS_PREFIX || 'helpdesk:realtime:';
const appKey = (process.env.APP_KEY || '').replace(/^base64:/, '');
const appKeyBuffer = appKey ? Buffer.from(appKey, 'base64') : Buffer.from(process.env.REALTIME_APP_KEY || 'local-dev-key');

const redisUrl = process.env.REDIS_URL || `redis://${process.env.REDIS_HOST || '127.0.0.1'}:${process.env.REDIS_PORT || 6379}`;

const publisher = new Redis(redisUrl);
const subscriber = new Redis(redisUrl);

const channelClients = new Map();

function verifyToken(token, channel) {
    if (!token || !channel) {
        return false;
    }

    const parts = token.split('.');

    if (parts.length !== 2) {
        return false;
    }

    const [encoded, signature] = parts;
    const expected = createHmac('sha256', appKeyBuffer).update(encoded).digest('hex');

    try {
        if (!timingSafeEqual(Buffer.from(expected), Buffer.from(signature))) {
            return false;
        }
    } catch {
        return false;
    }

    const json = Buffer.from(encoded.replace(/-/g, '+').replace(/_/g, '/'), 'base64').toString('utf8');
    const payload = JSON.parse(json);

    if (!payload.exp || payload.exp < Math.floor(Date.now() / 1000)) {
        return false;
    }

    if (payload.scope === 'agent') {
        if (payload.tenant_id && channel && !channel.startsWith(`${payload.tenant_id}.`)) {
            return false;
        }

        const suffix = payload.tenant_id && channel.startsWith(`${payload.tenant_id}.`)
            ? channel.slice(payload.tenant_id.length + 1)
            : channel;

        if (suffix === 'workspace') {
            return true;
        }

        const userMatch = suffix.match(/^user\.(\d+)$/);

        if (userMatch) {
            return Number(payload.user_id) === Number(userMatch[1]);
        }

        return false;
    }

    return payload.channel === channel;
}

function addClient(channel, ws) {
    if (!channelClients.has(channel)) {
        channelClients.set(channel, new Set());
    }

    channelClients.get(channel).add(ws);
    ws.subscribedChannels = ws.subscribedChannels || new Set();
    ws.subscribedChannels.add(channel);
}

function removeClient(ws) {
    for (const channel of ws.subscribedChannels || []) {
        channelClients.get(channel)?.delete(ws);
    }
}

function broadcast(channel, payload) {
    const clients = channelClients.get(channel);

    if (!clients) {
        return;
    }

    const message = JSON.stringify({ ...payload, channel });

    for (const client of clients) {
        if (client.readyState === 1) {
            client.send(message);
        }
    }
}

const wss = new WebSocketServer({ host, port });

wss.on('error', (error) => {
    if (error.code === 'EADDRINUSE') {
        console.error(`Port ${port} is already in use. Another realtime server is probably running.`);
        console.error(`Stop it with: lsof -nP -iTCP:${port} -sTCP:LISTEN`);
        console.error('Or set REALTIME_WS_PORT in .env to a free port.');
    } else {
        console.error('Realtime server error:', error.message);
    }

    process.exit(1);
});

wss.on('listening', () => {
    console.log(`helpefi realtime server listening on ws://${host}:${port}`);
});

wss.on('connection', (ws, request) => {
    const url = new URL(request.url, 'http://localhost');
    ws.authToken = url.searchParams.get('token');

    ws.on('message', (raw) => {
        let data;

        try {
            data = JSON.parse(String(raw));
        } catch {
            return;
        }

        if (data.action !== 'subscribe' || !data.channel) {
            return;
        }

        const subscriptionToken = data.token || ws.authToken;

        if (!verifyToken(subscriptionToken, data.channel)) {
            ws.send(JSON.stringify({ event: 'error', data: { message: 'Unauthorized subscription.' } }));

            return;
        }

        addClient(data.channel, ws);
        ws.send(JSON.stringify({ event: 'subscribed', data: { channel: data.channel } }));
    });

    ws.on('close', () => removeClient(ws));
});

subscriber.psubscribe(`${redisPrefix}*`);
subscriber.on('pmessage', (_pattern, fullChannel, message) => {
    const channel = fullChannel.replace(redisPrefix, '');

    try {
        broadcast(channel, JSON.parse(message));
    } catch {
    }
});

export { wss };
