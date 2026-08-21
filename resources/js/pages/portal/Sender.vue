<script setup lang="ts">
/**
 * Who the customer is looking at. Only what has been filled in on the settings
 * screen - the details are still being collected (SPEC §12), and a blank space
 * beats a placeholder on a page a customer reads.
 *
 * The logo is given a definite height and lets its width follow. An SVG can
 * declare percentage dimensions, which inside an img resolve against nothing
 * and leave it with no intrinsic size, so max- constraints alone collapse it
 * to 0x0 and the logo silently disappears. The aspect ratio from its viewBox
 * is enough once one axis is definite.
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
      class="h-12 w-auto max-w-48 object-contain"
    />
    <p v-else-if="sender.company_name" class="font-medium">
      {{ sender.company_name }}
    </p>
  </div>
</template>
