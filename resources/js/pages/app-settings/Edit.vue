<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import AppSettingsController from '@/actions/App/Http/Controllers/AppSettingsController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/app-settings';

defineProps<{
    settings: {
        logo_path: string | null;
        logo_url: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Settings', href: edit() }],
    },
});
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
                v-bind="AppSettingsController.update.form()"
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
