<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategoryController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import ResourceHeader from '@/components/ResourceHeader.vue';
import { index } from '@/routes/product-categories';

interface Category {
    id: number;
    name: string;
    products_count: number;
}

defineProps<{ categories: Category[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-col space-y-6 p-4">
        <ResourceHeader
            title="Categories"
            description="A flat set of tags for grouping products"
            :create-href="ProductCategoryController.create().url"
            create-label="New category"
        />

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Products</th>
                        <th class="w-px px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="categories.length === 0">
                        <td
                            colspan="3"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No categories yet.
                        </td>
                    </tr>
                    <tr
                        v-for="category in categories"
                        :key="category.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    ProductCategoryController.edit(category.id)
                                        .url
                                "
                                class="font-medium underline-offset-4 hover:underline"
                            >
                                {{ category.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ category.products_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <ConfirmDeleteButton
                                :action="
                                    ProductCategoryController.destroy.form(
                                        category.id,
                                    )
                                "
                                title="Delete category?"
                                description="Products in this category are kept — they simply become uncategorised."
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
