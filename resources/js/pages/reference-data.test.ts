import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ConfirmDeleteButton from '@/components/ConfirmDeleteButton.vue';
import CategoryCreate from '@/pages/product-categories/Create.vue';
import CategoryEdit from '@/pages/product-categories/Edit.vue';
import CategoryIndex from '@/pages/product-categories/Index.vue';
import TaxClassCreate from '@/pages/tax-classes/Create.vue';
import TaxClassEdit from '@/pages/tax-classes/Edit.vue';
import TaxClassIndex from '@/pages/tax-classes/Index.vue';
import { pageProps, resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () => (await import('@/test-support/inertia')).inertiaStub());
vi.mock('@/components/ui/select', async () => (await import('@/test-support/ui')).selectStub());

beforeEach(() => {
  resetInertiaStub();
});

describe('product-categories', () => {
  it('lists categories with how many products use them', () => {
    const wrapper = mount(CategoryIndex, {
      props: {
        categories: [{ id: 5, name: 'Hosting', products_count: 4 }],
      },
    });

    expect(wrapper.text()).toContain('Hosting');
    expect(wrapper.find('tbody tr').text()).toContain('4');
  });

  it('says so when there are none', () => {
    expect(mount(CategoryIndex, { props: { categories: [] } }).text()).toContain(
      'No categories yet',
    );
  });

  it('promises that deleting a category keeps its products', () => {
    const wrapper = mount(CategoryIndex, {
      props: {
        categories: [{ id: 5, name: 'Hosting', products_count: 4 }],
      },
    });

    // The foreign key nulls category_id rather than cascading, and the
    // confirmation has to match that. It lives in a dialog that is closed
    // until the button is pressed, so the prop is the honest thing to read.
    expect(wrapper.findComponent(ConfirmDeleteButton).props('description')).toContain(
      'become uncategorised',
    );
  });

  it('creates through a single name field', () => {
    const wrapper = mount(CategoryCreate);

    expect(wrapper.find('input[name="name"]').exists()).toBe(true);
    expect(wrapper.text()).toContain('New category');
  });

  it('edits starting from the current name', () => {
    const wrapper = mount(CategoryEdit, {
      props: { category: { id: 5, name: 'Hosting' } },
    });

    const name = wrapper.find('input[name="name"]').element as HTMLInputElement;

    expect(name.value).toBe('Hosting');
  });
});

describe('tax-classes', () => {
  const taxClass = {
    id: 3,
    name: 'Standard 21%',
    percentage: '21.00',
    products_count: 2,
  };

  it('lists tax classes with their rate', () => {
    const wrapper = mount(TaxClassIndex, {
      props: { taxClasses: [taxClass] },
    });

    expect(wrapper.text()).toContain('Standard 21%');
    // Four decimals, because that is what the column stores and a rate
    // shown rounded is a rate somebody will type back in wrong.
    expect(wrapper.text()).toContain('21,0000%');
  });

  it('says so when there are none', () => {
    expect(mount(TaxClassIndex, { props: { taxClasses: [] } }).text()).toContain(
      'No tax classes yet',
    );
  });

  it('stays quiet when the last delete did not fail', () => {
    const wrapper = mount(TaxClassIndex, {
      props: { taxClasses: [taxClass] },
    });

    expect(wrapper.find('[class*="bg-destructive/10"]').exists()).toBe(false);
  });

  it('explains a refused delete rather than losing the message', () => {
    // The controller refuses to delete a tax class still in use and sends
    // the reason back as a validation error.
    pageProps.errors = {
      taxClass: 'This tax class is still used by one or more products.',
    };

    const wrapper = mount(TaxClassIndex, {
      props: { taxClasses: [taxClass] },
    });

    expect(wrapper.find('[class*="bg-destructive/10"]').text()).toContain(
      'still used by one or more products',
    );
  });

  it('creates through a name and a percentage', () => {
    const wrapper = mount(TaxClassCreate);

    expect(wrapper.find('input[name="name"]').exists()).toBe(true);
    expect(wrapper.find('input[name="percentage"]').exists()).toBe(true);
  });

  it('edits starting from the current rate', () => {
    const wrapper = mount(TaxClassEdit, { props: { taxClass } });

    const percentage = wrapper.find('input[name="percentage"]').element as HTMLInputElement;

    expect(percentage.value).toBe('21.00');
  });

  it('warns that editing a rate leaves saved quotes alone', () => {
    const wrapper = mount(TaxClassEdit, { props: { taxClass } });

    // Line items carry their own tax class, so this is a real guarantee
    // rather than reassurance.
    expect(wrapper.text()).toContain('does not alter quotes already saved');
  });
});
