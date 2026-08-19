import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import QuoteCreate from '@/pages/quotes/Create.vue';
import QuoteEdit from '@/pages/quotes/Edit.vue';
import QuoteForm from '@/pages/quotes/Form.vue';
import QuoteIndex from '@/pages/quotes/Index.vue';
import QuoteTotals from '@/pages/quotes/Totals.vue';
import { resetInertiaStub } from '@/test-support/inertia';
import type { CalculatedQuote } from '@/types';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

const options = {
    customers: [{ id: 1, company_name: 'Acme BV' }],
    products: [
        {
            id: 7,
            name: 'Hosting',
            price_ex_vat: '90.00',
            tax_class_id: 3,
            specs: [],
        },
    ],
    taxClasses: [{ id: 3, name: 'Standard 21%', percentage: '21.00' }],
};

function totals(overrides: Partial<CalculatedQuote> = {}): CalculatedQuote {
    return {
        lines: [],
        taxClassTotals: [
            {
                taxClassId: 3,
                name: 'Standard 21%',
                percentage: '21.00',
                net: '180.00',
                vat: '37.80',
            },
        ],
        subtotalBeforeQuoteDiscount: '180.00',
        quoteDiscount: '0.00',
        subtotal: '180.00',
        vatTotal: '37.80',
        calculatedTotal: '217.80',
        roundingOverride: null,
        total: '217.80',
        ...overrides,
    };
}

beforeEach(() => {
    resetInertiaStub();
});

describe('quotes/Index', () => {
    it('lists quotes with their totals', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 1,
                        customer_name: 'Acme BV',
                        version_number: 2,
                        line_count: 3,
                        total: '217.80',
                    },
                    {
                        id: 2,
                        customer_name: 'Globex',
                        version_number: 1,
                        line_count: 1,
                        total: '12.10',
                    },
                ],
            },
        });

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('Acme BV');
        expect(wrapper.text()).toContain('€ 217.80');
    });

    it('says so when there are none', () => {
        const wrapper = mount(QuoteIndex, { props: { quotes: [] } });

        expect(wrapper.text()).toContain('No quotes yet');
    });

    it('shows a dash for a quote that has no version yet', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 1,
                        customer_name: 'Acme BV',
                        version_number: null,
                        line_count: 0,
                        total: '0.00',
                    },
                ],
            },
        });

        expect(wrapper.find('tbody tr').text()).toContain('-');
    });

    it('links each quote to its editor', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 42,
                        customer_name: 'Acme BV',
                        version_number: 1,
                        line_count: 1,
                        total: '10.00',
                    },
                ],
            },
        });

        expect(wrapper.find('tbody a').attributes('href')).toContain('42');
    });
});

describe('quotes/Totals', () => {
    it('shows a placeholder until the first calculation arrives', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: null, calculating: false },
        });

        expect(wrapper.find('.animate-pulse').exists()).toBe(true);
    });

    it('reports the tax owed for each class present', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: totals(), calculating: false },
        });

        expect(wrapper.text()).toContain('VAT 21.00%');
        expect(wrapper.text()).toContain('€ 37.80');
        expect(wrapper.text()).toContain('€ 217.80');
    });

    it('hides the discount rows when nothing was discounted', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: totals(), calculating: false },
        });

        expect(wrapper.text()).not.toContain('Quote discount');
    });

    it('shows the discount rows once something was discounted', () => {
        const wrapper = mount(QuoteTotals, {
            props: {
                totals: totals({ quoteDiscount: '18.00', subtotal: '162.00' }),
                calculating: false,
            },
        });

        expect(wrapper.text()).toContain('Quote discount');
        expect(wrapper.text()).toContain('- € 18.00');
    });

    it('says plainly when the total was overridden', () => {
        const wrapper = mount(QuoteTotals, {
            props: {
                totals: totals({ roundingOverride: '200.00', total: '200.00' }),
                calculating: false,
            },
        });

        // The calculated figure is reported but is explicitly not the total,
        // which is what SPEC §5 asks for.
        expect(wrapper.text()).toContain('Overridden');
        expect(wrapper.text()).toContain('217.80');
    });

    it('indicates when a calculation is in flight', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: totals(), calculating: true },
        });

        expect(wrapper.text()).toContain('Calculating');
    });
});

describe('quotes/Create', () => {
    it('offers a blank builder that posts to the store route', () => {
        const wrapper = mount(QuoteCreate, { props: options });

        const form = wrapper.findComponent(QuoteForm);

        expect(wrapper.text()).toContain('New quote');
        expect(form.props('submitMethod')).toBe('post');
        expect(form.props('newVersionUrl')).toBeUndefined();
    });
});

describe('quotes/Edit', () => {
    const quote = {
        id: 9,
        customer_id: 1,
        version_number: 2,
        version_count: 2,
        discount_type: null,
        discount_value: null,
        rounding_override: null,
        line_items: [],
    };

    it('hands the builder the saved version and its totals', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        const form = wrapper.findComponent(QuoteForm);

        expect(form.props('submitMethod')).toBe('put');
        expect(form.props('quote')).toMatchObject({ id: 9 });
        expect(form.props('initialTotals')).toMatchObject({ total: '217.80' });
    });

    it('allows saving as a new version', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(
            wrapper.findComponent(QuoteForm).props('newVersionUrl'),
        ).toContain('/versions');
    });

    it('says which version is being edited once there is more than one', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(wrapper.text()).toContain('version 2 of 2');
    });

    it('warns that saving overwrites while there is only one version', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: { ...quote, version_number: 1, version_count: 1 },
                totals: totals(),
                ...options,
            },
        });

        expect(wrapper.text()).toContain('overwrites this version');
    });
});
