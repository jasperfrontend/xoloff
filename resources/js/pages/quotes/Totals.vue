<script setup lang="ts">
import type { CalculatedQuote } from '@/types';

defineProps<{
    totals: CalculatedQuote | null;
    calculating: boolean;
}>();
</script>

<template>
    <aside class="sticky top-4 grid gap-3 rounded-xl border p-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium">Totals</h2>
            <span v-if="calculating" class="text-xs text-muted-foreground">
                Calculating
            </span>
        </div>

        <div v-if="!totals" class="grid gap-2" aria-hidden="true">
            <div class="h-4 w-3/4 animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-1/2 animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-2/3 animate-pulse rounded bg-muted"></div>
        </div>

        <dl v-else class="grid gap-2 text-sm">
            <div
                v-if="totals.quoteDiscount !== '0.00'"
                class="flex justify-between"
            >
                <dt class="text-foreground">Subtotal before discount</dt>
                <dd class="tabular-nums">
                    € {{ totals.subtotalBeforeQuoteDiscount }}
                </dd>
            </div>

            <div
                v-if="totals.quoteDiscount !== '0.00'"
                class="flex justify-between"
            >
                <dt class="text-foreground">Quote discount</dt>
                <dd class="tabular-nums">- € {{ totals.quoteDiscount }}</dd>
            </div>

            <div class="flex justify-between">
                <dt class="text-foreground">Subtotal ex. VAT</dt>
                <dd class="tabular-nums">€ {{ totals.subtotal }}</dd>
            </div>

            <div
                v-for="taxClassTotal in totals.taxClassTotals"
                :key="taxClassTotal.taxClassId"
                class="flex justify-between"
            >
                <dt class="text-foreground">
                    VAT {{ taxClassTotal.percentage }}%
                    <span class="text-muted-foreground">
                        over € {{ taxClassTotal.net }}
                    </span>
                </dt>
                <dd class="tabular-nums">€ {{ taxClassTotal.vat }}</dd>
            </div>

            <div class="flex justify-between border-t pt-2 font-medium">
                <dt>Total</dt>
                <dd class="tabular-nums">€ {{ totals.total }}</dd>
            </div>

            <p
                v-if="totals.roundingOverride !== null"
                class="rounded-lg bg-muted p-2 text-xs text-foreground"
            >
                Overridden. The calculated total was €
                {{ totals.calculatedTotal }} and is discarded.
            </p>
        </dl>
    </aside>
</template>
