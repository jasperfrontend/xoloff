<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import ResourceHeader from '@/components/ResourceHeader.vue';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/products';

interface Product {
    id: number;
    name: string;
    price_ex_vat: string;
    specs_count: number;
    tax_class: { id: number; name: string; percentage: string } | null;
    category: { id: number; name: string } | null;
}

defineProps<{ products: Product[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});
</script>

<template>
    <Head title="Products" />

    <div class="flex flex-col space-y-6 p-4">
        <ResourceHeader
            title="Products"
            description="The catalog quote lines are built from"
            :create-href="ProductController.create().url"
            create-label="New product"
        />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Category</th>
                        <th class="px-4 py-3 font-medium">Price ex. VAT</th>
                        <th class="px-4 py-3 font-medium">Tax class</th>
                        <th class="px-4 py-3 font-medium">Specs</th>
                        <th class="w-px px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="products.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-foreground"
                        >
                            No products yet.
                        </td>
                    </tr>
                    <tr
                        v-for="product in products"
                        :key="product.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="ProductController.edit(product.id).url"
                                class="cursor-pointer font-medium underline-offset-4 hover:underline"
                            >
                                {{ product.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-foreground">
                            {{ product.category?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatMoney(product.price_ex_vat) }}
                        </td>
                        <td class="px-4 py-3 text-foreground">
                            {{ product.tax_class?.name ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-foreground">
                            {{ product.specs_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ConfirmDeleteButton
                                :action="
                                    ProductController.destroy.form(product.id)
                                "
                                title="Delete product?"
                                :description="`${product.name} and its specifications will be permanently removed.`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
