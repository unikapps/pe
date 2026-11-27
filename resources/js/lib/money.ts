import type { Dollars } from '@/types';

const NANOS_PER_DOLLAR = 1_000_000_000n;

/**
 * Format an exact decimal dollar string for display, rounding half up to `places`.
 * Uses BigInt so the page never introduces float rounding of its own.
 */
export function formatUsd(amount: Dollars, places = 4): string {
    const negative = amount.startsWith('-');
    const [whole, fraction = ''] = amount.replace('-', '').split('.');
    const nanos =
        BigInt(whole) * NANOS_PER_DOLLAR +
        BigInt(fraction.padEnd(9, '0').slice(0, 9));

    const unit = 10n ** BigInt(9 - places);
    const rounded = (nanos + unit / 2n) / unit;
    const scale = 10n ** BigInt(places);
    const digits = (rounded % scale).toString().padStart(places, '0');

    return `${negative ? '-' : ''}$${rounded / scale}${places > 0 ? `.${digits}` : ''}`;
}
