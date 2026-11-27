<?php

use App\ImageProvider\WebhookSignature;

$body = '{"id":"evt_1","type":"generation.succeeded"}';

it('accepts a correctly signed, recent request', function () use ($body) {
    $signature = new WebhookSignature('whsec_test');

    expect($signature->isValid($body, providerSignature($body, 'whsec_test', 1_000), now: 1_100))->toBeTrue();
});

it('rejects forged, tampered, replayed and malformed requests', function (string $requestBody, ?string $header) {
    expect((new WebhookSignature('whsec_test'))->isValid($requestBody, $header, now: 1_100))->toBeFalse();
})->with([
    'wrong secret' => [$body, providerSignature($body, 'whsec_other', 1_000)],
    'tampered body' => [$body.' ', providerSignature($body, 'whsec_test', 1_000)],
    'older than 5 minutes' => [$body, providerSignature($body, 'whsec_test', 1_100 - 301)],
    'missing header' => [$body, null],
    'no signature part' => [$body, 't=1000'],
]);

it('never accepts anything when no secret is configured', function () use ($body) {
    expect((new WebhookSignature(''))->isValid($body, providerSignature($body, '', 1_000), now: 1_000))->toBeFalse();
});
