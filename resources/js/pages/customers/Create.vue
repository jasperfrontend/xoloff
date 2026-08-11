<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import Heading from '@/components/Heading.vue';
import CustomerForm from '@/pages/customers/Form.vue';
import { create, index } from '@/routes/customers';

defineProps<{ countries: Record<string, string> }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Customers', href: index() },
            { title: 'New customer', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="New customer" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            title="New customer"
            description="Add a company you send quotes to"
        />

        <CustomerForm
            :action="CustomerController.store.form()"
            :countries="countries"
            submit-label="Create customer"
        />
    </div>
</template>
