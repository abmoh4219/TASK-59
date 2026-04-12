import apiClient from './client';
import type { AttendanceRecord, ExceptionRequest } from '../types';

export async function getTodayAttendance(): Promise<AttendanceRecord> {
  const response = await apiClient.get('/attendance/today');
  return response.data;
}

export async function getAttendanceByDate(date: string): Promise<AttendanceRecord> {
  const response = await apiClient.get(`/attendance/${date}`);
  return response.data;
}

export async function getAttendanceHistory(params: { from?: string; to?: string; page?: number }): Promise<{
  data: AttendanceRecord[];
  total: number;
  page: number;
}> {
  const response = await apiClient.get('/attendance/history', { params });
  return response.data;
}

export async function getMyRequests(): Promise<ExceptionRequest[]> {
  const response = await apiClient.get('/requests');
  return response.data;
}

export async function getRequestDetail(id: number): Promise<ExceptionRequest> {
  const response = await apiClient.get(`/requests/${id}`);
  return response.data;
}

export async function createExceptionRequest(data: {
  requestType: string;
  startDate: string;
  endDate: string;
  startTime: string;
  endTime: string;
  reason: string;
  clientKey: string;
}): Promise<ExceptionRequest> {
  const response = await apiClient.post('/requests', data);
  return response.data;
}

export async function withdrawRequest(id: number): Promise<void> {
  await apiClient.post(`/requests/${id}/withdraw`);
}

export async function getApprovalQueue(): Promise<ExceptionRequest[]> {
  const response = await apiClient.get('/approvals/queue');
  return response.data;
}

export async function approveStep(stepId: number, comment: string): Promise<void> {
  await apiClient.post(`/approvals/${stepId}/approve`, { comment });
}

export async function rejectStep(stepId: number, comment: string): Promise<void> {
  await apiClient.post(`/approvals/${stepId}/reject`, { comment });
}
