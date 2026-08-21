<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { formatDate } from '@/lib/dates';
import PortalSender from '@/pages/portal/Sender.vue';

/**
 * What a magic link shows once its window has closed (SPEC §7). Never a "not
 * found": the link was real and the customer did nothing wrong, so this says
 * what happened and how to get a new one rather than reading as a mistake they
 * made.
 */
defineProps<{
    sender: { company_name: string | null; logo_url: string | null };
    quote: { id: number; valid_until: string | null };
}>();
</script>

<template>
    <Head :title="`Offerte ${quote.id}`" />

    <div class="rounded-xl border bg-background p-8">
        <PortalSender :sender="sender" />

        <h1 class="mt-6 text-xl font-semibold">
            Deze offerte is niet meer geldig
        </h1>

        <p v-if="quote.valid_until" class="mt-2 text-foreground">
            Offerte {{ quote.id }} was geldig tot en met
            {{ formatDate(quote.valid_until) }}.
        </p>

        <p class="mt-4 text-sm text-foreground">
            Neem gerust contact met ons op als u de offerte alsnog wilt
            bekijken. We sturen u dan een nieuwe.
        </p>
    </div>
</template>
