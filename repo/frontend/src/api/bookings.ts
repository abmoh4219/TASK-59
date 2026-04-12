import apiClient from './client';
import type { Resource, Booking } from '../types';

export async function getResources(): Promise<Resource[]> {
  const response = await apiClient.get('/resources');
  return response.data;
}

export async function getResourceAvailability(
  id: number,
  date: string
): Promise<{ available: boolean; slots: { start: string; end: string }[] }> {
  const response = await apiClient.get(`/resources/${id}/availability`, {
    params: { date },
  });
  return response.data;
}

export async function getMyBookings(): Promise<Booking[]> {
  const response = await apiClient.get('/bookings');
  return response.data;
}

export async function getBooking(id: number): Promise<Booking> {
  const response = await apiClient.get(`/bookings/${id}`);
  return response.data;
}

export async function createBooking(data: {
  resourceId: number;
  startDatetime: string;
  endDatetime: string;
  purpose: string;
  travelers: number[];
  clientKey: string;
}): Promise<Booking> {
  const response = await apiClient.post('/bookings', data);
  return response.data;
}

export async function cancelBooking(id: number): Promise<void> {
  await apiClient.delete(`/bookings/${id}`);
}
