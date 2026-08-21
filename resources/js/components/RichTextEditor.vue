<script setup lang="ts">
import {
  Bold,
  Italic,
  Link2,
  Link2Off,
  List,
  ListOrdered,
  Quote,
  Redo2,
  Strikethrough,
  Underline,
  Undo2,
} from '@lucide/vue';
import type { Level } from '@tiptap/extension-heading';
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import { onBeforeUnmount, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
  modelValue: string;
  label: string;
  describedBy?: string;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

// A first-level heading would compete with the document title, and levels
// below three have nowhere to sit in a quote.
const HEADING_LEVELS: Level[] = [2, 3];

const editor = useEditor({
  content: props.modelValue,
  extensions: [
    StarterKit.configure({
      // Kept to exactly what the sanitiser accepts and the quote
      // template knows how to style. Rules and code blocks have no place
      // in a quote intro.
      heading: { levels: HEADING_LEVELS },
      horizontalRule: false,
      code: false,
      codeBlock: false,
      link: { openOnClick: false },
    }),
  ],
  editorProps: {
    attributes: {
      class: 'rich-text min-h-40 px-3 py-2 focus:outline-none',
      'aria-label': props.label,
      ...(props.describedBy ? { 'aria-describedby': props.describedBy } : {}),
    },
  },
  onUpdate: ({ editor: instance }) => emit('update:modelValue', instance.getHTML()),
});

/**
 * Only written back when the value differs from what the editor already holds.
 * Assigning on every change would reset the caret to the start of the document
 * on each keystroke.
 */
watch(
  () => props.modelValue,
  (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
      editor.value.commands.setContent(value, { emitUpdate: false });
    }
  },
);

onBeforeUnmount(() => editor.value?.destroy());

/**
 * Drops text in wherever the caret last was.
 *
 * focus() is what restores that position: a button outside the editor takes
 * the selection with it unless the click is prevented, and even then TipTap
 * needs telling to come back. The caller is responsible for the mousedown
 * half - see the toolbar below, which does the same thing.
 */
function insert(text: string): void {
  editor.value?.chain().focus().insertContent(text).run();
}

defineExpose({ insert });

interface ToolbarAction {
  label: string;
  icon: unknown;
  run: () => void;
  isActive?: () => boolean;
  isDisabled?: () => boolean;
}

/**
 * Asked for rather than typed, because a link the sanitiser will strip on save
 * is worse than no link: the text stays and the destination quietly vanishes.
 */
function toggleLink() {
  if (!editor.value) {
    return;
  }

  if (editor.value.isActive('link')) {
    editor.value.chain().focus().unsetLink().run();

    return;
  }

  const href = window.prompt('Link to which address?')?.trim();

  if (!href) {
    return;
  }

  editor.value.chain().focus().setLink({ href }).run();
}

const toolbar: ToolbarAction[] = [
  {
    label: 'Bold',
    icon: Bold,
    run: () => editor.value?.chain().focus().toggleBold().run(),
    isActive: () => editor.value?.isActive('bold') ?? false,
  },
  {
    label: 'Italic',
    icon: Italic,
    run: () => editor.value?.chain().focus().toggleItalic().run(),
    isActive: () => editor.value?.isActive('italic') ?? false,
  },
  {
    label: 'Underline',
    icon: Underline,
    run: () => editor.value?.chain().focus().toggleUnderline().run(),
    isActive: () => editor.value?.isActive('underline') ?? false,
  },
  {
    label: 'Strikethrough',
    icon: Strikethrough,
    run: () => editor.value?.chain().focus().toggleStrike().run(),
    isActive: () => editor.value?.isActive('strike') ?? false,
  },
  {
    label: 'Bulleted list',
    icon: List,
    run: () => editor.value?.chain().focus().toggleBulletList().run(),
    isActive: () => editor.value?.isActive('bulletList') ?? false,
  },
  {
    label: 'Numbered list',
    icon: ListOrdered,
    run: () => editor.value?.chain().focus().toggleOrderedList().run(),
    isActive: () => editor.value?.isActive('orderedList') ?? false,
  },
  {
    label: 'Quotation',
    icon: Quote,
    run: () => editor.value?.chain().focus().toggleBlockquote().run(),
    isActive: () => editor.value?.isActive('blockquote') ?? false,
  },
  {
    label: 'Link',
    icon: Link2,
    run: toggleLink,
    isActive: () => editor.value?.isActive('link') ?? false,
  },
  {
    label: 'Remove link',
    icon: Link2Off,
    run: () => editor.value?.chain().focus().unsetLink().run(),
    isDisabled: () => !editor.value?.isActive('link'),
  },
  {
    label: 'Undo',
    icon: Undo2,
    run: () => editor.value?.chain().focus().undo().run(),
    isDisabled: () => !editor.value?.can().undo(),
  },
  {
    label: 'Redo',
    icon: Redo2,
    run: () => editor.value?.chain().focus().redo().run(),
    isDisabled: () => !editor.value?.can().redo(),
  },
];
</script>

<template>
  <div
    class="rounded-lg border bg-background text-foreground focus-within:ring-1 focus-within:ring-ring"
  >
    <!-- The toolbar swallows mousedown so that clicking a button never takes
             the selection out of the editor underneath it. -->
    <div class="flex flex-wrap gap-1 border-b p-1">
      <!-- Headings sit apart from the toggles: they replace one another
                 rather than stacking, so they read better as words. -->
      <button
        v-for="level in HEADING_LEVELS"
        :key="`heading-${level}`"
        type="button"
        :aria-label="`Heading ${level}`"
        :aria-pressed="editor?.isActive('heading', { level }) ?? false"
        :class="
          cn(
            'cursor-pointer rounded-md px-2 py-1 text-xs font-semibold hover:bg-accent',
            editor?.isActive('heading', { level })
              ? 'bg-accent text-accent-foreground'
              : 'text-foreground',
          )
        "
        @mousedown.prevent
        @click="editor?.chain().focus().toggleHeading({ level }).run()"
      >
        H{{ level }}
      </button>

      <button
        v-for="action in toolbar"
        :key="action.label"
        type="button"
        :aria-label="action.label"
        :aria-pressed="action.isActive ? action.isActive() : undefined"
        :disabled="action.isDisabled?.() ?? false"
        :class="
          cn(
            'cursor-pointer rounded-md p-1.5 hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40',
            action.isActive?.() ? 'bg-accent text-accent-foreground' : 'text-foreground',
          )
        "
        @mousedown.prevent
        @click="action.run()"
      >
        <component :is="action.icon" class="size-4" />
      </button>
    </div>

    <EditorContent :editor="editor" />
  </div>
</template>
