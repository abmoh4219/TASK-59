import { describe, it, expect } from 'vitest';

// Pure utility function under test
function maskPhone(phone: string | null | undefined): string | null {
  if (!phone) return null;
  const digits = phone.replace(/\D/g, '');
  if (digits.length < 7) return null;
  if (digits.length >= 11) {
    return `(${digits.slice(1, 4)}) ***-${digits.slice(-4)}`;
  }
  if (digits.length >= 10) {
    return `(${digits.slice(0, 3)}) ***-${digits.slice(-4)}`;
  }
  return `***-${digits.slice(-4)}`;
}

describe('maskPhone', () => {
  it('masks a full US number with country code "+15551234567"', () => {
    expect(maskPhone('+15551234567')).toBe('(555) ***-4567');
  });

  it('masks a 10-digit number "5551234567"', () => {
    expect(maskPhone('5551234567')).toBe('(555) ***-4567');
  });

  it('masks "+12125559876" showing area code and last 4', () => {
    expect(maskPhone('+12125559876')).toBe('(212) ***-9876');
  });

  it('masks a 7-digit number "1234567"', () => {
    expect(maskPhone('1234567')).toBe('***-4567');
  });

  it('returns null for null input', () => {
    expect(maskPhone(null)).toBeNull();
  });

  it('returns null for undefined input', () => {
    expect(maskPhone(undefined)).toBeNull();
  });

  it('returns null for empty string', () => {
    expect(maskPhone('')).toBeNull();
  });
});
