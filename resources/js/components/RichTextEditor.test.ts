import type { Editor } from '@tiptap/vue-3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import RichTextEditor from '@/components/RichTextEditor.vue';

/**
 * TipTap pushes editor state into Vue through a ref whose trigger is deferred
 * by two nested animation frames, so anything that reads the toolbar's active
 * or disabled state has to wait for both. Without this, the assertions read
 * whatever the toolbar looked like before the click.
 */
async function settle(wrapper: VueWrapper) {
    for (let frame = 0; frame < 3; frame += 1) {
        await new Promise((resolve) => requestAnimationFrame(resolve));
    }

    await wrapper.vm.$nextTick();
}

function build(modelValue = '<p>Hallo daar</p>') {
    return mount(RichTextEditor, {
        props: { modelValue, label: 'Intro text' },
    });
}

/**
 * The editor instance, reached the same way the component's own template does.
 * Used only to place a selection, which is a thing a person does with a mouse
 * and jsdom cannot.
 */
function editorOf(wrapper: VueWrapper): Editor {
    return (wrapper.vm as unknown as { editor: Editor }).editor;
}

function latestValue(wrapper: VueWrapper): string {
    const emitted = wrapper.emitted('update:modelValue') as
        string[][] | undefined;

    return emitted ? emitted[emitted.length - 1][0] : '';
}

describe('RichTextEditor', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    // useEditor builds the editor once the component is mounted, so even the
    // initial content is a frame away.
    it('shows the content it was given', async () => {
        const wrapper = build();
        await settle(wrapper);

        expect(wrapper.text()).toContain('Hallo daar');
    });

    it('names itself for anyone not using a mouse', async () => {
        const wrapper = build();
        await settle(wrapper);

        expect(wrapper.find('[contenteditable]').attributes('aria-label')).toBe(
            'Intro text',
        );
    });

    it('reports what was typed as html', async () => {
        const wrapper = build();

        await wrapper
            .find('button[aria-label="Bulleted list"]')
            .trigger('click');
        await settle(wrapper);

        expect(latestValue(wrapper)).toContain('<ul>');
        expect(latestValue(wrapper)).toContain('Hallo daar');
    });

    it('shows which formatting the cursor is sitting in', async () => {
        const wrapper = build();
        const heading = wrapper.find('button[aria-label="Heading 2"]');

        expect(heading.attributes('aria-pressed')).toBe('false');

        await heading.trigger('click');
        await settle(wrapper);

        expect(heading.attributes('aria-pressed')).toBe('true');
        expect(latestValue(wrapper)).toContain('<h2>');
    });

    it('applies a mark to the selected text', async () => {
        const wrapper = build();

        editorOf(wrapper).commands.selectAll();
        await wrapper.find('button[aria-label="Bold"]').trigger('click');
        await settle(wrapper);

        expect(latestValue(wrapper)).toBe('<p><strong>Hallo daar</strong></p>');
    });

    /**
     * Only the first level or two of heading have anywhere to sit in a quote,
     * and a rule or a code block has nowhere at all. Offering formatting the
     * template cannot render, or the sanitiser would strip, is worse than not
     * offering it.
     */
    it('offers only the formatting a quote can carry', () => {
        const labels = build()
            .findAll('button')
            .map((button) => button.attributes('aria-label'));

        expect(labels).toContain('Heading 2');
        expect(labels).toContain('Heading 3');
        expect(labels).not.toContain('Heading 1');
        expect(labels).not.toContain('Horizontal rule');
        expect(labels).not.toContain('Code block');
    });

    it('cannot undo before anything has been done', async () => {
        const wrapper = build();
        await settle(wrapper);

        expect(
            wrapper.find('button[aria-label="Undo"]').attributes('disabled'),
        ).toBeDefined();

        await wrapper
            .find('button[aria-label="Bulleted list"]')
            .trigger('click');
        await settle(wrapper);

        expect(
            wrapper.find('button[aria-label="Undo"]').attributes('disabled'),
        ).toBeUndefined();
    });

    it('takes back what undo undoes', async () => {
        const wrapper = build();

        await wrapper
            .find('button[aria-label="Bulleted list"]')
            .trigger('click');
        await settle(wrapper);
        await wrapper.find('button[aria-label="Undo"]').trigger('click');
        await settle(wrapper);

        expect(latestValue(wrapper)).toBe('<p>Hallo daar</p>');
    });

    it('asks where a link should point and marks the selection', async () => {
        const wrapper = build();

        vi.spyOn(window, 'prompt').mockReturnValue('https://xolution.nl');

        editorOf(wrapper).commands.selectAll();
        await wrapper.find('button[aria-label="Link"]').trigger('click');
        await settle(wrapper);

        expect(latestValue(wrapper)).toContain('href="https://xolution.nl"');
    });

    it('leaves the text alone when the address is not given', async () => {
        const wrapper = build();

        vi.spyOn(window, 'prompt').mockReturnValue(null);

        editorOf(wrapper).commands.selectAll();
        await wrapper.find('button[aria-label="Link"]').trigger('click');
        await settle(wrapper);

        expect(latestValue(wrapper)).not.toContain('href');
        expect(wrapper.text()).toContain('Hallo daar');
    });

    it('cannot remove a link where there is none', async () => {
        const wrapper = build();
        await settle(wrapper);

        expect(
            wrapper
                .find('button[aria-label="Remove link"]')
                .attributes('disabled'),
        ).toBeDefined();
    });

    /**
     * Clicking the toolbar must not pull the caret out of the document, or the
     * command would land somewhere other than where the person was working.
     */
    it('keeps the selection when the toolbar is clicked', async () => {
        const wrapper = build();
        const event = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
        });

        wrapper.find('button[aria-label="Bold"]').element.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(true);
    });

    /**
     * Writing the incoming value back on every keystroke would send the caret
     * to the top of the document each time a character was typed.
     */
    /**
     * Placeholders are inserted by buttons that live outside the editor, so
     * "where the caret last was" is the whole contract. Covered here, against
     * the real TipTap, rather than in the page that draws those buttons.
     */
    it('inserts text where the caret last was', async () => {
        const wrapper = build('<p>Beste ,</p>');
        await settle(wrapper);

        // Between "Beste " and the comma. Positions count from 1, since 0 is
        // before the paragraph itself.
        editorOf(wrapper).commands.setTextSelection(7);

        (wrapper.vm as unknown as { insert: (text: string) => void }).insert(
            '[[[customer_first_name]]]',
        );
        await settle(wrapper);

        expect(latestValue(wrapper)).toBe(
            '<p>Beste [[[customer_first_name]]],</p>',
        );
    });

    /**
     * The token is plain text. If the editor ever decided a placeholder looked
     * like markup, the sanitiser would strip it on save and the quote would
     * silently lose its greeting.
     */
    it('keeps an inserted placeholder as plain text', async () => {
        const wrapper = build('<p></p>');
        await settle(wrapper);

        (wrapper.vm as unknown as { insert: (text: string) => void }).insert(
            '[[[customer_company_name]]]',
        );
        await settle(wrapper);

        expect(latestValue(wrapper)).toBe('<p>[[[customer_company_name]]]</p>');
    });

    it('does not rewrite the document when the value has not changed', async () => {
        const wrapper = build();
        const setContent = vi.spyOn(editorOf(wrapper).commands, 'setContent');

        await wrapper.setProps({ modelValue: '<p>Hallo daar</p>' });
        await settle(wrapper);

        expect(setContent).not.toHaveBeenCalled();

        await wrapper.setProps({ modelValue: '<p>Iets anders</p>' });
        await settle(wrapper);

        expect(wrapper.text()).toContain('Iets anders');
    });
});
