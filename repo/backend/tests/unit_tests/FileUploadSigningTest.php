<?php

namespace App\Tests\UnitTests;

use App\Service\AuditService;
use App\Service\FileUploadService;
use App\Service\RateLimitService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Locks in fail-closed behavior for FileUploadService::getSignedUrl():
 *   - throws when APP_SIGNING_KEY is null, empty, too short, or a placeholder
 *   - returns a deterministic, correctly-prefixed URL for valid keys
 */
class FileUploadSigningTest extends TestCase
{
    private function makeService(?string $key): FileUploadService
    {
        return new FileUploadService(
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(RateLimitService::class),
            $this->createMock(AuditService::class),
            $key,
        );
    }

    public function testNullSigningKeyThrows(): void
    {
        $svc = $this->makeService(null);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not configured');
        $svc->getSignedUrl(1);
    }

    public function testEmptySigningKeyThrows(): void
    {
        $svc = $this->makeService('');
        $this->expectException(\RuntimeException::class);
        $svc->getSignedUrl(1);
    }

    public function testShortSigningKeyThrows(): void
    {
        $svc = $this->makeService('tooshort');
        $this->expectException(\RuntimeException::class);
        $svc->getSignedUrl(1);
    }

    public function testPlaceholderSigningKeyThrows(): void
    {
        $svc = $this->makeService('default-key-default-key-default');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('placeholder');
        $svc->getSignedUrl(1);
    }

    public function testChangeMePlaceholderRejected(): void
    {
        $svc = $this->makeService('change-me-this-key-is-32-bytes!');
        $this->expectException(\RuntimeException::class);
        $svc->getSignedUrl(1);
    }

    public function testValidSigningKeyProducesSignedUrl(): void
    {
        $svc = $this->makeService('a-real-32-byte-signing-key-0001');
        $url = $svc->getSignedUrl(42, 60);
        $this->assertStringStartsWith('/api/files/42?', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }
}
