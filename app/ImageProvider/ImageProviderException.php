<?php

namespace App\ImageProvider;

use RuntimeException;

/**
 * An error response from the provider, or a failure to reach it.
 *
 * Branch on $errorCode (the provider's "code"), as its docs recommend.
 */
final class ImageProviderException extends RuntimeException
{
    /** Errors where nothing was created and the same request may succeed later. */
    private const array RETRYABLE_CODES = [
        'rate_limit_exceeded',
        'concurrency_limit_exceeded',
        'overloaded',
        'connection_failed',
    ];

    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }

    public function isRetryable(): bool
    {
        return in_array($this->errorCode, self::RETRYABLE_CODES, true)
            || ($this->httpStatus !== null && $this->httpStatus >= 500);
    }
}
