<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import QuoteController from '@/actions/App/Http/Controllers/QuoteController';
import QuoteVersionController from '@/actions/App/Http/Controllers/QuoteVersionController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import ResourceHeader from '@/components/ResourceHeader.vue';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/quotes';

interface Quote {
    id: number;
    customer_name: string;
    version_number: number | null;
    line_count: number;
    total: string;
}

defineProps<{ quotes: Quote[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotes', href: index() }],
    },
});
</script>

<template>
    <Head title="Quotes" />

    <div class="flex flex-col space-y-6 p-4">
        <ResourceHeader
            title="Quotes"
            description="Every quote, showing the total of its current version"
            :create-href="QuoteController.create().url"
            create-label="New quote"
        />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Quote</th>
                        <th class="px-4 py-3 font-medium">Customer</th>
                        <th class="px-4 py-3 font-medium">Version</th>
                        <th class="px-4 py-3 font-medium">Lines</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="w-px px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="quotes.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-foreground"
                        >
                            No quotes yet.
                        </td>
                    </tr>
                    <tr
                        v-for="quote in quotes"
                        :key="quote.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="QuoteController.edit(quote.id).url"
                                class="cursor-pointer font-medium underline-offset-4 hover:underline"
                            >
                                Quote {{ quote.id }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-foreground">
                            {{ quote.customer_name }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            <Link
                                v-if="quote.version_number"
                                :href="
                                    QuoteVersionController.index(quote.id).url
                                "
                                class="cursor-pointer underline-offset-4 hover:underline"
                            >
                                V{{ quote.version_number }}
                            </Link>
                            <span v-else class="text-foreground">-</span>
                        </td>
                        <td class="px-4 py-3 text-foreground tabular-nums">
                            {{ quote.line_count }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatMoney(quote.total) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ConfirmDeleteButton
                                :action="QuoteController.destroy.form(quote.id)"
                                title="Delete quote?"
                                :description="`Quote ${quote.id} and every version of it will be permanently removed.`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
