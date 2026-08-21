<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import QuotePdfController from '@/actions/App/Http/Controllers/QuotePdfController';
import QuoteVersionController from '@/actions/App/Http/Controllers/QuoteVersionController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { formatAmount, formatMoney, formatPercentage } from '@/lib/money';
import Totals from '@/pages/quotes/Totals.vue';
import { index } from '@/routes/quotes';
import type { CalculatedQuote, DiscountType } from '@/types';

interface VersionLineItem {
  id: number;
  name: string;
  specs: Record<string, string> | null;
  quantity: string;
  unit_price_ex_vat: string;
  tax_class_name: string;
  discount_type: DiscountType | null;
  discount_value: string | null;
}

interface Version {
  id: number;
  version_number: number;
  is_current: boolean;
  saved_at: string | null;
  intro_text_snapshot: string | null;
  footer_text_snapshot: string | null;
  line_items: VersionLineItem[];
}

const props = defineProps<{
  quote: { id: number; customer_name: string };
  version: Version;
  totals: CalculatedQuote;
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Quotes', href: index() }],
  },
});

const page = usePage();
const pdfError = computed(() => page.props.errors?.pdf);

function describeDiscount(lineItem: VersionLineItem): string | null {
  if (lineItem.discount_type === null || lineItem.discount_value === null) {
    return null;
  }

  return lineItem.discount_type === 'percentage'
    ? formatPercentage(lineItem.discount_value)
    : formatMoney(lineItem.discount_value);
}

function specEntries(specs: Record<string, string> | null) {
  return Object.entries(specs ?? {});
}

/**
 * Matched by id rather than by position. The engine happens to return lines in
 * the order it was given them, but a net figure landing against the wrong
 * description is not a mistake anyone would spot by reading the page.
 */
function netFor(lineItem: VersionLineItem): string | null {
  return props.totals.lines.find((line) => line.lineItemId === lineItem.id)?.net ?? null;
}
</script>

<template>
  <Head :title="`Quote ${quote.id} V${version.version_number}`" />

  <div class="flex flex-col space-y-6 p-4">
    <div class="flex items-start justify-between gap-4">
      <Heading
        variant="small"
        :title="`Quote ${quote.id}, version ${version.version_number}`"
        :description="`${quote.customer_name}, saved ${formatDateTime(version.saved_at)}`"
      />

      <div class="flex items-center gap-2">
        <Button variant="secondary" as-child>
          <!-- Reprints this version exactly as it went out, texts
                         and all, rather than whatever the quote says now. A
                         plain link, because the response is a file. -->
          <a
            :href="
              QuotePdfController.version({
                quote: props.quote.id,
                version: props.version.id,
              }).url
            "
          >
            Download PDF
          </a>
        </Button>

        <Button variant="secondary" as-child>
          <Link :href="QuoteVersionController.index(props.quote.id).url">All versions</Link>
        </Button>
      </div>
    </div>

    <div
      v-if="pdfError"
      class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ pdfError }}
    </div>

    <!--
            There is no edit here on purpose. Rewriting a version that a
            customer may already have seen is the thing versioning exists to
            prevent, so the page says so rather than leaving it to be inferred
            from a missing button.
        -->
    <p class="max-w-prose rounded-lg border bg-muted/30 px-4 py-3 text-sm text-foreground">
      <template v-if="version.is_current">
        This is the current version. Edit it from the quote itself.
      </template>
      <template v-else>
        This version has been superseded and is kept exactly as it was saved, texts included. It
        cannot be edited.
      </template>
    </p>

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
      <div class="grid gap-6">
        <!--
                    Rendered as markup rather than escaped, because that is
                    what it is: editor output, allowlisted down to a handful of
                    tags by App\Support\Html\RichText before it was ever
                    stored. Escaping it here would print the tags on screen.
                -->
        <section v-if="version.intro_text_snapshot" class="grid gap-2 rounded-xl border p-4">
          <h2 class="font-medium">Intro</h2>
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div class="rich-text text-sm text-foreground" v-html="version.intro_text_snapshot"></div>
        </section>

        <section class="overflow-x-auto rounded-xl border">
          <table class="w-full text-sm">
            <thead class="bg-muted/50 text-left">
              <tr>
                <th class="px-4 py-3 font-medium">Description</th>
                <th class="px-4 py-3 font-medium">Qty</th>
                <th class="px-4 py-3 font-medium">Unit price</th>
                <th class="px-4 py-3 font-medium">Discount</th>
                <th class="px-4 py-3 font-medium">VAT</th>
                <th class="px-4 py-3 font-medium">Net</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="version.line_items.length === 0">
                <td colspan="6" class="px-4 py-8 text-center text-foreground">
                  This version has no lines.
                </td>
              </tr>
              <tr
                v-for="lineItem in version.line_items"
                :key="lineItem.id"
                class="border-t align-top"
              >
                <td class="px-4 py-3">
                  <div class="font-medium">
                    {{ lineItem.name }}
                  </div>
                  <dl
                    v-if="specEntries(lineItem.specs).length"
                    class="mt-1 grid gap-0.5 text-xs text-foreground"
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
                <td class="px-4 py-3 tabular-nums">
                  {{ formatAmount(lineItem.quantity) }}
                </td>
                <td class="px-4 py-3 tabular-nums">
                  {{ formatMoney(lineItem.unit_price_ex_vat) }}
                </td>
                <td class="px-4 py-3 tabular-nums">
                  {{ describeDiscount(lineItem) ?? '-' }}
                </td>
                <td class="px-4 py-3 text-foreground">
                  {{ lineItem.tax_class_name }}
                </td>
                <td class="px-4 py-3 tabular-nums">
                  {{ netFor(lineItem) === null ? '-' : formatMoney(netFor(lineItem)!) }}
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <section v-if="version.footer_text_snapshot" class="grid gap-2 rounded-xl border p-4">
          <h2 class="font-medium">Footer</h2>
          <!-- eslint-disable-next-line vue/no-v-html -->
          <div
            class="rich-text text-sm text-foreground"
            v-html="version.footer_text_snapshot"
          ></div>
        </section>
      </div>

      <Totals :totals="totals" :calculating="false" />
    </div>
  </div>
</template>
