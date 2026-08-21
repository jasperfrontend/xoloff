import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import CustomerCreate from '@/pages/customers/Create.vue';
import CustomerEdit from '@/pages/customers/Edit.vue';
import CustomerForm from '@/pages/customers/Form.vue';
import CustomerIndex from '@/pages/customers/Index.vue';
import { resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () => (await import('@/test-support/inertia')).inertiaStub());
vi.mock('@/components/ui/select', async () => (await import('@/test-support/ui')).selectStub());

const countries = { NL: 'Netherlands', BE: 'Belgium', US: 'United States' };
const salutations = { heer: 'Mr (heer)', mevrouw: 'Ms (mevrouw)' };

const customer = {
  id: 1,
  company_name: 'Acme BV',
  salutation: 'heer',
  first_name: 'Wile',
  last_name: 'Coyote',
  contact_person: 'Wile Coyote',
  email: 'wile@acme.test',
  billing_address: 'Desert Road 1',
  country: 'US',
};

/**
 * Two selects on this form now. Picking the first would silently test the
 * wrong one the next time another is added above it.
 */
function selectNamed(wrapper: ReturnType<typeof mount>, name: string) {
  return wrapper
    .findAllComponents({ name: 'SelectStub' })
    .find((select) => select.props('name') === name);
}

function buildForm(props: Record<string, unknown> = {}) {
  return mount(CustomerForm, {
    props: {
      action: {},
      countries,
      salutations,
      submitLabel: 'Create customer',
      ...props,
    },
  });
}

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
    expect(mount(CustomerIndex, { props: { customers: [] } }).text()).toContain('No customers yet');
  });
});

describe('customers/Form', () => {
  it('submits the fields the request expects', () => {
    const wrapper = buildForm();

    const names = wrapper.findAll('input, textarea').map((field) => field.attributes('name'));

    // These names are the contract with CustomerRequest.
    expect(names).toContain('company_name');
    expect(names).toContain('first_name');
    expect(names).toContain('last_name');
    expect(names).toContain('email');
    expect(names).toContain('billing_address');
    expect(selectNamed(wrapper, 'salutation')).toBeDefined();
    expect(selectNamed(wrapper, 'country')).toBeDefined();
  });

  /**
   * Quote texts greet people by name, so both halves are needed. How they
   * are addressed formally is optional.
   */
  it('demands both halves of the name but not the salutation', () => {
    const wrapper = buildForm();

    expect(wrapper.find('input[name="first_name"]').attributes('required')).toBeDefined();
    expect(wrapper.find('input[name="last_name"]').attributes('required')).toBeDefined();
  });

  /**
   * A select cannot hold an empty value, so "no salutation" needs one of its
   * own that is translated back to nothing on the way out.
   */
  it('offers no salutation as a choice and sends it as null', () => {
    const wrapper = buildForm();

    expect(selectNamed(wrapper, 'salutation')?.props('defaultValue')).toBe('none');
    expect(wrapper.text()).toContain('None');

    const transform = wrapper.findComponent({ name: 'FormStub' }).props('transform') as (
      data: Record<string, unknown>,
    ) => Record<string, unknown>;

    expect(transform({ salutation: 'none' }).salutation).toBeNull();
    expect(transform({ salutation: 'heer' }).salutation).toBe('heer');
  });

  it('keeps the salutation an existing customer already has', () => {
    expect(selectNamed(buildForm({ customer }), 'salutation')?.props('defaultValue')).toBe('heer');
  });

  it('defaults a new customer to the Netherlands', () => {
    // Most customers are Dutch, and VAT treatment is chosen by hand anyway
    // (SPEC 2), so the default is a convenience rather than a rule.
    expect(selectNamed(buildForm(), 'country')?.props('defaultValue')).toBe('NL');
  });

  it('keeps the country an existing customer already has', () => {
    expect(selectNamed(buildForm({ customer }), 'country')?.props('defaultValue')).toBe('US');
  });

  it('starts from the customer it is editing', () => {
    const wrapper = buildForm({ customer, submitLabel: 'Save changes' });

    const valueOf = (selector: string) =>
      (wrapper.find(selector).element as HTMLInputElement).value;

    expect(valueOf('input[name="company_name"]')).toBe('Acme BV');
    expect(valueOf('input[name="first_name"]')).toBe('Wile');
    expect(valueOf('input[name="last_name"]')).toBe('Coyote');
    expect(valueOf('textarea[name="billing_address"]')).toBe('Desert Road 1');
  });
});

describe('customers/Create and Edit', () => {
  it('create offers a blank form', () => {
    const wrapper = mount(CustomerCreate, {
      props: { countries, salutations },
    });

    expect(wrapper.text()).toContain('New customer');
    expect(wrapper.findComponent(CustomerForm).props('customer')).toBeUndefined();
  });

  it('edit passes the customer through and names it', () => {
    const wrapper = mount(CustomerEdit, {
      props: { customer, countries, salutations },
    });

    expect(wrapper.text()).toContain('Acme BV');
    expect(wrapper.findComponent(CustomerForm).props('customer')).toMatchObject({ id: 1 });
  });
});
