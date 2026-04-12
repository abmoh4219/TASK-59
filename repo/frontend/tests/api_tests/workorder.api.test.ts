/**
 * workorder.api.test.ts — Vitest API-layer tests for work order client logic.
 *
 * Tests cover:
 *  - FormData construction (buildWorkOrderFormData)
 *  - Work order list filtering (client-side filter helper)
 *  - Photo validation: count, MIME type, file size
 *
 * No mocked HTTP calls are needed here — these tests exercise pure utility
 * functions that mirror what the real API client uses before dispatching requests.
 */

import { describe, it, expect } from 'vitest';

// ---------------------------------------------------------------------------
// Utility functions under test
// ---------------------------------------------------------------------------

/**
 * Build a FormData payload for the POST /api/work-orders endpoint.
 * Mirrors the structure expected by WorkOrderController::create().
 */
function buildWorkOrderFormData(data: {
  category: string;
  priority: string;
  description: string;
  building: string;
  room: string;
  photos: File[];
}): FormData {
  const fd = new FormData();
  fd.append('category', data.category);
  fd.append('priority', data.priority);
  fd.append('description', data.description);
  fd.append('building', data.building);
  fd.append('room', data.room);
  data.photos.forEach((p) => fd.append('photos[]', p));
  return fd;
}

/**
 * Filter a work order list array by status string.
 * Used in the WorkOrderListPage to filter the client-side cache before
 * making a fresh API call.
 */
function filterWorkOrdersByStatus(
  orders: Array<{ id: number; status: string; category: string }>,
  status: string | null,
): Array<{ id: number; status: string; category: string }> {
  if (status === null || status === '') return orders;
  return orders.filter((o) => o.status === status);
}

/**
 * Validate the number of photos attached to a work order submission.
 * Backend enforces max 5 (WorkOrderService::create).
 */
function validatePhotoCount(photos: File[]): { valid: boolean; error?: string } {
  if (photos.length > 5) return { valid: false, error: 'Max 5 photos' };
  return { valid: true };
}

/**
 * Validate a single photo's MIME type. Backend accepts JPEG and PNG only.
 */
function validatePhotoType(file: File): { valid: boolean; error?: string } {
  const allowed = ['image/jpeg', 'image/png'];
  if (!allowed.includes(file.type)) return { valid: false, error: 'Only JPEG/PNG allowed' };
  return { valid: true };
}

/**
 * Validate a single photo's file size. Max 10 MB per photo.
 */
function validatePhotoSize(file: File): { valid: boolean; error?: string } {
  if (file.size > 10 * 1024 * 1024) return { valid: false, error: 'Max 10MB per photo' };
  return { valid: true };
}

// ---------------------------------------------------------------------------
// Helper: create a File with a specific size (bypassing the read-only .size)
// ---------------------------------------------------------------------------
function fileWithSize(name: string, type: string, sizeBytes: number): File {
  const file = new File([], name, { type });
  Object.defineProperty(file, 'size', { value: sizeBytes, writable: false });
  return file;
}

// ---------------------------------------------------------------------------
// Tests: buildWorkOrderFormData
// ---------------------------------------------------------------------------

describe('buildWorkOrderFormData', () => {
  it('includes all required text fields in the FormData', () => {
    const photo1 = new File([], 'photo1.jpg', { type: 'image/jpeg' });
    const photo2 = new File([], 'photo2.png', { type: 'image/png' });

    const fd = buildWorkOrderFormData({
      category: 'Plumbing',
      priority: 'HIGH',
      description: 'Leaking pipe under sink.',
      building: 'Block A',
      room: '101',
      photos: [photo1, photo2],
    });

    expect(fd.get('category')).toBe('Plumbing');
    expect(fd.get('priority')).toBe('HIGH');
    expect(fd.get('description')).toBe('Leaking pipe under sink.');
    expect(fd.get('building')).toBe('Block A');
    expect(fd.get('room')).toBe('101');
  });

  it('appends each photo under the photos[] key', () => {
    const photo1 = new File([], 'photo1.jpg', { type: 'image/jpeg' });
    const photo2 = new File([], 'photo2.jpg', { type: 'image/jpeg' });

    const fd = buildWorkOrderFormData({
      category: 'Electrical',
      priority: 'MEDIUM',
      description: 'Broken socket.',
      building: 'Block B',
      room: '202',
      photos: [photo1, photo2],
    });

    const allPhotos = fd.getAll('photos[]');
    expect(allPhotos).toHaveLength(2);
    expect((allPhotos[0] as File).name).toBe('photo1.jpg');
    expect((allPhotos[1] as File).name).toBe('photo2.jpg');
  });

  it('creates FormData with no photos when photos array is empty', () => {
    const fd = buildWorkOrderFormData({
      category: 'General',
      priority: 'LOW',
      description: 'No photos attached.',
      building: 'HQ',
      room: 'Lobby',
      photos: [],
    });

    expect(fd.getAll('photos[]')).toHaveLength(0);
    // Text fields are still present
    expect(fd.get('category')).toBe('General');
  });
});

// ---------------------------------------------------------------------------
// Tests: filterWorkOrdersByStatus
// ---------------------------------------------------------------------------

describe('filterWorkOrdersByStatus', () => {
  const sampleOrders = [
    { id: 1, status: 'submitted',   category: 'Plumbing' },
    { id: 2, status: 'dispatched',  category: 'Electrical' },
    { id: 3, status: 'in_progress', category: 'HVAC' },
    { id: 4, status: 'completed',   category: 'General' },
    { id: 5, status: 'submitted',   category: 'Other' },
  ];

  it('returns all orders when status filter is null', () => {
    const result = filterWorkOrdersByStatus(sampleOrders, null);
    expect(result).toHaveLength(5);
  });

  it('returns all orders when status filter is an empty string', () => {
    const result = filterWorkOrdersByStatus(sampleOrders, '');
    expect(result).toHaveLength(5);
  });

  it('filters to only "submitted" orders when status is "submitted"', () => {
    const result = filterWorkOrdersByStatus(sampleOrders, 'submitted');
    expect(result).toHaveLength(2);
    result.forEach((o) => expect(o.status).toBe('submitted'));
  });

  it('filters to only "in_progress" orders', () => {
    const result = filterWorkOrdersByStatus(sampleOrders, 'in_progress');
    expect(result).toHaveLength(1);
    expect(result[0].id).toBe(3);
  });

  it('returns an empty array when no orders match the status', () => {
    const result = filterWorkOrdersByStatus(sampleOrders, 'rated');
    expect(result).toHaveLength(0);
  });
});

// ---------------------------------------------------------------------------
// Tests: validatePhotoCount
// ---------------------------------------------------------------------------

describe('validatePhotoCount', () => {
  it('accepts 0 photos (no attachment)', () => {
    const result = validatePhotoCount([]);
    expect(result.valid).toBe(true);
    expect(result.error).toBeUndefined();
  });

  it('accepts exactly 3 photos', () => {
    const photos = [
      new File([], 'a.jpg', { type: 'image/jpeg' }),
      new File([], 'b.jpg', { type: 'image/jpeg' }),
      new File([], 'c.png', { type: 'image/png' }),
    ];
    const result = validatePhotoCount(photos);
    expect(result.valid).toBe(true);
  });

  it('accepts exactly 5 photos (boundary — max allowed)', () => {
    const photos = Array.from({ length: 5 }, (_, i) =>
      new File([], `photo${i}.jpg`, { type: 'image/jpeg' })
    );
    const result = validatePhotoCount(photos);
    expect(result.valid).toBe(true);
  });

  it('rejects 6 photos (one over the limit)', () => {
    const photos = Array.from({ length: 6 }, (_, i) =>
      new File([], `photo${i}.jpg`, { type: 'image/jpeg' })
    );
    const result = validatePhotoCount(photos);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Max 5 photos');
  });

  it('rejects 10 photos (well over the limit)', () => {
    const photos = Array.from({ length: 10 }, (_, i) =>
      new File([], `photo${i}.jpg`, { type: 'image/jpeg' })
    );
    const result = validatePhotoCount(photos);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Max 5 photos');
  });
});

// ---------------------------------------------------------------------------
// Tests: validatePhotoType
// ---------------------------------------------------------------------------

describe('validatePhotoType', () => {
  it('accepts image/jpeg', () => {
    const file = new File([], 'photo.jpg', { type: 'image/jpeg' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(true);
    expect(result.error).toBeUndefined();
  });

  it('accepts image/png', () => {
    const file = new File([], 'photo.png', { type: 'image/png' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(true);
    expect(result.error).toBeUndefined();
  });

  it('rejects application/pdf', () => {
    const file = new File([], 'document.pdf', { type: 'application/pdf' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Only JPEG/PNG allowed');
  });

  it('rejects image/gif', () => {
    const file = new File([], 'animated.gif', { type: 'image/gif' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Only JPEG/PNG allowed');
  });

  it('rejects image/webp', () => {
    const file = new File([], 'photo.webp', { type: 'image/webp' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Only JPEG/PNG allowed');
  });

  it('rejects a file with no MIME type', () => {
    const file = new File([], 'noextension', { type: '' });
    const result = validatePhotoType(file);
    expect(result.valid).toBe(false);
  });
});

// ---------------------------------------------------------------------------
// Tests: validatePhotoSize
// ---------------------------------------------------------------------------

describe('validatePhotoSize', () => {
  const MB = 1024 * 1024;

  it('accepts a 1 MB file', () => {
    const file = fileWithSize('small.jpg', 'image/jpeg', 1 * MB);
    const result = validatePhotoSize(file);
    expect(result.valid).toBe(true);
  });

  it('accepts a 5 MB file', () => {
    const file = fileWithSize('medium.jpg', 'image/jpeg', 5 * MB);
    const result = validatePhotoSize(file);
    expect(result.valid).toBe(true);
    expect(result.error).toBeUndefined();
  });

  it('accepts exactly 10 MB (boundary — max allowed)', () => {
    const file = fileWithSize('boundary.jpg', 'image/jpeg', 10 * MB);
    const result = validatePhotoSize(file);
    expect(result.valid).toBe(true);
  });

  it('rejects a 15 MB file (over the limit)', () => {
    const file = fileWithSize('large.jpg', 'image/jpeg', 15 * MB);
    const result = validatePhotoSize(file);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Max 10MB per photo');
  });

  it('rejects a file just 1 byte over 10 MB', () => {
    const file = fileWithSize('over.jpg', 'image/jpeg', 10 * MB + 1);
    const result = validatePhotoSize(file);
    expect(result.valid).toBe(false);
    expect(result.error).toBe('Max 10MB per photo');
  });
});
