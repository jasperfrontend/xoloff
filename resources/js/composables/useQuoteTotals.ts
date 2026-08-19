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

                // A failed validation resolves with no response instead of
                // throwing. Choosing a discount type before typing its value is
                // a normal halfway state, and it should leave the last good
                // totals on screen rather than blanking them.
                if (calculated) {
                    totals.value = calculated;
                }
            } catch {
                // Same reasoning for a request that does throw.
            }
        }, delay);
    }

    tryOnScopeDispose(() => clearTimeout(timer));

    return { totals, request, refresh };
}
