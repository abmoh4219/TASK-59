<?php

namespace App\Tests\UnitTests;

use App\Service\EncryptionService;
use PHPUnit\Framework\TestCase;

class EncryptionServiceTest extends TestCase
{
    private const TEST_KEY = 'test-encryption-key-32-bytes-ok!';

    private EncryptionService $encryptionService;

    protected function setUp(): void
    {
        $this->encryptionService = new EncryptionService(self::TEST_KEY);
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $plaintext = 'Hello World';
        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testDifferentNonceEachEncryption(): void
    {
        $plaintext = 'Hello World';
        $encrypted1 = $this->encryptionService->encrypt($plaintext);
        $encrypted2 = $this->encryptionService->encrypt($plaintext);

        // Each call uses a random nonce, so the base64 outputs must differ
        $this->assertNotSame($encrypted1, $encrypted2);
    }

    public function testEmptyStringEncryption(): void
    {
        $plaintext = '';
        $encrypted = $this->encryptionService->encrypt($plaintext);
        $decrypted = $this->encryptionService->decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    public function testWrongKeyFailsDecryption(): void
    {
        $this->expectException(\RuntimeException::class);

        $encrypted = $this->encryptionService->encrypt('sensitive data');

        // Attempt to decrypt with a completely different key
        $wrongKeyService = new EncryptionService('totally-wrong-key-32-bytes-xxxxx');
        $wrongKeyService->decrypt($encrypted);
    }
}
