<script setup lang="ts">
import type { PlaceholderOption } from '@/types';

/**
 * The placeholders a quote text can carry, as things to click rather than
 * things to remember.
 *
 * Typing them by hand is the failure mode this exists to prevent: a misspelled
 * token resolves to nothing, and the first anyone would know is a quote
 * greeting a customer with an empty space where their name should be.
 */
defineProps<{
    placeholders: PlaceholderOption[];
    /** Names the editor these belong to, for screen readers. */
    describes: string;
}>();

const emit = defineEmits<{ insert: [token: string] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="text-xs text-muted-foreground">Insert:</span>

        <!--
            mousedown is swallowed so that clicking one of these never takes the
            selection out of the editor, which is the whole point: the token has
            to land where the caret already was. The toolbar inside the editor
            does the same thing for the same reason.
        -->
        <button
            v-for="placeholder in placeholders"
            :key="placeholder.token"
            type="button"
            :title="`${placeholder.token} becomes ${placeholder.example}`"
            :aria-label="`Insert ${placeholder.label} into ${describes}`"
            class="cursor-pointer rounded-full border px-2.5 py-1 text-xs hover:bg-accent hover:text-accent-foreground"
            @mousedown.prevent
            @click="emit('insert', placeholder.token)"
        >
            {{ placeholder.label }}
        </button>
    </div>
</template>
