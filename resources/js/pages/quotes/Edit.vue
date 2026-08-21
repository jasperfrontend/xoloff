<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import QuotePdfController from '@/actions/App/Http/Controllers/QuotePdfController';
import QuoteVersionController from '@/actions/App/Http/Controllers/QuoteVersionController';
import Heading from '@/components/Heading.vue';
import QuoteStatusBadge from '@/components/QuoteStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/dates';
import QuoteForm from '@/pages/quotes/Form.vue';
import SendQuoteDialog from '@/pages/quotes/SendQuoteDialog.vue';
import { index, update } from '@/routes/quotes';
import { store as storeVersion } from '@/routes/quotes/versions';
import type {
    CalculatedQuote,
    CustomerOption,
    ProductOption,
    QuoteContent,
    QuoteStatus,
    TaxClassOption,
} from '@/types';

interface Quote extends QuoteContent {
    id: number;
    customer_id: number | null;
    customer_email: string;
    status: QuoteStatus;
    status_label: string;
    is_editable: boolean;
    deny_reason: string | null;
    sent_at: string | null;
    valid_until: string | null;
    validity_days: number;
    follows_the_default: boolean;
    magic_link: string | null;
    version_number: number;
    version_count: number;
}

const props = defineProps<{
    quote: Quote;
    totals: CalculatedQuote | null;
    customers: CustomerOption[];
    products: ProductOption[];
    taxClasses: TaxClassOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotes', href: index() }],
    },
});

// A refused download or a refused send redirects back rather than failing
// silently, so the reason has to land somewhere the person can see it.
const page = usePage();
const pdfError = computed(() => page.props.errors?.pdf);
const sendError = computed(() => page.props.errors?.send);
const quoteError = computed(() => page.props.errors?.quote);
</script>

<template>
    <Head :title="`Quote ${quote.id}`" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <Heading
                    variant="small"
                    :title="`Quote ${quote.id}`"
                    :description="
                        quote.version_count > 1
                            ? `Editing version ${quote.version_number} of ${quote.version_count}`
                            : 'Saving overwrites this version unless you save as a new one'
                    "
                />

                <QuoteStatusBadge
                    :status="quote.status"
                    :label="quote.status_label"
                />
            </div>

            <div class="flex items-center gap-2">
                <SendQuoteDialog
                    v-if="quote.is_editable"
                    :quote-id="quote.id"
                    :customer-email="quote.customer_email"
                    :validity-days="quote.validity_days"
                    :follows-the-default="quote.follows_the_default"
                    :already-sent="quote.magic_link !== null"
                />

                <Button variant="secondary" as-child>
                    <!-- A plain link rather than an Inertia visit: the response
                         is a file, not a page. -->
                    <a :href="QuotePdfController.current(props.quote.id).url">
                        Download PDF
                    </a>
                </Button>

                <Button
                    v-if="quote.version_count > 1"
                    variant="secondary"
                    as-child
                >
                    <Link
                        :href="QuoteVersionController.index(props.quote.id).url"
                    >
                        Version history
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="pdfError || sendError || quoteError"
            class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ pdfError ?? sendError ?? quoteError }}
        </div>

        <!--
            Said plainly rather than left to be inferred from missing buttons.
            M6 hashes the document at signing as evidence of what the signer
            saw, and an edit afterwards would leave that hash describing
            something that no longer exists.
        -->
        <p
            v-if="!quote.is_editable"
            class="max-w-prose rounded-lg border bg-muted/30 px-4 py-3 text-sm text-foreground"
        >
            The customer has
            {{ quote.status === 'approved' ? 'approved' : 'declined' }} this
            quote, so it is kept exactly as they answered it and can no longer
            be changed or deleted. Raise a new quote to offer different terms.
        </p>

        <!--
            A refusal is the one status that comes with something to read, so
            it gets its own panel rather than a line in the sent block. Shown
            even when the customer declined without explaining, because "no
            reason given" is itself worth knowing.
        -->
        <div
            v-if="quote.status === 'denied'"
            class="grid gap-1 rounded-xl border border-destructive/50 bg-destructive/10 p-4 text-sm"
        >
            <span class="font-medium">The customer declined this quote</span>
            <p
                v-if="quote.deny_reason"
                class="whitespace-pre-line text-foreground"
            >
                {{ quote.deny_reason }}
            </p>
            <p v-else class="text-foreground">They gave no reason.</p>
        </div>

        <!--
            Shown once the quote has been sent. The link is here as well as in
            the customer's inbox, because an address that bounced or a message
            that never arrived is exactly when someone needs to pass it on by
            hand.
        -->
        <div
            v-if="quote.magic_link"
            class="grid gap-2 rounded-xl border p-4 text-sm"
        >
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span class="font-medium"
                    >Sent to {{ quote.customer_email }}</span
                >
                <span class="text-foreground">
                    {{ formatDateTime(quote.sent_at) }}
                </span>
                <span v-if="quote.valid_until" class="text-foreground">
                    Valid until {{ formatDate(quote.valid_until) }}
                </span>
            </div>

            <a
                :href="quote.magic_link"
                class="cursor-pointer font-mono text-xs break-all underline-offset-4 hover:underline"
            >
                {{ quote.magic_link }}
            </a>
        </div>

        <QuoteForm
            :customers="customers"
            :products="products"
            :tax-classes="taxClasses"
            :quote="props.quote"
            :initial-totals="totals"
            :submit-url="update(quote.id).url"
            submit-method="put"
            submit-label="Save changes"
            :new-version-url="
                quote.is_editable ? storeVersion(quote.id).url : undefined
            "
            :read-only="!quote.is_editable"
        />
    </div>
</template>
