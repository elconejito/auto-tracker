export function resourceAttributes(resource) {
    return resource?.attributes ?? resource ?? {};
}

export function toDateTimeLocal(value) {
    if (!value) {
        return '';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (part) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function toAtomDateTime(value) {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? value : date.toISOString();
}

export function formatOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return 'Unavailable';
    }

    return `${Number(value).toLocaleString('en-US')} mi`;
}

export function formatGallons(value) {
    if (value === null || value === undefined || value === '') {
        return 'Unavailable';
    }

    return `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 3 })} gal`;
}

export function formatMpg(value) {
    if (value === null || value === undefined || value === '') {
        return 'Unavailable';
    }

    return `${Number(value).toLocaleString('en-US', { maximumFractionDigits: 1 })} MPG`;
}

export function formatMoney(value) {
    if (value === null || value === undefined || value === '') {
        return 'Unavailable';
    }

    return Number(value).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

export function formatDateTime(value) {
    if (!value) {
        return 'Unavailable';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Unavailable'
        : date.toLocaleString('en-US', { dateStyle: 'medium', timeStyle: 'short' });
}

export function paginationFrom(response) {
    const pagination = response?.meta?.pagination ?? response?.meta ?? {};

    return {
        currentPage: Number(pagination.current_page ?? pagination.currentPage ?? 1),
        lastPage: Number(pagination.total_pages ?? pagination.last_page ?? pagination.lastPage ?? 1),
        perPage: Number(pagination.per_page ?? pagination.perPage ?? pagination.page_size ?? 10),
        total: Number(pagination.total ?? 0),
    };
}
