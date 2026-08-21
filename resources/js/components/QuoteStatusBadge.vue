<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { QuoteStatus } from '@/types';

const props = defineProps<{
    status: QuoteStatus;
    /**
     * The wording comes from the server, so that the label a quote shows in
     * the list and on its own screen can never disagree.
     */
    label: string;
}>();

/**
 * Only a denied quote is coloured as a problem. A draft is the ordinary
 * resting state and reads as chrome; sent and opened are progress, not
 * achievement; approval is the one thing worth being loud about.
 */
const variant = computed(() => {
    const variants: Record<
        QuoteStatus,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        draft: 'outline',
        sent: 'secondary',
        opened: 'secondary',
        approved: 'default',
        denied: 'destructive',
    };

    return variants[props.status];
});
</script>

<template>
    <Badge :variant="variant">{{ label }}</Badge>
</template>
