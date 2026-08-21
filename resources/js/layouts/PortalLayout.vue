<script setup lang="ts">
/**
 * The one layout a customer sees (SPEC §7). No sidebar, no account menu and no
 * navigation: there is exactly one page behind a magic link and nowhere else
 * to go from it.
 */
</script>

<template>
    <!--
        `portal` pins the light palette regardless of the reader's system
        theme - see the comment on that class in app.css. A quote is a
        document, and every logo uploaded for it is drawn for a white page.

        The backdrop is opaque on purpose. At any opacity below 1 the dark
        page behind it shows through, and the document ends up sitting on a
        near-black field.
    -->
    <div
        class="portal flex min-h-svh flex-col items-center bg-muted px-4 py-12 text-foreground"
    >
        <!--
            Wide enough for the line-item table to breathe. The quote is the
            page, not a card sitting on it.
        -->
        <main class="w-full max-w-3xl">
            <slot />
        </main>
    </div>
</template>
