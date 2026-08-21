<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { formatDate } from '@/lib/dates';
import PortalSender from '@/pages/portal/Sender.vue';

/**
 * Dutch, like the PDF and for the same reason: this is a page a customer
 * reads, not a screen either of the two people who use xoloff works in.
 *
 * Deliberately a cover page. Reading the quote itself and approving or denying
 * it is M5 (SPEC §8), which fills this in below rather than replacing it, so
 * nothing here promises content that is not on the page yet.
 */
defineProps<{
    sender: { company_name: string | null; logo_url: string | null };
    quote: {
        id: number;
        company_name: string;
        contact_person: string;
        valid_until: string | null;
    };
}>();
</script>

<template>
    <Head :title="`Offerte ${quote.id}`" />

    <div class="rounded-xl border bg-background p-8">
        <PortalSender :sender="sender" />

        <h1 class="mt-6 text-xl font-semibold">
            Offerte {{ quote.id }} voor {{ quote.company_name }}
        </h1>

        <p class="mt-2 text-foreground">Beste {{ quote.contact_person }},</p>

        <p v-if="quote.valid_until" class="mt-4 text-sm text-foreground">
            Deze offerte is geldig tot en met
            <strong>{{ formatDate(quote.valid_until) }}</strong
            >.
        </p>
    </div>
</template>
