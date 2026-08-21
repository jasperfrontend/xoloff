import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Xolution's green is the one brand decision baked into the app, so it lives
 * as a handful of tokens in app.css rather than behind a theme picker. These
 * tests guard the two things that are easy to get wrong by hand: the accent
 * tokens have to stay readable, and `.portal` has to keep redeclaring them.
 *
 * `.portal` is the trap. It exists to hold the customer's side of the app in
 * light mode whatever the reader's system says, and it does that by repeating
 * the light tokens. Add a green token to `:root` alone and the admin side
 * turns green while the customer looking at the quote still sees grey.
 *
 * The stylesheet is read off disk rather than imported, because vitest stubs
 * css imports out to an empty string, `?raw` included. Carriage returns are
 * stripped because the file is checked out with CRLF endings on Windows and
 * the parsing below splits on newlines.
 */
const css = readFileSync(resolve(process.cwd(), 'resources/css/app.css'), 'utf8').replaceAll(
  '\r',
  '',
);

/**
 * The accent tokens, as opposed to the neutrals. Only these carry the brand
 * colour, and only these are worth asserting on.
 */
const ACCENT_TOKENS = ['--primary', '--primary-foreground', '--ring'] as const;

const SCHEME_BLOCKS = [':root', '.dark', '.portal'] as const;

type Rgb = { r: number; g: number; b: number };

/** Every `--token: value;` declaration in one top level block of app.css. */
function readBlock(selector: string): Record<string, string> {
  const opening = `\n${selector} {\n`;
  const start = css.indexOf(opening);

  if (start === -1) {
    throw new Error(`No ${selector} block in app.css`);
  }

  const body = css.slice(start + opening.length);
  const end = body.indexOf('\n}');

  if (end === -1) {
    throw new Error(`The ${selector} block in app.css is never closed`);
  }

  const tokens: Record<string, string> = {};

  for (const line of body.slice(0, end).split('\n')) {
    const declaration = line.match(/^\s*(--[a-z-]+):\s*(.+);$/);

    if (declaration) {
      tokens[declaration[1]] = declaration[2];
    }
  }

  return tokens;
}

function parseHsl(value: string): Rgb {
  const match = value.match(/^hsl\(\s*([\d.]+)[\s,]+([\d.]+)%[\s,]+([\d.]+)%\s*\)$/);

  if (!match) {
    throw new Error(`Not an hsl() colour: ${value}`);
  }

  const hue = Number(match[1]);
  const saturation = Number(match[2]) / 100;
  const lightness = Number(match[3]) / 100;

  const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
  const sector = hue / 60;
  const second = chroma * (1 - Math.abs((sector % 2) - 1));
  const floor = lightness - chroma / 2;

  const [r, g, b] = (
    [
      [chroma, second, 0],
      [second, chroma, 0],
      [0, chroma, second],
      [0, second, chroma],
      [second, 0, chroma],
      [chroma, 0, second],
    ] as const
  )[Math.floor(sector) % 6];

  return { r: r + floor, g: g + floor, b: b + floor };
}

/** WCAG 2.1 relative luminance, taking channels already scaled to 0..1. */
function luminance({ r, g, b }: Rgb): number {
  const linear = (channel: number) =>
    channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;

  return 0.2126 * linear(r) + 0.7152 * linear(g) + 0.0722 * linear(b);
}

function contrast(one: string, other: string): number {
  const [lighter, darker] = [luminance(parseHsl(one)), luminance(parseHsl(other))].sort(
    (a, b) => b - a,
  );

  return (lighter + 0.05) / (darker + 0.05);
}

describe('theme tokens', () => {
  it('holds the brand green rather than the starter kit grey', () => {
    for (const selector of SCHEME_BLOCKS) {
      // Hue 126 is Xolution's green. The lightness differs per block.
      expect(readBlock(selector)['--primary'], `${selector} primary is not green`).toMatch(
        /^hsl\(126 /,
      );
    }
  });

  it('repeats every accent token in the portal, so the customer sees green too', () => {
    const root = readBlock(':root');
    const portal = readBlock('.portal');

    for (const token of ACCENT_TOKENS) {
      expect(portal[token], `.portal does not match :root on ${token}`).toBe(root[token]);
    }
  });

  /**
   * 4.5:1 is the AA floor for text, and a button label is text. The brand
   * green itself sits at 3.97:1 against white, which is why the fill is a
   * shade darker than the logo colour in the light blocks and a good deal
   * lighter in the dark one.
   */
  it('keeps a label readable on a primary button in every block', () => {
    for (const selector of SCHEME_BLOCKS) {
      const tokens = readBlock(selector);

      expect(
        contrast(tokens['--primary'], tokens['--primary-foreground']),
        `${selector} primary is under the AA floor for text`,
      ).toBeGreaterThanOrEqual(4.5);
    }
  });

  /**
   * A focus ring is a non-text UI element, so 3:1 against what it is drawn on
   * is the bar. Miss it and keyboard users lose the ring entirely.
   */
  it('keeps the focus ring visible against the background', () => {
    for (const selector of SCHEME_BLOCKS) {
      const tokens = readBlock(selector);

      expect(
        contrast(tokens['--ring'], tokens['--background']),
        `${selector} focus ring is under the AA floor for ui elements`,
      ).toBeGreaterThanOrEqual(3);
    }
  });

  /**
   * The logo tile in the sidebar is drawn with the sidebar's own primary
   * pair. The starter kit left both sides white in dark mode, which hid the
   * mark entirely, so this is worth pinning down now that they are green.
   */
  it('keeps the sidebar logo tile readable in both schemes', () => {
    for (const selector of [':root', '.dark']) {
      const tokens = readBlock(selector);

      expect(
        contrast(tokens['--sidebar-primary'], tokens['--sidebar-primary-foreground']),
        `${selector} sidebar logo tile is under the AA floor for text`,
      ).toBeGreaterThanOrEqual(4.5);
    }
  });
});
