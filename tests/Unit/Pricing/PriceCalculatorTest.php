<?php

use App\Enums\Quality;
use App\Enums\Size;
use App\Pricing\GenerationSpec;
use App\Pricing\PriceCalculator;
use App\Pricing\PricingTable;
use App\Pricing\Rates;
use App\Pricing\TokenUsage;

$rates = fn (): Rates => Rates::fromArray(providerPricing()['rates']);

it('matches the cost of the succeeded generation in the docs', function () use ($rates) {
    $cost = (new PriceCalculator)->providerCost(new TokenUsage(12, 765, 6450, 3184), $rates());

    expect($cost->toDecimal())->toBe('0.305510000');
});

it('matches the daily total in the docs usage example', function () use ($rates) {
    $cost = (new PriceCalculator)->providerCost(new TokenUsage(41, 0, 3300, 2950), $rates());

    expect($cost->toDecimal())->toBe('0.169080000');
});

it('charges users the provider cost plus a 25% markup', function () use ($rates) {
    $calculator = new PriceCalculator;
    $cost = $calculator->providerCost(new TokenUsage(12, 765, 6450, 3184), $rates());

    expect($calculator->userPrice($cost)->toDecimal())->toBe('0.381887500');
});

it('estimates the worked "most a generation can cost" example from the docs', function () {
    $spec = new GenerationSpec(str_repeat('a', 400), Size::Landscape, Quality::High, referenceImageCount: 2);

    $estimate = (new PriceCalculator)->estimate($spec, PricingTable::fromArray(providerPricing()));

    // Provider maximum $0.373875, plus 25%.
    expect($estimate->max->toDecimal())->toBe('0.467343750');
});

it('never lets the actual price exceed the estimated maximum', function (Quality $quality, Size $size) {
    $calculator = new PriceCalculator;
    $table = PricingTable::fromArray(providerPricing());
    $prompt = str_repeat('x', 103);
    $estimate = $calculator->estimate(new GenerationSpec($prompt, $size, $quality, 1), $table);

    // Worst case the docs allow: +15% text tokens, maximum reasoning.
    $worst = new TokenUsage(
        (int) ceil(26 * 1.15),
        765,
        $table->outputTokens($quality, $size),
        $table->reasoningTokens($quality)['max'],
    );

    expect($calculator->userPrice($calculator->providerCost($worst, $table->rates))->amount)
        ->toBeLessThanOrEqual($estimate->max->amount)
        ->and($estimate->min->amount)->toBeLessThanOrEqual($estimate->expected->amount)
        ->and($estimate->expected->amount)->toBeLessThanOrEqual($estimate->max->amount);
})->with(Quality::cases())->with(Size::cases());

it('counts Unicode characters, not bytes or UTF-16 units', function () {
    expect((new GenerationSpec('🎨🎨🎨🎨', Size::Square, Quality::Low, 0))->promptCharacters())->toBe(4);
});
