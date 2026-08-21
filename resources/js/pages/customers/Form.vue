<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/customers';

interface Customer {
    company_name: string;
    salutation: string | null;
    first_name: string;
    last_name: string;
    email: string;
    billing_address: string;
    country: string;
}

const props = defineProps<{
    action: Record<string, unknown>;
    countries: Record<string, string>;
    salutations: Record<string, string>;
    customer?: Customer;
    submitLabel: string;
}>();

/**
 * The select needs a value for "no salutation", because an empty string is not
 * something it can hold. It is translated back to nothing on submit, which is
 * what the nullable column and the nullable rule expect.
 */
const NO_SALUTATION = 'none';

const country = props.customer?.country ?? 'NL';
const billingAddress = ref(props.customer?.billing_address ?? '');
</script>

<template>
    <Form
        v-bind="action"
        class="max-w-2xl space-y-6"
        :transform="
            (data) => ({
                ...data,
                salutation:
                    data.salutation === NO_SALUTATION ? null : data.salutation,
            })
        "
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="company_name">Company name</Label>
            <Input
                id="company_name"
                name="company_name"
                :default-value="customer?.company_name"
                required
                autofocus
            />
            <InputError :message="errors.company_name" />
        </div>

        <!--
            Three fields rather than one, because a quote text greets a person
            by name and "Beste Daan Daansen" is not how anyone writes. The
            salutation sits first, in the order the words appear in a sentence.
        -->
        <div class="grid gap-4 sm:grid-cols-[10rem_1fr_1fr]">
            <div class="grid gap-2">
                <Label for="salutation">Salutation</Label>
                <Select
                    name="salutation"
                    :default-value="customer?.salutation ?? NO_SALUTATION"
                >
                    <SelectTrigger id="salutation" class="cursor-pointer">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            :value="NO_SALUTATION"
                            class="cursor-pointer"
                        >
                            None
                        </SelectItem>
                        <SelectItem
                            v-for="(label, value) in salutations"
                            :key="value"
                            :value="value"
                            class="cursor-pointer"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="errors.salutation" />
            </div>

            <div class="grid gap-2">
                <Label for="first_name">First name</Label>
                <Input
                    id="first_name"
                    name="first_name"
                    :default-value="customer?.first_name"
                    required
                />
                <InputError :message="errors.first_name" />
            </div>

            <div class="grid gap-2">
                <Label for="last_name">Last name</Label>
                <Input
                    id="last_name"
                    name="last_name"
                    :default-value="customer?.last_name"
                    required
                />
                <InputError :message="errors.last_name" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="email">Email address</Label>
            <Input
                id="email"
                name="email"
                type="email"
                :default-value="customer?.email"
                required
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="billing_address">Billing address</Label>
            <textarea
                id="billing_address"
                v-model="billingAddress"
                name="billing_address"
                rows="4"
                required
                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            ></textarea>
            <InputError :message="errors.billing_address" />
        </div>

        <div class="grid gap-2">
            <Label for="country">Country</Label>
            <Select name="country" :default-value="country">
                <SelectTrigger id="country">
                    <SelectValue placeholder="Select a country" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="(name, code) in countries"
                        :key="code"
                        :value="code"
                    >
                        {{ name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-foreground">
                Determines which VAT treatment is appropriate - you still pick
                the tax class by hand on each quote.
            </p>
            <InputError :message="errors.country" />
        </div>

        <div class="flex items-center gap-4">
            <Button :disabled="processing">{{ submitLabel }}</Button>
            <Button variant="ghost" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
