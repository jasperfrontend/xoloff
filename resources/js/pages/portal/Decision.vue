<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import type { QuoteStatus } from '@/types';

/**
 * Yes or no, and what the page says once one of them has been chosen
 * (SPEC §8).
 *
 * Deliberately not a dialog. This is the one page a customer reads, often on a
 * phone, possibly forwarded to someone who has never seen this application -
 * an inline panel has fewer ways to go wrong than a modal, and nothing here is
 * so dangerous that it needs a second click to confirm. Declining does ask for
 * the reason first, which doubles as that pause.
 */
const props = defineProps<{
  /**
   * Addresses rather than the token, so the credential reaches this page
   * only inside the links that need it.
   */
  approveUrl: string;
  denyUrl: string;
  status: QuoteStatus;
  denyReason: string | null;
  canDecide: boolean;
}>();

const decliningNow = ref(false);
</script>

<template>
  <section class="rounded-xl border bg-background p-6">
    <template v-if="props.status === 'approved'">
      <h2 class="font-semibold">U heeft deze offerte geaccepteerd</h2>
      <p class="mt-1 text-sm text-foreground">
        Dank u wel. We nemen contact met u op over de volgende stappen.
      </p>
    </template>

    <template v-else-if="props.status === 'denied'">
      <h2 class="font-semibold">U heeft deze offerte afgewezen</h2>
      <p class="mt-1 text-sm text-foreground">
        Dank u voor uw reactie. Neem gerust contact met ons op als u een aangepaste offerte wilt
        ontvangen.
      </p>
      <!--
                Read back rather than only stored, so a note someone took the
                trouble to write visibly landed somewhere.
            -->
      <blockquote
        v-if="props.denyReason"
        class="mt-4 border-l-2 pl-4 text-sm whitespace-pre-line text-muted-foreground"
      >
        {{ props.denyReason }}
      </blockquote>
    </template>

    <template v-else-if="props.canDecide">
      <h2 class="font-semibold">Gaat u akkoord met deze offerte?</h2>

      <div v-if="!decliningNow" class="mt-4 flex flex-wrap gap-3">
        <Form :action="props.approveUrl" method="post" v-slot="{ processing }">
          <Button type="submit" class="cursor-pointer" :disabled="processing"> Akkoord </Button>
        </Form>

        <Button
          type="button"
          variant="secondary"
          class="cursor-pointer"
          @click="decliningNow = true"
        >
          Niet akkoord
        </Button>
      </div>

      <Form
        v-else
        :action="props.denyUrl"
        method="post"
        class="mt-4 grid gap-3"
        v-slot="{ errors, processing }"
      >
        <div class="grid gap-2">
          <label for="reason" class="text-sm font-medium">
            Wilt u kort toelichten waarom? (niet verplicht)
          </label>
          <textarea
            id="reason"
            name="reason"
            rows="3"
            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
          ></textarea>
          <p v-if="errors.reason" class="text-sm text-destructive">
            {{ errors.reason }}
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <Button type="submit" variant="destructive" class="cursor-pointer" :disabled="processing">
            Offerte afwijzen
          </Button>
          <Button
            type="button"
            variant="ghost"
            class="cursor-pointer"
            @click="decliningNow = false"
          >
            Terug
          </Button>
        </div>
      </Form>
    </template>
  </section>
</template>
