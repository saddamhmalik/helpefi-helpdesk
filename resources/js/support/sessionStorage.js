export function storageKey(base, scope = '') {
    if (!scope) {
        return base;
    }

    return `${scope}:${base}`;
}

export function readSessionItem(key, fallback = null) {
    try {
        const value = sessionStorage.getItem(key);

        return value ?? fallback;
    } catch {
        return fallback;
    }
}

export function writeSessionItem(key, value) {
    try {
        sessionStorage.setItem(key, value);

        return true;
    } catch {
        return false;
    }
}

export function readSessionJson(key, fallback) {
    const raw = readSessionItem(key);

    if (raw === null) {
        return fallback;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return fallback;
    }
}

export function writeSessionJson(key, value) {
    try {
        return writeSessionItem(key, JSON.stringify(value));
    } catch {
        return false;
    }
}
