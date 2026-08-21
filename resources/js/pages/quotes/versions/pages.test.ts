import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import VersionIndex from '@/pages/quotes/versions/Index.vue';
import VersionShow from '@/pages/quotes/versions/Show.vue';
import { pageProps, resetInertiaStub } from '@/test-support/inertia';
import type { CalculatedQuote } from '@/types';

vi.mock('@inertiajs/vue3', async () => (await import('@/test-support/inertia')).inertiaStub());

const quote = { id: 9, customer_name: 'Acme BV' };

function version(overrides: Record<string, unknown> = {}) {
  return {
    id: 21,
    version_number: 2,
    is_current: true,
    saved_at: '2026-08-12T12:32:00Z',
    line_count: 1,
    total: '217.80',
    ...overrides,
  };
}

const totals: CalculatedQuote = {
  lines: [
    {
      lineItemId: 55,
      name: 'Managed hosting',
      quantity: '2.00',
      unitPriceExVat: '90.00',
      subtotal: '180.00',
      lineDiscount: '0.00',
      quoteDiscountShare: '0.00',
      net: '180.00',
      taxClassId: 3,
      taxClassName: 'Standard 21%',
      taxClassPercentage: '21.00',
    },
  ],
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
};

function showProps(overrides: Record<string, unknown> = {}) {
  return {
    quote,
    version: {
      id: 21,
      version_number: 1,
      is_current: false,
      saved_at: '2026-08-12T12:32:00Z',
      intro_text_snapshot: '<p>Beste klant</p>',
      footer_text_snapshot: '<p>Algemene voorwaarden</p>',
      line_items: [
        {
          id: 55,
          name: 'Managed hosting',
          specs: { 'Billing period': 'Monthly' },
          quantity: '2.00',
          unit_price_ex_vat: '90.00',
          tax_class_name: 'Standard 21%',
          discount_type: null,
          discount_value: null,
        },
      ],
      ...overrides,
    },
    totals,
  };
}

beforeEach(() => {
  resetInertiaStub();
});

describe('quotes/versions/Index', () => {
  it('names versions the way the spec does', () => {
    const wrapper = mount(VersionIndex, {
      props: { quote, versions: [version({ version_number: 3 })] },
    });

    expect(wrapper.find('tbody tr').text()).toContain('V3');
  });

  it('says which version is the live one', () => {
    const wrapper = mount(VersionIndex, {
      props: {
        quote,
        versions: [version(), version({ id: 20, version_number: 1, is_current: false })],
      },
    });

    const rows = wrapper.findAll('tbody tr');

    expect(rows[0].text()).toContain('Current');
    expect(rows[1].text()).not.toContain('Current');
  });

  /**
   * Deleting the current version would silently promote an older one, so the
   * option is not offered rather than offered and refused.
   */
  it('offers removal only for a version that has been superseded', () => {
    const wrapper = mount(VersionIndex, {
      props: {
        quote,
        versions: [version(), version({ id: 20, version_number: 1, is_current: false })],
      },
    });

    const rows = wrapper.findAll('tbody tr');

    expect(rows[0].findComponent(ConfirmDeleteButton).exists()).toBe(false);
    expect(rows[1].findComponent(ConfirmDeleteButton).exists()).toBe(true);
  });

  it('shows money and dates the dutch way', () => {
    const row = mount(VersionIndex, {
      props: { quote, versions: [version()] },
    })
      .find('tbody tr')
      .text();

    expect(row).toContain('€ 217,80');
    expect(row).toContain('12-08-2026 14:32');
  });

  it('says so when a quote has no versions', () => {
    expect(mount(VersionIndex, { props: { quote, versions: [] } }).text()).toContain(
      'no saved versions yet',
    );
  });

  /**
   * The server refuses to delete the current version. If that ever reaches
   * the page anyway, it has to be visible rather than a silent no-op.
   */
  it('shows why a deletion was refused', () => {
    pageProps.errors = { version: 'This is the current version.' };

    expect(
      mount(VersionIndex, {
        props: { quote, versions: [version()] },
      }).text(),
    ).toContain('This is the current version.');
  });
});

describe('quotes/versions/Show', () => {
  it('shows the lines with their specs', () => {
    const wrapper = mount(VersionShow, { props: showProps() });

    expect(wrapper.text()).toContain('Managed hosting');
    expect(wrapper.text()).toContain('Billing period');
    expect(wrapper.text()).toContain('Monthly');
  });

  it('renders the snapshotted texts as the markup they are', () => {
    const wrapper = mount(VersionShow, { props: showProps() });

    expect(wrapper.html()).toContain('<p>Beste klant</p>');
    expect(wrapper.html()).toContain('<p>Algemene voorwaarden</p>');
  });

  it('leaves out an intro that was never written', () => {
    const wrapper = mount(VersionShow, {
      props: showProps({ intro_text_snapshot: null }),
    });

    expect(wrapper.text()).not.toContain('Intro');
    expect(wrapper.text()).toContain('Footer');
  });

  /**
   * A net figure against the wrong description is not a mistake anyone would
   * catch by reading the page, so the line it belongs to is matched by id.
   */
  it('puts each net figure against its own line', () => {
    const props = showProps();

    props.version.line_items = [
      { ...props.version.line_items[0], id: 77, name: 'Second' },
      { ...props.version.line_items[0], id: 55, name: 'First' },
    ];
    props.totals = {
      ...totals,
      lines: [
        { ...totals.lines[0], lineItemId: 55, net: '180.00' },
        { ...totals.lines[0], lineItemId: 77, net: '40.00' },
      ],
    };

    const rows = mount(VersionShow, { props }).findAll('tbody tr');

    expect(rows[0].text()).toContain('Second');
    expect(rows[0].text()).toContain('€ 40,00');
    expect(rows[1].text()).toContain('First');
    expect(rows[1].text()).toContain('€ 180,00');
  });

  /**
   * Not offering an edit button is not the same as saying why, and the reason
   * is the whole point of keeping old versions.
   */
  it('says a superseded version cannot be edited', () => {
    expect(mount(VersionShow, { props: showProps() }).text()).toContain('cannot be edited');
  });

  it('sends you to the quote itself for the current version', () => {
    const wrapper = mount(VersionShow, {
      props: showProps({ is_current: true }),
    });

    expect(wrapper.text()).toContain('Edit it from the quote itself');
    expect(wrapper.text()).not.toContain('cannot be edited');
  });

  /**
   * A file, not a page, so it has to be a plain link: an Inertia visit would
   * try to swap the page for a PDF body.
   */
  it('offers this version as a download of its own', () => {
    const link = mount(VersionShow, { props: showProps() })
      .findAll('a')
      .find((anchor) => anchor.text() === 'Download PDF');

    expect(link).toBeDefined();
    expect(link!.attributes('href')).toBe('/quotes/9/versions/21/pdf');
  });

  it('shows why a download was refused', () => {
    pageProps.errors = { pdf: 'The PDF service did not respond.' };

    expect(mount(VersionShow, { props: showProps() }).text()).toContain(
      'The PDF service did not respond.',
    );
  });

  it('says so when a version has no lines', () => {
    expect(mount(VersionShow, { props: showProps({ line_items: [] }) }).text()).toContain(
      'no lines',
    );
  });
});
