import apiClient from './client';

export async function fetchVehicles(params = {}) {
    const response = await apiClient.get('/vehicles', { params });

    return response.data;
}

export async function fetchVehicle(id) {
    const response = await apiClient.get(`/vehicles/${id}`);

    return response.data.data;
}

export async function createVehicle(attributes) {
    const response = await apiClient.post('/vehicles', attributes);

    return response.data.data;
}

export async function updateVehicle(id, attributes) {
    const response = await apiClient.patch(`/vehicles/${id}`, attributes);

    return response.data.data;
}

export async function archiveVehicle(id) {
    const response = await apiClient.post(`/vehicles/${id}/archive`);

    return response.data.data;
}

export async function restoreVehicle(id) {
    const response = await apiClient.post(`/vehicles/${id}/restore`);

    return response.data.data;
}
