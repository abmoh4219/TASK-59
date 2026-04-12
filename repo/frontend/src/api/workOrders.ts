import apiClient from './client';
import type { WorkOrder } from '../types';

export async function getWorkOrders(params?: {
  status?: string;
  priority?: string;
  page?: number;
}): Promise<{ data: WorkOrder[]; total: number; page: number }> {
  const response = await apiClient.get('/work-orders', { params });
  return response.data;
}

export async function getWorkOrder(id: number): Promise<WorkOrder> {
  const response = await apiClient.get(`/work-orders/${id}`);
  return response.data;
}

export async function createWorkOrder(formData: FormData): Promise<WorkOrder> {
  const response = await apiClient.post('/work-orders', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return response.data;
}

export async function updateWorkOrderStatus(
  id: number,
  status: string,
  notes?: string,
  technicianId?: number
): Promise<WorkOrder> {
  const response = await apiClient.patch(`/work-orders/${id}/status`, {
    status,
    notes,
    technicianId,
  });
  return response.data;
}

export async function rateWorkOrder(id: number, rating: number): Promise<void> {
  await apiClient.post(`/work-orders/${id}/rate`, { rating });
}
