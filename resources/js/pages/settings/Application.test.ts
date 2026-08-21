import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AppSettingsEdit from '@/pages/settings/Application.vue';
import { resetInertiaStub, submissions } from '@/test-support/inertia';

vi.mock('@inertiajs/vue3', async () => (await import('@/test-support/inertia')).inertiaStub());

type Settings = InstanceType<typeof AppSettingsEdit>['$props']['settings'];

const blank: Settings = {
  logo_vector_url: null,
  logo_raster_url: null,
  logo_vector_preview_url: null,
  logo_raster_preview_url: null,
  company_name: null,
  company_address: null,
  company_kvk: null,
  company_vat_number: null,
  default_validity_days: 30,
};

function build(settings: Partial<Settings> = {}) {
  return mount(AppSettingsEdit, {
    props: { settings: { ...blank, ...settings } },
  });
}

const saved = {
  logo_vector_url: 'https://xolution.nl/wp-content/uploads/logo.svg',
  logo_raster_url: 'https://xolution.nl/wp-content/uploads/logo-600w.png',
  logo_vector_preview_url: '/logo',
  logo_raster_preview_url: '/logo/email',
};

const details = {
  company_name: 'Xolution',
  company_address: 'Voorbeeldstraat 1',
  company_kvk: '01234567',
  company_vat_number: 'NL001234567B01',
};

describe('settings/Application', () => {
  beforeEach(() => {
    resetInertiaStub();
  });

  it('says so when neither logo has been saved', () => {
    const wrapper = build();

    expect(wrapper.text()).toContain('Not set');
    expect(wrapper.find('img').exists()).toBe(false);
  });

  /**
   * The stored copies, not the addresses they came from. Previewing the
   * remote images would show what is out there rather than what this
   * application actually holds, which is the one thing this screen is for.
   */
  it('previews the stored copies rather than the remote addresses', () => {
    const sources = build(saved)
      .findAll('img')
      .map((image) => image.attributes('src'));

    expect(sources).toEqual(['/logo', '/logo/email']);
  });

  /**
   * Two fields for one logo can drift apart, and one of them lives only in
   * email where nobody looks critically. Showing both is what makes a
   * mismatch visible.
   */
  it('shows both previews side by side, even when only one is set', () => {
    const wrapper = build({
      logo_vector_url: 'https://xolution.nl/logo.svg',
      logo_vector_preview_url: '/logo',
    });

    expect(wrapper.findAll('img')).toHaveLength(1);
    expect(wrapper.text()).toContain('Not set');
  });

  it('keeps both addresses in their fields so a typo can be corrected', () => {
    const wrapper = build(saved);

    const valueOf = (name: string) =>
      (wrapper.find(`input[name="${name}"]`).element as HTMLInputElement).value;

    expect(valueOf('logo_vector_url')).toBe('https://xolution.nl/wp-content/uploads/logo.svg');
    expect(valueOf('logo_raster_url')).toBe('https://xolution.nl/wp-content/uploads/logo-600w.png');
  });

  /**
   * Either alone is enough, so neither field can be required - and clearing
   * one is how that logo is removed.
   */
  it('demands neither address', () => {
    const wrapper = build();

    for (const name of ['logo_vector_url', 'logo_raster_url']) {
      expect(wrapper.find(`input[name="${name}"]`).attributes('required')).toBeUndefined();
    }
  });

  it('says why there are two and what each is for', () => {
    const text = build().text();

    expect(text).toContain('quote page and the PDF');
    expect(text).toContain('cannot draw an SVG');
    expect(text).toContain('300 pixels wide');
  });

  /**
   * Xolution's own details print on every quote PDF (SPEC §7). The address
   * is a textarea rather than an input, because it is several lines and is
   * printed with the breaks it was typed with.
   */
  describe('the details printed on quotes', () => {
    it('shows what has already been saved', () => {
      const wrapper = build(details);

      expect((wrapper.find('input[name="company_name"]').element as HTMLInputElement).value).toBe(
        'Xolution',
      );
      expect(
        (wrapper.find('textarea[name="company_address"]').element as HTMLTextAreaElement).value,
      ).toBe('Voorbeeldstraat 1');
      expect((wrapper.find('input[name="company_kvk"]').element as HTMLInputElement).value).toBe(
        '01234567',
      );
      expect(
        (wrapper.find('input[name="company_vat_number"]').element as HTMLInputElement).value,
      ).toBe('NL001234567B01');
    });

    it('starts empty rather than showing the word null', () => {
      const wrapper = build();

      expect(wrapper.text()).not.toContain('null');
      expect((wrapper.find('input[name="company_name"]').element as HTMLInputElement).value).toBe(
        '',
      );
    });

    /**
     * The values are still being collected, so the server takes them one
     * at a time and the form must not demand the rest.
     */
    it('demands none of them', () => {
      const wrapper = build();

      for (const name of Object.keys(details)) {
        expect(wrapper.find(`[name="${name}"]`).attributes('required')).toBeUndefined();
      }
    });

    it('submits all four under the names the server reads', async () => {
      const wrapper = build(details);

      await wrapper.findAll('form')[0].trigger('submit');

      expect(submissions).toHaveLength(1);
      expect(submissions[0].data).toEqual(details);
    });

    /**
     * The logo saves on its own. Sharing a form would hold a KvK number
     * hostage to someone else's web server being up at that moment.
     */
    it('saves separately from the logo', () => {
      const logoForm = build()
        .findAll('form')
        .find((form) => form.find('input[name="logo_vector_url"]').exists());

      expect(logoForm).toBeDefined();
      expect(logoForm!.find('input[name="company_name"]').exists()).toBe(false);
    });
  });

  /**
   * How long a quote stays valid once sent (SPEC §7). Sending a quote can
   * give that one client more leeway without changing this.
   */
  describe('the validity window', () => {
    it('shows the current default', () => {
      const input = build({ default_validity_days: 45 }).find(
        'input[name="default_validity_days"]',
      );

      expect((input.element as HTMLInputElement).value).toBe('45');
    });

    /**
     * Unlike the company details this one has no blank state: every quote
     * sent takes its expiry from it.
     */
    it('will not be left empty', () => {
      const input = build().find('input[name="default_validity_days"]');

      expect(input.attributes('required')).toBeDefined();
      expect(input.attributes('min')).toBe('1');
      expect(input.attributes('max')).toBe('365');
    });

    it('submits on its own, without the company details', async () => {
      const wrapper = build({ default_validity_days: 45 });
      const form = wrapper
        .findAll('form')
        .find((candidate) => candidate.find('input[name="default_validity_days"]').exists());

      await form!.trigger('submit');

      expect(submissions).toHaveLength(1);
      expect(submissions[0].data).toEqual({
        default_validity_days: '45',
      });
    });
  });
});
