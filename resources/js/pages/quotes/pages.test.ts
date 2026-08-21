import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import QuoteCreate from '@/pages/quotes/Create.vue';
import QuoteEdit from '@/pages/quotes/Edit.vue';
import QuoteForm from '@/pages/quotes/Form.vue';
import QuoteIndex from '@/pages/quotes/Index.vue';
import SendQuoteDialog from '@/pages/quotes/SendQuoteDialog.vue';
import QuoteTotals from '@/pages/quotes/Totals.vue';
import { pageProps, resetInertiaStub } from '@/test-support/inertia';
import type { CalculatedQuote } from '@/types';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

/** formatMoney joins the symbol to the amount with a non-breaking space. */
const NBSP = ' ';

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
                        status: 'draft' as const,
                        status_label: 'Draft',
                        version_number: 2,
                        line_count: 3,
                        total: '217.80',
                    },
                    {
                        id: 2,
                        customer_name: 'Globex',
                        status: 'draft' as const,
                        status_label: 'Draft',
                        version_number: 1,
                        line_count: 1,
                        total: '12.10',
                    },
                ],
            },
        });

        expect(wrapper.findAll('tbody tr')).toHaveLength(2);
        expect(wrapper.text()).toContain('Acme BV');
        expect(wrapper.text()).toContain(`€${NBSP}217,80`);
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
                        status: 'draft' as const,
                        status_label: 'Draft',
                        version_number: null,
                        line_count: 0,
                        total: '0.00',
                    },
                ],
            },
        });

        expect(wrapper.find('tbody tr').text()).toContain('-');
    });

    /**
     * Where a quote stands, at a glance (SPEC §3). The wording comes from the
     * server so it cannot drift from the quote's own screen.
     */
    it('shows each quote where it stands', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 1,
                        customer_name: 'Acme BV',
                        status: 'sent' as const,
                        status_label: 'Sent',
                        version_number: 1,
                        line_count: 1,
                        total: '10.00',
                    },
                    {
                        id: 2,
                        customer_name: 'Globex',
                        status: 'denied' as const,
                        status_label: 'Denied',
                        version_number: 1,
                        line_count: 1,
                        total: '10.00',
                    },
                ],
            },
        });

        const rows = wrapper.findAll('tbody tr');

        expect(rows[0].text()).toContain('Sent');
        expect(rows[1].text()).toContain('Denied');
    });

    /**
     * Only a denied quote is coloured as a problem. A draft is the ordinary
     * resting state, so it must not read as one.
     */
    it('colours a denied quote as a problem and a draft as chrome', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 1,
                        customer_name: 'Acme BV',
                        status: 'draft' as const,
                        status_label: 'Draft',
                        version_number: 1,
                        line_count: 1,
                        total: '10.00',
                    },
                    {
                        id: 2,
                        customer_name: 'Globex',
                        status: 'denied' as const,
                        status_label: 'Denied',
                        version_number: 1,
                        line_count: 1,
                        total: '10.00',
                    },
                ],
            },
        });

        const badges = wrapper.findAll('[data-slot="badge"]');

        expect(badges[0].classes()).not.toContain('bg-destructive');
        expect(badges[1].classes()).toContain('bg-destructive');
    });

    it('links each quote to its editor', () => {
        const wrapper = mount(QuoteIndex, {
            props: {
                quotes: [
                    {
                        id: 42,
                        customer_name: 'Acme BV',
                        status: 'draft' as const,
                        status_label: 'Draft',
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

        expect(wrapper.text()).toContain('VAT 21,00%');
        expect(wrapper.text()).toContain(`€${NBSP}37,80`);
        expect(wrapper.text()).toContain(`€${NBSP}217,80`);
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
        expect(wrapper.text()).toContain(`- €${NBSP}18,00`);
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
        expect(wrapper.text()).toContain('217,80');
    });

    it('warns when the figures no longer describe the form', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: totals(), calculating: false, stale: true },
        });

        // Holding the previous figures is deliberate, but doing it silently
        // made an incomplete discount look like an applied one.
        expect(wrapper.text()).toContain('out of date');
    });

    it('says nothing about staleness while the figures are current', () => {
        const wrapper = mount(QuoteTotals, {
            props: { totals: totals(), calculating: false },
        });

        expect(wrapper.text()).not.toContain('out of date');
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
        customer_email: 'anna@acme.test',
        status: 'draft' as const,
        status_label: 'Draft',
        deny_reason: null,
        sent_at: null,
        valid_until: null,
        validity_days: 30,
        follows_the_default: true,
        magic_link: null,
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

    it('shows where the quote stands', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: { ...quote, status: 'opened', status_label: 'Opened' },
                totals: totals(),
                ...options,
            },
        });

        expect(wrapper.find('[data-slot="badge"]').text()).toBe('Opened');
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

    /**
     * SPEC §3: the deny reason is shown when the status is denied. Stephan
     * needs it as much as the customer does - it is the only status that
     * arrives with something to read.
     */
    it('shows why the customer declined', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: {
                    ...quote,
                    status: 'denied',
                    status_label: 'Denied',
                    deny_reason: 'Te duur voor dit kwartaal.',
                },
                totals: totals(),
                ...options,
            },
        });

        expect(wrapper.text()).toContain('declined this quote');
        expect(wrapper.text()).toContain('Te duur voor dit kwartaal.');
    });

    /**
     * Declining without explaining is allowed, so "no reason given" is itself
     * the thing worth saying rather than an empty panel.
     */
    it('says so when they declined without explaining', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: { ...quote, status: 'denied', status_label: 'Denied' },
                totals: totals(),
                ...options,
            },
        });

        expect(wrapper.text()).toContain('gave no reason');
    });

    it('says nothing about refusals on a quote nobody refused', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(wrapper.text()).not.toContain('declined this quote');
    });

    /**
     * Sending is what M4 adds to this screen (SPEC §7). The dialog is handed
     * the window the quote would actually go out with, resolved on the server,
     * so it never has to know the default-versus-override rule itself.
     */
    it('offers to send the quote with the window it would go out with', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: {
                    ...quote,
                    validity_days: 45,
                    follows_the_default: false,
                },
                totals: totals(),
                ...options,
            },
        });

        const dialog = wrapper.findComponent(SendQuoteDialog);

        expect(dialog.props('validityDays')).toBe(45);
        expect(dialog.props('followsTheDefault')).toBe(false);
        expect(dialog.props('customerEmail')).toBe('anna@acme.test');
        expect(dialog.props('alreadySent')).toBe(false);
    });

    /**
     * The link is on this screen as well as in the customer's inbox, because
     * an address that bounced is exactly when someone needs to pass it on by
     * hand.
     */
    it('shows the link and the window once the quote has been sent', () => {
        const wrapper = mount(QuoteEdit, {
            props: {
                quote: {
                    ...quote,
                    status: 'sent',
                    status_label: 'Sent',
                    sent_at: '2026-08-21T09:00:00+00:00',
                    valid_until: '2026-09-20',
                    magic_link: 'https://xoloff.test/offerte/abc',
                },
                totals: totals(),
                ...options,
            },
        });

        const link = wrapper
            .findAll('a')
            .find((anchor) => anchor.text().includes('/offerte/abc'));

        expect(link?.attributes('href')).toBe(
            'https://xoloff.test/offerte/abc',
        );
        expect(wrapper.text()).toContain('anna@acme.test');
        expect(wrapper.text()).toContain('20-09-2026');
        expect(
            wrapper.findComponent(SendQuoteDialog).props('alreadySent'),
        ).toBe(true);
    });

    it('shows no link while the quote has never been sent', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(wrapper.text()).not.toContain('Sent to');
    });

    it('shows a refused send rather than swallowing it', () => {
        pageProps.errors = {
            send: 'This quote has no saved version to send yet.',
        };

        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(wrapper.text()).toContain('no saved version to send');
    });

    /**
     * A file, not a page, so it has to be a plain link: an Inertia visit would
     * try to swap the page for a PDF body.
     */
    it('offers the quote as a download', () => {
        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        const link = wrapper
            .findAll('a')
            .find((anchor) => anchor.text() === 'Download PDF');

        expect(link).toBeDefined();
        expect(link!.attributes('href')).toBe('/quotes/9/pdf');
    });

    it('offers the history only once there is history to see', () => {
        const withHistory = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });
        const withoutHistory = mount(QuoteEdit, {
            props: {
                quote: { ...quote, version_number: 1, version_count: 1 },
                totals: totals(),
                ...options,
            },
        });

        expect(withHistory.text()).toContain('Version history');
        expect(withoutHistory.text()).not.toContain('Version history');
        // The download stands on its own: a quote with one version is still
        // a quote you can send.
        expect(withoutHistory.text()).toContain('Download PDF');
    });

    it('shows why a download was refused', () => {
        pageProps.errors = { pdf: 'The PDF service did not respond.' };

        const wrapper = mount(QuoteEdit, {
            props: { quote, totals: totals(), ...options },
        });

        expect(wrapper.text()).toContain('The PDF service did not respond.');
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
