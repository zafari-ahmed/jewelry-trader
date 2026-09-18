# Decision log

Answers to questions raised during the build, so a fresh session doesn't re-ask them.
CLAUDE.md remains the specification; this file records where reality diverged from it and why.

## Module 0 — pre-flight

| # | Decision | Source |
|---|---|---|
| Laravel version | **13.x**, not the spec's 11. Laravel 11 is past security support; `composer audit` flagged 3 advisories (incl. high-severity CRLF injection) fixed only in 12.60+/13.x. | User, 2026-09-18 |
| Git history | Repo previously held an unrelated booking/calendar app. Started a **fresh orphan history** on local `main`. Old history remains at `origin/main` and local branch `calendarChange` until a force-push is authorised. | User, 2026-09-18 |
| Database | MySQL `jewelry_trader`, user `zahmed`. Tests run against `jewelry_trader_test` (MySQL, not SQLite — `pdo_sqlite` is not installed). | User |
| Queue / cache / session | `database` driver. No Redis on this host, so **no Horizon**. Spec permits this fallback. | Environment |
| Images | No GD/Imagick. **No thumbnail generation** in Module 5 — originals are served until an image driver is installed. | User |
| Mail | Mailgun transport. `MAILGUN_DOMAIN` / `MAILGUN_SECRET` still blank in `.env`. | User |

## Module 0 — design conversion

| # | Decision | Rationale |
|---|---|---|
| Tailwind | **Tailwind 4** with CSS-first `@theme` in `resources/css/app.css`. `tailwind.config.js` does not exist in Tailwind 4; the spec's rule 3.8 is enforced in the `@theme` block instead. | Laravel 13 ships Tailwind 4 |
| Spacing scale | Tailwind's default spacing scale is **disabled** (`--spacing-*: initial`) and replaced with the design's literal px values. The design is not on a 4px grid (9, 11, 13, 18, 22, 44px). Disabling the default means an off-system value like `p-5` fails to compile rather than silently rendering. | Rule 3.8 |
| Type scale | Half-pixel sizes (12.5px, 13.5px, 11.5px) are load-bearing in the design and are preserved exactly as named tokens. | Design fidelity |
| Fonts | Source Serif 4 + Source Sans 3, **self-hosted** via the Vite Bunny plugin rather than the design's Google Fonts CDN link. Identical rendering, no third-party request from pages handling customer PII. | CCPA/GDPR posture |

## Business rules

| # | Decision | Source |
|---|---|---|
| Roles | Follow **the spec's six** (Super Admin, Store Manager, Sales Staff, Inventory Specialist/Appraiser, Accountant/Bookkeeper, Customer Service). The design's matrix (Admin/Manager/Sales/Intake/Viewer) is not built. | User |
| Inventory locks | Follow **the spec's five effects** (full, sales, rental, edit, view). The design's names (valuation hold, repair/workshop, consignment dispute, legal hold) are free text in `inventory_locks.reason`. | User |
| Override types | Seed the **union** of the spec's list and the design's: late_fee, damage, deposit, id_verification, rental, discount, price, price_below_floor, discount_above_limit, sell_locked_item, return_outside_window. Data-driven, so the list is editable. | User |
| Return window | `pos.return_window_days` = **30** (default). Receipt footer text is rendered *from* this setting — the design's hardcoded "14 DAYS" copy is not reproduced. | User |
| Stripe secret "Reveal" | **Not built.** The design's Reveal button contradicts rule 3.2. Rotate-only; keys are write-only after first save. | User |
| POS custom line items | **In Phase 1.** `order_items.product_id` becomes nullable with `description` + `price` on the line, for services like ring sizing (design: `SVC-011`, $145). Decided now because retrofitting it onto live order data is expensive. | User |
| POS hold / park sale | Not in Phase 1. | User |
| Store credit / gift cards | Not in Phase 1. Refund destinations are card and cash only. | User |
| Storefront layaway, viewing requests, appraisal PDFs | **None in Phase 1.** The design's affordances are dropped rather than shipped dead. | User |
| Tax | US **state-based**. Web orders are taxed on the shipping address; POS on the location's state. | User |
| Web orders | Belong to a dedicated **"Web" location** record. | User |
| Customers | One `customers` record shared by POS and storefront, matched on email. **Guest checkout allowed.** | User |
| Currency | USD only. | User |
| Cross-location visibility | Sales Staff **can see** other locations' inventory. | User |
| Transfer approval | The **sending** location's manager approves. | User |
| Commission credit | The **logged-in** POS user earns the commission on the sale. | User |
| Photos | No hard server-side count and no required photo types for submit-for-review; the 5–15 range is guidance in the UI. | User |

## Module 1 — Super Admin Configuration Hub

| # | Decision | Rationale |
|---|---|---|
| Settings registry | `App\Support\SettingsRegistry` declares which settings exist, their type and seeded default. The **values** live in the `settings` table and are edited from the panel — the registry is metadata for the seeder and the form renderer, not configuration. | Rule 3.1 without a UI that has to guess at field types |
| Settings cache | All settings are cached as one array under `settings.all` and flushed on every write. | A per-request DB hit per `Setting::get()` would be paid on every page |
| Stripe key pairs | Live and test keys are stored as separate settings; `payments.test_mode` selects the pair Module 3 reads. | Rotating live keys must not disturb test credentials |
| Secrets in forms | Secret inputs render empty with the masked value as placeholder. An empty submission keeps the stored value; there is no way to read a secret back through the UI. | Rule 3.2 |
| Audit masking | Only `is_encrypted` settings are masked in the audit log. A gateway switch or tax change stays readable in the trail. | Module 1 acceptance: changes auditable, secrets masked |
| Audit retention | Two settings: `security.audit_retention_days` (730) and `security.audit_retention_days_financial` (2557 = 7 years, validated as a floor). `audit_logs.category` separates them. | IRS 7-year recordkeeping on financial records |
| Auth | Session login built now (`/login`), since settings pages need a gated user. Module 8 inserts MFA between password check and redirect. The design system has no login screen — built from its tokens. | Module 1 cannot be gated without it |
| Observers | Audited models are registered in `AppServiceProvider::bootAuditing()` rather than by attribute on the trait, so the audited list is visible in one place. Modules 3–4 append Payment, Product, Order. | Rule 3.5 |
