import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AuditLogIndex from '@/pages/audit-log/Index.vue';
import { resetInertiaStub, visits } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

function entry(overrides: Record<string, unknown> = {}) {
    return {
        id: 1,
        action: 'updated',
        action_label: 'Updated',
        entity_type: 'quote_version',
        entity_id: 21,
        label: 'Quote 9 version 2',
        quote_id: 9,
        payload: {
            label: 'Quote 9 version 2',
            changes: { name: { from: 'A', to: 'B' } },
        },
        user_name: 'Stephan',
        created_at: '2026-08-12T12:32:00Z',
        ...overrides,
    };
}

function build(overrides: Record<string, unknown> = {}) {
    return mount(AuditLogIndex, {
        props: {
            entries: {
                data: [entry()],
                links: [],
                total: 1,
            },
            filters: {
                quote_id: null,
                user_id: null,
                action: null,
                from: null,
                to: null,
            },
            quotes: [{ id: 9, label: 'Quote 9, Acme BV' }],
            users: [{ id: 2, name: 'Stephan' }],
            actions: [
                { value: 'created', label: 'Created' },
                { value: 'updated', label: 'Updated' },
                { value: 'exported', label: 'Downloaded as PDF' },
            ],
            ...overrides,
        },
    });
}

/** The stubbed selects report a choice the way the real ones do. */
function chooseInSelect(wrapper: VueWrapper, index: number, value: string) {
    return wrapper
        .findAllComponents({ name: 'SelectStub' })
        [index].vm.$emit('update:modelValue', value);
}

function apply(wrapper: VueWrapper) {
    return wrapper
        .findAll('button')
        .find((button) => button.text() === 'Apply')!
        .trigger('click');
}

describe('audit-log/Index', () => {
    beforeEach(() => {
        resetInertiaStub();
    });

    it('reads an entry back as a sentence rather than as columns of ids', () => {
        const row = build().find('tbody tr').text();

        expect(row).toContain('12-08-2026 14:32');
        expect(row).toContain('Stephan');
        expect(row).toContain('Updated');
        expect(row).toContain('Quote version');
        expect(row).toContain('Quote 9 version 2');
    });

    /**
     * A seeder or a console command has nobody behind it, and so does an entry
     * that outlived the person who caused it.
     */
    it('names nobody as the system', () => {
        const wrapper = build({
            entries: {
                data: [entry({ user_name: null })],
                links: [],
                total: 1,
            },
        });

        expect(wrapper.find('tbody tr').text()).toContain('System');
    });

    it('keeps the detail out of the way until it is asked for', async () => {
        const wrapper = build();

        expect(wrapper.find('pre').exists()).toBe(false);

        await wrapper
            .find('button[aria-label="Details of entry 1"]')
            .trigger('click');

        expect(wrapper.find('pre').text()).toContain('"from": "A"');
    });

    it('asks the server for the filtered log', async () => {
        const wrapper = build();

        // Select order: quote, who, what.
        await chooseInSelect(wrapper, 0, '9');
        await chooseInSelect(wrapper, 2, 'exported');
        await wrapper.find('#from').setValue('2026-08-01');
        await wrapper.vm.$nextTick();

        await apply(wrapper);

        expect(visits[0].data).toEqual({
            quote_id: '9',
            action: 'exported',
            from: '2026-08-01',
        });
    });

    /**
     * An empty string cannot be a select value and still show a placeholder, so
     * "any" stands in for it. It must never reach the server as a filter.
     */
    it('does not send a filter that was left at any', async () => {
        const wrapper = build();

        await apply(wrapper);

        expect(visits[0].data).toEqual({});
    });

    it('offers to clear the filters only when some are set', async () => {
        const wrapper = build();

        const clearButton = () =>
            wrapper.findAll('button').find((b) => b.text() === 'Clear filters');

        expect(clearButton()).toBeUndefined();

        await chooseInSelect(wrapper, 0, '9');
        await wrapper.vm.$nextTick();

        expect(clearButton()).toBeDefined();

        await clearButton()!.trigger('click');

        expect(visits[0].data).toEqual({});
    });

    it('opens with the filters the server was given', () => {
        const wrapper = build({
            filters: {
                quote_id: 9,
                user_id: null,
                action: null,
                from: '2026-08-01',
                to: null,
            },
        });

        expect((wrapper.find('#from').element as HTMLInputElement).value).toBe(
            '2026-08-01',
        );
        expect(
            wrapper.findAll('button').find((b) => b.text() === 'Clear filters'),
        ).toBeDefined();
    });

    /**
     * "Nothing has happened" and "nothing matches" are different facts, and
     * confusing them sends someone looking for a bug that is not there.
     */
    it('distinguishes an empty log from an empty result', async () => {
        const empty = { data: [], links: [], total: 0 };

        expect(build({ entries: empty }).text()).toContain(
            'Nothing has happened yet',
        );

        const filtered = build({
            entries: empty,
            filters: {
                quote_id: 9,
                user_id: null,
                action: null,
                from: null,
                to: null,
            },
        });

        expect(filtered.text()).toContain('Nothing matches these filters');
    });

    it('counts what it is showing', () => {
        expect(build().text()).toContain('1 entry');
        expect(
            build({
                entries: { data: [entry()], links: [], total: 4 },
            }).text(),
        ).toContain('4 entries');
    });

    /**
     * The paginator writes its arrows as html entities, which would show up
     * literally if they were printed as text.
     */
    it('shows readable pagination labels', () => {
        const wrapper = build({
            entries: {
                data: [entry()],
                total: 60,
                links: [
                    { url: null, label: '&laquo; Previous', active: false },
                    { url: '/audit-log?page=1', label: '1', active: true },
                    { url: '/audit-log?page=2', label: '2', active: false },
                    {
                        url: '/audit-log?page=2',
                        label: 'Next &raquo;',
                        active: false,
                    },
                ],
            },
        });

        const pagination = wrapper.find('nav[aria-label="Pagination"]');

        expect(pagination.text()).toContain('Previous');
        expect(pagination.text()).toContain('Next');
        expect(pagination.text()).not.toContain('laquo');
    });
});
