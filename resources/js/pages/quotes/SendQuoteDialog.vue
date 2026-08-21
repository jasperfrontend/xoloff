<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Send } from '@lucide/vue';
import { ref } from 'vue';
import QuoteSendController from '@/actions/App/Http/Controllers/QuoteSendController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    quoteId: number;
    customerEmail: string;
    /** The window this quote would be sent with, already resolved. */
    validityDays: number;
    /** Whether that window is the application default rather than this quote's own. */
    followsTheDefault: boolean;
    /** Whether the quote has been sent before, which changes what this promises. */
    alreadySent: boolean;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button class="cursor-pointer">
                <Send class="size-4" />
                {{ props.alreadySent ? 'Send again' : 'Send quote' }}
            </Button>
        </DialogTrigger>

        <DialogContent>
            <Form
                v-bind="QuoteSendController.form(props.quoteId)"
                @success="open = false"
                v-slot="{ errors, processing }"
            >
                <DialogHeader>
                    <DialogTitle>
                        {{
                            props.alreadySent
                                ? `Send quote ${props.quoteId} again?`
                                : `Send quote ${props.quoteId}?`
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        This issues a link for {{ props.customerEmail }} and
                        marks the quote as sent.
                        <template v-if="props.alreadySent">
                            The existing link keeps working; only its expiry
                            date moves.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2 py-4">
                    <Label for="validity_days">Valid for</Label>
                    <div class="flex items-center gap-2">
                        <Input
                            id="validity_days"
                            name="validity_days"
                            type="number"
                            min="1"
                            max="365"
                            class="w-24"
                            :default-value="props.validityDays"
                        />
                        <span class="text-sm text-foreground">days</span>
                    </div>
                    <p class="text-xs text-foreground">
                        {{
                            props.followsTheDefault
                                ? 'Follows the application default. Change it here to give this client more leeway.'
                                : 'This quote has its own window. Set it back to the default in Settings.'
                        }}
                    </p>
                    <InputError :message="errors.validity_days" />
                </div>

                <DialogFooter>
                    <DialogClose as-child>
                        <Button
                            type="button"
                            variant="secondary"
                            class="cursor-pointer"
                        >
                            Cancel
                        </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        class="cursor-pointer"
                        :disabled="processing"
                    >
                        {{ props.alreadySent ? 'Send again' : 'Send quote' }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
