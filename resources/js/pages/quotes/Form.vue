<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
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
import QuoteTotals from '@/pages/quotes/Totals.vue';
import { index, preview } from '@/routes/quotes';
import type {
    CalculatedQuote,
    CustomerOption,
    DiscountType,
    ProductOption,
    QuoteContent,
    QuoteLineItem,
    TaxClassOption,
} from '@/types';

interface QuoteFormData extends QuoteContent {
    customer_id: number | null;
}

/**
 * Optional money values are held as empty strings rather than null, because
 * that is what an empty number input produces. They are converted back to null
 * in toPayload before anything is sent.
 */
interface LineItemState extends Omit<QuoteLineItem, 'discount_value'> {
    discount_value: string;
}

interface FormState {
    customer_id: number | null;
    discount_type: DiscountType | null;
    discount_value: string;
    rounding_override: string;
    line_items: LineItemState[];
}

const props = defineProps<{
    customers: CustomerOption[];
    products: ProductOption[];
    taxClasses: TaxClassOption[];
    quote?: QuoteFormData;
    initialTotals?: CalculatedQuote | null;
    submitLabel: string;
    submitUrl: string;
    submitMethod: 'post' | 'put';
    newVersionUrl?: string;
}>();

/**
 * The rest of the app builds forms with the <Form> component, but the builder
 * needs its data in script: the totals panel posts it to the server on every
 * edit, and "Save as new version" sends the same payload to a second URL.
 */
const form = useForm<FormState>({
    customer_id: props.quote?.customer_id ?? null,
    discount_type: props.quote?.discount_type ?? null,
    discount_value: props.quote?.discount_value ?? '',
    rounding_override: props.quote?.rounding_override ?? '',
    line_items:
        props.quote?.line_items?.map((lineItem) => ({
            ...lineItem,
            discount_value: lineItem.discount_value ?? '',
        })) ?? [],
});

function nullIfBlank(value: string): string | null {
    return value === '' ? null : value;
}

/**
 * An empty input means "not set", which the server expects as null rather than
 * an empty string. Doing it here keeps the save and the live preview sending
 * exactly the same shape.
 */
function toPayload(): QuoteContent {
    return {
        discount_type: form.discount_type,
        discount_value: nullIfBlank(form.discount_value),
        rounding_override: nullIfBlank(form.rounding_override),
        line_items: form.line_items.map((lineItem) => ({
            ...lineItem,
            discount_value: nullIfBlank(lineItem.discount_value),
        })),
    };
}

/**
 * Totals come from the server so the browser never holds a second opinion
 * about the money. SPEC §5 is implemented once, in PHP.
 */
const totals = ref<CalculatedQuote | null>(props.initialTotals ?? null);

const previewRequest = useHttp<QuoteContent, CalculatedQuote>(toPayload());

let previewTimer: ReturnType<typeof setTimeout> | undefined;

function refreshTotals() {
    clearTimeout(previewTimer);

    previewTimer = setTimeout(async () => {
        try {
            // transform runs at submit time. The data passed to useHttp is only
            // an initial snapshot, so without this every request would send the
            // empty payload the builder started with.
            const calculated = await previewRequest
                .transform(() => toPayload())
                .post(preview().url);

            // A failed validation resolves with no response rather than
            // throwing, so this has to be checked instead of assigned blindly.
            // Choosing a discount type before typing its value is a normal
            // halfway state, and it should not blank the panel.
            if (calculated) {
                totals.value = calculated;
            }
        } catch {
            // Same reasoning for a request that does throw: the last good
            // totals stay on screen rather than flashing at someone who is
            // still typing.
        }
    }, 300);
}

watch(
    () => [
        form.discount_type,
        form.discount_value,
        form.rounding_override,
        form.line_items,
    ],
    refreshTotals,
    { deep: true },
);

onBeforeUnmount(() => clearTimeout(previewTimer));

function addLineItem() {
    form.line_items.push({
        product_id: null,
        name: '',
        specs: null,
        quantity: '1',
        unit_price_ex_vat: '0.00',
        tax_class_id: props.taxClasses[0]?.id ?? null,
        discount_type: null,
        discount_value: '',
    });
}

/**
 * Catalog values seed the line and nothing more. Everything stays editable
 * afterwards, and the line keeps working if the product is later deleted
 * (SPEC §3).
 */
function applyProduct(lineItem: LineItemState, productId: string) {
    const product = props.products.find(
        (candidate) => candidate.id === Number(productId),
    );

    if (!product) {
        return;
    }

    lineItem.product_id = product.id;
    lineItem.name = product.name;
    lineItem.unit_price_ex_vat = product.price_ex_vat;
    lineItem.tax_class_id = product.tax_class_id;
    lineItem.specs = product.specs.length
        ? Object.fromEntries(
              product.specs.map((spec) => [spec.key, spec.value]),
          )
        : null;
}

function removeLineItem(indexToRemove: number) {
    form.line_items.splice(indexToRemove, 1);
}

function clearDiscount(target: {
    discount_type: DiscountType | null;
    discount_value: string;
}) {
    if (target.discount_type === null) {
        target.discount_value = '';
    }
}

function submit() {
    form.transform(() => ({ customer_id: form.customer_id, ...toPayload() }))[
        props.submitMethod
    ](props.submitUrl, { preserveScroll: true });
}

function saveAsNewVersion() {
    if (props.newVersionUrl) {
        form.transform(() => ({
            customer_id: form.customer_id,
            ...toPayload(),
        })).post(props.newVersionUrl, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid max-w-md gap-2">
                <Label for="customer_id">Customer</Label>
                <Select
                    :model-value="form.customer_id?.toString()"
                    @update:model-value="form.customer_id = Number($event)"
                >
                    <SelectTrigger id="customer_id" class="cursor-pointer">
                        <SelectValue placeholder="Select a customer" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id.toString()"
                            class="cursor-pointer"
                        >
                            {{ customer.company_name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.customer_id" />
            </div>

            <div class="grid gap-3 rounded-xl border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <Label>Line items</Label>
                        <p class="text-xs text-foreground">
                            Pick a product to fill the line, then edit anything
                            you like. Catalog values are only defaults.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        class="cursor-pointer"
                        @click="addLineItem"
                    >
                        <Plus class="size-4" />
                        Add line
                    </Button>
                </div>

                <p
                    v-if="form.line_items.length === 0"
                    class="text-sm text-foreground"
                >
                    No lines yet. An empty quote is a perfectly good draft.
                </p>

                <div
                    v-for="(lineItem, lineIndex) in form.line_items"
                    :key="lineIndex"
                    class="grid gap-3 rounded-lg border p-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="grid flex-1 gap-2">
                            <Label :for="`line-${lineIndex}-product`">
                                Insert from catalog
                            </Label>
                            <Select
                                :model-value="lineItem.product_id?.toString()"
                                @update:model-value="
                                    applyProduct(lineItem, String($event))
                                "
                            >
                                <SelectTrigger
                                    :id="`line-${lineIndex}-product`"
                                    class="cursor-pointer"
                                >
                                    <SelectValue
                                        placeholder="Start from a blank line"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="product in products"
                                        :key="product.id"
                                        :value="product.id.toString()"
                                        class="cursor-pointer"
                                    >
                                        {{ product.name }} (€
                                        {{ product.price_ex_vat }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="mt-6 cursor-pointer"
                            aria-label="Remove line"
                            @click="removeLineItem(lineIndex)"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`line-${lineIndex}-name`"
                            >Description</Label
                        >
                        <Input
                            :id="`line-${lineIndex}-name`"
                            v-model="lineItem.name"
                            required
                        />
                        <InputError
                            :message="
                                form.errors[`line_items.${lineIndex}.name`]
                            "
                        />
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label :for="`line-${lineIndex}-quantity`"
                                >Quantity</Label
                            >
                            <Input
                                :id="`line-${lineIndex}-quantity`"
                                v-model="lineItem.quantity"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                            />
                            <InputError
                                :message="
                                    form.errors[
                                        `line_items.${lineIndex}.quantity`
                                    ]
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line-${lineIndex}-price`"
                                >Unit price ex. VAT</Label
                            >
                            <Input
                                :id="`line-${lineIndex}-price`"
                                v-model="lineItem.unit_price_ex_vat"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                            />
                            <InputError
                                :message="
                                    form.errors[
                                        `line_items.${lineIndex}.unit_price_ex_vat`
                                    ]
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label :for="`line-${lineIndex}-tax`"
                                >Tax class</Label
                            >
                            <Select
                                :model-value="lineItem.tax_class_id?.toString()"
                                @update:model-value="
                                    lineItem.tax_class_id = Number($event)
                                "
                            >
                                <SelectTrigger
                                    :id="`line-${lineIndex}-tax`"
                                    class="cursor-pointer"
                                >
                                    <SelectValue placeholder="Select" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="taxClass in taxClasses"
                                        :key="taxClass.id"
                                        :value="taxClass.id.toString()"
                                        class="cursor-pointer"
                                    >
                                        {{ taxClass.name }} ({{
                                            taxClass.percentage
                                        }}%)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="
                                    form.errors[
                                        `line_items.${lineIndex}.tax_class_id`
                                    ]
                                "
                            />
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`line-${lineIndex}-discount-type`"
                                >Line discount</Label
                            >
                            <Select
                                :model-value="lineItem.discount_type ?? 'none'"
                                @update:model-value="
                                    lineItem.discount_type =
                                        $event === 'none'
                                            ? null
                                            : ($event as
                                                  'percentage' | 'fixed');
                                    clearDiscount(lineItem);
                                "
                            >
                                <SelectTrigger
                                    :id="`line-${lineIndex}-discount-type`"
                                    class="cursor-pointer"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        value="none"
                                        class="cursor-pointer"
                                    >
                                        No discount
                                    </SelectItem>
                                    <SelectItem
                                        value="percentage"
                                        class="cursor-pointer"
                                    >
                                        Percentage
                                    </SelectItem>
                                    <SelectItem
                                        value="fixed"
                                        class="cursor-pointer"
                                    >
                                        Fixed amount
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div v-if="lineItem.discount_type" class="grid gap-2">
                            <Label :for="`line-${lineIndex}-discount-value`">
                                {{
                                    lineItem.discount_type === 'percentage'
                                        ? 'Percentage'
                                        : 'Amount'
                                }}
                            </Label>
                            <Input
                                :id="`line-${lineIndex}-discount-value`"
                                v-model="lineItem.discount_value"
                                type="number"
                                step="0.01"
                                min="0"
                                :max="
                                    lineItem.discount_type === 'percentage'
                                        ? 100
                                        : undefined
                                "
                            />
                            <InputError
                                :message="
                                    form.errors[
                                        `line_items.${lineIndex}.discount_value`
                                    ]
                                "
                            />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="discount_type">Quote discount</Label>
                    <Select
                        :model-value="form.discount_type ?? 'none'"
                        @update:model-value="
                            form.discount_type =
                                $event === 'none'
                                    ? null
                                    : ($event as 'percentage' | 'fixed');
                            clearDiscount(form);
                        "
                    >
                        <SelectTrigger
                            id="discount_type"
                            class="cursor-pointer"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none" class="cursor-pointer">
                                No discount
                            </SelectItem>
                            <SelectItem
                                value="percentage"
                                class="cursor-pointer"
                            >
                                Percentage
                            </SelectItem>
                            <SelectItem value="fixed" class="cursor-pointer">
                                Fixed amount
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-foreground">
                        Applied before VAT and split across the lines in
                        proportion to their value.
                    </p>
                </div>

                <div v-if="form.discount_type" class="grid gap-2">
                    <Label for="discount_value">
                        {{
                            form.discount_type === 'percentage'
                                ? 'Percentage'
                                : 'Amount'
                        }}
                    </Label>
                    <Input
                        id="discount_value"
                        v-model="form.discount_value"
                        type="number"
                        step="0.01"
                        min="0"
                        :max="
                            form.discount_type === 'percentage'
                                ? 100
                                : undefined
                        "
                    />
                    <InputError :message="form.errors.discount_value" />
                </div>

                <div class="grid gap-2">
                    <Label for="rounding_override">Rounding override</Label>
                    <Input
                        id="rounding_override"
                        v-model="form.rounding_override"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Leave empty to use the calculated total"
                    />
                    <p class="text-xs text-foreground">
                        Replaces the total outright. There is no reconciliation
                        line and the calculated total is not shown to the
                        customer.
                    </p>
                    <InputError :message="form.errors.rounding_override" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    class="cursor-pointer"
                    :disabled="form.processing"
                >
                    {{ submitLabel }}
                </Button>

                <Button
                    v-if="newVersionUrl"
                    type="button"
                    variant="secondary"
                    class="cursor-pointer"
                    :disabled="form.processing"
                    @click="saveAsNewVersion"
                >
                    Save as new version
                </Button>

                <Button variant="ghost" as-child class="cursor-pointer">
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>

        <QuoteTotals
            :totals="totals"
            :calculating="previewRequest.processing"
        />
    </div>
</template>
