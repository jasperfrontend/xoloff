<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TaxClassController from '@/actions/App/Http/Controllers/TaxClassController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import ResourceHeader from '@/components/ResourceHeader.vue';
import { formatTaxRate } from '@/lib/money';
import { index } from '@/routes/tax-classes';

interface TaxClass {
  id: number;
  name: string;
  percentage: string;
  products_count: number;
}

defineProps<{ taxClasses: TaxClass[] }>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Tax classes', href: index() }],
  },
});

const page = usePage();
const deleteError = computed(() => page.props.errors?.taxClass);
</script>

<template>
  <Head title="Tax classes" />

  <div class="flex flex-col space-y-6 p-4">
    <ResourceHeader
      title="Tax classes"
      description="VAT rates available when building a quote"
      :create-href="TaxClassController.create().url"
      create-label="New tax class"
    />

    <div
      v-if="deleteError"
      class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
    >
      {{ deleteError }}
    </div>

    <div class="overflow-x-auto rounded-xl border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-4 py-3 font-medium">Name</th>
            <th class="px-4 py-3 font-medium">Rate</th>
            <th class="px-4 py-3 font-medium">Products</th>
            <th class="w-px px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="taxClasses.length === 0">
            <td colspan="4" class="px-4 py-8 text-center text-foreground">No tax classes yet.</td>
          </tr>
          <tr v-for="taxClass in taxClasses" :key="taxClass.id" class="border-t">
            <td class="px-4 py-3">
              <Link
                :href="TaxClassController.edit(taxClass.id).url"
                class="cursor-pointer font-medium underline-offset-4 hover:underline"
              >
                {{ taxClass.name }}
              </Link>
            </td>
            <td class="px-4 py-3 tabular-nums">
              {{ formatTaxRate(taxClass.percentage) }}
            </td>
            <td class="px-4 py-3 text-foreground">
              {{ taxClass.products_count }}
            </td>
            <td class="px-4 py-3 text-right">
              <ConfirmDeleteButton
                :action="TaxClassController.destroy.form(taxClass.id)"
                title="Delete tax class?"
                description="Only possible while no product still uses it."
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
