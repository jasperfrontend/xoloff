<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
    description: string;
    createHref?: string;
    createLabel?: string;
}>();
</script>

<template>
    <div class="flex items-start justify-between gap-4">
        <Heading variant="small" :title="title" :description="description" />

        <Button v-if="createHref" as-child size="sm">
            <Link :href="createHref">
                <Plus class="size-4" />
                {{ createLabel ?? 'New' }}
            </Link>
        </Button>
    </div>
</template>
