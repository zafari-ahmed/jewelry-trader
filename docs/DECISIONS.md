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

## Module 2 — AI Abstraction Layer (scaffold only)

| # | Decision | Rationale |
|---|---|---|
| **Spec conflict: vendor names** | Module 1 says "seed with OpenAI"; Module 2's acceptance says the codebase must have **zero** references to that name. Resolved by moving the seeded provider list to `database/seeders/data/ai_providers.json` — the names are data (which is what "providers are rows, not code" means), and no PHP, Blade, config or migration carries a vendor string. `NoVendorReferencesTest` enforces this over app/, config/, routes/, bootstrap/, resources/views/, database/migrations/ and database/seeders/. | Satisfies both readings; flag raised in the Module 2 report |
| Provider driver class | Added `ai_providers.driver_class`, mirroring `payment_gateways.driver_class`. A Phase 2 provider is a row plus a class; the resolver never changes. | Rule 3.3 |
| Two-key gate | A capability resolves to a real provider only when `ai.enabled` **and** the capability flag are on **and** an active provider row names a class that implements the contract. Any gap falls back to the Null provider. | A half-configured provider must refuse loudly, not half-work |
| Value object vs model | The value object is `App\Services\AI\AiAnalysisResult` (the spec's name); the Eloquent model for `ai_analysis_results` is `App\Models\AiAnalysisRecord` to avoid a name collision. | Readability at call sites |
| product_id foreign keys | `ai_analysis_results` and `ai_correction_log` carry indexed `product_id` with **no** FK constraint — `products` does not exist until Module 4, which adds the constraint. | Tables had to exist now per §6 |

## Module 3 — Payment Abstraction (Stripe live)

| # | Decision | Rationale |
|---|---|---|
| SDK boundary | The Stripe SDK is touched in exactly one class, `StripeApiClient`, behind our own `StripeApi` interface. `StripeGateway` holds the logic (amounts, statuses, errors, webhook dispatch) and is tested against an in-memory double. | Gateway logic is testable without network or keys; an SDK upgrade has one blast radius |
| Money | Stored in **minor units** (`amount`, `amount_refunded` as integer cents) on both `payments` and `payment_splits`. | Float money is how rounding bugs reach the books |
| Cash | Recorded with no gateway call, as a split row with a `cash:<uuid>` reference. Change due is computed from the tendered amount and kept in the split's response. | Spec: "cash (recorded, no gateway call)" |
| Tenders | A sale is a list of `Tender` objects; one tender = card or cash, several = split. Every sale writes `payment_splits` rows — including single-tender sales — so refunds have one code path. | Uniform refund logic |
| Charge before record | Gateway calls happen **outside** the DB transaction, records are written inside it. A gateway call cannot be rolled back, so money moves first and is recorded second. | A rolled-back transaction must never hide a real charge |
| Failed split reversal | If any tender in a split fails, tenders that already succeeded are refunded at the gateway and the attempt is recorded as a failed payment + `payment.split_reversed` audit events. | A customer must never be left charged for a sale that did not complete |
| Refund ordering | Refunds draw from **card tenders first**, then cash. | Card refunds follow the gateway's rules; cash is unrestricted, so it is the flexible remainder |
| Webhook secret | Verified against the secret for the key pair currently in use (`payments.test_mode` decides). A bad signature is logged as a **security** event and rejected before the body is read. | Rotation with no deploy; bad signatures are hostile until proven otherwise |
| Stripe as refund truth | `charge.refunded` raises `amount_refunded` to match Stripe, so a refund issued from the Stripe dashboard is reflected here. | Refunds can originate outside this application |
| order_id foreign key | `payments.order_id` is indexed with no FK — `orders` arrives in Module 4, which adds the constraint. | Same as the AI tables |
| **Not yet verified** | The live-charge acceptance criterion (real Stripe test charge, refund, exchange) is **unverified**: no Stripe test keys have been provided. Everything below that boundary is covered by the in-memory double. | Needs `pk_test_…`/`sk_test_…` entered at Settings → Payments |
