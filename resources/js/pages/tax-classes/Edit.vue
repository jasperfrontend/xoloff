<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TaxClassController from '@/actions/App/Http/Controllers/TaxClassController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/tax-classes';

interface TaxClass {
  id: number;
  name: string;
  percentage: string;
}

defineProps<{ taxClass: TaxClass }>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Tax classes', href: index() }],
  },
});
</script>

<template>
  <Head :title="taxClass.name" />

  <div class="flex flex-col space-y-6 p-4">
    <Heading variant="small" :title="taxClass.name" description="Update this VAT rate" />

    <Form
      v-bind="TaxClassController.update.form(taxClass.id)"
      class="max-w-lg space-y-6"
      v-slot="{ errors, processing }"
    >
      <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" name="name" :default-value="taxClass.name" required autofocus />
        <InputError :message="errors.name" />
      </div>

      <div class="grid gap-2">
        <Label for="percentage">Percentage</Label>
        <Input
          id="percentage"
          name="percentage"
          type="number"
          step="0.01"
          min="0"
          max="100"
          :default-value="taxClass.percentage"
          required
        />
        <p class="text-xs text-foreground">
          Changing this does not alter quotes already saved - line items carry their own tax class.
        </p>
        <InputError :message="errors.percentage" />
      </div>

      <div class="flex items-center gap-4">
        <Button :disabled="processing">Save changes</Button>
        <Button variant="ghost" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </Form>
  </div>
</template>
