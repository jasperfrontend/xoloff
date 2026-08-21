import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import AppSettingsEdit from '@/pages/app-settings/Edit.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

type Settings = InstanceType<typeof AppSettingsEdit>['$props']['settings'];

const blank: Settings = {
    logo_path: null,
    logo_url: null,
    company_name: null,
    company_address: null,
    company_kvk: null,
    company_vat_number: null,
    default_validity_days: 30,
};

function build(settings: Partial<Settings> = {}) {
    return mount(AppSettingsEdit, {
        props: { settings: { ...blank, ...settings } },
    });
}

const uploaded = {
    logo_path: 'logos/xolution.png',
    logo_url: '/storage/logos/xolution.png',
};

const details = {
    company_name: 'Xolution',
    company_address: 'Voorbeeldstraat 1',
    company_kvk: '01234567',
    company_vat_number: 'NL001234567B01',
};

describe('app-settings/Edit', () => {
    beforeEach(() => {
        resetInertiaStub();
    });

    it('says so when no logo has been uploaded', () => {
        const wrapper = build();

        expect(wrapper.text()).toContain('No logo uploaded yet');
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('shows the logo that is in use', () => {
        const wrapper = build(uploaded);

        expect(wrapper.find('img').attributes('src')).toBe(
            '/storage/logos/xolution.png',
        );
        expect(wrapper.text()).not.toContain('No logo uploaded yet');
    });

    /**
     * Uploading over an existing logo replaces it, so the button should not
     * promise to add a second one.
     */
    it('offers to replace rather than upload once there is a logo', () => {
        expect(build().text()).toContain('Upload logo');
        expect(build(uploaded).text()).toContain('Replace logo');
    });

    it('offers removal only when there is something to remove', () => {
        expect(build().find('button[aria-label="Remove logo"]').exists()).toBe(
            false,
        );
        expect(
            build(uploaded).find('button[aria-label="Remove logo"]').exists(),
        ).toBe(true);
    });

    /**
     * The server refuses anything else, and an accept list that disagreed with
     * it would let the file picker offer files that are then rejected.
     */
    it('offers the file picker only the formats the server takes', () => {
        const input = build().find('input[type="file"]');

        expect(input.attributes('name')).toBe('logo');
        expect(input.attributes('accept')).toBe(
            'image/png,image/jpeg,image/webp',
        );
    });

    // The wording lives in the confirmation dialog, which only renders once
    // the dialog is opened, so it is read off the prop that carries it.
    it('promises that a downloaded quote keeps the logo it was printed with', () => {
        const description = build(uploaded)
            .findComponent(ConfirmDeleteButton)
            .props('description');

        expect(description).toContain('keep the logo they were printed with');
    });

    /**
     * Xolution's own details print on every quote PDF (SPEC §7). The address
     * is a textarea rather than an input, because it is several lines and is
     * printed with the breaks it was typed with.
     */
    describe('the details printed on quotes', () => {
        it('shows what has already been saved', () => {
            const wrapper = build(details);

            expect(
                (
                    wrapper.find('input[name="company_name"]')
                        .element as HTMLInputElement
                ).value,
            ).toBe('Xolution');
            expect(
                (
                    wrapper.find('textarea[name="company_address"]')
                        .element as HTMLTextAreaElement
                ).value,
            ).toBe('Voorbeeldstraat 1');
            expect(
                (
                    wrapper.find('input[name="company_kvk"]')
                        .element as HTMLInputElement
                ).value,
            ).toBe('01234567');
            expect(
                (
                    wrapper.find('input[name="company_vat_number"]')
                        .element as HTMLInputElement
                ).value,
            ).toBe('NL001234567B01');
        });

        it('starts empty rather than showing the word null', () => {
            const wrapper = build();

            expect(wrapper.text()).not.toContain('null');
            expect(
                (
                    wrapper.find('input[name="company_name"]')
                        .element as HTMLInputElement
                ).value,
            ).toBe('');
        });

        /**
         * The values are still being collected, so the server takes them one
         * at a time and the form must not demand the rest.
         */
        it('demands none of them', () => {
            const wrapper = build();

            for (const name of Object.keys(details)) {
                expect(
                    wrapper.find(`[name="${name}"]`).attributes('required'),
                ).toBeUndefined();
            }
        });

        it('submits all four under the names the server reads', async () => {
            const wrapper = build(details);

            await wrapper.findAll('form')[0].trigger('submit');

            expect(submissions).toHaveLength(1);
            expect(submissions[0].data).toEqual(details);
        });

        /**
         * The logo saves on its own. Sharing a form would mean a validation
         * error on any field silently dropped the chosen file, which a file
         * input cannot be repopulated with.
         */
        it('saves separately from the logo', () => {
            const wrapper = build();
            const forms = wrapper.findAll('form');

            expect(forms[0].find('input[type="file"]').exists()).toBe(false);
            expect(
                wrapper
                    .findAll('form')
                    .find((form) => form.find('input[type="file"]').exists())
                    ?.find('input[name="company_name"]')
                    .exists(),
            ).toBe(false);
        });
    });

    /**
     * How long a quote stays valid once sent (SPEC §7). Sending a quote can
     * give that one client more leeway without changing this.
     */
    describe('the validity window', () => {
        it('shows the current default', () => {
            const input = build({ default_validity_days: 45 }).find(
                'input[name="default_validity_days"]',
            );

            expect((input.element as HTMLInputElement).value).toBe('45');
        });

        /**
         * Unlike the company details this one has no blank state: every quote
         * sent takes its expiry from it.
         */
        it('will not be left empty', () => {
            const input = build().find('input[name="default_validity_days"]');

            expect(input.attributes('required')).toBeDefined();
            expect(input.attributes('min')).toBe('1');
            expect(input.attributes('max')).toBe('365');
        });

        it('submits on its own, without the company details', async () => {
            const wrapper = build({ default_validity_days: 45 });
            const form = wrapper
                .findAll('form')
                .find((candidate) =>
                    candidate
                        .find('input[name="default_validity_days"]')
                        .exists(),
                );

            await form!.trigger('submit');

            expect(submissions).toHaveLength(1);
            expect(submissions[0].data).toEqual({
                default_validity_days: '45',
            });
        });
    });
});
