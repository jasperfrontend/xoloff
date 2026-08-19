<script setup lang="ts">
import { Form, Link } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
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
import { formatPercentage, normalizeAmount } from '@/lib/money';
import { index } from '@/routes/products';

interface Spec {
    key: string;
    value: string;
}

interface Product {
    name: string;
    price_ex_vat: string;
    tax_class_id: number;
    category_id: number | null;
    specs?: Spec[];
}

interface TaxClass {
    id: number;
    name: string;
    percentage: string;
}

interface Category {
    id: number;
    name: string;
}

const props = defineProps<{
    action: Record<string, unknown>;
    taxClasses: TaxClass[];
    categories: Category[];
    product?: Product;
    submitLabel: string;
}>();

/**
 * Settles a half-typed "90,5" into "90.50", so what is shown, sent and saved
 * are the same number.
 */
const price = ref(props.product?.price_ex_vat ?? '');

function settlePrice() {
    price.value = normalizeAmount(price.value);
}

// Specs are a free-form key/value list, replaced wholesale on save.
const specs = ref<Spec[]>(
    props.product?.specs?.map((spec) => ({ ...spec })) ?? [],
);

function addSpec() {
    specs.value.push({ key: '', value: '' });
}

function removeSpec(indexToRemove: number) {
    specs.value.splice(indexToRemove, 1);
}
</script>

<template>
    <Form
        v-bind="action"
        class="max-w-2xl space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                name="name"
                :default-value="product?.name"
                required
                autofocus
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="price_ex_vat">Price excluding VAT</Label>
            <Input
                id="price_ex_vat"
                v-model="price"
                name="price_ex_vat"
                type="number"
                step="0.01"
                min="0"
                required
                @blur="settlePrice"
            />
            <InputError :message="errors.price_ex_vat" />
        </div>

        <div class="grid gap-2">
            <Label for="tax_class_id">Default tax class</Label>
            <Select
                name="tax_class_id"
                :default-value="product?.tax_class_id?.toString()"
            >
                <SelectTrigger id="tax_class_id">
                    <SelectValue placeholder="Select a tax class" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="taxClass in taxClasses"
                        :key="taxClass.id"
                        :value="taxClass.id.toString()"
                    >
                        {{ taxClass.name }} ({{
                            formatPercentage(taxClass.percentage)
                        }})
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-foreground">
                A default only - it can be overridden per line on a quote.
            </p>
            <InputError :message="errors.tax_class_id" />
        </div>

        <div class="grid gap-2">
            <Label for="category_id">Category</Label>
            <Select
                name="category_id"
                :default-value="product?.category_id?.toString()"
            >
                <SelectTrigger id="category_id">
                    <SelectValue placeholder="No category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id.toString()"
                    >
                        {{ category.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <InputError :message="errors.category_id" />
        </div>

        <div class="grid gap-3 rounded-xl border p-4">
            <div class="flex items-center justify-between">
                <div>
                    <Label>Specifications</Label>
                    <p class="text-xs text-foreground">
                        Billing period, startup cost, contract duration - any
                        key/value pair.
                    </p>
                </div>
                <Button
                    type="button"
                    variant="secondary"
                    size="sm"
                    @click="addSpec"
                >
                    <Plus class="size-4" />
                    Add
                </Button>
            </div>

            <p v-if="specs.length === 0" class="text-sm text-foreground">
                No specifications yet.
            </p>

            <div
                v-for="(spec, specIndex) in specs"
                :key="specIndex"
                class="flex items-start gap-2"
            >
                <div class="flex-1">
                    <Input
                        v-model="spec.key"
                        :name="`specs[${specIndex}][key]`"
                        placeholder="Billing period"
                        required
                    />
                    <InputError :message="errors[`specs.${specIndex}.key`]" />
                </div>
                <div class="flex-1">
                    <Input
                        v-model="spec.value"
                        :name="`specs[${specIndex}][value]`"
                        placeholder="Monthly"
                        required
                    />
                    <InputError :message="errors[`specs.${specIndex}.value`]" />
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    aria-label="Remove specification"
                    @click="removeSpec(specIndex)"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <Button :disabled="processing">{{ submitLabel }}</Button>
            <Button variant="ghost" as-child>
                <Link :href="index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
