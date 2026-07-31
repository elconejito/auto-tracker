import apiClient from './client';

export async function fetchVehicleDashboard(vehicleId) {
    const response = await apiClient.get(`/vehicles/${vehicleId}/dashboard`);

    return response.data.data;
}

export async function fetchVehicleTimeline(vehicleId, page = 1) {
    const response = await apiClient.get(`/vehicles/${vehicleId}/timeline`, {
        params: {
            'page[number]': page,
            'page[size]': 15,
        },
    });

    return response.data;
}
