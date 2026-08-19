<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import QuoteForm from '@/pages/quotes/Form.vue';
import { index, update } from '@/routes/quotes';
import { store as storeVersion } from '@/routes/quotes/versions';
import type {
    CalculatedQuote,
    CustomerOption,
    ProductOption,
    QuoteContent,
    TaxClassOption,
} from '@/types';

interface Quote extends QuoteContent {
    id: number;
    customer_id: number | null;
    version_number: number;
    version_count: number;
}

const props = defineProps<{
    quote: Quote;
    totals: CalculatedQuote | null;
    customers: CustomerOption[];
    products: ProductOption[];
    taxClasses: TaxClassOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotes', href: index() }],
    },
});
</script>

<template>
    <Head :title="`Quote ${quote.id}`" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            :title="`Quote ${quote.id}`"
            :description="
                quote.version_count > 1
                    ? `Editing version ${quote.version_number} of ${quote.version_count}`
                    : 'Saving overwrites this version unless you save as a new one'
            "
        />

        <QuoteForm
            :customers="customers"
            :products="products"
            :tax-classes="taxClasses"
            :quote="props.quote"
            :initial-totals="totals"
            :submit-url="update(quote.id).url"
            submit-method="put"
            submit-label="Save changes"
            :new-version-url="storeVersion(quote.id).url"
        />
    </div>
</template>
