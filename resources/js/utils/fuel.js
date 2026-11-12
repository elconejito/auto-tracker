export function resourceAttributes(resource) {
    return resource?.attributes ?? resource ?? {};
}

export function toDateInput(value) {
    return typeof value === 'string' && /^\d{4}-\d{2}-\d{2}(?:T| |$)/.test(value) ? value.slice(0, 10) : '';
}

export function formatFuelDate(value) {
    const day = toDateInput(value);
    const date = new Date(`${day}T00:00:00Z`);

    return Number.isNaN(date.getTime())
        ? 'Unavailable'
        : date.toLocaleDateString('en-US', { dateStyle: 'medium', timeZone: 'UTC' });
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
