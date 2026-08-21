import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import PortalDecision from '@/pages/portal/Decision.vue';
import PortalExpired from '@/pages/portal/Expired.vue';
import PortalQuote from '@/pages/portal/Quote.vue';
import PortalSender from '@/pages/portal/Sender.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';
import type { CalculatedQuote } from '@/types';

vi.mock('@inertiajs/vue3', async () => (await import('@/test-support/inertia')).inertiaStub());

const noSender = { company_name: null, logo_url: null };

/** formatMoney joins the symbol to the amount with a non-breaking space. */
const NBSP = ' ';

const quote = {
  id: 9,
  company_name: 'Acme BV',
  contact_person: 'Anna',
  valid_until: '2026-09-20',
  pdf_url: '/offerte/abc/pdf',
  approve_url: '/offerte/abc/akkoord',
  deny_url: '/offerte/abc/afwijzen',
  status: 'opened' as const,
  deny_reason: null,
  can_decide: true,
};

const version = {
  version_number: 1,
  intro_text_snapshot: '<p>Hierbij onze offerte</p>',
  footer_text_snapshot: '<p>Algemene voorwaarden van toepassing</p>',
  line_items: [
    {
      id: 4,
      name: 'Managed hosting',
      specs: { 'Billing period': 'Monthly' },
      quantity: '2.00',
      unit_price_ex_vat: '90.00',
      tax_class_percentage: '21.00',
    },
  ],
};

const totals: CalculatedQuote = {
  lines: [
    {
      lineItemId: 4,
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

function buildQuotePage(overrides: Record<string, unknown> = {}) {
  return mount(PortalQuote, {
    props: { sender: noSender, quote, version, totals, ...overrides },
  });
}

describe('portal/Quote', () => {
  it('names the company the quote is for', () => {
    expect(buildQuotePage().text()).toContain('Offerte 9 voor Acme BV');
  });

  /**
   * The intro is written in the quote editor and almost always opens with a
   * greeting of its own. Two on the one page a customer reads looks like a
   * mail merge that went wrong.
   */
  it('greets the contact only when the intro does not', () => {
    expect(
      buildQuotePage({
        version: { ...version, intro_text_snapshot: null },
      }).text(),
    ).toContain('Beste Anna');

    expect(buildQuotePage().text()).not.toContain('Beste Anna');
  });

  /**
   * Dates are read day-first here, like everywhere else in xoloff, and this
   * is the one page where getting it wrong reaches a customer.
   */
  it('says how long the quote stands, the Dutch way round', () => {
    expect(buildQuotePage().text()).toContain('20-09-2026');
  });

  it('lists the lines with the specs that belong to them', () => {
    const wrapper = buildQuotePage();

    expect(wrapper.text()).toContain('Managed hosting');
    expect(wrapper.text()).toContain('Billing period');
    expect(wrapper.text()).toContain('Monthly');
  });

  /**
   * Every figure comes from the engine. The page holds no second opinion
   * about the money, and it is the customer's money.
   */
  it('shows the engine figures, formatted the Dutch way', () => {
    const text = buildQuotePage().text();

    expect(text).toContain(`€${NBSP}180,00`);
    expect(text).toContain(`€${NBSP}37,80`);
    expect(text).toContain(`€${NBSP}217,80`);
  });

  /**
   * A quote may mix rates, and a single "btw" figure would hide which rate
   * applied to what.
   */
  it('breaks the VAT out per rate', () => {
    expect(buildQuotePage().text()).toContain('Btw 21,00% over');
  });

  /**
   * Matched by id rather than by position: an amount landing against the
   * wrong description is not a mistake a customer would spot.
   */
  it('shows a dash rather than a wrong amount when a line has no total', () => {
    const wrapper = buildQuotePage({
      totals: {
        ...totals,
        lines: [{ ...totals.lines[0], lineItemId: 99 }],
      },
    });

    expect(wrapper.find('tbody tr').text()).toContain('-');
    expect(wrapper.find('tbody tr').text()).not.toContain('180,00');
  });

  it('renders the saved texts as markup rather than printing the tags', () => {
    const wrapper = buildQuotePage();

    expect(wrapper.html()).toContain('<p>Hierbij onze offerte</p>');
    expect(wrapper.text()).not.toContain('<p>');
  });

  it('offers the quote as a file rather than as a page visit', () => {
    const link = buildQuotePage()
      .findAll('a')
      .find((anchor) => anchor.text().includes('PDF'));

    expect(link?.attributes('href')).toBe('/offerte/abc/pdf');
    expect(link?.attributes('data-method')).toBeUndefined();
  });

  /**
   * Only reachable if the quote's last version was removed after it was
   * sent. Better a cover page than an empty table at a customer.
   */
  it('stands as a cover page when there is no version to show', () => {
    const wrapper = buildQuotePage({ version: null, totals: null });

    expect(wrapper.text()).toContain('Offerte 9 voor Acme BV');
    expect(wrapper.find('table').exists()).toBe(false);
  });
});

describe('portal/Expired', () => {
  const props = {
    sender: noSender,
    quote: { id: 9, valid_until: '2026-01-01' },
  };

  /**
   * SPEC §7: never a harsh "not found". The link was real and the customer
   * did nothing wrong, so this must not read as their mistake.
   */
  it('says the window passed and offers a way forward', () => {
    const wrapper = mount(PortalExpired, { props });

    expect(wrapper.text()).toContain('niet meer geldig');
    expect(wrapper.text()).toContain('01-01-2026');
    expect(wrapper.text()).toContain('nieuwe');
    expect(wrapper.text()).not.toContain('niet gevonden');
  });
});

/**
 * The details are still being collected (SPEC §12), so every combination of
 * filled and blank has to look deliberate on a page a customer reads.
 */
describe('portal/Sender', () => {
  it('shows the logo when there is one', () => {
    const wrapper = mount(PortalSender, {
      props: {
        sender: {
          company_name: 'Xolution',
          logo_url: '/storage/logos/x.png',
        },
      },
    });

    expect(wrapper.find('img').attributes('src')).toBe('/storage/logos/x.png');
  });

  it('falls back to the name when there is no logo', () => {
    const wrapper = mount(PortalSender, {
      props: { sender: { company_name: 'Xolution', logo_url: null } },
    });

    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.text()).toContain('Xolution');
  });

  it('shows nothing rather than a placeholder when neither is set', () => {
    const wrapper = mount(PortalSender, { props: { sender: noSender } });

    expect(wrapper.find('img').exists()).toBe(false);
    expect(wrapper.text().trim()).toBe('');
  });
});

/**
 * Yes or no (SPEC §8), and what the page says once one of them has been
 * chosen.
 */
describe('portal/Decision', () => {
  beforeEach(() => {
    resetInertiaStub();
  });

  function buildDecision(overrides: Record<string, unknown> = {}) {
    return mount(PortalDecision, {
      props: {
        approveUrl: '/offerte/abc/akkoord',
        denyUrl: '/offerte/abc/afwijzen',
        status: 'opened' as const,
        denyReason: null,
        canDecide: true,
        ...overrides,
      },
    });
  }

  it('asks the question while there is still one to ask', () => {
    expect(buildDecision().text()).toContain('Gaat u akkoord');
  });

  it('sends an approval to the address it was given', async () => {
    const wrapper = buildDecision();

    await wrapper.find('form').trigger('submit');

    expect(submissions).toHaveLength(1);
    expect(submissions[0].url).toBe('/offerte/abc/akkoord');
    expect(submissions[0].method).toBe('post');
  });

  /**
   * Declining asks for the reason first, which doubles as the pause a
   * one-click refusal would not have.
   */
  it('asks why before it declines', async () => {
    const wrapper = buildDecision();

    expect(wrapper.find('textarea').exists()).toBe(false);

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Niet akkoord')!
      .trigger('click');

    expect(wrapper.find('textarea').exists()).toBe(true);
    expect(wrapper.text()).toContain('niet verplicht');
  });

  it('sends the reason under the name the server reads', async () => {
    const wrapper = buildDecision();

    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Niet akkoord')!
      .trigger('click');

    await wrapper.find('textarea').setValue('Te duur');
    await wrapper.find('form').trigger('submit');

    expect(submissions).toHaveLength(1);
    expect(submissions[0].url).toBe('/offerte/abc/afwijzen');
    expect(submissions[0].data).toEqual({ reason: 'Te duur' });
  });

  it('stops asking once the quote has been approved', () => {
    const wrapper = buildDecision({ status: 'approved', canDecide: false });

    expect(wrapper.text()).toContain('geaccepteerd');
    expect(wrapper.text()).not.toContain('Gaat u akkoord');
    expect(wrapper.find('form').exists()).toBe(false);
  });

  /**
   * Read back rather than only stored, so a note someone took the trouble to
   * write visibly landed somewhere.
   */
  it('repeats the reason back after a denial', () => {
    const wrapper = buildDecision({
      status: 'denied',
      canDecide: false,
      denyReason: 'Te duur voor dit kwartaal.',
    });

    expect(wrapper.text()).toContain('afgewezen');
    expect(wrapper.text()).toContain('Te duur voor dit kwartaal.');
  });

  it('shows nothing to decide when the reason is absent', () => {
    const wrapper = buildDecision({ status: 'denied', canDecide: false });

    expect(wrapper.find('blockquote').exists()).toBe(false);
  });

  /**
   * Reachable when the quote's last version was removed after it was sent.
   * There is nothing on the page to agree to.
   */
  it('asks nothing when there is nothing to decide on', () => {
    const wrapper = buildDecision({ canDecide: false });

    expect(wrapper.text()).not.toContain('Gaat u akkoord');
    expect(wrapper.find('form').exists()).toBe(false);
  });
});
