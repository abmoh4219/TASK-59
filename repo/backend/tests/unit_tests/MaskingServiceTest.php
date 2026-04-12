<?php

namespace App\Tests\UnitTests;

use App\Service\MaskingService;
use PHPUnit\Framework\TestCase;

class MaskingServiceTest extends TestCase
{
    private MaskingService $maskingService;

    protected function setUp(): void
    {
        $this->maskingService = new MaskingService();
    }

    // --- maskPhone tests ---

    public function testMaskPhoneFullUSWithCountryCode(): void
    {
        $result = $this->maskingService->maskPhone('+15551234567');
        $this->assertSame('(555) ***-4567', $result);
    }

    public function testMaskPhoneTenDigit(): void
    {
        $result = $this->maskingService->maskPhone('5551234567');
        $this->assertSame('(555) ***-4567', $result);
    }

    public function testMaskPhoneDifferentAreaCode(): void
    {
        $result = $this->maskingService->maskPhone('+12125551234');
        $this->assertSame('(212) ***-1234', $result);
    }

    public function testMaskPhoneSevenDigit(): void
    {
        $result = $this->maskingService->maskPhone('1234567');
        $this->assertSame('***-4567', $result);
    }

    public function testMaskPhoneNull(): void
    {
        $result = $this->maskingService->maskPhone(null);
        $this->assertNull($result);
    }

    public function testMaskPhoneEmptyString(): void
    {
        $result = $this->maskingService->maskPhone('');
        $this->assertNull($result);
    }

    // --- maskForLog tests ---

    public function testMaskForLogRedactsPhone(): void
    {
        $data = ['phone' => '5551234567', 'name' => 'John'];
        $result = $this->maskingService->maskForLog($data);

        $this->assertSame('[REDACTED]', $result['phone']);
        $this->assertSame('John', $result['name']);
    }

    public function testMaskForLogRedactsPassword(): void
    {
        $data = ['password' => 'secret123', 'action' => 'login'];
        $result = $this->maskingService->maskForLog($data);

        $this->assertSame('[REDACTED]', $result['password']);
        $this->assertSame('login', $result['action']);
    }

    public function testMaskForLogRedactsNestedSensitiveKeys(): void
    {
        $data = [
            'token'  => 'abc',
            'nested' => ['phone' => '123'],
        ];
        $result = $this->maskingService->maskForLog($data);

        $this->assertSame('[REDACTED]', $result['token']);
        $this->assertSame('[REDACTED]', $result['nested']['phone']);
    }
}
