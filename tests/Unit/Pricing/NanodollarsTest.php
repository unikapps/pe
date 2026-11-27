<?php

use App\Pricing\Nanodollars;

it('parses and prints exact nine-decimal amounts', function (string $input, int $amount, string $output) {
    $money = Nanodollars::fromDecimal($input);

    expect($money->amount)->toBe($amount)
        ->and($money->toDecimal())->toBe($output);
})->with([
    ['0.046125000', 46_125_000, '0.046125000'],
    ['12.50', 12_500_000_000, '12.500000000'],
    ['0', 0, '0.000000000'],
    ['0.000000001', 1, '0.000000001'],
    ['-1.5', -1_500_000_000, '-1.500000000'],
]);

it('rejects anything that is not an exact amount', function (string $input) {
    Nanodollars::fromDecimal($input);
})->with(['1.0000000001', '1e-9', 'abc', '', '1.'])->throws(InvalidArgumentException::class);

it('applies a percentage, rounding a fraction of a nanodollar up', function (int $amount, int $expected) {
    expect((new Nanodollars($amount))->percentRoundedUp(125)->amount)->toBe($expected);
})->with([
    [0, 0],
    [1, 2],    // 1.25 -> 2
    [4, 5],    // exact
    [6262, 7828], // 7827.5 -> 7828
]);
