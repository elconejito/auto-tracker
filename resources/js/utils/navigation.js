export function returnPath(value, fallback) {
    if (typeof value !== 'string' || !value.startsWith('/') || value.startsWith('//') || /[\\\u0000-\u001f]/.test(value)) {
        return fallback;
    }

    return value;
}
