<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Rate-limiting facade for named Symfony rate limiters.
 *
 * Standard endpoints : 60 requests/minute per user.
 * Upload endpoints   : 10 uploads/minute per user.
 *
 * The underlying limiters are configured in config/packages/framework.yaml
 * under framework.rate_limiter as 'standard_api' and 'upload_api'.
 */
class RateLimitService
{
    /** Holds the last RateLimit result so controllers can read header values. */
    private ?RateLimit $lastRateLimit = null;

    public function __construct(
        #[Autowire(service: 'limiter.standard_api')] private readonly RateLimiterFactory $standardApiLimiter,
        #[Autowire(service: 'limiter.upload_api')]   private readonly RateLimiterFactory $uploadApiLimiter,
    ) {}

    /**
     * Consumes one token from the standard API limiter keyed by user ID.
     *
     * @return bool true when the request is within the allowed rate, false when throttled
     */
    public function checkStandardLimit(int $userId): bool
    {
        $limiter = $this->standardApiLimiter->create("api_user_{$userId}");

        $this->lastRateLimit = $limiter->consume(1);

        return $this->lastRateLimit->isAccepted();
    }

    /**
     * Consumes one token from the upload API limiter keyed by user ID.
     *
     * @return bool true when the upload is within the allowed rate, false when throttled
     */
    public function checkUploadLimit(int $userId): bool
    {
        $limiter = $this->uploadApiLimiter->create("upload_user_{$userId}");

        $this->lastRateLimit = $limiter->consume(1);

        return $this->lastRateLimit->isAccepted();
    }

    /**
     * Returns the number of seconds until the next token becomes available.
     *
     * Call this after checkStandardLimit() or checkUploadLimit() to populate
     * a Retry-After response header when a request has been throttled.
     */
    public function getRetryAfter(): int
    {
        if ($this->lastRateLimit === null) {
            return 0;
        }

        $retryAfter = $this->lastRateLimit->getRetryAfter();

        if ($retryAfter === null) {
            return 0;
        }

        $seconds = $retryAfter->getTimestamp() - time();

        return max(0, $seconds);
    }
}
