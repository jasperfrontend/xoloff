<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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

defineProps<{
    /** Wayfinder form props, e.g. CustomerController.destroy.form(id) */
    action: Record<string, unknown>;
    title: string;
    description: string;
    label?: string;
}>();

const open = ref(false);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button
                variant="ghost"
                size="sm"
                class="text-destructive hover:text-destructive"
                :aria-label="label ?? 'Delete'"
            >
                <Trash2 class="size-4" />
            </Button>
        </DialogTrigger>

        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="secondary">Cancel</Button>
                </DialogClose>

                <Form v-bind="action" @success="open = false">
                    <Button type="submit" variant="destructive">Delete</Button>
                </Form>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
