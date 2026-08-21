<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import QuoteForm from '@/pages/quotes/Form.vue';
import { create, index, store } from '@/routes/quotes';
import type { CustomerOption, ProductOption, TaxClassOption } from '@/types';

defineProps<{
  customers: CustomerOption[];
  products: ProductOption[];
  taxClasses: TaxClassOption[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Quotes', href: index() },
      { title: 'New quote', href: create() },
    ],
  },
});
</script>

<template>
  <Head title="New quote" />

  <div class="flex flex-col space-y-6 p-4">
    <Heading
      variant="small"
      title="New quote"
      description="Totals are calculated on the server as you build"
    />

    <QuoteForm
      :customers="customers"
      :products="products"
      :tax-classes="taxClasses"
      :submit-url="store().url"
      submit-method="post"
      submit-label="Create quote"
    />
  </div>
</template>
