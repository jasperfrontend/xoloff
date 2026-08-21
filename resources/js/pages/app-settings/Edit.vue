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
        logo_url: string | null;
        logo_preview_url: string | null;
        company_name: string | null;
        company_address: string | null;
        company_kvk: string | null;
        company_vat_number: string | null;
        default_validity_days: number;
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
            <div>
                <h2 class="font-medium">Quotes</h2>
                <p class="text-sm text-foreground">
                    How long a quote stays valid after it is sent.
                </p>
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
                        The starting point for every quote. A single quote can
                        be given a longer window when you send it, without
                        changing this.
                    </p>
                    <InputError :message="errors.default_validity_days" />
                </div>

                <div>
                    <Button :disabled="processing">Save</Button>
                </div>
            </Form>
        </div>

        <div class="grid max-w-lg gap-4 rounded-xl border p-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="font-medium">Logo</h2>
                    <p class="text-sm text-foreground">
                        Printed at the top of every quote PDF, and shown to the
                        customer on their quote page.
                    </p>
                </div>

                <ConfirmDeleteButton
                    v-if="settings.logo_preview_url"
                    :action="AppSettingsController.destroyLogo.form()"
                    title="Remove the logo?"
                    description="Quotes will print without one until another address is saved. Quotes already downloaded keep the logo they were printed with."
                    label="Remove logo"
                />
            </div>

            <!--
                The stored copy, not the address it came from. Showing the
                remote image would prove that something is out there rather
                than that this application actually holds it, which is the one
                thing worth seeing here.
            -->
            <div
                class="flex h-32 items-center justify-center rounded-lg border border-dashed bg-muted/30 p-4"
            >
                <img
                    v-if="settings.logo_preview_url"
                    :src="settings.logo_preview_url"
                    alt="The logo printed on quotes"
                    class="max-h-24 max-w-full object-contain"
                />
                <p
                    v-else
                    class="flex items-center gap-2 text-sm text-foreground"
                >
                    <ImageOff class="size-4" />
                    No logo saved yet
                </p>
            </div>

            <Form
                v-bind="AppSettingsController.storeLogo.form()"
                class="grid gap-3"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="logo_url">Logo address</Label>
                    <Input
                        id="logo_url"
                        name="logo_url"
                        type="url"
                        inputmode="url"
                        placeholder="https://xolution.nl/wp-content/uploads/logo.svg"
                        :default-value="settings.logo_url ?? ''"
                        required
                    />
                    <p class="text-xs text-foreground">
                        Fetched and stored once, now, so printing a quote never
                        depends on that address still answering. PNG, JPG, WebP
                        or SVG. Change the file at that address and press save
                        again to pick it up.
                    </p>
                    <InputError :message="errors.logo_url" />
                </div>

                <div>
                    <Button :disabled="processing">
                        {{
                            settings.logo_preview_url
                                ? 'Fetch again'
                                : 'Save logo'
                        }}
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
