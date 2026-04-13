/**
 * workorder.api.test.ts — validates real production work-order API wrappers
 * from `frontend/src/api/workOrders.ts`. Uses vi.mock to stub axios so the
 * tests exercise the actual functions (URL building, payload shape, method
 * dispatch) without hitting a live backend.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';

const getMock = vi.fn();
const postMock = vi.fn();
const patchMock = vi.fn();

vi.mock('axios', () => {
  const instance = {
    get: getMock,
    post: postMock,
    patch: patchMock,
    interceptors: {
      request: { use: () => 0 },
      response: { use: () => 0 },
    },
  };
  return { default: { create: vi.fn(() => instance) }, create: vi.fn(() => instance) };
});

beforeEach(() => {
  getMock.mockReset();
  postMock.mockReset();
  patchMock.mockReset();
});

describe('work-order api wrappers (real production imports)', () => {
  it('getWorkOrders() forwards filters as query params', async () => {
    const { getWorkOrders } = await import('../../src/api/workOrders');
    getMock.mockResolvedValueOnce({
      data: { data: [{ id: 1 }], total: 1, page: 1 },
    });

    const res = await getWorkOrders({ status: 'submitted', page: 2 });

    expect(getMock).toHaveBeenCalledWith('/work-orders', {
      params: { status: 'submitted', page: 2 },
    });
    expect(res.total).toBe(1);
  });

  it('getWorkOrder() targets the id-scoped URL', async () => {
    const { getWorkOrder } = await import('../../src/api/workOrders');
    getMock.mockResolvedValueOnce({ data: { id: 42, category: 'Plumbing' } });

    const wo = await getWorkOrder(42);

    expect(getMock).toHaveBeenCalledWith('/work-orders/42');
    expect(wo.category).toBe('Plumbing');
  });

  it('createWorkOrder() sends multipart FormData to /work-orders', async () => {
    const { createWorkOrder } = await import('../../src/api/workOrders');
    postMock.mockResolvedValueOnce({ data: { id: 7 } });

    const fd = new FormData();
    fd.append('category', 'Electrical');
    fd.append('priority', 'MEDIUM');
    fd.append('description', 'Broken socket');
    fd.append('building', 'Block B');
    fd.append('room', '202');

    const wo = await createWorkOrder(fd);

    expect(postMock).toHaveBeenCalledTimes(1);
    const [url, body, config] = postMock.mock.calls[0];
    expect(url).toBe('/work-orders');
    expect(body).toBe(fd);
    expect(config).toMatchObject({ headers: { 'Content-Type': 'multipart/form-data' } });
    expect(wo.id).toBe(7);
  });

  it('updateWorkOrderStatus() PATCHes the status endpoint with the full payload', async () => {
    const { updateWorkOrderStatus } = await import('../../src/api/workOrders');
    patchMock.mockResolvedValueOnce({ data: { id: 9, status: 'dispatched' } });

    await updateWorkOrderStatus(9, 'dispatched', 'sending it out', 6);

    expect(patchMock).toHaveBeenCalledWith('/work-orders/9/status', {
      status: 'dispatched',
      notes: 'sending it out',
      technicianId: 6,
    });
  });

  it('rateWorkOrder() posts the rating payload', async () => {
    const { rateWorkOrder } = await import('../../src/api/workOrders');
    postMock.mockResolvedValueOnce({ data: { message: 'ok' } });

    await rateWorkOrder(11, 5);

    expect(postMock).toHaveBeenCalledWith('/work-orders/11/rate', { rating: 5 });
  });
});
