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
| **Verified against Stripe** | Closed on 2026-09-22: `php artisan payments:verify` ran a real test-mode charge, a partial refund, the remainder, a card+cash split, and a split refund — all passed with the stored credentials. The command refuses to run while `payments.test_mode` is off, so it can never move real money. Re-run it after any key rotation. | Module 3 acceptance |
| Key prefix validation | The Payments form rejects a key whose prefix does not match the field (`pk_`/`sk_`/`whsec_`, live vs test). | A publishable key pasted into the secret field would otherwise surface only as a cryptic gateway error at checkout — which happened |

## Module 4 — Core Platform

| # | Decision | Rationale |
|---|---|---|
| Money in cents | Every money column stores integer **minor units** and carries a `_cents` suffix (`retail_price_cents`, `total_cents`). This renames the spec's columns. | Matches the payments tables; the suffix stops a caller reading 6800 as $6,800. Float money is how rounding reaches the books |
| **Tax precision bug** | `decimal(6,4)` was too narrow for a real US rate: NYC's 8.875% rounds to 8.88% and overcharges ~$0.03 per $100. Widened `locations.tax_rate` and `orders.tax_rate` to `decimal(9,6)`. | Found by a failing total in test; would have mispriced every NY sale |
| Tax recorded per order | `orders.tax_rate` and `tax_state` store the rate actually applied. | A later rate change must not rewrite past orders |
| Pricing is append-only | A price change inserts a `pricing` row; the current price is the latest. | Price history survives; Phase 2 AI pricing writes rows with `priced_by` null |
| Sold-once guarantee | `markPaid()` locks each `inventory_stock` row `FOR UPDATE` inside the same transaction as the order status and payment link. A claimed item aborts the whole transaction. | Module 4 acceptance. Verified by a forked two-process test **and** a negative control: with the lock removed, both processes sold the same ring |
| Cross-location visibility | `view-all-locations` permission. Sales Staff, Store Manager, Inventory Specialist and Accountant hold it by default (the business wants staff to see other locations); scoping is enforced in policies and query scopes for roles without it. | Reconciles the spec's acceptance criterion with the business answer; tests cover a user **without** the permission so the scoping is not decorative |
| Transfers | Stock moves only on completion: approval marks it `transferred` so it cannot also be sold, and completion moves the row's `location_id` rather than duplicating it. | One stock row per piece per location |
| Order numbers | `ORD-{year}-{000001}`, sequential per year, derived from the highest existing number. | Spec format |
| Product form scope | `ProductForm` is plain CRUD; Module 5 replaces the screen with the photo upload and colour-coded field workflow writing the same tables. | Avoids building the intake UI twice |
| Component widths | `x-ui.input/select/textarea` default to `w-full` unless the caller passes a width class. | Filter bars need inline widths; forms need full width |

## Module 5 — Inventory Creation + Colour-Coded Fields

| # | Decision | Rationale |
|---|---|---|
| **Sixth state: `neutral`** | The spec names five colours. Green is explicitly *computed* ("once a valid value is entered"), so a rule stored as green means "optional, nothing special" — and an empty optional field must not render green, or an untouched record looks finished. Such fields render `neutral`: no status dot, plain border, counted separately in completeness. | Found by a completeness test that counted 9 complete fields on a record with 4 values |
| Field values without columns | Rules are editable in Settings, so a Super Admin can add a field name that has no products column (ring size, chain length, movement type). Those values are stored in `products.attributes` (JSON). | A migration per new field would defeat config-driven rules |
| Category overrides | `field_color_rules.category` is null for defaults; a row naming a category slug overrides it. Categories are a managed table, per the earlier answer. | "A ring needs a size, a brooch doesn't" |
| Gray is never required | Saving a gray rule forces `is_required` false. | A field that is not applicable cannot block submission |
| Rule cache | All rules cached under one key, flushed on every write. | The intake form reads every rule on every render; a Settings change must still apply immediately |
| Server-side gate | `ProductIntakeService::submitForReview()` re-runs the required-field check and throws; the Livewire disabled button is a convenience. Tested by calling the service directly, bypassing the UI. | Module 5 acceptance: enforced server-side, not just client-side |
| Photo rules | 5–15 is shown as guidance and never blocks submission; no photo type is mandatory (per the earlier answer). Photos store originals — no thumbnails, since this host has no image driver. | docs/DECISIONS.md pre-flight |
| Intake metric | `created_at → submitted_for_review_at`, surfaced on the review queue as median / slowest / count, with the median tile turning red above five minutes. | Module 5 acceptance: "surfaced as a metric to managers" |
| Approve ≠ list | Two separate actions and two statuses; listing is refused unless the item is approved. | Spec: an item can be approved for the record without being published |
| ProductForm removed | Module 4's plain CRUD form is deleted, replaced by `ProductIntake`. The static `admin/inventory-create` and `admin/review` views are removed too. | One intake screen, not two |

## Module 6 — Point of Sale

| # | Decision | Rationale |
|---|---|---|
| Cart in session | `pos.cart` and `pos.location` are session-backed, so a refresh mid-sale keeps the customer's items. | A lost cart at the counter is worse than a stale one |
| Cart is a value object | `App\Services\Pos\Cart` holds the arithmetic (subtotal, per-line discount, tax, staff-ceiling check); the Livewire component stays thin. | Money maths is testable without a browser |
| Pre-filled tender | Reaching payment pre-fills one card tender for the full amount, so the common sale is search → add → charge. | Two-minute target; no modal in the happy path |
| Tenders must equal the total | The charge button is disabled and the service re-checks server-side. | Prevents a part-paid order being marked paid |
| Discount ceiling | Per line, percentage or fixed, capped at the line value. Above `pos.max_staff_discount_percent`, the apply is refused unless the user holds `apply-discount-above-threshold` — checked in the component, not the view. | Rule 3.6 |
| Refund amount | A returned line refunds its discounted value **plus the tax charged on it**, capped at what remains refundable on the order. | The customer paid tax on that line and gets it back |
| Return window | Outside `pos.return_window_days`, a refund is refused unless the user can `approve-overrides`. Module 9 replaces that check with a real override request. | The window is configurable, not hardcoded |
| Exchange | One transaction: return the old lines (restocking them), create the replacement order, and settle only the difference. A cheaper replacement refunds the difference; an even swap moves no money. | Spec: "new order line plus refund/credit adjustment in one transaction" |
| Card token | The register passes the gateway's test token. Mounting the Stripe Payment Element on the POS screen is the remaining step before real card entry at the counter — no card reader is in scope (docs/DECISIONS.md). | Module 7 mounts the same Element for the storefront |
| Receipt | On screen, printable at `/pos/receipt/{order}` (80mm `@page`, browser print), and emailed when a customer email is on file. Company details, footer text and return window all read from settings. | Spec |

## Module 7 — Storefront

| # | Decision | Rationale |
|---|---|---|
| Search behind an interface | `ProductSearchService` with `KeywordProductSearch` bound in Phase 1. Module 2's `AiSearchProvider` becomes an alternate binding later with no change to calling code. | Spec |
| Scout indexes, queries do not | Products are Scout-`Searchable` with `shouldBeSearchable()` tied to public visibility, but Phase 1 queries the database directly. The "only listed, in-stock" guarantee lives in `Product::publiclyVisible()` — one place — rather than in an index that could drift and leak a draft. | Module 7 acceptance |
| Payment Element | Added `prepare()` and `verify()` to `PaymentGatewayInterface`. The browser confirms the intent directly with Stripe; the server **re-reads it from the gateway** before recording, and rejects an intent whose amount does not match the order. The browser is never authoritative about whether money moved. | PCI DSS SAQ-A; spec asks the Element be brand-styled |
| Element theming | Stripe's appearance API is fed from the design tokens at runtime (`--color-gold`, `--color-border-field`, 2px radius, Source Sans). | Rule 3.8 — no second palette |
| Stripe.js loaded lazily | Injected only when a customer reaches payment, so browsing makes no third-party request. | Privacy (CCPA/GDPR) |
| **Web fulfilment bug** | Web orders belong to the Web location, but stock sits at a physical store — `markPaid` found no stock row and left paid orders pending. `lockStockFor()` now falls back to the piece's actual location for web orders, still under `FOR UPDATE`. Found by a real checkout, not by a test. | The sold-once guarantee must hold across channels |
| Customer guard | Customers authenticate on their own `customer` guard against the `customers` table; `password` is deliberately **not** mass-assignable. A guest who bought earlier claims that record on registration, keeping their history. | Staff and customers are different populations |
| Cart re-validated on read | The session cart drops anything no longer publicly visible, so a piece sold at the counter disappears from a web bag rather than reaching checkout. | One-of-a-kind stock |
| Alpine started once | Livewire bundles its own Alpine; this bundle starts Alpine only on pages without Livewire. Two instances were running on every Livewire page. | Found via console warnings |

## Module 8 — Security: RBAC, MFA, Audit

| # | Decision | Rationale |
|---|---|---|
| MFA enforced by middleware | `RequireTwoFactor` runs on the whole `web` group, so no route can be reached by a user who owes a second factor — Super Admin included. The routes that *satisfy* the requirement (setup, challenge, logout) are the only exemptions. | Module 8 acceptance: MFA cannot be bypassed by any role |
| Which roles need MFA | Read from `security.mfa_required_roles`; changing it takes effect on the next request. Tested by turning it on for a role mid-test. | Rule 3.1 |
| Secret handling | `two_factor_secret` and `two_factor_recovery_codes` use encrypted casts; recovery codes are additionally **hashed**, so a database leak yields nothing usable. Codes are shown once, at enrolment. | Rule 3.2 |
| QR code | Rendered inline as SVG by `bacon/bacon-qr-code`; the secret never reaches a third-party image service. | Privacy |
| A new session re-challenges | Login clears `two_factor_passed_at`, so enrolment alone does not grant standing access. | Spec: "login is blocked without a valid code thereafter" |
| Role matrix as a test | `RolePermissionMatrixTest` asserts all nine protected actions for all six roles as one comparison. A permission moved in the seeder without thinking fails here. | Module 8 acceptance: one policy test per role per action |
| Retention per category | `audit:purge` (daily at 03:15) applies `security.audit_retention_days` to general activity and `security.audit_retention_days_financial` to financial entries. | IRS 7-year recordkeeping |
| audit_logs.created_at writable | Needed to backdate entries when testing retention and for any future import; the trail is append-only by use, not by column locking. | Testability |
| Test isolation | `ConcurrentSaleTest` uses `DatabaseTruncation` (it needs committed rows for real locks) and now clears `audit_logs` and `settings` too — leftovers were breaking retention counts in later tests. | Found only when running the full suite |
