import { vi } from 'vitest';
import { defineComponent, h, reactive } from 'vue';
import type { PropType } from 'vue';

/**
 * Stand-ins for the parts of @inertiajs/vue3 the app actually uses.
 *
 * The real components expect an Inertia application context that only exists
 * once the page has been booted by app.ts, so mounting anything that renders a
 * Head or a Link fails outright without these. The stubs are kept deliberately
 * thin: they render the same shape the templates rely on and nothing more.
 */

export const submissions: {
    method: string;
    url: string;
    data: Record<string, unknown>;
}[] = [];

/**
 * Where router.get lands. Separate from submissions because a filtered
 * listing is a navigation rather than a form post, and a test asserting on
 * filters should not have to know that.
 */
export const visits: {
    url: string;
    data: Record<string, unknown>;
}[] = [];

export const pageProps: Record<string, unknown> = reactive({
    name: 'Xoloff',
    sidebarOpen: false,
    auth: { user: { id: 1, name: 'Test', email: 'test@example.test' } },
    flash: {},
    errors: {} as Record<string, string>,
});

/**
 * Renders nothing. Titles are not the subject of any test here, and the real
 * one needs a head provider.
 */
const Head = defineComponent({
    name: 'HeadStub',
    props: { title: { type: String, default: '' } },
    setup: () => () => null,
});

/**
 * The real Link intercepts clicks and drives the router. Rendering a plain
 * anchor keeps href assertions honest without any of that machinery.
 */
const Link = defineComponent({
    name: 'LinkStub',
    props: {
        href: {
            type: [String, Object] as PropType<string | { url: string }>,
            default: '',
        },
        method: { type: String, default: 'get' },
        as: { type: String, default: 'a' },
    },
    setup:
        (props, { slots }) =>
        () =>
            h(
                props.as,
                {
                    href:
                        typeof props.href === 'string'
                            ? props.href
                            : props.href?.url,
                    'data-method': props.method,
                },
                slots.default?.(),
            ),
});

/**
 * Renders a real form element and exposes the slot props the templates read.
 * Submitting records the attempt rather than issuing a request.
 */
const Form = defineComponent({
    name: 'FormStub',
    props: {
        action: {
            type: [String, Object] as PropType<
                string | Record<string, unknown>
            >,
            default: '',
        },
        method: { type: String, default: 'post' },
        transform: {
            type: Function as PropType<
                (data: Record<string, unknown>) => Record<string, unknown>
            >,
            default: undefined,
        },
    },
    setup(props, { slots }) {
        const state = reactive({
            errors: {},
            processing: false,
            wasSuccessful: false,
        });

        return () =>
            h(
                'form',
                {
                    onSubmit: (event: Event) => {
                        event.preventDefault();

                        // Collected from the DOM exactly as the real Form
                        // does, so a field that was never given a name shows
                        // up here as missing rather than passing silently.
                        // Hidden inputs count, which is how a page submits a
                        // value that has no input of its own.
                        const fields = Object.fromEntries(
                            new FormData(
                                event.target as HTMLFormElement,
                            ).entries(),
                        );

                        submissions.push({
                            method: props.method,
                            url:
                                typeof props.action === 'string'
                                    ? props.action
                                    : '',
                            data: props.transform
                                ? props.transform(fields)
                                : fields,
                        });
                    },
                },
                slots.default?.(state),
            );
    },
});

/**
 * Mirrors the parts of useForm the pages touch: fields are reachable directly
 * on the returned object, and every submit method records what it was given.
 */
function useForm<T extends Record<string, unknown>>(initial: T | (() => T)) {
    const data = typeof initial === 'function' ? initial() : initial;

    let transformer: ((data: T) => Record<string, unknown>) | null = null;

    const form = reactive({
        ...data,
        errors: {} as Record<string, string>,
        processing: false,
        hasErrors: false,
        wasSuccessful: false,
        recentlySuccessful: false,
        transform(callback: (data: T) => Record<string, unknown>) {
            transformer = callback;

            return form;
        },
        reset() {
            return form;
        },
        submit(method: string, url: string) {
            const current = Object.fromEntries(
                Object.keys(data).map((key) => [
                    key,
                    (form as Record<string, unknown>)[key],
                ]),
            ) as T;

            submissions.push({
                method,
                url,
                data: transformer ? transformer(current) : current,
            });

            return Promise.resolve();
        },
        get(url: string) {
            return form.submit('get', url);
        },
        post(url: string) {
            return form.submit('post', url);
        },
        put(url: string) {
            return form.submit('put', url);
        },
        patch(url: string) {
            return form.submit('patch', url);
        },
        delete(url: string) {
            return form.submit('delete', url);
        },
    });

    return form;
}

/**
 * The address the test is pretending to be at. Anything reading it does so
 * through a getter, so a test can move the page and have what it mounted
 * follow along.
 */
export const pageState = reactive({ url: '/' });

/**
 * Returns whatever the current test put in pageProps.
 */
function usePage() {
    return {
        props: pageProps,
        get url() {
            return pageState.url;
        },
        component: 'Test',
        version: null,
    };
}

const router = {
    visit: vi.fn(),
    get: vi.fn((url: string, data: Record<string, unknown> = {}) => {
        visits.push({ url, data });
    }),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reload: vi.fn(),
    cancelAll: vi.fn(),
    on: vi.fn(() => () => {}),
};

/**
 * What the next useHttp request should resolve with. Tests that care about
 * totals set this; the rest leave it undefined and the panel simply holds.
 */
export const httpResponse: { value: unknown } = { value: undefined };

/**
 * Enough of useHttp for a component to mount and fire requests. The precise
 * snapshot-versus-transform behaviour that caused a real bug is modelled in
 * useQuoteTotals.test.ts, which stubs this itself.
 */
function useHttp<T extends Record<string, unknown>>(initial: T) {
    let transformer: ((data: T) => unknown) | null = null;

    const request = {
        processing: false,
        transform(callback: (data: T) => unknown) {
            transformer = callback;

            return request;
        },
        post(url: string) {
            submissions.push({
                method: 'post',
                url,
                data: (transformer ? transformer(initial) : initial) as Record<
                    string,
                    unknown
                >,
            });

            return Promise.resolve(httpResponse.value);
        },
    };

    return request;
}

/**
 * The module shape handed to vi.mock.
 */
export function inertiaStub() {
    return { Head, Link, Form, useForm, useHttp, usePage, router };
}

export function resetInertiaStub() {
    submissions.length = 0;
    visits.length = 0;
    httpResponse.value = undefined;
    pageProps.errors = {};
    pageState.url = '/';
    router.visit.mockClear();
    router.get.mockClear();
    router.post.mockClear();
    router.delete.mockClear();
}
