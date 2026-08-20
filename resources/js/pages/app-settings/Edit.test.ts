import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import AppSettingsEdit from '@/pages/app-settings/Edit.vue';
import { resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

function build(
    settings: { logo_path: string | null; logo_url: string | null } = {
        logo_path: null,
        logo_url: null,
    },
) {
    return mount(AppSettingsEdit, { props: { settings } });
}

const uploaded = {
    logo_path: 'logos/xolution.png',
    logo_url: '/storage/logos/xolution.png',
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
});
