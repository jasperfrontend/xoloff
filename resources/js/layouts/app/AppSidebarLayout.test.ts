import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

describe('app/AppSidebarLayout', () => {
    /**
     * Both keep a wide table from scrolling the page sideways, but hidden
     * makes the element a scroll container, and position: sticky inside a
     * scroll container that never scrolls does not stick. It cost the settings
     * navigation its sticky once already.
     */
    it('clips sideways overflow rather than hiding it', () => {
        const classes = mount(AppSidebarLayout)
            .find('[data-slot="sidebar-inset"]')
            .classes();

        expect(classes).toContain('overflow-x-clip');
        expect(classes).not.toContain('overflow-x-hidden');
    });
});
