<?php

namespace App\Service;

/**
 * EncryptionService — AES-256-GCM encryption at rest for sensitive fields.
 *
 * Uses PHP sodium extension (libsodium) for authenticated encryption:
 * - Algorithm: AES-256-GCM (AEAD)
 * - Each encryption generates a unique random nonce (12 bytes)
 * - Output format: base64(nonce + ciphertext + tag)
 * - Key sourced from APP_ENCRYPTION_KEY environment variable (must be 32 bytes)
 *
 * Used for: phone numbers, sensitive PII stored in database.
 * Only HR Admin role can access decrypted values via API.
 */
class EncryptionService
{
    private string $encryptionKey;

    public function __construct(string $appEncryptionKey)
    {
        // Ensure key is exactly 32 bytes for AES-256
        $this->encryptionKey = str_pad(
            substr($appEncryptionKey, 0, 32),
            32,
            "\0"
        );
    }

    /**
     * Encrypt plaintext using AES-256-GCM with a random nonce.
     * Returns base64-encoded string containing nonce + ciphertext + auth tag.
     */
    public function encrypt(string $plaintext): string
    {
        if (sodium_crypto_aead_aes256gcm_is_available()) {
            // Generate random 12-byte nonce (IETF standard for AES-GCM)
            $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);

            // Encrypt with authenticated encryption (ciphertext includes auth tag)
            $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
                $plaintext,
                '', // additional authenticated data (empty)
                $nonce,
                $this->encryptionKey
            );

            // Return base64(nonce + ciphertext)
            return base64_encode($nonce . $ciphertext);
        }

        // Fallback: use openssl if sodium AES-GCM not available on this CPU
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            16
        );

        return base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt a base64-encoded AES-256-GCM encrypted string.
     * Extracts nonce from the first 12 bytes, then decrypts the rest.
     */
    public function decrypt(string $encoded): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new \RuntimeException('Invalid base64 encoding in encrypted data');
        }

        if (sodium_crypto_aead_aes256gcm_is_available()) {
            $nonceLength = SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES; // 12 bytes
            if (strlen($decoded) < $nonceLength) {
                throw new \RuntimeException('Encrypted data too short');
            }

            $nonce = substr($decoded, 0, $nonceLength);
            $ciphertext = substr($decoded, $nonceLength);

            $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
                $ciphertext,
                '', // additional authenticated data
                $nonce,
                $this->encryptionKey
            );

            if ($plaintext === false) {
                throw new \RuntimeException('Decryption failed — invalid key or corrupted data');
            }

            return $plaintext;
        }

        // Fallback: openssl
        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed — invalid key or corrupted data');
        }

        return $plaintext;
    }
}
