import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PlaceholderPicker from '@/components/PlaceholderPicker.vue';
import PremadeTextsEdit from '@/pages/premade-texts/Edit.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

/** What each stubbed editor was asked to insert, and by which text. */
const inserted: { label: string; text: string }[] = [];

/**
 * The editor itself is covered in RichTextEditor.test.ts against the real
 * TipTap. Here it is stood in for, so these tests are about the two things the
 * page is responsible for: getting what the editor holds into the request,
 * given that a contenteditable is not a form field and submits nothing by
 * itself, and handing a placeholder to the right editor.
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
            setup(props, { expose }) {
                // Mirrors what the real editor exposes. Where the token lands
                // inside the document is TipTap's business and is covered
                // against the real thing in RichTextEditor.test.ts; what this
                // page is responsible for is asking the right editor.
                expose({
                    insert: (text: string) =>
                        inserted.push({ label: props.label, text }),
                });

                return () => props.modelValue;
            },
        }),
    };
});

const placeholders = [
    {
        token: '[[[customer_first_name]]]',
        label: 'First name',
        example: 'Daan',
    },
    {
        token: '[[[customer_company_name]]]',
        label: 'Company',
        example: 'Acme BV',
    },
];

function build(
    texts = { intro: '<p>Beste klant</p>', footer: '<p>Voorwaarden</p>' },
) {
    return mount(PremadeTextsEdit, { props: { texts, placeholders } });
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
        inserted.length = 0;
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

/**
 * Placeholders in the quote texts, so a greeting addresses the customer it is
 * actually going to. Not in the spec - see App\Enums\Placeholder for why it
 * is here.
 */
describe('the placeholder picker', () => {
    beforeEach(() => {
        inserted.length = 0;
    });

    it('offers every placeholder the server sent, to both texts', () => {
        const wrapper = build();
        const pickers = wrapper.findAllComponents(PlaceholderPicker);

        expect(pickers).toHaveLength(2);
        expect(pickers[0].props('describes')).toBe('the intro text');
        expect(pickers[1].props('describes')).toBe('the footer text');
        expect(pickers[0].props('placeholders')).toEqual(placeholders);
    });

    /**
     * Typing the token by hand is the failure mode this exists to prevent: a
     * misspelled one resolves to nothing, and the first anyone would know is a
     * quote greeting a customer with a blank where their name should be.
     */
    it('inserts the token rather than the label it shows', async () => {
        const wrapper = build();

        await wrapper
            .findAllComponents(PlaceholderPicker)[0]
            .findAll('button')
            .find((button) => button.text() === 'First name')!
            .trigger('click');

        expect(inserted).toEqual([
            { label: 'Intro text', text: '[[[customer_first_name]]]' },
        ]);
    });

    it('sends the token to the text whose button was pressed', async () => {
        const wrapper = build();

        await wrapper
            .findAllComponents(PlaceholderPicker)[1]
            .findAll('button')
            .find((button) => button.text() === 'Company')!
            .trigger('click');

        expect(inserted).toEqual([
            { label: 'Footer text', text: '[[[customer_company_name]]]' },
        ]);
    });

    /**
     * The token has to land where the caret already was, so pressing a chip
     * must not take the selection out of the editor on its way there.
     */
    it('does not steal the selection from the editor', () => {
        const chip = build()
            .findAllComponents(PlaceholderPicker)[0]
            .find('button');

        const mousedown = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
        });
        chip.element.dispatchEvent(mousedown);

        expect(mousedown.defaultPrevented).toBe(true);
    });

    it('says what each placeholder turns into', () => {
        const chip = build()
            .findAllComponents(PlaceholderPicker)[0]
            .findAll('button')
            .find((button) => button.text() === 'First name');

        expect(chip!.attributes('title')).toContain('Daan');
    });

    it('explains that placeholders are filled in when a version is saved', () => {
        expect(build().text()).toContain('the customer the quote is going to');
    });
});
