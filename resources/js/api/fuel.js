import apiClient from './client';

export async function fetchFuelEntries(vehicleId, params = {}) {
    const response = await apiClient.get(`/vehicles/${vehicleId}/fuel-entries`, { params });

    return response.data;
}

export async function fetchFuelEntry(id) {
    const response = await apiClient.get(`/fuel-entries/${id}`);

    return response.data.data;
}

export async function createFuelEntry(vehicleId, attributes) {
    const response = await apiClient.post(`/vehicles/${vehicleId}/fuel-entries`, attributes);

    return response.data.data;
}

export async function updateFuelEntry(id, attributes) {
    const response = await apiClient.patch(`/fuel-entries/${id}`, attributes);

    return response.data.data;
}

export async function deleteFuelEntry(id) {
    await apiClient.delete(`/fuel-entries/${id}`);
}
