import { describe, expect, it, vi } from 'vitest';
import { useCurrentUrl } from '@/composables/useCurrentUrl';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

describe('useCurrentUrl', () => {
    it('recognises the page it is on', () => {
        expect(isCurrentUrl('/settings/app', '/settings/app')).toBe(true);
        expect(isCurrentUrl('/settings/app', '/settings/app/logo')).toBe(false);
    });

    it('recognises a page below the one it is given', () => {
        expect(isCurrentOrParentUrl('/settings/app', '/settings/app')).toBe(
            true,
        );
        expect(
            isCurrentOrParentUrl('/settings/app', '/settings/app/logo'),
        ).toBe(true);
    });

    /**
     * The reason this is not a plain startsWith. /settings/app is not a parent
     * of /settings/appearance, and treating it as one lit up two items in the
     * settings navigation at the same time.
     */
    it('does not treat a shared prefix as a parent', () => {
        expect(
            isCurrentOrParentUrl('/settings/app', '/settings/appearance'),
        ).toBe(false);
        expect(isCurrentOrParentUrl('/quotes', '/quotes-archive')).toBe(false);
    });

    it('takes a trailing slash off the page it is given', () => {
        expect(
            isCurrentOrParentUrl('/settings/app/', '/settings/app/logo'),
        ).toBe(true);
    });

    it('compares an absolute address by its path', () => {
        expect(
            isCurrentOrParentUrl(
                'http://localhost/settings/app',
                '/settings/app/logo',
            ),
        ).toBe(true);
    });
});
