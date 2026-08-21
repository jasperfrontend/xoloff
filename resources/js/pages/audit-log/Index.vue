<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { formatDateTime } from '@/lib/dates';
import { index } from '@/routes/audit-log';

interface Entry {
  id: number;
  action: string;
  action_label: string;
  entity_type: string;
  entity_id: number;
  label: string | null;
  quote_id: number | null;
  payload: Record<string, unknown>;
  user_name: string | null;
  created_at: string | null;
}

interface Filters {
  quote_id: number | null;
  user_id: number | null;
  action: string | null;
  from: string | null;
  to: string | null;
}

const props = defineProps<{
  entries: {
    data: Entry[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
  };
  filters: Filters;
  quotes: { id: number; label: string }[];
  users: { id: number; name: string }[];
  actions: { value: string; label: string }[];
}>();

defineOptions({
  layout: {
    breadcrumbs: [{ title: 'Audit log', href: index() }],
  },
});

// "any" is a real value rather than an empty string, because a select cannot
// hold an empty value and still show its placeholder.
const ANY = 'any';

const quoteId = ref(String(props.filters.quote_id ?? ANY));
const userId = ref(String(props.filters.user_id ?? ANY));
const action = ref(props.filters.action ?? ANY);
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

/**
 * Read from what the server actually filtered on, not from the inputs.
 *
 * Computed from the local refs it would appear the instant a select changed,
 * offering to clear filters that had not been applied yet - which was the
 * wrong way round, since applying happens on change now anyway.
 */
const hasFilters = computed(
  () =>
    props.filters.quote_id !== null ||
    props.filters.user_id !== null ||
    props.filters.action !== null ||
    props.filters.from !== null ||
    props.filters.to !== null,
);

function apply() {
  router.get(
    index().url,
    {
      ...(quoteId.value !== ANY ? { quote_id: quoteId.value } : {}),
      ...(userId.value !== ANY ? { user_id: userId.value } : {}),
      ...(action.value !== ANY ? { action: action.value } : {}),
      ...(from.value ? { from: from.value } : {}),
      ...(to.value ? { to: to.value } : {}),
    },
    { preserveState: true, preserveScroll: true },
  );
}

function clear() {
  quoteId.value = ANY;
  userId.value = ANY;
  action.value = ANY;
  from.value = '';
  to.value = '';
  apply();
}

/**
 * Applied as they change rather than behind a button. A filter that has been
 * chosen but not yet applied is a screen showing something other than what it
 * says it is showing.
 *
 * Dates fire this on every keystroke while a date input is being typed into,
 * so the visit is debounced. Selects settle immediately and are unaffected.
 */
let dateChange: number | undefined;

watch([quoteId, userId, action], () => apply());
watch([from, to], () => {
  clearTimeout(dateChange);
  dateChange = window.setTimeout(apply, 400);
});

const expanded = ref<number | null>(null);

function toggle(entryId: number) {
  expanded.value = expanded.value === entryId ? null : entryId;
}

/** The entity types as stored, read back as something a person would say. */
const entityNames: Record<string, string> = {
  app_settings: 'Settings',
  customer: 'Customer',
  premade_text: 'Quote text',
  product: 'Product',
  product_category: 'Category',
  quote: 'Quote',
  quote_version: 'Quote version',
  tax_class: 'Tax class',
};

function entityName(entry: Entry): string {
  return entityNames[entry.entity_type] ?? entry.entity_type;
}

/**
 * The paginator writes its arrows as html entities. Stripped rather than
 * rendered as markup, so the page has no reason to hand anything to v-html.
 */
function pageLabel(label: string): string {
  return label.replaceAll('&laquo;', '').replaceAll('&raquo;', '').trim();
}
</script>

<template>
  <Head title="Audit log" />

  <div class="flex flex-col space-y-6 p-4">
    <Heading
      variant="small"
      title="Audit log"
      description="Everything that has happened, newest first"
    />

    <div class="grid gap-4 rounded-xl border p-4">
      <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div class="grid gap-2">
          <Label for="quote_id">Quote</Label>
          <Select v-model="quoteId">
            <SelectTrigger id="quote_id" class="cursor-pointer">
              <SelectValue placeholder="Any quote" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ANY">Any quote</SelectItem>
              <SelectItem v-for="quote in quotes" :key="quote.id" :value="String(quote.id)">
                {{ quote.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="grid gap-2">
          <Label for="user_id">Who</Label>
          <Select v-model="userId">
            <SelectTrigger id="user_id" class="cursor-pointer">
              <SelectValue placeholder="Anyone" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ANY">Anyone</SelectItem>
              <SelectItem v-for="user in users" :key="user.id" :value="String(user.id)">
                {{ user.name }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="grid gap-2">
          <Label for="action">What</Label>
          <Select v-model="action">
            <SelectTrigger id="action" class="cursor-pointer">
              <SelectValue placeholder="Anything" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem :value="ANY">Anything</SelectItem>
              <SelectItem v-for="option in actions" :key="option.value" :value="option.value">
                {{ option.label }}
              </SelectItem>
            </SelectContent>
          </Select>
        </div>

        <div class="grid gap-2">
          <Label for="from">From</Label>
          <Input id="from" v-model="from" type="date" name="from" />
        </div>

        <div class="grid gap-2">
          <Label for="to">To</Label>
          <Input id="to" v-model="to" type="date" name="to" />
        </div>
      </div>

      <div class="flex items-center gap-3">
        <Button v-if="hasFilters" variant="ghost" class="cursor-pointer" @click="clear">
          Clear filters
        </Button>
        <p class="text-sm text-foreground">
          {{ entries.total }}
          {{ entries.total === 1 ? 'entry' : 'entries' }}
        </p>
      </div>
    </div>

    <div class="overflow-x-auto rounded-xl border">
      <table class="w-full text-sm">
        <thead class="bg-muted/50 text-left">
          <tr>
            <th class="px-4 py-3 font-medium">When</th>
            <th class="px-4 py-3 font-medium">Who</th>
            <th class="px-4 py-3 font-medium">What</th>
            <th class="px-4 py-3 font-medium">Which</th>
            <th class="w-px px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="entries.data.length === 0">
            <td colspan="5" class="px-4 py-8 text-center text-foreground">
              {{ hasFilters ? 'Nothing matches these filters.' : 'Nothing has happened yet.' }}
            </td>
          </tr>

          <template v-for="entry in entries.data" :key="entry.id">
            <tr class="border-t">
              <td class="px-4 py-3 tabular-nums">
                {{ formatDateTime(entry.created_at) }}
              </td>
              <td class="px-4 py-3 text-foreground">
                <!-- A seeder or a console command has nobody
                                     behind it, and so does an entry that
                                     outlived the person who caused it. -->
                {{ entry.user_name ?? 'System' }}
              </td>
              <td class="px-4 py-3">{{ entry.action_label }}</td>
              <td class="px-4 py-3">
                <span class="text-muted-foreground">
                  {{ entityName(entry) }}
                </span>
                {{ entry.label ?? `#${entry.entity_id}` }}
              </td>
              <td class="px-4 py-3 text-right">
                <Button
                  variant="ghost"
                  size="sm"
                  class="cursor-pointer"
                  :aria-expanded="expanded === entry.id"
                  :aria-label="`Details of entry ${entry.id}`"
                  @click="toggle(entry.id)"
                >
                  <ChevronDown
                    class="size-4 transition-transform"
                    :class="{
                      'rotate-180': expanded === entry.id,
                    }"
                  />
                </Button>
              </td>
            </tr>
            <tr v-if="expanded === entry.id" class="border-t">
              <td colspan="5" class="bg-muted/30 px-4 py-3">
                <pre class="overflow-x-auto text-xs text-foreground">{{
                  JSON.stringify(entry.payload, null, 2)
                }}</pre>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <nav v-if="entries.links.length > 3" class="flex flex-wrap gap-1" aria-label="Pagination">
      <template v-for="link in entries.links" :key="link.label">
        <Link
          v-if="link.url"
          :href="link.url"
          class="cursor-pointer rounded-md border px-3 py-1 text-sm"
          :class="link.active ? 'bg-accent text-accent-foreground' : ''"
          :aria-current="link.active ? 'page' : undefined"
        >
          {{ pageLabel(link.label) }}
        </Link>
        <span v-else class="rounded-md border px-3 py-1 text-sm text-muted-foreground">
          {{ pageLabel(link.label) }}
        </span>
      </template>
    </nav>
  </div>
</template>
