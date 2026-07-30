export function validationErrorsFrom(error) {
    const errors = error.response?.data?.errors;

    if (!Array.isArray(errors)) {
        return {};
    }

    return errors.reduce((fieldErrors, item) => {
        const pointer = item.source?.pointer ?? '';
        const field = pointer.replace(/^\/data\/attributes\//, '').replaceAll('/', '.');

        if (field && !fieldErrors[field]) {
            fieldErrors[field] = item.detail;
        }

        return fieldErrors;
    }, {});
}

export function messageFrom(error, fallback = 'Something went wrong. Please try again.') {
    const errors = error.response?.data?.errors;

    if (Array.isArray(errors) && errors[0]?.detail) {
        return errors[0].detail;
    }

    return error.response?.data?.message ?? error.response?.data?.detail ?? fallback;
}
