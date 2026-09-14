export class ApiError extends Error {
    constructor(status, message, retryAfter = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.retryAfter = retryAfter;
    }
}

export async function requestJson(url, { signal } = {}) {
    const response = await fetch(url, {
        credentials: 'include',
        headers: { Accept: 'application/json' },
        signal,
    });
    if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new ApiError(response.status, body?.message || response.statusText, response.headers.get('Retry-After'));
    }
    return response.json();
}
