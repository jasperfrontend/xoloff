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
    logo_url: null,
    logo_preview_url: null,
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

const saved = {
    logo_url: 'https://xolution.nl/wp-content/uploads/logo.svg',
    logo_preview_url: '/logo',
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

    it('says so when no logo has been saved', () => {
        const wrapper = build();

        expect(wrapper.text()).toContain('No logo saved yet');
        expect(wrapper.find('img').exists()).toBe(false);
    });

    /**
     * The stored copy, not the address it came from. Previewing the remote
     * image would show what is out there rather than what this application
     * actually holds, which is the one thing this screen is for.
     */
    it('previews the stored copy rather than the remote address', () => {
        const wrapper = build(saved);

        expect(wrapper.find('img').attributes('src')).toBe('/logo');
        expect(wrapper.text()).not.toContain('No logo saved yet');
    });

    it('keeps the address in the field so a typo can be corrected', () => {
        const input = build(saved).find('input[name="logo_url"]');

        expect((input.element as HTMLInputElement).value).toBe(
            'https://xolution.nl/wp-content/uploads/logo.svg',
        );
        expect(input.attributes('type')).toBe('url');
    });

    it('offers to fetch again rather than to save once there is a logo', () => {
        expect(build().text()).toContain('Save logo');
        expect(build(saved).text()).toContain('Fetch again');
    });

    it('offers removal only when there is something to remove', () => {
        expect(build().find('button[aria-label="Remove logo"]').exists()).toBe(
            false,
        );
        expect(
            build(saved).find('button[aria-label="Remove logo"]').exists(),
        ).toBe(true);
    });

    // The wording lives in the confirmation dialog, which only renders once
    // the dialog is opened, so it is read off the prop that carries it.
    it('promises that a downloaded quote keeps the logo it was printed with', () => {
        const description = build(saved)
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
         * The logo saves on its own. Sharing a form would hold a KvK number
         * hostage to someone else's web server being up at that moment.
         */
        it('saves separately from the logo', () => {
            const logoForm = build()
                .findAll('form')
                .find((form) => form.find('input[name="logo_url"]').exists());

            expect(logoForm).toBeDefined();
            expect(logoForm!.find('input[name="company_name"]').exists()).toBe(
                false,
            );
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
