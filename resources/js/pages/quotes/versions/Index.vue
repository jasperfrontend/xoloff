<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import QuoteController from '@/actions/App/Http/Controllers/QuoteController';
import QuoteVersionController from '@/actions/App/Http/Controllers/QuoteVersionController';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { index } from '@/routes/quotes';

interface Version {
    id: number;
    version_number: number;
    is_current: boolean;
    saved_at: string | null;
    line_count: number;
    total: string;
}

const props = defineProps<{
    quote: { id: number; customer_name: string };
    versions: Version[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Quotes', href: index() }],
    },
});

const page = usePage();
const deleteError = computed(() => page.props.errors?.version);
</script>

<template>
    <Head :title="`Quote ${quote.id} versions`" />

    <div class="flex flex-col space-y-6 p-4">
        <div class="flex items-start justify-between gap-4">
            <Heading
                variant="small"
                :title="`Quote ${quote.id} versions`"
                :description="`Every saved version for ${quote.customer_name}`"
            />

            <Button variant="secondary" as-child>
                <Link :href="QuoteController.edit(props.quote.id).url">
                    Back to quote
                </Link>
            </Button>
        </div>

        <div
            v-if="deleteError"
            class="rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive"
        >
            {{ deleteError }}
        </div>

        <div class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Version</th>
                        <th class="px-4 py-3 font-medium">Saved</th>
                        <th class="px-4 py-3 font-medium">Lines</th>
                        <th class="px-4 py-3 font-medium">Total</th>
                        <th class="w-px px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="versions.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-foreground"
                        >
                            This quote has no saved versions yet.
                        </td>
                    </tr>
                    <tr
                        v-for="version in versions"
                        :key="version.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    QuoteVersionController.show({
                                        quote: props.quote.id,
                                        version: version.id,
                                    }).url
                                "
                                class="cursor-pointer font-medium underline-offset-4 hover:underline"
                            >
                                V{{ version.version_number }}
                            </Link>
                            <span
                                v-if="version.is_current"
                                class="ml-2 rounded-full bg-muted px-2 py-0.5 text-xs text-foreground"
                            >
                                Current
                            </span>
                        </td>
                        <td class="px-4 py-3 text-foreground tabular-nums">
                            {{ formatDateTime(version.saved_at) }}
                        </td>
                        <td class="px-4 py-3 text-foreground tabular-nums">
                            {{ version.line_count }}
                        </td>
                        <td class="px-4 py-3 tabular-nums">
                            {{ formatMoney(version.total) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <!--
                                The current version is the quote's content, so
                                removing it would silently promote an older one
                                and read as the quote changing by itself.
                            -->
                            <ConfirmDeleteButton
                                v-if="!version.is_current"
                                :action="
                                    QuoteVersionController.destroy.form({
                                        quote: props.quote.id,
                                        version: version.id,
                                    })
                                "
                                title="Delete this version?"
                                :description="`Version ${version.version_number} and its lines will be permanently removed. The current version is not affected.`"
                                :label="`Delete version ${version.version_number}`"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
