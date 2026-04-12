import apiClient from './client';
import type { User } from '../types';

export async function login(username: string, password: string): Promise<{ user: User; csrfToken: string }> {
  const response = await apiClient.post('/auth/login', { username, password });
  return response.data;
}

export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}

export async function getMe(): Promise<{ user: User; csrfToken: string }> {
  const response = await apiClient.get('/auth/me');
  return response.data;
}

export async function getCsrfToken(): Promise<string> {
  const response = await apiClient.get('/auth/csrf-token');
  return response.data.csrfToken;
}
