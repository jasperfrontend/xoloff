<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ProductCategoryController from '@/actions/App/Http/Controllers/ProductCategoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/product-categories';

interface Category {
    id: number;
    name: string;
}

defineProps<{ category: Category }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categories', href: index() }],
    },
});
</script>

<template>
    <Head :title="category.name" />

    <div class="flex flex-col space-y-6 p-4">
        <Heading
            variant="small"
            :title="category.name"
            description="Rename this category"
        />

        <Form
            v-bind="ProductCategoryController.update.form(category.id)"
            class="max-w-lg space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    name="name"
                    :default-value="category.name"
                    required
                    autofocus
                />
                <InputError :message="errors.name" />
            </div>

            <div class="flex items-center gap-4">
                <Button :disabled="processing">Save changes</Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
