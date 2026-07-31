import { resourceAttributes } from './fuel';

export const SERVICE_TYPES = [
    'oil_change',
    'tire_replacement',
    'tire_rotation',
    'brake_pad_replacement',
    'brake_rotor_replacement',
    'timing_belt_replacement',
    'serpentine_belt_replacement',
    'battery_replacement',
    'air_filter_replacement',
    'cabin_air_filter_replacement',
    'spark_plug_replacement',
    'transmission_service',
    'coolant_service',
    'vehicle_inspection',
    'generic_service',
];

export const SERVICE_LABELS = {
    oil_change: 'Oil change',
    tire_replacement: 'Tire replacement',
    tire_rotation: 'Tire rotation',
    brake_pad_replacement: 'Brake pad replacement',
    brake_rotor_replacement: 'Brake rotor replacement',
    timing_belt_replacement: 'Timing belt replacement',
    serpentine_belt_replacement: 'Serpentine belt replacement',
    battery_replacement: 'Battery replacement',
    air_filter_replacement: 'Air filter replacement',
    cabin_air_filter_replacement: 'Cabin air filter replacement',
    spark_plug_replacement: 'Spark plug replacement',
    transmission_service: 'Transmission service',
    coolant_service: 'Coolant service',
    vehicle_inspection: 'Vehicle inspection',
    generic_service: 'Generic service',
};

export const POSITION_OPTIONS = [
    { value: 'all', label: 'All' },
    { value: 'front', label: 'Front' },
    { value: 'rear', label: 'Rear' },
    { value: 'front_left', label: 'Front left' },
    { value: 'front_right', label: 'Front right' },
    { value: 'rear_left', label: 'Rear left' },
    { value: 'rear_right', label: 'Rear right' },
];

export function serviceLabel(type) {
    return SERVICE_LABELS[type] ?? type?.replaceAll('_', ' ') ?? 'Service';
}

export function serviceResourceAttributes(service) {
    const attributes = resourceAttributes(service);

    return {
        type: service?.type ?? attributes.type ?? service?.serviceable_type ?? '',
        line_cost: service?.line_cost ?? attributes.line_cost ?? '',
        details: service?.details ?? attributes.details ?? resourceAttributes(service?.serviceable),
    };
}

export function serviceSummary(service) {
    const normalized = serviceResourceAttributes(service);
    const details = normalized.details ?? {};
    const values = [
        details.name,
        details.position && POSITION_OPTIONS.find((option) => option.value === details.position)?.label,
        details.manufacturer,
        details.model,
        details.size,
        details.viscosity,
        details.pattern,
    ].filter(Boolean);

    return values.join(' · ');
}

export function formatMaintenanceDate(value) {
    if (!value) {
        return 'Date unavailable';
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? 'Date unavailable'
        : date.toLocaleDateString('en-US', { dateStyle: 'medium' });
}

export function formatMaintenanceMoney(value) {
    if (value === null || value === undefined || value === '') {
        return 'Cost unavailable';
    }

    return Number(value).toLocaleString('en-US', { style: 'currency', currency: 'USD' });
}

export function formatMaintenanceOdometer(value) {
    if (value === null || value === undefined || value === '') {
        return 'Odometer unavailable';
    }

    return `${Number(value).toLocaleString('en-US')} mi`;
}
