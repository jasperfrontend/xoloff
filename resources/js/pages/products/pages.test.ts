import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProductCreate from '@/pages/products/Create.vue';
import ProductEdit from '@/pages/products/Edit.vue';
import ProductForm from '@/pages/products/Form.vue';
import ProductIndex from '@/pages/products/Index.vue';
import { resetInertiaStub } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

const taxClasses = [{ id: 3, name: 'Standard 21%', percentage: '21.00' }];
const categories = [{ id: 5, name: 'Hosting' }];

const product = {
    id: 7,
    name: 'Managed hosting',
    price_ex_vat: '90.00',
    tax_class_id: 3,
    category_id: 5,
    specs: [{ key: 'Billing period', value: 'Monthly' }],
};

function names(wrapper: VueWrapper) {
    return wrapper.findAll('input').map((input) => input.attributes('name'));
}

beforeEach(() => {
    resetInertiaStub();
});

describe('products/Index', () => {
    it('lists products with their price', () => {
        const wrapper = mount(ProductIndex, {
            props: {
                products: [
                    {
                        id: 7,
                        name: 'Managed hosting',
                        price_ex_vat: '90.00',
                        specs_count: 1,
                        tax_class: {
                            id: 3,
                            name: 'Standard 21%',
                            percentage: '21.00',
                        },
                        category: { id: 5, name: 'Hosting' },
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Managed hosting');
        expect(wrapper.text()).toContain('€ 90.00');
        expect(wrapper.text()).toContain('Standard 21%');
    });

    it('says so when there are none', () => {
        expect(
            mount(ProductIndex, { props: { products: [] } }).text(),
        ).toContain('No products yet');
    });

    it('shows a dash for a product with no category', () => {
        const wrapper = mount(ProductIndex, {
            props: {
                products: [
                    {
                        id: 7,
                        name: 'Loose product',
                        price_ex_vat: '10.00',
                        specs_count: 0,
                        tax_class: {
                            id: 3,
                            name: 'Standard 21%',
                            percentage: '21.00',
                        },
                        category: null,
                    },
                ],
            },
        });

        expect(wrapper.find('tbody tr').text()).toContain('-');
    });
});

describe('products/Form', () => {
    it('submits the fields the request expects', () => {
        const wrapper = mount(ProductForm, {
            props: {
                action: {},
                taxClasses,
                categories,
                submitLabel: 'Create product',
            },
        });

        // These names are the contract with ProductRequest.
        expect(names(wrapper)).toContain('name');
        expect(names(wrapper)).toContain('price_ex_vat');
    });

    it('starts from the product it is editing', () => {
        const wrapper = mount(ProductForm, {
            props: {
                action: {},
                taxClasses,
                categories,
                product,
                submitLabel: 'Save changes',
            },
        });

        const name = wrapper.find('input[name="name"]')
            .element as HTMLInputElement;
        const price = wrapper.find('input[name="price_ex_vat"]')
            .element as HTMLInputElement;

        expect(name.value).toBe('Managed hosting');
        expect(price.value).toBe('90.00');
    });

    it('lists the specs it was given', () => {
        const wrapper = mount(ProductForm, {
            props: {
                action: {},
                taxClasses,
                categories,
                product,
                submitLabel: 'Save changes',
            },
        });

        expect(names(wrapper)).toContain('specs[0][key]');
        expect(names(wrapper)).toContain('specs[0][value]');
    });

    it('says so when a product has no specs', () => {
        const wrapper = mount(ProductForm, {
            props: {
                action: {},
                taxClasses,
                categories,
                submitLabel: 'Create product',
            },
        });

        expect(wrapper.text()).toContain('No specifications yet');
    });

    it('adds and removes spec rows, keeping the indexes contiguous', async () => {
        const wrapper = mount(ProductForm, {
            props: {
                action: {},
                taxClasses,
                categories,
                submitLabel: 'Create product',
            },
        });

        const add = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Add'))!;

        await add.trigger('click');
        await add.trigger('click');

        expect(names(wrapper)).toContain('specs[1][key]');

        await wrapper
            .findAll('button[aria-label="Remove specification"]')[0]
            .trigger('click');

        // A gap in the indexes would make the server drop a spec silently.
        expect(names(wrapper)).toContain('specs[0][key]');
        expect(names(wrapper)).not.toContain('specs[1][key]');
    });
});

describe('products/Create and Edit', () => {
    it('create offers a blank form', () => {
        const wrapper = mount(ProductCreate, {
            props: { taxClasses, categories },
        });

        expect(wrapper.text()).toContain('New product');
        expect(
            wrapper.findComponent(ProductForm).props('product'),
        ).toBeUndefined();
    });

    it('edit passes the product through and names it', () => {
        const wrapper = mount(ProductEdit, {
            props: { product, taxClasses, categories },
        });

        expect(wrapper.text()).toContain('Managed hosting');
        expect(
            wrapper.findComponent(ProductForm).props('product'),
        ).toMatchObject({ id: 7 });
        expect(wrapper.findComponent(ProductForm).props('submitLabel')).toBe(
            'Save changes',
        );
    });
});
