<script setup lang="ts">
/**
 * Who the customer is looking at. Only what has been filled in on the settings
 * screen - the details are still being collected (SPEC §12), and a blank space
 * beats a placeholder on a page a customer reads.
 */
defineProps<{
    sender: { company_name: string | null; logo_url: string | null };
}>();
</script>

<template>
    <div>
        <img
            v-if="sender.logo_url"
            :src="sender.logo_url"
            :alt="sender.company_name ?? ''"
            class="max-h-12 max-w-48 object-contain"
        />
        <p v-else-if="sender.company_name" class="font-medium">
            {{ sender.company_name }}
        </p>
    </div>
</template>
