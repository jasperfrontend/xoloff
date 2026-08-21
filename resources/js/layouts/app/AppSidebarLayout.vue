<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
  breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
  breadcrumbs: () => [],
});
</script>

<template>
  <AppShell variant="sidebar">
    <AppSidebar />
    <!--
            Clip rather than hidden. Both keep a wide table from
            scrolling the page sideways, but hidden makes this element a
            scroll container, and position: sticky inside a scroll container
            that never scrolls simply does not stick.
        -->
    <AppContent variant="sidebar" class="overflow-x-clip">
      <AppSidebarHeader :breadcrumbs="breadcrumbs" />
      <slot />
    </AppContent>
    <Toaster />
  </AppShell>
</template>
