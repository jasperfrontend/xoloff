---
paths:
  - 'resources/css/**'
---

# Css

## Brand green lives in the default tokens, not a theme picker
Xolution's green is baked into app.css as the accent tokens (--primary, --primary-foreground, --ring, and the --sidebar- pair). There is no theme picker, and no plans for one - the switcher in AppearanceTabs.vue answers light/dark/system only. Same reasoning as the PDF template in SPEC.md §6: single tenant, so a rebrand is a CSS edit and a redeploy.

Two traps:
- `.portal` redeclares the light tokens so the customer's side never goes dark. Change an accent token in `:root` and you must change it in `.portal` too, or the customer keeps seeing the old colour.
- The brand green #2e9238 is only 3.97:1 against white, under the 4.5:1 AA floor for text. So light blocks use a slightly darker hsl(126 52% 34%) for fills and dark uses hsl(126 45% 55%) with a dark label. The logo and the PDF accent stay at the true #2e9238.

resources/js/theme.test.ts pins all of this. Run it after touching any colour token.
