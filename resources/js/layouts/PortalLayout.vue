<script setup lang="ts">
/**
 * The one layout a customer sees (SPEC §7). No sidebar, no account menu and no
 * navigation: there is exactly one page behind a magic link and nowhere else
 * to go from it.
 */
</script>

<template>
    <div class="flex min-h-svh flex-col items-center bg-muted/30 px-4 py-12">
        <main class="w-full max-w-xl">
            <slot />
        </main>
    </div>
</template>
