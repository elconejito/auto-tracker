import apiClient from './client';

export async function fetchMaintenanceEvents(vehicleId, params = {}) {
    const response = await apiClient.get(`/vehicles/${vehicleId}/maintenance-events`, { params });

    return response.data;
}

export async function fetchMaintenanceEvent(id) {
    const response = await apiClient.get(`/maintenance-events/${id}`);

    return response.data.data;
}

export async function createMaintenanceEvent(vehicleId, attributes) {
    const response = await apiClient.post(`/vehicles/${vehicleId}/maintenance-events`, attributes);

    return response.data.data;
}

export async function updateMaintenanceEvent(id, attributes) {
    const response = await apiClient.patch(`/maintenance-events/${id}`, attributes);

    return response.data.data;
}

export async function deleteMaintenanceEvent(id) {
    await apiClient.delete(`/maintenance-events/${id}`);
}
