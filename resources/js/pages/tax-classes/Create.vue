<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import TaxClassController from '@/actions/App/Http/Controllers/TaxClassController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index } from '@/routes/tax-classes';

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Tax classes', href: index() },
      { title: 'New tax class', href: create() },
    ],
  },
});
</script>

<template>
  <Head title="New tax class" />

  <div class="flex flex-col space-y-6 p-4">
    <Heading
      variant="small"
      title="New tax class"
      description="A VAT rate you can apply to products and quote lines"
    />

    <Form
      v-bind="TaxClassController.store.form()"
      class="max-w-lg space-y-6"
      v-slot="{ errors, processing }"
    >
      <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input id="name" name="name" required autofocus placeholder="Standard 21%" />
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
          required
          placeholder="21.00"
        />
        <p class="text-xs text-foreground">0 is valid - use it for zero-rated or reverse-charge.</p>
        <InputError :message="errors.percentage" />
      </div>

      <div class="flex items-center gap-4">
        <Button :disabled="processing">Create tax class</Button>
        <Button variant="ghost" as-child>
          <Link :href="index()">Cancel</Link>
        </Button>
      </div>
    </Form>
  </div>
</template>
