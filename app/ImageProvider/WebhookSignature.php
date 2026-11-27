<?php

namespace App\ImageProvider;

/**
 * Verifies the the provider-Signature header: "t=<unix timestamp>,v1=<hex HMAC-SHA256>",
 * where the HMAC covers "<timestamp>.<raw request body>".
 */
final readonly class WebhookSignature
{
    public function __construct(
        private string $secret,
        private int $toleranceSeconds = 300,
    ) {}

    public function isValid(string $rawBody, ?string $header, int $now): bool
    {
        // An unset secret must never make every signature "valid".
        if ($this->secret === '') {
            return false;
        }

        $parts = [];

        foreach (explode(',', (string) $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        if (! isset($parts['t'], $parts['v1']) || ! ctype_digit($parts['t'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];

        // Old signatures are replays: each delivery attempt is signed afresh.
        if (abs($now - $timestamp) > $this->toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$rawBody}", $this->secret);

        return hash_equals($expected, $parts['v1']);
    }
}
