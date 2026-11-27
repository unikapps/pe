<?php

namespace App\Pricing;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An exact amount of US dollars, stored as a whole number of nanodollars (1e-9 USD).
 *
 * The provider reports money as decimal strings with nine decimal places, so nine
 * places is the precision we need to match its records exactly. Integers keep
 * the arithmetic exact; floats would drift.
 */
final readonly class Nanodollars implements JsonSerializable, Stringable
{
    public const int PER_DOLLAR = 1_000_000_000;

    public function __construct(public int $amount) {}

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse an exact decimal dollar string such as "0.046125000" or "12.50".
     */
    public static function fromDecimal(string $dollars): self
    {
        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,9}))?$/', $dollars, $matches)) {
            throw new InvalidArgumentException("Not an exact dollar amount with at most 9 decimals: [{$dollars}].");
        }

        $whole = (int) $matches[2];
        $fraction = (int) str_pad($matches[3] ?? '', 9, '0');
        $amount = $whole * self::PER_DOLLAR + $fraction;

        return new self($matches[1] === '-' ? -$amount : $amount);
    }

    /**
     * The exact amount as a dollar string with nine decimals, e.g. "0.046125000".
     */
    public function toDecimal(): string
    {
        $sign = $this->amount < 0 ? '-' : '';
        $absolute = abs($this->amount);

        return sprintf('%s%d.%09d', $sign, intdiv($absolute, self::PER_DOLLAR), $absolute % self::PER_DOLLAR);
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function minus(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function times(int $factor): self
    {
        return new self($this->amount * $factor);
    }

    /**
     * Multiply by a whole-number percentage, rounding any fraction of a nanodollar up.
     *
     * Only used for non-negative amounts (prices), where rounding up means we
     * never undercharge by a sub-nanodollar remainder.
     */
    public function percentRoundedUp(int $percent): self
    {
        if ($this->amount < 0) {
            throw new InvalidArgumentException('Percentages are only applied to non-negative amounts.');
        }

        $scaled = $this->amount * $percent;

        return new self(intdiv($scaled, 100) + ($scaled % 100 === 0 ? 0 : 1));
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
