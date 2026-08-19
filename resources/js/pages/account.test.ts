import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ForgotPassword from '@/pages/auth/ForgotPassword.vue';
import Login from '@/pages/auth/Login.vue';
import ResetPassword from '@/pages/auth/ResetPassword.vue';
import Dashboard from '@/pages/Dashboard.vue';
import Appearance from '@/pages/settings/Appearance.vue';
import Profile from '@/pages/settings/Profile.vue';
import Security from '@/pages/settings/Security.vue';
import {
    pageProps,
    resetInertiaStub,
    submissions,
} from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () =>
    (await import('@/test-support/inertia')).inertiaStub(),
);
vi.mock('@/components/ui/select', async () =>
    (await import('@/test-support/ui')).selectStub(),
);

function fieldNames(html: string) {
    return [...html.matchAll(/name="([a-z_]+)"/g)].map((match) => match[1]);
}

beforeEach(() => {
    resetInertiaStub();
});

describe('auth/Login', () => {
    it('asks for the two things Fortify expects', () => {
        const wrapper = mount(Login, { props: { canResetPassword: true } });

        expect(fieldNames(wrapper.html())).toContain('email');
        expect(fieldNames(wrapper.html())).toContain('password');
    });

    it('offers the password reset link when resets are available', () => {
        const wrapper = mount(Login, { props: { canResetPassword: true } });

        expect(wrapper.text()).toContain('Forgot your password');
    });

    it('hides the reset link when resets are not available', () => {
        const wrapper = mount(Login, { props: { canResetPassword: false } });

        expect(wrapper.text()).not.toContain('Forgot your password');
    });

    it('shows a status message when one is passed', () => {
        const wrapper = mount(Login, {
            props: {
                canResetPassword: true,
                status: 'Your password has been reset.',
            },
        });

        expect(wrapper.text()).toContain('Your password has been reset.');
    });

    it('does not offer a way to register', () => {
        // Xoloff is a closed two-user system with no public registration
        // (SPEC §2), and the login screen must not imply otherwise.
        const wrapper = mount(Login, { props: { canResetPassword: true } });

        expect(wrapper.text().toLowerCase()).not.toContain('sign up');
        expect(wrapper.text().toLowerCase()).not.toContain('create an account');
    });
});

describe('auth/ForgotPassword', () => {
    it('asks only for an email address', () => {
        const wrapper = mount(ForgotPassword, { props: {} });

        expect(fieldNames(wrapper.html())).toContain('email');
        expect(fieldNames(wrapper.html())).not.toContain('password');
    });

    it('shows a status message when one is passed', () => {
        const wrapper = mount(ForgotPassword, {
            props: { status: 'Reset link sent.' },
        });

        expect(wrapper.text()).toContain('Reset link sent.');
    });
});

describe('auth/ResetPassword', () => {
    it('asks for a new password twice', () => {
        const wrapper = mount(ResetPassword, {
            props: {
                token: 'reset-token',
                email: 'jasper@example.test',
                passwordRules: 'minlength: 8;',
            },
        });

        const names = fieldNames(wrapper.html());

        expect(names).toContain('email');
        expect(names).toContain('password');
        expect(names).toContain('password_confirmation');
    });

    it('carries the token back to the server, which no input holds', async () => {
        const wrapper = mount(ResetPassword, {
            props: {
                token: 'reset-token',
                email: 'jasper@example.test',
                passwordRules: 'minlength: 8;',
            },
        });

        await wrapper.find('form').trigger('submit');

        // The token travels on the form's transform rather than in a field.
        // Losing it would leave the reset unable to prove which emailed link
        // it came from.
        expect(submissions[0].data).toMatchObject({
            token: 'reset-token',
            email: 'jasper@example.test',
        });
    });
});

describe('settings/Profile', () => {
    it('starts from the signed-in user', () => {
        pageProps.auth = {
            user: { id: 1, name: 'Jasper', email: 'jasper@example.test' },
        };

        const wrapper = mount(Profile, { props: {} });

        const name = wrapper.find('input[name="name"]')
            .element as HTMLInputElement;

        expect(name.value).toBe('Jasper');
    });

    it('does not offer account deletion', () => {
        // Self-deletion was removed outright: with two permanent users and no
        // registration route it is a one-way door.
        const wrapper = mount(Profile, { props: {} });

        expect(wrapper.text().toLowerCase()).not.toContain('delete account');
    });
});

describe('settings/Security', () => {
    it('requires the current password before setting a new one', () => {
        const wrapper = mount(Security, {
            props: { passwordRules: 'minlength: 8;' },
        });

        const names = fieldNames(wrapper.html());

        expect(names).toContain('current_password');
        expect(names).toContain('password');
        expect(names).toContain('password_confirmation');
    });

    it('does not offer two factor authentication or passkeys', () => {
        // Both were removed from the starter kit rather than left behind a
        // blocked route (SPEC §2).
        const wrapper = mount(Security, {
            props: { passwordRules: 'minlength: 8;' },
        });

        expect(wrapper.text().toLowerCase()).not.toContain('two factor');
        expect(wrapper.text().toLowerCase()).not.toContain('passkey');
    });
});

describe('settings/Appearance and Dashboard', () => {
    it('appearance renders its heading', () => {
        expect(mount(Appearance, { props: {} }).text().toLowerCase()).toContain(
            'appearance',
        );
    });

    it('dashboard mounts', () => {
        expect(mount(Dashboard, { props: {} }).html()).toBeTruthy();
    });
});
