<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import QuoteVersionController from '@/actions/App/Http/Controllers/QuoteVersionController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`Quote ${quote.id}`"
                :description="
                    quote.version_count > 1
                        ? `Editing version ${quote.version_number} of ${quote.version_count}`
                        : 'Saving overwrites this version unless you save as a new one'
                "
            />

            <Button v-if="quote.version_count > 1" variant="secondary" as-child>
                <Link :href="QuoteVersionController.index(props.quote.id).url">
                    Version history
                </Link>
            </Button>
        </div>

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
