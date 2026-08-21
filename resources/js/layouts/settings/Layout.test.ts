import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { pageState, resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

afterEach(() => resetInertiaStub());

function links() {
    return mount(SettingsLayout)
        .findAll('nav[aria-label="Settings"] a')
        .map((link) => ({
            title: link.text(),
            href: link.attributes('href'),
        }));
}

/** The titles the navigation is showing as the page you are on. */
function highlighted() {
    return mount(SettingsLayout)
        .findAll('nav[aria-label="Settings"] a')
        .filter((link) => link.classes().includes('bg-muted'))
        .map((link) => link.text());
}

describe('settings/Layout', () => {
    it('offers every settings screen there is', () => {
        expect(links()).toEqual([
            { title: 'Profile', href: '/settings/profile' },
            { title: 'Security', href: '/settings/security' },
            { title: 'Appearance', href: '/settings/appearance' },
            { title: 'Application', href: '/settings/app' },
        ]);
    });

    it('shows one item as the page you are on', () => {
        pageState.url = '/settings/appearance';

        expect(highlighted()).toEqual(['Appearance']);
    });

    /**
     * /settings/app is a character prefix of /settings/appearance, which lit
     * both of them up.
     */
    it('does not light up an item that merely shares a prefix', () => {
        pageState.url = '/settings/app';

        expect(highlighted()).toEqual(['Application']);
    });

    /**
     * A settings page is long and its navigation is short, so the navigation
     * follows you down rather than scrolling away. Only where there is room
     * beside the content: stacked on a narrow screen it would pin itself over
     * what you came to read.
     *
     * self-start is load-bearing. A flex item stretches to the height of the
     * row by default, which leaves a sticky one nothing to slide within.
     */
    it('keeps the navigation in view on a wide screen', () => {
        const aside = mount(SettingsLayout).find('aside');

        expect(aside.classes()).toEqual(
            expect.arrayContaining(['lg:sticky', 'lg:top-6', 'lg:self-start']),
        );
        expect(aside.classes()).not.toContain('sticky');
    });
});
