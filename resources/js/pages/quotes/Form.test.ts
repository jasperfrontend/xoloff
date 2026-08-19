import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import QuoteForm from '@/pages/quotes/Form.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

const taxClasses = [
    { id: 3, name: 'Standard 21%', percentage: '21.00' },
    { id: 4, name: 'Zero rated', percentage: '0.00' },
];

const products = [
    {
        id: 7,
        name: 'Managed hosting',
        price_ex_vat: '90.00',
        tax_class_id: 4,
        specs: [{ key: 'Billing period', value: 'Monthly' }],
    },
];

function build(overrides: Record<string, unknown> = {}) {
    return mount(QuoteForm, {
        props: {
            customers: [{ id: 1, company_name: 'Acme BV' }],
            products,
            taxClasses,
            submitLabel: 'Create quote',
            submitUrl: '/quotes',
            submitMethod: 'post' as const,
            ...overrides,
        },
    });
}

/**
 * The selects are stubbed, so choosing a value means emitting it, exactly as
 * the real component does when an option is clicked.
 */
function chooseInSelect(wrapper: VueWrapper, index: number, value: string) {
    return wrapper
        .findAllComponents({ name: 'SelectStub' })
        [index].vm.$emit('update:modelValue', value);
}

async function addLine(wrapper: VueWrapper) {
    await wrapper
        .findAll('button')
        .find((button) => button.text().includes('Add line'))!
        .trigger('click');
}

describe('quotes/Form', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        resetInertiaStub();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('starts with no lines and says so', () => {
        expect(build().text()).toContain('No lines yet');
    });

    it('adds a line with workable defaults', async () => {
        const wrapper = build();

        await addLine(wrapper);

        expect(wrapper.text()).not.toContain('No lines yet');
        expect(
            (wrapper.find('input[type="number"]').element as HTMLInputElement)
                .value,
        ).toBe('1');
    });

    it('fills a line from the catalog without locking it', async () => {
        const wrapper = build();

        await addLine(wrapper);
        // Select order: customer, then the line's product picker.
        await chooseInSelect(wrapper, 1, '7');
        await wrapper.vm.$nextTick();

        const description = wrapper.find('input:not([type="number"])');

        expect((description.element as HTMLInputElement).value).toBe(
            'Managed hosting',
        );

        // The line takes the product's own tax class, not the first in the list.
        await description.setValue('Renamed by hand');
        await wrapper.find('form').trigger('submit');

        const lineItems = submissions[0].data.line_items as Record<
            string,
            unknown
        >[];

        expect(lineItems[0].name).toBe('Renamed by hand');
        expect(lineItems[0].product_id).toBe(7);
        expect(lineItems[0].tax_class_id).toBe(4);
        expect(lineItems[0].specs).toEqual({ 'Billing period': 'Monthly' });
    });

    it('removes the line it is asked to remove', async () => {
        const wrapper = build();

        await addLine(wrapper);
        await addLine(wrapper);
        await chooseInSelect(wrapper, 1, '7');
        await wrapper.vm.$nextTick();

        const remove = wrapper.findAll('button[aria-label="Remove line"]');

        expect(remove).toHaveLength(2);

        await remove[0].trigger('click');
        await wrapper.find('form').trigger('submit');

        const lineItems = submissions[0].data.line_items as Record<
            string,
            unknown
        >[];

        expect(lineItems).toHaveLength(1);
        expect(lineItems[0].product_id).toBeNull();
    });

    it('submits to the url and method it was given', async () => {
        const wrapper = build({
            submitUrl: '/quotes/9',
            submitMethod: 'put' as const,
        });

        await chooseInSelect(wrapper, 0, '1');
        await wrapper.find('form').trigger('submit');

        expect(submissions[0].method).toBe('put');
        expect(submissions[0].url).toBe('/quotes/9');
        expect(submissions[0].data.customer_id).toBe(1);
    });

    it('sends empty optional values as null rather than empty strings', async () => {
        const wrapper = build();

        await addLine(wrapper);
        await wrapper.find('form').trigger('submit');

        // The server treats these as "not set", and an empty string would fail
        // its numeric validation instead.
        expect(submissions[0].data.discount_value).toBeNull();
        expect(submissions[0].data.rounding_override).toBeNull();

        const lineItems = submissions[0].data.line_items as Record<
            string,
            unknown
        >[];

        expect(lineItems[0].discount_value).toBeNull();
    });

    it('offers saving as a new version only when there is a version to supersede', async () => {
        expect(build().text()).not.toContain('Save as new version');

        const wrapper = build({ newVersionUrl: '/quotes/9/versions' });

        expect(wrapper.text()).toContain('Save as new version');

        await wrapper
            .findAll('button')
            .find((button) => button.text() === 'Save as new version')!
            .trigger('click');

        expect(submissions[0].method).toBe('post');
        expect(submissions[0].url).toBe('/quotes/9/versions');
    });

    it('asks the server to reprice when the content changes', async () => {
        const wrapper = build();

        await addLine(wrapper);
        await vi.runAllTimersAsync();

        const previews = submissions.filter((submission) =>
            submission.url.includes('preview'),
        );

        expect(previews).toHaveLength(1);
        expect(previews[0].data.line_items as unknown[]).toHaveLength(1);
    });

    it('hides the discount amount until a discount type is chosen', async () => {
        const wrapper = build();

        expect(wrapper.find('#discount_value').exists()).toBe(false);

        // With no lines, the only selects are the customer and the quote-level
        // discount type.
        await chooseInSelect(wrapper, 1, 'percentage');
        await wrapper.vm.$nextTick();

        expect(wrapper.find('#discount_value').exists()).toBe(true);
    });

    it('forgets the discount amount when the discount is turned off again', async () => {
        const wrapper = build();

        await chooseInSelect(wrapper, 1, 'percentage');
        await wrapper.vm.$nextTick();
        await wrapper.find('#discount_value').setValue('10');

        await chooseInSelect(wrapper, 1, 'none');
        await wrapper.vm.$nextTick();

        await wrapper.find('form').trigger('submit');

        // Leaving the old amount behind would silently reapply it the next time
        // a type was chosen.
        expect(submissions[0].data.discount_type).toBeNull();
        expect(submissions[0].data.discount_value).toBeNull();
    });
});
