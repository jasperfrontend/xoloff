# Xoloff (Xolution Offerte) - Full Specification, Milestones 1–7

## How to use this document

This is the complete build spec for xoloff, broken into seven sequential milestones. **Each milestone is a stop-and-test checkpoint** - write Pest/PHPUnit tests as you go (Laravel's default `phpunit.xml` is already configured, use it), confirm the milestone's Definition of Done is met, and stop for review before starting the next one. Don't skip ahead.

The full data model (§3) is defined once, up front, covering all seven milestones. Each milestone section tells you which parts of it to actually build at that stage - don't build columns or tables ahead of the milestone that needs them unless a later milestone's design depends on an earlier migration shape (a few of these are flagged explicitly).

There is no fixed deadline on this project. Correctness matters more than speed, especially in Milestone 2 (the calculation engine) and Milestone 6 (the legal signature trail).

---

## 1. Overview

Xoloff is a custom quote/proposal system built **for Xolution** (a midsized WordPress web hosting and development agency), replacing their previous tool, Offorte, which they found expensive and clunky. Xolution will use xoloff to send quotes to their own clients.

This is a small, single-tenant system: **exactly two users** (Jasper, the developer, and Stephan, Xolution's owner), no roles/permissions, no multi-tenancy, no ambition to become a general CRM. It does one thing: build a quote, calculate it correctly, get it signed, and keep Stephan informed along the way.

- **Repo:** `jasperfrontend/xoloff`
- **Tech stack:** Laravel 13, InertiaJS, Vue 3 (from the [Laravel Starter Kit for Vue](https://laravel.com/docs/13.x/starter-kits#vue)), PostgreSQL
- **PDF rendering:** existing Gotenberg container at `xolution-pdf-printer.onrender.com` (credentials provided separately). Gotenberg's Chromium engine converts an HTML+CSS document into a PDF - this shapes how PDF export is built (see §6).
- **Deployment:** Linode. Provision this **alongside Milestone 1**, not at the end, so every subsequent milestone deploys against a real environment instead of colliding into one big integration step later.
- **E-signature:** `creagia/laravel-sign-pad` (compatible with Laravel 11–13, PHP 8.2–8.5 - confirmed to fit this stack). Handles the signature-pad canvas UI and stamps a signature onto a certified PDF via TCPDF. It does **not** provide a legal audit trail (IP, consent text, etc.) - that's custom work, specified in §8.

---

## 2. Cross-Cutting Decisions

- **Auth:** plain email + password only. Socialite/OAuth (Apple, Google, Discord, Twitch - all considered) was ruled out entirely as unnecessary overhead for two known users.
- **No public registration.** The database is preseeded with exactly two users. The Starter Kit's registration scaffolding can stay in the codebase but must not be reachable in production.
- **VAT logic is manual, not automatic.** The system does not need to intelligently determine the correct tax treatment for a given customer - whoever builds the quote picks the tax class. This is explicitly out of scope for all milestones.
- **The dynamic font/color settings page from the original brainwave doc is dropped entirely** - not deferred, dropped. See §6.
- **Notifications are fire-and-forget.** Sent once, no retry, no outcome-checking - this is consistent with how notifications are handled elsewhere in Jasper's own projects. Every notification send and every quote status change is logged in the audit log (§3, §9).

---

## 3. Full Data Model

### `users`
Exactly 2 seeded rows. `name`, `email`, `password`. No public registration route.

### `customers`
- `company_name`, `contact_person`, `email`, `billing_address`, `country`
- `country` matters because VAT treatment depends on it (e.g. a US-based customer gets the whole quote zero-rated/reversed).

### `product_categories`
Flat list (tags, not hierarchical). `name`.

### `tax_classes`
- `name` (e.g. "Standard 21%", "Reduced 9%", "Zero-rated / reverse charge"), `percentage`
- Freely extensible - not hardcoded to hosting/web dev, in case Xolution sells other product types later.

### `products`
- `name`, `price_ex_vat`, `tax_class_id` (FK, default - overridable per quote line), `category_id` (FK)

### `product_specs`
- `product_id` (FK), `key`, `value` - a flexible key/value list per product (examples: billing period, startup cost, contract duration), not fixed columns.

### `premade_texts`
- `key` (`intro`, `footer`), `content` (markdown/basic HTML from a Vue3-compatible WYSIWYG editor)
- The footer is where the mandatory legal disclaimer lives (e.g. reference to *algemene voorwaarden*) - a legal requirement, not optional copy.

### `quotes` - the logical, ongoing quote (persists across versions)
- `customer_id` (FK)
- `status` - added in **Milestone 4**: `draft` (implicit default before M4 exists), `sent`, `opened`, `approved`, `denied`
- `magic_link_token` - added in **Milestone 4**
- `valid_until`, `validity_days_override` - added in **Milestone 4** (see §7)
- `sent_at` - added in **Milestone 4**
- `deny_reason` - added in **Milestone 5** (nullable text, shown when status is `denied`)
- `help_scout_conversation_id` - added in **Milestone 7**
- The **current version** of a quote is simply the row in `quote_versions` with the highest `version_number` for that `quote_id` - no separate pointer column needed.

### `quote_versions` - a snapshot of quote content
A new row is created only on an explicit "Save as new version" action, never automatically on every save (auto-versioning on every save would flood the history with unfinished draft edits).

- `quote_id` (FK → `quotes`)
- `version_number` (int, starts at 1, increments per `quote_id`)
- `discount` (nullable - type: percentage/fixed, value - quote-level discount)
- `rounding_override` (nullable decimal - see §5)
- `intro_text_snapshot`, `footer_text_snapshot` (text, copied from `premade_texts` at save time - snapshotted, not live-referenced, so a quote a customer already viewed or signed stays accurate even if the global footer text is edited later)

### `quote_line_items`
- `quote_version_id` (FK → `quote_versions`)
- `product_id` (nullable FK - a line can be detached from its originating product once inserted)
- `name`, `specs` (jsonb, copied from the product then freely editable - catalog values are defaults, not locked)
- `quantity`
- `unit_price_ex_vat`
- `tax_class_id` (FK)
- `discount` (nullable - type + value, line-level)

### `quote_signatures` - Milestone 6
- `quote_version_id` (FK → `quote_versions` - ties the signature to the *exact version* signed, not the quote in the abstract)
- `signer_name` (typed by the signer at the moment of signing - not pulled from the customer record, to demonstrate clear intent)
- `ip_address`, `user_agent`
- `signed_at` (UTC timestamp)
- `consent_text` (the verbatim wording of the consent statement/checkbox shown - store the literal string, since if the wording changes later you need to know what a given signer actually agreed to)
- `document_hash` (SHA-256 of the rendered quote content at the moment of signing - proves the signed version wasn't altered afterward)
- `signature_image_path` (from `laravel-sign-pad`)

### `audit_log`
- `user_id` (nullable, for system-generated events)
- `entity_type`, `entity_id` (polymorphic reference - quote, product, customer, tax_class, category, etc.)
- `action` (`created`, `updated`, `deleted`, `status_changed`, `notification_sent`)
- `payload` (jsonb - diff or event detail)
- Needs a **browsable UI**, filterable by quote, by date range, and by which user caused the entry. This is one log covering both CRUD operations and status/notification events - not separate tables.

### `app_settings`
- `default_validity_days` (default 30)
- `slack_notifications_enabled`, `helpscout_notifications_enabled` (booleans, toggled in a settings UI)
- `logo_url`, `logo_mime`, `logo_data` - the logo, fetched from an address Xolution already hosts it at and kept here as bytes. This replaced `logo_path` and the upload screen on 2026-08-21 (see §6).
- `company_name`, `company_address`, `company_kvk`, `company_vat_number` - Xolution's own identity, printed on the quote PDF. Added in **Milestone 4** (see §7). These live here rather than in the PDF template so they can be corrected without a redeploy.
- Actual secrets (Slack webhook URL, Help Scout API key/mailbox, SMTP credentials) live in `.env` / GitHub secrets, **not** in this table - see §10.

---

## 4. Milestone 1 - Foundation & Reference Data

**Build:** Starter Kit setup, Postgres schema and CRUD UI for `users` (seeded, 2 rows), `customers`, `product_categories`, `tax_classes`, `products` + `product_specs`. Auth locked to the two seeded users, no public registration route exposed. Provision the Linode deployment target now.

**Explicitly not in scope:** quotes, calculations, PDF, anything customer-facing.

**Definition of Done:** all reference-data CRUD flows have feature tests; auth correctly rejects unregistered access attempts; the app is deployed and reachable on Linode.

---

## 5. Milestone 2 - Quote Builder & Calculation Engine

This is the highest-risk milestone in the project - it's the money math. Give it the heaviest test coverage of anywhere in the codebase.

**Build:** `quotes` and `quote_versions` (without the M4+ columns) and `quote_line_items`. A quote builder UI: attach a customer, add line items from the product catalog (fully editable after insertion - catalog values are just defaults), set quantities, apply discounts.

**Calculation order - exact, do not deviate:**

1. Line subtotal = `quantity × unit_price_ex_vat`
2. Apply the **line-level discount** (if any) to that subtotal - pre-VAT
3. Sum all discounted line subtotals → quote subtotal
4. Apply the **quote-level discount** (if any) to the quote subtotal - pre-VAT
5. Calculate VAT **on the discounted amount**, per tax class present on the quote (a single quote can have mixed tax classes across its lines)
6. Calculated total = discounted subtotal + VAT

**Rounding override:** if `rounding_override` is set on a `quote_version`, it simply *replaces* the calculated total for display and PDF purposes. No reconciliation line, no adjustment entry - the override is the definitive value and the calculated total is discarded without a trace.

**Test this milestone especially hard against:** mixed tax classes on one quote, 0%/reverse-charge lines, discounts stacking (line + quote level together), and the rounding override behavior.

**Definition of Done:** a quote can be built end-to-end with correct totals under all the above scenarios, verified by tests, not just manual spot-checks.

---

## 6. Milestone 3 - Content, Versioning, Audit Log & PDF Export

**Build:**
- `premade_texts` with a Vue3-compatible WYSIWYG editor (markdown + basic HTML)
- Logo UI (one-time setup). **Changed 2026-08-21: an address, not an upload.**
- Versioning: the explicit "Save as new version" action, creating a new `quote_versions` row; a document list showing each quote's current version number (e.g. "V3"), with a way to view and delete old versions
- The `audit_log` and its browsable, filterable UI
- PDF export

**PDF export - the settings-page question is resolved, not deferred:** Gotenberg's Chromium engine converts an HTML+CSS document to PDF, so the branded output was always going to be an HTML template under the hood regardless of how the "settings" question was answered. The decision made was to **drop the dynamic font/color settings page (the Bunny Fonts API integration, per-element color pickers) entirely** - not build it later, not stub it. Instead: design **one genuinely good, hardcoded HTML/CSS quote template** with Xolution's branding baked in, and feed it to Gotenberg. If the branding ever needs to change, that's a CSS edit and a redeploy - a perfectly reasonable process for a single-tenant, two-person tool, not a missing feature.

**The logo is fetched from a URL rather than uploaded.** Originally this was an upload stored at `app_settings.logo_path`. The reason for the change is deployment: an uploaded file makes the filesystem a second stateful thing, needing a mount that exists before the container starts, a `storage:link` inside it, and backups covering two places. For one file of a few dozen kilobytes, the database is the better home - it is already persistent and already backed up.

Three consequences worth knowing:

- **It is fetched when the address is saved, not when a quote is printed.** A wrong address is then a message under the field, in front of the person who typed it, rather than a logo missing from a document a client already has. Printing never depends on anyone else's web server, and Gotenberg needs no outbound access (see §7).
- **SVG is allowed now, where the upload refused it.** An uploaded SVG was a real risk: it is a document that can carry script and was going to be rendered by a Chromium. Fetched, it only ever reaches an `img` element or a data URI in one, where it is treated as an image and nothing in it runs. It must never be inlined into the page itself.
- **The fetch is guarded.** This application requests an address a person typed, which is the shape of an SSRF whether or not anyone means it that way, and the host it runs on can reach its own cloud metadata service. https only, no redirects followed, hosts resolving to private or reserved addresses refused, an image content type required, and a 2 MB cap.

The template must include: the intro/footer text snapshots from the current `quote_version`, the line items with specs and pricing, the calculated (or overridden) totals, the logo, and the mandatory footer legal text. Page numbering is automatic via Gotenberg - no extra work needed. Trigger: a plain "Download PDF" button on the quote screen. No email, no portal, no e-signature involved yet.

**Definition of Done:** a quote can be saved as a new version, old versions are listable/viewable/deletable, every CRUD/version/PDF action is visible in the audit log UI with working filters, and the downloaded PDF looks genuinely professional, not just functional.

---

## 7. Milestone 4 - Sending & Tracking

**Build:** the "send quote" action (generates `magic_link_token`, sets `sent_at`, transitions `status` to `sent`), integration with Xolution's transactional email, portal-visit-based open/read tracking (**not** email open-tracking pixels - unreliable and widely blocked), and the quote validity window.

**Validity window:** defaults to `app_settings.default_validity_days` (30), with a simple per-quote override (`quotes.validity_days_override`) so Stephan can give a specific client more leeway via one click in the UI. When a magic link is visited after the quote's `valid_until` has passed, show a gentle message about the timeframe having passed - never a harsh "not found" error.

**Email integration:** Xolution's transactional email runs through `smtp.xolution.nu`, with antispamcloud.com handling cleaning before an unspecified downstream delivery step.

> **`.nu` is not a typo - do not "correct" it.** The SMTP host really is `smtp.xolution.nu`, while Xolution's email *addresses* live on `.nl` (`stephan@xolution.nl`, Help Scout inbox `contact@xolution.nl`). Both domains are in active use for different purposes. Confirmed by Jasper, 2026-08-12. This isn't a blocker to reaching this milestone - request the exact SMTP credentials/config from Stephan when you actually get here.

**Carried over from Milestone 3, and closed here.** Both surfaced while building the PDF export and neither belonged to M3's Definition of Done, so they were left open rather than quietly absorbed. Kept on the record because each cost something to work out.

- **Xolution's own details on the quote PDF.** Built 2026-08-21. The template printed the logo and the customer but nothing identifying the sender, which is conspicuous on Dutch business correspondence. `company_name`, `company_address`, `company_kvk` and `company_vat_number` live on `app_settings` (§3) and print opposite the customer's address, showing only what has been filled in. They are read live rather than snapshotted onto the version, unlike the intro and footer texts: where the sender sits today is a fact about the sender, not part of what was offered, so a corrected address belongs on a reprint of an old version too.

  The four columns were built without waiting on a requirements answer. Asked what he wanted on there, Stephan said "oh just the usual, y'know" (Jasper, 2026-08-20), which is not a specification and would not have become one by asking again in a different way. Name, address, KvK and BTW is the usual. The values arrived the next day, once there was a rendered PDF to react to - **that is the form of the question he can actually answer, and the move to reach for the next time a requirement stalls like this one.**

- **The Gotenberg container answering to strangers.** Closed 2026-08-21. It had been reachable unauthenticated - `/health`, `/version`, `/forms/chromium/convert/html`, with `/forms/chromium/convert/url` enabled too, so anyone who knew the hostname could make Xolution's Render instance fetch URLs and render arbitrary HTML at Xolution's expense. Basic auth is now on at the container (`API_ENABLE_BASIC_AUTH=true`, with `GOTENBERG_API_BASIC_AUTH_USERNAME` and `GOTENBERG_API_BASIC_AUTH_PASSWORD`). Verified: `/version` answers 401 without credentials and 200 with them.

  Two things about it are worth not rediscovering. `/health` stays open by design, which is what keeps a Render health check from failing on every deploy - do not "fix" that. And the toggle is `API_ENABLE_BASIC_AUTH` while the credentials are prefixed `GOTENBERG_`; the asymmetry is Gotenberg's own, not a typo.

  Switching it on broke every PDF download until the application was taught to send the credentials: it read them as `GOTENBERG_USERNAME` / `GOTENBERG_PASSWORD` while they had been set under Gotenberg's own longer names. It now reads the longer names first, so one pair of values goes into the container's environment and into `.env` unchanged. **A value that has to be renamed on the way in is a value that will eventually be renamed wrongly** - the same reasoning produced the `XOL_` mail keys in §12.

  **Still open, and not urgent:** Gotenberg's outbound filtering (`CHROMIUM_DENY_PRIVATE_IPS` and its siblings, recognised from 8.31 onwards; the container runs 8.36.0). Basic auth stops strangers, those stop a compromised caller using Gotenberg as an SSRF pivot. Nothing in xoloff fetches anything outbound - the logo is embedded as a data URI and the HTML is posted inline - so every one of them is safe to switch on.

> `RENDER_API_URL` and `RENDER_API_KEY` are Render's **management** API, for listing services and triggering deploys. They are not Gotenberg credentials and are not involved in rendering a PDF. The application needs `GOTENBERG_URL` plus the two basic-auth values, and nothing else.

**Definition of Done:** sending a quote generates a working magic link, a portal visit is correctly recorded as an "opened" event, and an expired link shows the gentle message rather than a 404. Xolution's own details appear on the PDF, and the Gotenberg container no longer answers to strangers.

---

## 8. Milestone 5 - Client Portal & Approval

**Build:** the public magic-link portal page - view the current quote version, approve or deny. That's the entire surface for now: no comment field, no "request a call" button, no list of past quotes for the customer (a nice-to-have, not required). Denial opens an optional reason textbox (`quotes.deny_reason`) - this fully replaces the earlier "countered" status concept, which was dropped.

**Fill in the cover page M4 left behind, don't replace it.** That page already shows the sender, the quote number, who it is for and the date it stops being valid, deliberately promising no content it did not yet have. Read cold it looks broken (Jasper, 2026-08-21) - which is the constraint this milestone inherits rather than a fault to fix in passing: whatever goes below that header has to carry enough weight that a customer never wonders whether the page failed to load. This is the only page in xoloff a customer ever sees, and the sparseness is only acceptable while nobody is looking at it.

**Definition of Done:** a customer can open a valid magic link, view the quote, and approve or deny it; both actions correctly update `status` and are logged in the audit log.

---

## 9. Milestone 6 - E-Signature

**Build:** integrate `creagia/laravel-sign-pad` for the signature-pad canvas and PDF stamping. Build the `quote_signatures` table and the audit-trail capture around it (§3) - this is the part the package does **not** give you for free, and it's what actually makes a Simple Electronic Signature legally defensible under eIDAS: it's the evidence of *who* signed, *what* they saw, and that the document wasn't altered afterward, not just the drawn signature image itself.

At minimum, capture at the moment of signing: the signer's typed full name (typed fresh, not pre-filled from the customer record), IP address, user-agent, a UTC timestamp, the verbatim consent text they agreed to, and a SHA-256 hash of the rendered quote content - all tied to the specific `quote_version` being signed. Signing an approved quote should be the event that finalizes `status = approved`.

This isn't formal legal advice - worth a real legal sanity-check before this goes live - but it's standard SES evidentiary practice and a solid baseline to build against.

**Definition of Done:** a customer can draw a signature on approval, the resulting PDF is certified/stamped, and the full evidence record exists in `quote_signatures`, verifiable against the exact version they saw.

---

## 10. Milestone 7 - Notifications

**Build:** Slack and Help Scout integrations, both toggleable independently via `app_settings`, both fire-and-forget (sent once, no retry, no outcome-checking - failures are logged, not retried, since these are side-effects of a status change, not the status change itself).

**Slack:** a single incoming webhook URL, posting to one channel - no per-event routing to different channels. Credentials go in `.env`/GitHub secrets, requested from Stephan when this milestone is reached.

**Help Scout:** one conversation created per quote (store its ID on `quotes.help_scout_conversation_id`), reused across the quote's lifecycle:
- Status-change events (opened, denied) are added as **notes** on that conversation via the Create Note endpoint (`POST /v2/conversations/{id}/notes`)
- On approval, the conversation is reassigned to Stephan via the Update Conversation endpoint

Mailbox ID and API key are requested from Stephan when this milestone is reached - same as the Slack webhook, not a blocker before then.

Every notification send is logged in the audit log (§3) alongside every status change - one log, not a separate table.

**Definition of Done:** each of the four status-change events (sent, opened, approved, denied) correctly fires both the Slack message and the Help Scout note/reassignment when enabled, is logged in the audit trail, and a failure in either channel doesn't block or roll back the underlying status change.

---

## 11. Dropped, Not Deferred

For clarity - these were part of the original brainwave document and were deliberately cut, not pushed to a future milestone:

- The dynamic font/color settings page (Bunny Fonts API search, per-element color pickers) - see §6.
- The "countered" quote status - fully replaced by the simpler deny-reason textbox in Milestone 5.
- Multi-user roles/permissions and multi-tenancy - this is a two-user, single-agency tool, permanently.
- Integration between an approved quote and Xolution's invoicing/billing systems - intentionally kept separate.

---

## 12. Config & Secrets to Gather Per Milestone

None of these block starting or building the relevant milestone's logic - they're just credentials to slot in when you get there:

- **Milestone 4:** all supplied 2026-08-21. SMTP for `smtp.xolution.nu` (via antispamcloud.com) arrived as `XOL_SMTP`, `XOL_FROM` and `XOL_PASS`, and is read under those names rather than renamed into Laravel's `MAIL_` ones (see §7 for why). `XOL_PORT` defaults to 587 and is **unconfirmed**: the relay answers on no port from outside Xolution's own network, so the first real send from Linode is what settles it - and if it turns out to be 465, that is implicit TLS and needs `MAIL_SCHEME=smtps` as well as the port. Xolution's KvK, BTW number and postal address are entered on the settings screen. The Gotenberg basic-auth pair is set on both the container and the application (see §7).
- **Milestone 7:** Slack incoming webhook URL; Help Scout mailbox ID and API key
