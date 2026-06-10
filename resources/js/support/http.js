import { csrfHeaders, isCsrfExpiredResponse, reloadOnCsrfExpiry } from './csrf.js';

export async function appFetch(input, init = {}) {
    const headers = {
        Accept: 'application/json',
        ...csrfHeaders(),
        ...(init.headers ?? {}),
    };

    const response = await fetch(input, {
        credentials: 'same-origin',
        ...init,
        headers,
    });

    if (isCsrfExpiredResponse(response)) {
        reloadOnCsrfExpiry();
    }

    return response;
}
