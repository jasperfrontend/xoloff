import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import PortalExpired from '@/pages/portal/Expired.vue';
import PortalQuote from '@/pages/portal/Quote.vue';
import PortalSender from '@/pages/portal/Sender.vue';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);

const noSender = { company_name: null, logo_url: null };

describe('portal/Quote', () => {
    const quote = {
        id: 9,
        company_name: 'Acme BV',
        contact_person: 'Anna',
        valid_until: '2026-09-20',
    };

    it('greets the contact and names the company the quote is for', () => {
        const wrapper = mount(PortalQuote, {
            props: { sender: noSender, quote },
        });

        expect(wrapper.text()).toContain('Beste Anna');
        expect(wrapper.text()).toContain('Offerte 9 voor Acme BV');
    });

    /**
     * Dates are read day-first here, like everywhere else in xoloff, and this
     * is the one page where getting it wrong reaches a customer.
     */
    it('says how long the quote stands, the Dutch way round', () => {
        const wrapper = mount(PortalQuote, {
            props: { sender: noSender, quote },
        });

        expect(wrapper.text()).toContain('20-09-2026');
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

        expect(wrapper.find('img').attributes('src')).toBe(
            '/storage/logos/x.png',
        );
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
