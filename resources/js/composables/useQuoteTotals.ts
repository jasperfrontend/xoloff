import { useHttp } from '@inertiajs/vue3';
import { tryOnScopeDispose } from '@vueuse/core';
import { ref } from 'vue';
import type { CalculatedQuote, QuoteContent } from '@/types';

/**
 * Keeps quote totals in step with the builder by asking the server to price
 * the current content. SPEC §5 is implemented once, in PHP, so the browser
 * never holds a second opinion about the money.
 *
 * This lives apart from the form component because both of its awkward details
 * were live bugs, and neither is reachable from a PHP test.
 */
export function useQuoteTotals(
  url: string,
  payload: () => QuoteContent,
  initialTotals: CalculatedQuote | null = null,
  delay = 300,
) {
  const totals = ref<CalculatedQuote | null>(initialTotals);

  /**
   * Set whenever the totals on screen no longer describe what is in the form,
   * which happens as soon as a request is refused. Holding the last good
   * figures is right, but doing it quietly is not: an incomplete discount
   * would otherwise look exactly like a discount that had been applied.
   */
  const stale = ref(false);

  const errors = ref<Record<string, string>>({});

  const request = useHttp<QuoteContent, CalculatedQuote>(payload());

  let timer: ReturnType<typeof setTimeout> | undefined;

  function refresh() {
    clearTimeout(timer);

    timer = setTimeout(async () => {
      try {
        // useHttp treats its argument as an initial snapshot rather
        // than a live source, so the payload has to be handed over
        // through transform, which runs at request time. Without this
        // every request sends the empty content the builder started
        // with.
        const calculated = await request.transform(payload).post(url);

        // A refused request resolves with no response instead of
        // throwing, so this has to be checked rather than assigned.
        if (calculated) {
          totals.value = calculated;
          stale.value = false;
          errors.value = {};

          return;
        }

        stale.value = true;
        errors.value = { ...request.errors } as Record<string, string>;
      } catch {
        stale.value = true;
      }
    }, delay);
  }

  tryOnScopeDispose(() => clearTimeout(timer));

  return { totals, stale, errors, request, refresh };
}
