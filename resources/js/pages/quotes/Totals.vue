<script setup lang="ts">
import { formatMoney, formatTaxRate } from '@/lib/money';
import type { CalculatedQuote } from '@/types';

withDefaults(
    defineProps<{
        totals: CalculatedQuote | null;
        calculating: boolean;
        stale?: boolean;
    }>(),
    { stale: false },
);
</script>

<template>
    <aside class="sticky top-4 grid gap-3 rounded-xl border p-4">
        <div class="flex items-center justify-between">
            <h2 class="font-medium">Totals</h2>
            <span v-if="calculating" class="text-xs text-muted-foreground">
                Calculating
            </span>
        </div>

        <!--
            Holding the previous figures while something is half-typed is
            deliberate, but it has to say so. An incomplete discount would
            otherwise look exactly like a discount that had been applied.
        -->
        <p
            v-if="stale"
            class="rounded-lg border border-destructive/50 bg-destructive/10 p-2 text-xs text-destructive"
        >
            These totals are out of date. Something in the quote is incomplete,
            so the figures below are from the last version that added up.
        </p>

        <div v-if="!totals" class="grid gap-2" aria-hidden="true">
            <div class="h-4 w-3/4 animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-1/2 animate-pulse rounded bg-muted"></div>
            <div class="h-4 w-2/3 animate-pulse rounded bg-muted"></div>
        </div>

        <dl v-else class="grid gap-2 text-sm" :class="{ 'opacity-50': stale }">
            <div
                v-if="totals.quoteDiscount !== '0.00'"
                class="flex justify-between"
            >
                <dt class="text-foreground">Subtotal before discount</dt>
                <dd class="tabular-nums">
                    {{ formatMoney(totals.subtotalBeforeQuoteDiscount) }}
                </dd>
            </div>

            <div
                v-if="totals.quoteDiscount !== '0.00'"
                class="flex justify-between"
            >
                <dt class="text-foreground">Quote discount</dt>
                <dd class="tabular-nums">
                    - {{ formatMoney(totals.quoteDiscount) }}
                </dd>
            </div>

            <div class="flex justify-between">
                <dt class="text-foreground">Subtotal ex. VAT</dt>
                <dd class="tabular-nums">{{ formatMoney(totals.subtotal) }}</dd>
            </div>

            <div
                v-for="taxClassTotal in totals.taxClassTotals"
                :key="taxClassTotal.taxClassId"
                class="flex justify-between"
            >
                <dt class="text-foreground">
                    VAT {{ formatTaxRate(taxClassTotal.percentage) }}
                    <span class="text-muted-foreground">
                        over {{ formatMoney(taxClassTotal.net) }}
                    </span>
                </dt>
                <dd class="tabular-nums">
                    {{ formatMoney(taxClassTotal.vat) }}
                </dd>
            </div>

            <div class="flex justify-between border-t pt-2 font-medium">
                <dt>Total</dt>
                <dd class="tabular-nums">{{ formatMoney(totals.total) }}</dd>
            </div>

            <p
                v-if="totals.roundingOverride !== null"
                class="rounded-lg bg-muted p-2 text-xs text-foreground"
            >
                Overridden. The calculated total was
                {{ formatMoney(totals.calculatedTotal) }} and is discarded.
            </p>
        </dl>
    </aside>
</template>
