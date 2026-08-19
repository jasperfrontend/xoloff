import { describe, expect, it } from 'vitest';
import {
    formatAmount,
    formatMoney,
    formatPercentage,
    normalizeAmount,
} from '@/lib/money';

/** formatMoney joins the symbol to the amount with a non-breaking space. */
const NBSP = ' ';

describe('formatAmount', () => {
    it('uses a comma for the decimals', () => {
        expect(formatAmount('90.00')).toBe('90,00');
    });

    it('uses a full stop for the thousands', () => {
        expect(formatAmount('4682.70')).toBe('4.682,70');
        expect(formatAmount('1234567.89')).toBe('1.234.567,89');
    });

    it('always shows two decimals', () => {
        // "182,7" was on screen before this existed, next to "90,00".
        expect(formatAmount('182.7')).toBe('182,70');
        expect(formatAmount('43')).toBe('43,00');
    });

    it('reads the plain decimal strings the server sends', () => {
        expect(formatAmount('0.00')).toBe('0,00');
        expect(formatAmount('0.07')).toBe('0,07');
    });

    it('rounds half up, matching the engine', () => {
        expect(formatAmount('0.005')).toBe('0,01');
    });

    it('keeps a negative readable', () => {
        expect(formatAmount('-18.00')).toBe('-18,00');
    });

    it('falls back to zero rather than showing NaN', () => {
        expect(formatAmount('')).toBe('0,00');
        expect(formatAmount('nonsense')).toBe('0,00');
    });
});

describe('formatMoney', () => {
    it('puts a non-breaking space between the symbol and the amount', () => {
        expect(formatMoney('4682.70')).toBe(`€${NBSP}4.682,70`);
    });

    it('never leaves the symbol able to wrap on its own', () => {
        expect(formatMoney('90.00')).not.toContain('€ ');
    });
});

describe('formatPercentage', () => {
    it('formats a rate the Dutch way', () => {
        expect(formatPercentage('21.00')).toBe('21,00%');
        expect(formatPercentage('0.00')).toBe('0,00%');
    });
});

describe('normalizeAmount', () => {
    it('settles a half-typed amount into two decimals', () => {
        expect(normalizeAmount('182.7')).toBe('182.70');
        expect(normalizeAmount('43')).toBe('43.00');
    });

    it('accepts a comma, because a Dutch keyboard offers one', () => {
        expect(normalizeAmount('182,7')).toBe('182.70');
    });

    it('leaves an empty field empty, since that means not set', () => {
        // Turning this into "0.00" would apply a zero discount rather than
        // no discount, and the two are not the same thing.
        expect(normalizeAmount('')).toBe('');
        expect(normalizeAmount('   ')).toBe('');
    });

    it('hands back anything it cannot read, for validation to reject', () => {
        expect(normalizeAmount('nonsense')).toBe('nonsense');
    });

    it('produces the canonical form the server parses', () => {
        // The server splits on the full stop, so a comma would be truncated
        // into a completely different number.
        expect(normalizeAmount('1234,5')).toBe('1234.50');
    });
});
