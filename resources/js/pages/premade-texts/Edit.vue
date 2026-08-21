<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import PremadeTextController from '@/actions/App/Http/Controllers/PremadeTextController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PlaceholderPicker from '@/components/PlaceholderPicker.vue';
import RichTextEditor from '@/components/RichTextEditor.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/premade-texts';
import type { PlaceholderOption } from '@/types';

const props = defineProps<{
    texts: {
        intro: string;
        footer: string;
    };
    placeholders: PlaceholderOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quote texts', href: edit() }],
    },
});

// The editor is not an input, so the value it holds is mirrored into a hidden
// field for the form to submit.
const intro = ref(props.texts.intro);
const footer = ref(props.texts.footer);

// Reached into rather than driven by a prop, because inserting at the caret is
// something only the editor knows how to do: the position is inside its own
// document, not in the string this page holds.
const introEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
const footerEditor = ref<InstanceType<typeof RichTextEditor> | null>(null);
</script>

<template>
    <Head title="Quote texts" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            title="Quote texts"
            description="The intro and footer carried by every quote"
        />

        <Form
            v-bind="PremadeTextController.update.form()"
            class="max-w-3xl space-y-8"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="intro">Intro</Label>
                <RichTextEditor
                    ref="introEditor"
                    v-model="intro"
                    label="Intro text"
                    described-by="intro-help"
                />
                <PlaceholderPicker
                    :placeholders="placeholders"
                    describes="the intro text"
                    @insert="introEditor?.insert($event)"
                />
                <input type="hidden" name="intro" :value="intro" />
                <p id="intro-help" class="text-xs text-foreground">
                    Shown above the line items. Leave it empty if a quote should
                    open straight into the pricing.
                </p>
                <InputError :message="errors.intro" />
            </div>

            <div class="grid gap-2">
                <Label for="footer">Footer</Label>
                <RichTextEditor
                    ref="footerEditor"
                    v-model="footer"
                    label="Footer text"
                    described-by="footer-help"
                />
                <PlaceholderPicker
                    :placeholders="placeholders"
                    describes="the footer text"
                    @insert="footerEditor?.insert($event)"
                />
                <input type="hidden" name="footer" :value="footer" />
                <p id="footer-help" class="text-xs text-foreground">
                    This is where the reference to the algemene voorwaarden
                    lives, so it cannot be left empty.
                </p>
                <InputError :message="errors.footer" />
            </div>

            <p class="max-w-prose text-sm text-foreground">
                Quotes keep a copy of both texts from the moment they were
                saved, so editing here never changes a quote that has already
                gone out. Save a quote as a new version to give it the current
                wording. Placeholders are filled in at that same moment, with
                the customer the quote is going to.
            </p>

            <Button :disabled="processing">Save texts</Button>
        </Form>
    </div>
</template>
