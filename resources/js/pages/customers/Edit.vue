<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import Heading from '@/components/Heading.vue';
import CustomerForm from '@/pages/customers/Form.vue';
import { index } from '@/routes/customers';

interface Customer {
    id: number;
    company_name: string;
    salutation: string | null;
    first_name: string;
    last_name: string;
    email: string;
    billing_address: string;
    country: string;
}

defineProps<{
    customer: Customer;
    countries: Record<string, string>;
    salutations: Record<string, string>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: index() }],
    },
});
</script>

<template>
    <Head :title="customer.company_name" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            :title="customer.company_name"
            description="Update this customer's details"
        />

        <CustomerForm
            :action="CustomerController.update.form(customer.id)"
            :countries="countries"
            :salutations="salutations"
            :customer="customer"
            submit-label="Save changes"
        />
    </div>
</template>
