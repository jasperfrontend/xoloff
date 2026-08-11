<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CustomerController from '@/actions/App/Http/Controllers/CustomerController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import ResourceHeader from '@/components/ResourceHeader.vue';
import { index } from '@/routes/customers';

interface Customer {
    id: number;
    company_name: string;
    contact_person: string;
    email: string;
    country: string;
}

defineProps<{ customers: Customer[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Customers', href: index() }],
    },
});
</script>

<template>
    <Head title="Customers" />

    <div class="flex flex-col space-y-6 p-4">
        <ResourceHeader
            title="Customers"
            description="The companies you send quotes to"
            :create-href="CustomerController.create().url"
            create-label="New customer"
        />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Company</th>
                        <th class="px-4 py-3 font-medium">Contact</th>
                        <th class="px-4 py-3 font-medium">Email</th>
                        <th class="px-4 py-3 font-medium">Country</th>
                        <th class="w-px px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="customers.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No customers yet.
                        </td>
                    </tr>
                    <tr
                        v-for="customer in customers"
                        :key="customer.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="CustomerController.edit(customer.id).url"
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                {{ customer.company_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3">{{ customer.contact_person }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ customer.email }}
                        </td>
                        <td class="px-4 py-3">{{ customer.country }}</td>
                        <td class="px-4 py-3 text-right">
                            <ConfirmDeleteButton
                                :action="
                                    CustomerController.destroy.form(customer.id)
                                "
                                title="Delete customer?"
                                :description="`${customer.company_name} will be permanently removed.`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
