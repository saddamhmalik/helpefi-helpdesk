export function xsrfTokenFromCookie() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export function csrfTokenFromMeta() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function csrfHeaders() {
    const xsrf = xsrfTokenFromCookie();

    if (xsrf) {
        return {
            'X-XSRF-TOKEN': xsrf,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    const meta = csrfTokenFromMeta();

    if (meta) {
        return {
            'X-CSRF-TOKEN': meta,
            'X-Requested-With': 'XMLHttpRequest',
        };
    }

    return {
        'X-Requested-With': 'XMLHttpRequest',
    };
}

export function syncCsrfMeta(token) {
    if (!token) {
        return;
    }

    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta) {
        meta.setAttribute('content', token);
    }
}

export function isCsrfExpiredResponse(response) {
    return response?.status === 419;
}

let csrfReloadScheduled = false;

export function reloadOnCsrfExpiry() {
    if (csrfReloadScheduled) {
        return;
    }

    csrfReloadScheduled = true;
    window.location.reload();
}
