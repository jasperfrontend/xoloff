<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { formatDate } from '@/lib/dates';
import { formatAmount, formatMoney, formatPercentage } from '@/lib/money';
import PortalDecision from '@/pages/portal/Decision.vue';
import PortalSender from '@/pages/portal/Sender.vue';
import type { CalculatedQuote, QuoteStatus } from '@/types';

/**
 * The quote as the customer reads it (SPEC §8).
 *
 * Dutch, like the PDF and for the same reason: this is the one page in xoloff
 * a customer ever sees. Every figure comes from the server, because SPEC §5 is
 * implemented once, in PHP, and a second opinion about the money is the last
 * thing this page should hold.
 */
interface PortalLineItem {
  id: number;
  name: string;
  specs: Record<string, string> | null;
  quantity: string;
  unit_price_ex_vat: string;
  tax_class_percentage: string;
}

const props = defineProps<{
  sender: { company_name: string | null; logo_url: string | null };
  quote: {
    id: number;
    company_name: string;
    contact_person: string;
    valid_until: string | null;
    pdf_url: string;
    approve_url: string;
    deny_url: string;
    status: QuoteStatus;
    deny_reason: string | null;
    can_decide: boolean;
  };
  version: {
    version_number: number;
    intro_text_snapshot: string | null;
    footer_text_snapshot: string | null;
    line_items: PortalLineItem[];
  } | null;
  totals: CalculatedQuote | null;
}>();

// The PDF is rendered by a container that sleeps when idle, so asking for one
// can fail while the page itself is perfectly fine.
const page = usePage();
const pdfError = computed(() => page.props.errors?.pdf);

function specEntries(specs: Record<string, string> | null) {
  return Object.entries(specs ?? {});
}

/**
 * Matched by id rather than by position. The engine happens to return lines in
 * the order it was given them, but an amount landing against the wrong
 * description is not a mistake a customer would spot, and it is their money.
 */
function netFor(lineItem: PortalLineItem): string | null {
  return props.totals?.lines.find((line) => line.lineItemId === lineItem.id)?.net ?? null;
}
</script>

<template>
  <Head :title="`Offerte ${quote.id}`" />

  <div class="grid gap-4">
    <article class="rounded-xl border bg-background p-6 sm:p-8">
      <header class="flex flex-wrap items-start justify-between gap-4 border-b pb-6">
        <div>
          <h1 class="text-xl font-semibold">
            Offerte {{ quote.id }} voor {{ quote.company_name }}
          </h1>
          <p v-if="quote.valid_until" class="mt-1 text-sm text-foreground">
            Geldig tot en met
            <strong>{{ formatDate(quote.valid_until) }}</strong>
          </p>
        </div>

        <PortalSender :sender="sender" />
      </header>

      <!--
                Only when the intro does not open with one itself. That text is
                written in the quote editor and almost always starts "Beste
                klant," - greeting the customer twice on the one page they read
                looks like a mail merge that went wrong.
            -->
      <p v-if="!version?.intro_text_snapshot" class="mt-6 text-foreground">
        Beste {{ quote.contact_person }},
      </p>

      <!--
                Rendered as markup rather than escaped, because that is what it
                is: editor output, allowlisted down to a handful of tags by
                App\Support\Html\RichText before it was ever stored. Escaping it
                here would print the tags on screen.
            -->
      <!-- eslint-disable-next-line vue/no-v-html -->
      <div
        v-if="version?.intro_text_snapshot"
        class="rich-text mt-6 text-foreground"
        v-html="version.intro_text_snapshot"
      ></div>

      <template v-if="version && totals">
        <div class="mt-8 overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="border-b text-left">
              <tr class="text-xs text-muted-foreground uppercase">
                <th class="py-2 pr-4 font-medium">Omschrijving</th>
                <th class="px-2 py-2 text-right font-medium">Aantal</th>
                <th class="px-2 py-2 text-right font-medium">Stukprijs</th>
                <th class="px-2 py-2 text-right font-medium">Btw</th>
                <th class="py-2 pl-2 text-right font-medium">Bedrag</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="version.line_items.length === 0" class="border-b">
                <td colspan="5" class="py-6 text-center text-foreground">
                  Deze offerte bevat nog geen regels.
                </td>
              </tr>
              <tr
                v-for="lineItem in version.line_items"
                :key="lineItem.id"
                class="border-b align-top"
              >
                <td class="py-3 pr-4">
                  <div class="font-medium">
                    {{ lineItem.name }}
                  </div>
                  <dl
                    v-if="specEntries(lineItem.specs).length"
                    class="mt-1 flex flex-wrap gap-x-4 text-xs text-foreground"
                  >
                    <div
                      v-for="[key, value] in specEntries(lineItem.specs)"
                      :key="key"
                      class="flex gap-1"
                    >
                      <dt class="text-muted-foreground">{{ key }}:</dt>
                      <dd>{{ value }}</dd>
                    </div>
                  </dl>
                </td>
                <td class="px-2 py-3 text-right whitespace-nowrap tabular-nums">
                  {{ formatAmount(lineItem.quantity) }}
                </td>
                <td class="px-2 py-3 text-right whitespace-nowrap tabular-nums">
                  {{ formatMoney(lineItem.unit_price_ex_vat) }}
                </td>
                <td class="px-2 py-3 text-right whitespace-nowrap tabular-nums">
                  {{ formatPercentage(lineItem.tax_class_percentage) }}
                </td>
                <td class="py-3 pl-2 text-right whitespace-nowrap tabular-nums">
                  {{ netFor(lineItem) === null ? '-' : formatMoney(netFor(lineItem)!) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!--
                    Kept narrow and to the right, so the eye lands on the total
                    rather than sweeping the width of the page to find it. The
                    same shape the PDF uses, deliberately.
                -->
        <table class="mt-6 ml-auto w-full max-w-sm text-sm">
          <tbody>
            <tr v-if="totals.quoteDiscount !== '0.00'">
              <td class="py-1 text-muted-foreground">Subtotaal voor korting</td>
              <td class="py-1 text-right tabular-nums">
                {{ formatMoney(totals.subtotalBeforeQuoteDiscount) }}
              </td>
            </tr>
            <tr v-if="totals.quoteDiscount !== '0.00'">
              <td class="py-1 text-muted-foreground">Korting op de offerte</td>
              <td class="py-1 text-right tabular-nums">
                - {{ formatMoney(totals.quoteDiscount) }}
              </td>
            </tr>
            <tr>
              <td class="py-1 text-muted-foreground">Subtotaal excl. btw</td>
              <td class="py-1 text-right tabular-nums">
                {{ formatMoney(totals.subtotal) }}
              </td>
            </tr>
            <!--
                            One line per tax class. A quote may mix rates, and a
                            single "btw" figure would hide which rate applied to
                            what.
                        -->
            <tr v-for="taxClassTotal in totals.taxClassTotals" :key="taxClassTotal.taxClassId">
              <td class="py-1 text-muted-foreground">
                Btw
                {{ formatPercentage(taxClassTotal.percentage) }}
                over {{ formatMoney(taxClassTotal.net) }}
              </td>
              <td class="py-1 text-right tabular-nums">
                {{ formatMoney(taxClassTotal.vat) }}
              </td>
            </tr>
            <tr class="border-t">
              <td class="pt-3 text-base font-semibold">Totaal</td>
              <td class="pt-3 text-right text-base font-semibold tabular-nums">
                {{ formatMoney(totals.total) }}
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- eslint-disable-next-line vue/no-v-html -->
      <div
        v-if="version?.footer_text_snapshot"
        class="rich-text mt-10 border-t pt-6 text-xs text-muted-foreground"
        v-html="version.footer_text_snapshot"
      ></div>
    </article>

    <PortalDecision
      :approve-url="quote.approve_url"
      :deny-url="quote.deny_url"
      :status="quote.status"
      :deny-reason="quote.deny_reason"
      :can-decide="quote.can_decide"
    />

    <p
      v-if="pdfError"
      class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ pdfError }}
    </p>

    <p class="text-sm">
      <!-- A plain link, not an Inertia visit: the response is a file. -->
      <a :href="quote.pdf_url" class="cursor-pointer text-foreground underline underline-offset-4">
        Download deze offerte als PDF
      </a>
    </p>
  </div>
</template>
