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
    contact_person: string;
    email: string;
    billing_address: string;
    country: string;
}

const props = defineProps<{
    action: Record<string, unknown>;
    countries: Record<string, string>;
    customer?: Customer;
    submitLabel: string;
}>();

const country = props.customer?.country ?? 'NL';
const billingAddress = ref(props.customer?.billing_address ?? '');
</script>

<template>
    <Form
        v-bind="action"
        class="max-w-2xl space-y-6"
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

        <div class="grid gap-2">
            <Label for="contact_person">Contact person</Label>
            <Input
                id="contact_person"
                name="contact_person"
                :default-value="customer?.contact_person"
                required
            />
            <InputError :message="errors.contact_person" />
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
