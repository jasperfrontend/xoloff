import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useQuoteTotals } from '@/composables/useQuoteTotals';
import type { CalculatedQuote, QuoteContent } from '@/types';

/**
 * Records what each request actually put on the wire.
 */
const sentPayloads: QuoteContent[] = [];

let nextResponse: CalculatedQuote | undefined;

let nextErrors: Record<string, string> = {};

/**
 * A faithful stand-in for useHttp: it keeps the data it was given at setup and
 * only consults the transform callback when a request is made. A composable
 * that forgets to call transform therefore sends the stale snapshot here, in
 * exactly the way it did in the browser.
 */
vi.mock('@inertiajs/vue3', () => ({
  useHttp: (initialData: QuoteContent) => {
    let transformer: ((data: QuoteContent) => QuoteContent) | null = null;

    return {
      processing: false,
      transform(callback: (data: QuoteContent) => QuoteContent) {
        transformer = callback;

        return this;
      },
      errors: nextErrors,
      post() {
        sentPayloads.push(transformer ? transformer(initialData) : initialData);

        this.errors = nextErrors;

        return Promise.resolve(nextResponse);
      },
    };
  },
}));

function content(lineCount: number): QuoteContent {
  return {
    discount_type: null,
    discount_value: null,
    rounding_override: null,
    line_items: Array.from({ length: lineCount }, () => ({
      product_id: null,
      name: 'Line',
      specs: null,
      quantity: '1',
      unit_price_ex_vat: '90.00',
      tax_class_id: 1,
      discount_type: null,
      discount_value: null,
    })),
  };
}

function totalsFixture(total: string): CalculatedQuote {
  return {
    lines: [],
    taxClassTotals: [],
    subtotalBeforeQuoteDiscount: total,
    quoteDiscount: '0.00',
    subtotal: total,
    vatTotal: '0.00',
    calculatedTotal: total,
    roundingOverride: null,
    total,
  };
}

describe('useQuoteTotals', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    sentPayloads.length = 0;
    nextResponse = undefined;
    nextErrors = {};
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('sends the content as it is now, not as it was at setup', async () => {
    // The builder always starts empty and gains lines afterwards, which is
    // what made this the live bug: every request posted the empty payload
    // and the server correctly totalled it as zero.
    let current = content(0);

    const { refresh } = useQuoteTotals('/quotes/preview', () => current);

    current = content(2);
    refresh();

    await vi.runAllTimersAsync();

    expect(sentPayloads).toHaveLength(1);
    expect(sentPayloads[0].line_items).toHaveLength(2);
  });

  it('says the totals are stale when a request is refused', async () => {
    const current = content(1);

    nextResponse = totalsFixture('108.90');

    const { totals, stale, errors, refresh } = useQuoteTotals('/quotes/preview', () => current);

    refresh();
    await vi.runAllTimersAsync();

    expect(stale.value).toBe(false);

    // Picking a discount type and leaving its amount empty is refused by
    // the server, and used to look exactly like a discount that had been
    // applied: the figures simply stopped moving.
    nextResponse = undefined;
    nextErrors = {
      discount_value: 'The discount value field is required.',
    };

    refresh();
    await vi.runAllTimersAsync();

    expect(stale.value).toBe(true);
    expect(errors.value.discount_value).toContain('required');
    expect(totals.value?.total).toBe('108.90');
  });

  it('stops being stale once the content adds up again', async () => {
    const current = content(1);

    nextResponse = undefined;
    nextErrors = {
      discount_value: 'The discount value field is required.',
    };

    const { stale, errors, refresh } = useQuoteTotals('/quotes/preview', () => current);

    refresh();
    await vi.runAllTimersAsync();

    expect(stale.value).toBe(true);

    nextResponse = totalsFixture('196.02');
    nextErrors = {};

    refresh();
    await vi.runAllTimersAsync();

    expect(stale.value).toBe(false);
    expect(errors.value).toEqual({});
  });

  it('keeps the last good totals when a request comes back empty', async () => {
    const current = content(1);

    nextResponse = totalsFixture('108.90');

    const { totals, refresh } = useQuoteTotals('/quotes/preview', () => current);

    refresh();
    await vi.runAllTimersAsync();

    expect(totals.value?.total).toBe('108.90');

    // A failed validation, such as picking a discount type before typing
    // its value, resolves with no response at all.
    nextResponse = undefined;

    refresh();
    await vi.runAllTimersAsync();

    expect(totals.value?.total).toBe('108.90');
  });

  it('starts from the totals it was given', () => {
    const { totals } = useQuoteTotals('/quotes/preview', () => content(0), totalsFixture('217.80'));

    expect(totals.value?.total).toBe('217.80');
  });

  it('updates the totals when a request succeeds', async () => {
    nextResponse = totalsFixture('196.02');

    const { totals, refresh } = useQuoteTotals('/quotes/preview', () => content(1));

    expect(totals.value).toBeNull();

    refresh();
    await vi.runAllTimersAsync();

    expect(totals.value?.total).toBe('196.02');
  });

  it('collapses a burst of edits into one request', async () => {
    const { refresh } = useQuoteTotals('/quotes/preview', () => content(1));

    refresh();
    refresh();
    refresh();

    await vi.runAllTimersAsync();

    // Typing a price is several edits in a row, and each one must not cost
    // a round trip.
    expect(sentPayloads).toHaveLength(1);
  });
});
