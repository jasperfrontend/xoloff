<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/Heading.vue';
import ProductForm from '@/pages/products/Form.vue';
import { index } from '@/routes/products';

interface Spec {
    key: string;
    value: string;
}

interface Product {
    id: number;
    name: string;
    price_ex_vat: string;
    tax_class_id: number;
    category_id: number | null;
    specs: Spec[];
}

interface TaxClass {
    id: number;
    name: string;
    percentage: string;
}

interface Category {
    id: number;
    name: string;
}

defineProps<{
    product: Product;
    taxClasses: TaxClass[];
    categories: Category[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Products', href: index() }],
    },
});
</script>

<template>
    <Head :title="product.name" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            :title="product.name"
            description="Update this catalog entry"
        />

        <ProductForm
            :action="ProductController.update.form(product.id)"
            :tax-classes="taxClasses"
            :categories="categories"
            :product="product"
            submit-label="Save changes"
        />
    </div>
</template>
