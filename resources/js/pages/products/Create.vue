<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ProductController from '@/actions/App/Http/Controllers/ProductController';
import Heading from '@/components/Heading.vue';
import ProductForm from '@/pages/products/Form.vue';
import { create, index } from '@/routes/products';

interface TaxClass {
  id: number;
  name: string;
  percentage: string;
}

interface Category {
  id: number;
  name: string;
}

defineProps<{ taxClasses: TaxClass[]; categories: Category[] }>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Products', href: index() },
      { title: 'New product', href: create() },
    ],
  },
});
</script>

<template>
  <Head title="New product" />

  <div class="flex flex-col space-y-6 p-4">
    <Heading
      variant="small"
      title="New product"
      description="Catalog values act as defaults - every quote line stays editable"
    />

    <ProductForm
      :action="ProductController.store.form()"
      :tax-classes="taxClasses"
      :categories="categories"
      submit-label="Create product"
    />
  </div>
</template>
