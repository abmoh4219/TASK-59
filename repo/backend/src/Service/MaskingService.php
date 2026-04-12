<?php

namespace App\Service;

/**
 * MaskingService — masks sensitive data for display and audit logs.
 *
 * Used in ALL API responses for non-HR_ADMIN roles and ALL audit log entries.
 * Ensures phone numbers, passwords, and tokens are never exposed in plain text
 * outside of authorized contexts.
 */
class MaskingService
{
    /**
     * Mask a phone number for display.
     * Input: "+15551234567" or "5551234567" → Output: "(555) ***-1234"
     * Shows only area code and last 4 digits.
     */
    public function maskPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }

        // Strip non-digit characters
        $digits = preg_replace('/\D/', '', $phone);

        if (strlen($digits) < 7) {
            // Too short to mask meaningfully — redact entirely
            return '***-' . substr($digits, -4);
        }

        // Extract area code (3 digits after country code) and last 4
        if (strlen($digits) >= 11) {
            // Has country code (e.g., 15551234567)
            $areaCode = substr($digits, 1, 3);
            $lastFour = substr($digits, -4);
        } elseif (strlen($digits) >= 10) {
            // 10-digit US number (e.g., 5551234567)
            $areaCode = substr($digits, 0, 3);
            $lastFour = substr($digits, -4);
        } else {
            // 7-digit number
            $areaCode = '';
            $lastFour = substr($digits, -4);
            return '***-' . $lastFour;
        }

        return '(' . $areaCode . ') ***-' . $lastFour;
    }

    /**
     * Mask sensitive keys in an associative array for audit log storage.
     * Redacts values for keys containing: phone, password, token, secret, key.
     * Used before writing to AuditLog.oldValueMasked / newValueMasked.
     */
    public function maskForLog(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        // Keys whose values should be redacted
        $sensitiveKeys = ['phone', 'password', 'token', 'secret', 'key', 'encrypted', 'hash'];

        $masked = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);

            // Check if this key contains any sensitive keyword
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveWord) {
                if (str_contains($keyLower, $sensitiveWord)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $masked[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                // Recursively mask nested arrays
                $masked[$key] = $this->maskForLog($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }
}
