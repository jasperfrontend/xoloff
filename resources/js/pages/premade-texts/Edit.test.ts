import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PremadeTextsEdit from '@/pages/premade-texts/Edit.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

/**
 * The editor itself is covered in RichTextEditor.test.ts against the real
 * TipTap. Here it is stood in for, so these tests are about the one thing the
 * page is responsible for: getting what the editor holds into the request,
 * given that a contenteditable is not a form field and submits nothing by
 * itself.
 */
vi.mock('@/components/RichTextEditor.vue', async () => {
    const { defineComponent } = await import('vue');

    return {
        default: defineComponent({
            name: 'RichTextEditorStub',
            props: {
                modelValue: { type: String, required: true },
                label: { type: String, required: true },
            },
            emits: ['update:modelValue'],
            setup: (props) => () => props.modelValue,
        }),
    };
});

function build(
    texts = { intro: '<p>Beste klant</p>', footer: '<p>Voorwaarden</p>' },
) {
    return mount(PremadeTextsEdit, { props: { texts } });
}

function editorFor(wrapper: VueWrapper, label: string) {
    return wrapper
        .findAllComponents({ name: 'RichTextEditorStub' })
        .find((editor) => editor.props('label') === label)!;
}

function hiddenValue(wrapper: VueWrapper, name: string) {
    return (
        wrapper.find(`input[type="hidden"][name="${name}"]`)
            .element as HTMLInputElement
    ).value;
}

describe('premade-texts/Edit', () => {
    beforeEach(() => {
        resetInertiaStub();
    });

    it('opens both texts for editing', () => {
        const wrapper = build();

        expect(editorFor(wrapper, 'Intro text').props('modelValue')).toBe(
            '<p>Beste klant</p>',
        );
        expect(editorFor(wrapper, 'Footer text').props('modelValue')).toBe(
            '<p>Voorwaarden</p>',
        );
    });

    it('submits what the editors hold', async () => {
        const wrapper = build();

        editorFor(wrapper, 'Intro text').vm.$emit(
            'update:modelValue',
            '<p>Herschreven intro</p>',
        );
        editorFor(wrapper, 'Footer text').vm.$emit(
            'update:modelValue',
            '<p>Herschreven footer</p>',
        );
        await wrapper.vm.$nextTick();

        await wrapper.find('form').trigger('submit');

        // An HTML form only speaks GET and POST, so Wayfinder posts to a url
        // that spoofs the PUT.
        expect(submissions[0].method).toBe('post');
        expect(submissions[0].url).toBe('/premade-texts?_method=PUT');
        expect(submissions[0].data.intro).toBe('<p>Herschreven intro</p>');
        expect(submissions[0].data.footer).toBe('<p>Herschreven footer</p>');
    });

    /**
     * A contenteditable submits nothing, so what the editor holds is mirrored
     * into a hidden field. If that mirror ever stops tracking, the page would
     * silently save the text it was opened with.
     */
    it('keeps the hidden fields in step with the editors', async () => {
        const wrapper = build();

        expect(hiddenValue(wrapper, 'footer')).toBe('<p>Voorwaarden</p>');

        editorFor(wrapper, 'Footer text').vm.$emit(
            'update:modelValue',
            '<p>Nieuw</p>',
        );
        await wrapper.vm.$nextTick();

        expect(hiddenValue(wrapper, 'footer')).toBe('<p>Nieuw</p>');
    });

    it('opens cleanly when neither text has been written yet', () => {
        const wrapper = build({ intro: '', footer: '' });

        expect(hiddenValue(wrapper, 'intro')).toBe('');
        expect(hiddenValue(wrapper, 'footer')).toBe('');
    });

    it('says that the footer carries the legal text', () => {
        expect(build().text()).toContain('algemene voorwaarden');
    });

    /**
     * The reason editing here is safe is not obvious, and getting it wrong
     * means believing a quote already sent has quietly changed.
     */
    it('explains that quotes keep their own copy', () => {
        expect(build().text()).toContain('keep a copy');
    });
});
