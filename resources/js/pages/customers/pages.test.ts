import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CustomerCreate from '@/pages/customers/Create.vue';
import CustomerEdit from '@/pages/customers/Edit.vue';
import CustomerForm from '@/pages/customers/Form.vue';
import CustomerIndex from '@/pages/customers/Index.vue';
import { resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

const countries = { NL: 'Netherlands', BE: 'Belgium', US: 'United States' };

const customer = {
    id: 1,
    company_name: 'Acme BV',
    contact_person: 'Wile E. Coyote',
    email: 'wile@acme.test',
    billing_address: 'Desert Road 1',
    country: 'US',
};

beforeEach(() => {
    resetInertiaStub();
});

describe('customers/Index', () => {
    it('lists customers', () => {
        const wrapper = mount(CustomerIndex, {
            props: { customers: [customer] },
        });

        expect(wrapper.text()).toContain('Acme BV');
        expect(wrapper.text()).toContain('wile@acme.test');
        expect(wrapper.findAll('tbody tr')).toHaveLength(1);
    });

    it('says so when there are none', () => {
        expect(
            mount(CustomerIndex, { props: { customers: [] } }).text(),
        ).toContain('No customers yet');
    });
});

describe('customers/Form', () => {
    it('submits the fields the request expects', () => {
        const wrapper = mount(CustomerForm, {
            props: { action: {}, countries, submitLabel: 'Create customer' },
        });

        const names = wrapper
            .findAll('input, textarea')
            .map((field) => field.attributes('name'));

        // These names are the contract with CustomerRequest.
        expect(names).toContain('company_name');
        expect(names).toContain('contact_person');
        expect(names).toContain('email');
        expect(names).toContain('billing_address');
    });

    it('defaults a new customer to the Netherlands', () => {
        const wrapper = mount(CustomerForm, {
            props: { action: {}, countries, submitLabel: 'Create customer' },
        });

        // Most customers are Dutch, and VAT treatment is chosen by hand anyway
        // (SPEC §2), so the default is a convenience rather than a rule.
        expect(
            wrapper.findComponent({ name: 'SelectStub' }).props('defaultValue'),
        ).toBe('NL');
    });

    it('keeps the country an existing customer already has', () => {
        const wrapper = mount(CustomerForm, {
            props: {
                action: {},
                countries,
                customer,
                submitLabel: 'Save changes',
            },
        });

        expect(
            wrapper.findComponent({ name: 'SelectStub' }).props('defaultValue'),
        ).toBe('US');
    });

    it('starts from the customer it is editing', () => {
        const wrapper = mount(CustomerForm, {
            props: {
                action: {},
                countries,
                customer,
                submitLabel: 'Save changes',
            },
        });

        const company = wrapper.find('input[name="company_name"]')
            .element as HTMLInputElement;

        expect(company.value).toBe('Acme BV');
        const address = wrapper.find('textarea[name="billing_address"]')
            .element as HTMLTextAreaElement;

        expect(address.value).toBe('Desert Road 1');
    });
});

describe('customers/Create and Edit', () => {
    it('create offers a blank form', () => {
        const wrapper = mount(CustomerCreate, { props: { countries } });

        expect(wrapper.text()).toContain('New customer');
        expect(
            wrapper.findComponent(CustomerForm).props('customer'),
        ).toBeUndefined();
    });

    it('edit passes the customer through and names it', () => {
        const wrapper = mount(CustomerEdit, { props: { customer, countries } });

        expect(wrapper.text()).toContain('Acme BV');
        expect(
            wrapper.findComponent(CustomerForm).props('customer'),
        ).toMatchObject({ id: 1 });
    });
});
