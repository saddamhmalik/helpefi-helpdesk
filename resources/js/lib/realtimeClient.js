let socket = null;
let reconnectTimer = null;
let config = null;
const subscriptions = new Map();
const connectionListeners = new Set();

function notifyConnection(connected) {
    connectionListeners.forEach((handler) => handler(connected));
}

function isConnected() {
    return socket?.readyState === WebSocket.OPEN;
}

function connect(nextConfig) {
    config = nextConfig;

    if (!config?.url || !config?.token) {
        return null;
    }

    if (socket && (socket.readyState === WebSocket.OPEN || socket.readyState === WebSocket.CONNECTING)) {
        return socket;
    }

    const url = `${config.url}?token=${encodeURIComponent(config.token)}`;
    socket = new WebSocket(url);

    socket.addEventListener('open', () => {
        notifyConnection(true);

        for (const channel of subscriptions.keys()) {
            subscribe(channel);
        }
    });

    socket.addEventListener('message', (event) => {
        let payload;

        try {
            payload = JSON.parse(event.data);
        } catch {
            return;
        }

        const channel = payload.channel;
        const handlers = channel ? subscriptions.get(channel) : null;

        if (!handlers?.size) {
            return;
        }

        handlers.forEach((handler) => handler(payload));
    });

    socket.addEventListener('close', () => {
        notifyConnection(false);

        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
        }

        reconnectTimer = setTimeout(() => connect(config), 3000);
    });

    return socket;
}

function subscribe(channel, handler = null) {
    if (handler) {
        if (!subscriptions.has(channel)) {
            subscriptions.set(channel, new Set());
        }

        subscriptions.get(channel).add(handler);
    } else if (!subscriptions.has(channel)) {
        subscriptions.set(channel, new Set());
    }

    if (socket?.readyState === WebSocket.OPEN) {
        socket.send(JSON.stringify({ action: 'subscribe', channel }));
    }
}

function unsubscribe(channel, handler = null) {
    const handlers = subscriptions.get(channel);

    if (!handlers) {
        return;
    }

    if (handler) {
        handlers.delete(handler);
    }

    if (!handler || handlers.size === 0) {
        subscriptions.delete(channel);
    }
}

function disconnect() {
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }

    notifyConnection(false);
    subscriptions.clear();
    socket?.close();
    socket = null;
    config = null;
}

export function createRealtimeClient(nextConfig) {
    connect(nextConfig);

    return {
        subscribe,
        unsubscribe,
        disconnect,
    };
}

let sharedClient = null;
let sharedConfigKey = null;

export function getSharedRealtimeClient(nextConfig) {
    if (!nextConfig?.url || !nextConfig?.token) {
        return null;
    }

    const key = `${nextConfig.url}:${nextConfig.token}`;

    if (!sharedClient || sharedConfigKey !== key) {
        sharedClient?.disconnect();
        sharedClient = createRealtimeClient(nextConfig);
        sharedConfigKey = key;
    } else {
        connect(nextConfig);
    }

    return sharedClient;
}

export function isRealtimeConfigured(nextConfig) {
    return Boolean(nextConfig?.url && nextConfig?.token);
}

export function isRealtimeConnected() {
    return isConnected();
}

export function onRealtimeConnectionChange(handler) {
    connectionListeners.add(handler);
    handler(isConnected());

    return () => connectionListeners.delete(handler);
}
