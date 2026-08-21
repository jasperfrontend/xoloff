<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppSettingsController from '@/actions/App/Http/Controllers/AppSettingsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/app-settings';

const props = defineProps<{
  settings: {
    logo_vector_url: string | null;
    logo_raster_url: string | null;
    logo_vector_preview_url: string | null;
    logo_raster_preview_url: string | null;
    company_name: string | null;
    company_address: string | null;
    company_kvk: string | null;
    company_vat_number: string | null;
    default_validity_days: number;
  };
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Application settings', href: edit() }],
  },
});

const companyAddress = ref(props.settings.company_address ?? '');

const previews = computed(() => [
  {
    label: 'SVG, for the quote page and PDF',
    url: props.settings.logo_vector_preview_url,
  },
  {
    label: 'PNG or JPG, for email',
    url: props.settings.logo_raster_preview_url,
  },
]);
</script>

<template>
  <Head title="Application settings" />

  <h1 class="sr-only">Application settings</h1>

  <div class="flex flex-col space-y-6">
    <Heading
      variant="small"
      title="Application"
      description="Configuration shared by everyone, not your own account"
    />

    <div class="grid max-w-lg gap-4 rounded-xl border p-4">
      <div>
        <h2 class="font-medium">Your details</h2>
        <p class="text-sm text-foreground">
          Printed on every quote PDF, opposite the customer's address.
        </p>
      </div>

      <Form
        v-bind="AppSettingsController.update.form()"
        class="grid gap-4"
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <Label for="company_name">Company name</Label>
          <Input
            id="company_name"
            name="company_name"
            :default-value="settings.company_name ?? ''"
          />
          <InputError :message="errors.company_name" />
        </div>

        <div class="grid gap-2">
          <Label for="company_address">Address</Label>
          <textarea
            id="company_address"
            v-model="companyAddress"
            name="company_address"
            rows="3"
            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
          ></textarea>
          <p class="text-xs text-foreground">Printed with the line breaks you type here.</p>
          <InputError :message="errors.company_address" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <div class="grid gap-2">
            <Label for="company_kvk">KvK number</Label>
            <Input
              id="company_kvk"
              name="company_kvk"
              :default-value="settings.company_kvk ?? ''"
            />
            <InputError :message="errors.company_kvk" />
          </div>

          <div class="grid gap-2">
            <Label for="company_vat_number">BTW number</Label>
            <Input
              id="company_vat_number"
              name="company_vat_number"
              :default-value="settings.company_vat_number ?? ''"
            />
            <InputError :message="errors.company_vat_number" />
          </div>
        </div>

        <div>
          <Button :disabled="processing">Save details</Button>
        </div>
      </Form>
    </div>

    <div class="grid max-w-lg gap-4 rounded-xl border p-4">
      <div>
        <h2 class="font-medium">Quotes</h2>
        <p class="text-sm text-foreground">How long a quote stays valid after it is sent.</p>
      </div>

      <Form
        v-bind="AppSettingsController.update.form()"
        class="grid gap-4"
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <Label for="default_validity_days">Valid for</Label>
          <div class="flex items-center gap-2">
            <Input
              id="default_validity_days"
              name="default_validity_days"
              type="number"
              min="1"
              max="365"
              class="w-24"
              :default-value="settings.default_validity_days"
              required
            />
            <span class="text-sm text-foreground">days</span>
          </div>
          <p class="text-xs text-foreground">
            The starting point for every quote. A single quote can be given a longer window when you
            send it, without changing this.
          </p>
          <InputError :message="errors.default_validity_days" />
        </div>

        <div>
          <Button :disabled="processing">Save</Button>
        </div>
      </Form>
    </div>

    <div class="grid max-w-lg gap-4 rounded-xl border p-4">
      <div>
        <h2 class="font-medium">Logo</h2>
        <p class="text-sm text-foreground">
          Two addresses, because no one file works everywhere. The SVG goes on the quote page and
          the PDF; email clients mostly refuse to draw an SVG, so email uses the PNG. Either on its
          own is enough.
        </p>
      </div>

      <!--
                The stored copies, not the addresses they came from. Previewing
                the remote images would show what is out there rather than what
                this application actually holds. Side by side on purpose: two
                fields for one logo can drift apart, and seeing both is what
                makes that obvious.
            -->
      <div class="grid gap-3 sm:grid-cols-2">
        <div v-for="preview in previews" :key="preview.label" class="grid gap-1">
          <span class="text-xs text-muted-foreground">
            {{ preview.label }}
          </span>
          <div
            class="flex h-24 items-center justify-center rounded-lg border border-dashed bg-muted/30 p-3"
          >
            <img
              v-if="preview.url"
              :src="preview.url"
              :alt="preview.label"
              class="h-16 w-auto max-w-full object-contain"
            />
            <p v-else class="flex items-center gap-2 text-center text-xs text-foreground">
              <ImageOff class="size-4 shrink-0" />
              Not set
            </p>
          </div>
        </div>
      </div>

      <Form
        v-bind="AppSettingsController.storeLogo.form()"
        class="grid gap-4"
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <Label for="logo_vector_url">SVG address</Label>
          <Input
            id="logo_vector_url"
            name="logo_vector_url"
            type="url"
            inputmode="url"
            placeholder="https://xolution.nl/wp-content/uploads/logo.svg"
            :default-value="settings.logo_vector_url ?? ''"
          />
          <p class="text-xs text-foreground">
            Used on the quote page and in the PDF, where it stays sharp at any size.
          </p>
          <InputError :message="errors.logo_vector_url" />
        </div>

        <div class="grid gap-2">
          <Label for="logo_raster_url">PNG or JPG address</Label>
          <Input
            id="logo_raster_url"
            name="logo_raster_url"
            type="url"
            inputmode="url"
            placeholder="https://xolution.nl/wp-content/uploads/logo-600w.png"
            :default-value="settings.logo_raster_url ?? ''"
          />
          <p class="text-xs text-foreground">
            Used in quote emails, which cannot draw an SVG. At least 300 pixels wide, or it looks
            soft on a good screen.
          </p>
          <InputError :message="errors.logo_raster_url" />
        </div>

        <p class="text-xs text-foreground">
          Both are fetched and stored now, so printing or sending a quote never depends on those
          addresses still answering. Change a file at its address and save again to pick it up, or
          clear a field to remove that logo.
        </p>

        <div>
          <Button :disabled="processing">Save logos</Button>
        </div>
      </Form>
    </div>
  </div>
</template>
