/**
 * Money and number display, in Dutch conventions throughout: a comma for the
 * decimal separator, a full stop for thousands, and always two decimals.
 *
 * The server holds and sends canonical decimal strings such as "4682.70".
 * Those are the values that get calculated and saved. Everything here is for
 * reading only, and nothing formatted by this file is ever sent back.
 */
const amountFormatter = new Intl.NumberFormat('nl-NL', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

function toNumber(value: string | number): number {
    const parsed = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

/**
 * 4682.7 becomes "4.682,70".
 */
export function formatAmount(value: string | number): string {
    return amountFormatter.format(toNumber(value));
}

/**
 * 4682.7 becomes "€ 4.682,70", joined by a non-breaking space so the symbol
 * never ends up orphaned on its own line.
 */
export function formatMoney(value: string | number): string {
    return `€ ${formatAmount(value)}`;
}

/**
 * 21 becomes "21,00%".
 */
export function formatPercentage(value: string | number): string {
    return `${formatAmount(value)}%`;
}

/**
 * Settles a half-typed amount into the canonical two-decimal form the server
 * expects, so "182,7" is stored as "182.70" and then displayed as "182,70".
 * An empty field stays empty, because that means "not set" rather than zero.
 */
export function normalizeAmount(value: string | number): string {
    // Vue casts v-model on a number input to an actual number, so this receives
    // either depending on whether the field has been typed in yet.
    const text = String(value ?? '');

    if (text.trim() === '') {
        return '';
    }

    const parsed = Number(text.replace(',', '.'));

    return Number.isFinite(parsed) ? parsed.toFixed(2) : text;
}
