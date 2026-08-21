<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { ref } from 'vue';
import AppSettingsController from '@/actions/App/Http/Controllers/AppSettingsController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/app-settings';

const props = defineProps<{
    settings: {
        logo_path: string | null;
        logo_url: string | null;
        company_name: string | null;
        company_address: string | null;
        company_kvk: string | null;
        company_vat_number: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: edit() }],
    },
});

const companyAddress = ref(props.settings.company_address ?? '');
</script>

<template>
    <Head title="Settings" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            title="Settings"
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
                    <p class="text-xs text-foreground">
                        Printed with the line breaks you type here.
                    </p>
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
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium">Logo</h2>
                    <p class="text-sm text-foreground">
                        Printed at the top of every quote PDF.
                    </p>
                </div>

                <ConfirmDeleteButton
                    v-if="settings.logo_url"
                    :action="AppSettingsController.destroyLogo.form()"
                    title="Remove the logo?"
                    description="Quotes will print without one until a new logo is uploaded. Quotes already downloaded keep the logo they were printed with."
                    label="Remove logo"
                />
            </div>

            <div
                class="flex h-32 items-center justify-center rounded-lg border border-dashed bg-muted/30 p-4"
            >
                <img
                    v-if="settings.logo_url"
                    :src="settings.logo_url"
                    alt="The logo printed on quotes"
                    class="max-h-24 max-w-full object-contain"
                />
                <p
                    v-else
                    class="flex items-center gap-2 text-sm text-foreground"
                >
                    <ImageOff class="size-4" />
                    No logo uploaded yet
                </p>
            </div>

            <Form
                v-bind="AppSettingsController.storeLogo.form()"
                class="grid gap-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="logo">
                        {{ settings.logo_url ? 'Replace logo' : 'Upload logo' }}
                    </Label>
                    <Input
                        id="logo"
                        name="logo"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="cursor-pointer"
                        required
                    />
                    <p class="text-xs text-foreground">
                        PNG, JPG or WebP, up to 2 MB. A wide, transparent PNG
                        prints best.
                    </p>
                    <InputError :message="errors.logo" />
                </div>

                <div>
                    <Button :disabled="processing">
                        {{ settings.logo_url ? 'Replace logo' : 'Upload logo' }}
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
