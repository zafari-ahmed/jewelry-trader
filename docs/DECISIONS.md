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

## Module 9 — Overrides & Inventory Locks

| # | Decision | Rationale |
|---|---|---|
| Lock effects, not lock names | `inventory_locks.lock_type` is the spec's five effects (full, sales, rental, edit, view); the design's names live in the free-text reason. `InventoryLock::EFFECTS` maps each type to the actions it blocks. | User's answer; one enforcement table |
| Enforced at the service layer | `OrderService::markPaid`, `ProductIntakeService::save` and `TransferService::request` all check `isLockedFor()`, so POS, storefront and any future API are covered by one check. A `view` or `full` lock also drops the piece from `publiclyVisible()`. | Spec: "enforce at the service/policy layer, not the UI" |
| Every type × every action tested | `InventoryLockTest` asserts all four actions for all five lock types as one comparison, plus end-to-end cases (a sales-locked piece is still editable; an edit-locked piece is still sellable). | Module 9 acceptance |
| Overrides need reason + approval | `OverrideService::request()` refuses an empty reason; nothing is effective until someone with `approve-overrides` approves. A Super Admin self-approving is allowed but recorded with `self_approved: true`. | Spec: no silent bypass |
| Override types | Seeded as a class constant covering the union of the spec's and the design's lists. | docs/DECISIONS.md, Module 0 |
| Step-up challenge | Implemented as a configurable second factor with a custom skin: symbols and answers are rows, answers are **hashed**, and `security.stepup_actions` decides which actions it gates. `security.stepup_method` exists so it can be swapped for TOTP step-up with no code change. A pass is good for 10 minutes, not the session. | Spec's framing — brand identity over a standard step-up pattern |
| Seeded answers are placeholders | `StepUpChallengeSeeder` inserts `change-me-*` answers so the flow works out of the box; they must be replaced before going live. | A seeded secret is not a secret |

## Module 10 — Commissions & Payroll

| # | Decision | Rationale |
|---|---|---|
| Queued, after commit | `CalculateCommission` is dispatched from `DB::afterCommit`, so arithmetic never sits between a card and a receipt, and never runs for a sale that rolled back. | Spec: "never synchronously" |
| Tax is not commissionable | Every calculator works from `subtotal − discount`. Tax was never the shop's money. | Correctness |
| Profit basis | Margin is line total minus acquisition cost; discounts reduce margin, and service lines (no acquisition cost) are wholly margin. | The business asked for "no comment", so this is the defensible reading — flagged in the report |
| Tiered basis | The rate is chosen by the **sale's own** commissionable value, not period-to-date. Period tiering needs a running total and belongs in the report, not the per-order job. | Deterministic per order; flagged as an assumption |
| Split rounding | Shares are floored, then the remainder is distributed a cent at a time by largest fractional part. `SplitRoundingTest` checks 40 pool/share combinations, including indivisible ones. | Spec calls this out as the classic bug |
| One row per person per order | Unique on `(order_id, user_id)`; recalculating updates rather than duplicating. | Idempotent job retries |
| §221, no clawbacks | A refund leaves the commission row standing. Nothing in the code reduces an earned commission; a reduction must go through Module 9's override. | California Labor Code §221 |
| §2751, written terms | `staff_commission_assignments.terms_text` / `terms_document_path` store what was actually agreed, with `hasWrittenTerms()` making the distinction explicit. | California Labor Code §2751 |
| Fallback plan is virtual | With no assignment, the plan comes from Settings as an unsaved model, so changing the default applies immediately and the commission row records a null plan id. | Rule 3.1 |
| Payroll mapping | `commission.payroll_export_column_mapping` drives both headers and values; an unmapped source exports `[unmapped: x]` rather than a silent blank. | The target payroll format is unknown |
| Report scoping | Without `view-all-commissions` the report shows only the viewer's own rows; export needs `export-payroll`. | Spec's permission list |

## Module 11 — Phase 2 placeholders

| # | Decision | Rationale |
|---|---|---|
| One placeholder component | `Phase2Placeholder` renders all three features from a table of metadata: flag, description, the tables already waiting, and the seams Phase 2 will build behind. Three near-identical screens would be three places to drift. | Module 11: nav item + flag + tables, no logic |
| Honest when the flag is on | Turning a flag on does not pretend the module exists: the screen switches to "Flag on — module not yet built". | A flag that lies is worse than one that is off |
| Index naming | `storefront_inventory_requests`' unique index is named explicitly; the generated name exceeded MySQL's 64-character limit and failed *after* creating the table, leaving a half-applied migration. | Found by running it |
| Money columns | `rental_agreements.deposit_amount_cents` follows the same minor-units convention as every other money column. | Consistency with payments, orders, pricing |
| A test that no logic shipped | `PlaceholderTest` asserts that no Phase 2 service class exists, so "tables and flags only" stays true as the codebase grows. | §6 boundary |

## Client clarification — AI cataloguing moved into Phase 1

Raised by the client on 2026-09-25: assisted cataloguing (photo → analysis →
descriptions → price → approval) is their **primary competitive advantage** and
must ship in Phase 1, on tablets and phones. The build brief (CLAUDE.md §0,
Module 2, §6, Module 7) placed all AI capability in Phase 2 and forbade
implementing it. The brief and the client's own documentation disagreed; the
client's requirement wins.

| # | Decision | Rationale |
|---|---|---|
| **Vendor-neutral by construction** | The providers speak the common chat-completions shape, with endpoint, key and model held as settings. `NoVendorReferencesTest` still passes: the codebase names no AI service anywhere. | Satisfies the client's requirement 1 *and* the original acceptance criterion; scoping did not need to wait on their provider choice |
| Pricing is arithmetic, not a guess | `PricingEngine` prices from a rate table the business maintains (metal per gram, gemstone per carat, brand/period/condition multipliers, retail multiplier). A model reads photographs; it never invents money. Every suggestion carries its workings. | A language model cannot know today's platinum price. This is explainable and defensible to a customer, and a market feed can replace the rate table later without touching callers |
| Suggestions are never decisions | `products.ai_suggested_fields` records which fields hold an unverified suggestion. Such a field renders **yellow whatever its value**, turns green when accepted, and blue when replaced — with the change written to `ai_correction_log`. | The colour system already meant this; now it is enforced by data rather than convention |
| Confidence floor | A reading below `ai.min_confidence` (default 40%) suggests nothing. | Better silence than sending staff to correct guesswork |
| Never overwrites a person | Analysis fills only fields that are empty or already hold a suggestion. | A human's answer outranks the machine's |
| Search interprets, never selects | Natural-language search turns a sentence into the filters the catalogue already applies; the model never chooses which records come back, so the "listed and in stock" guarantee is untouched. A failing service falls back to keyword search. | A search box must not break because a model is slow |
| Prompts are settings | `ai.vision_prompt` and `ai.description_prompt` are editable in Settings. | House style is a business decision, not a deploy |
| Mobile | Admin navigation collapses behind a menu on small screens — it previously pushed the work area 1,102px down a 375px-wide phone. A dedicated "Take a photo" control uses the device camera directly. | Field staff catalogue on the device, per the client's workflow |

**Still open:** which provider and model the client chooses, and whether a
market-data feed should eventually replace the rate table. Neither blocks the
build; both are settings.

## The craftsman's price determination formula

Supplied by the client on 2026-10-02 as a 16-page specification, with the
instruction that it become the base calculation engine: *"This formula is the
floor. Every price the system calculates starts here."*

### What was built

The four steps, in a fixed order, each compounding on the one before:

| Step | What it determines | How |
|---|---|---|
| 1 | Determining factors | labour cost + material cost |
| 2 | Basic price | ÷ (100% − overhead% − design%) |
| 3 | Wholesale / cost price | ÷ (100% − wholesale agent commission%) |
| 4 | Retail price | ÷ (100% − retail agent commission%), then rounded up |

Subtract-and-divide throughout, never add — the markup lands on the final
price rather than on the starting one. Both of the specification's worked
examples are pinned as tests (`CraftsmanFormulaTest`): $4.00 → $10.50, and
$1,380 → $3,608.00.

### Answers to the client's six questions (§8.0)

| # | Question | Answer |
|---|---|---|
| 1 | Integrate as the base engine beneath the existing layers? | Yes. `CraftsmanFormula` always runs; `PricingEngine` orchestrates the layers around it |
| 2 | Percentages configurable per category? | Yes — `pricing.formula.category_overrides`, seeded with their four categories |
| 3 | Full workings displayed to staff? | Yes — every step is a `PricingLine` with its own arithmetic, shown on the intake screen and filed with the price |
| 4 | Each step independently toggled? | Yes. A step that is off passes the price through and the working says so |
| 5 | Output feeds the AI pricing engine? | Yes — Layers 3 and 4 apply to the formula's output, never inside it |
| 6 | Retail rounding configurable? | Yes — on/off plus the increment |

### Where the layers sit, and one correction to the specification

The specification's stack diagram and §7.0 place Layer 2 (live metal rates)
*below* the formula, feeding the material cost — "Material cost pulls from
Layer 1 (Base) or Layer 2 (API)". Its §4.2 worked example instead shows the
live-rate adjustment as a flat +$45 applied *after* Step 4.

Built per §7.0: **the live feed sets the material rate in Step 1.** A metals
feed reports what metal costs, and material cost is a Step 1 input; applying
it after a retail markup would mean a 3.75% move in platinum moved the shelf
price by 3.75% of *retail*, which is roughly eight times the real exposure.

Noted for the client: their §4.2 final figure of $6,945.00 does not reconcile
with the arithmetic either way — the stated layers give about $6,949 applying
the adjustment at the end, or about $7,129 applying it in Step 1. The
difference is presentational, not structural, but the number in their document
should not be treated as a test case.

### Other decisions

| Decision | Rationale |
|---|---|
| Layer 3 multiplier defaults rebased | They previously scaled intrinsic metal value; they now scale a retail price derived from cost. Cartier at ×2.60 made sense against melt value and is absurd against retail. Defaults are now the client's own figures — maker 1.35, Art Deco 1.25, excellent condition 1.05 |
| A markup ≥ 95% refuses its step | Dividing by zero or by a negative has no meaning here. The step is skipped, the working says why, and the settings form refuses the combination before it can be saved |
| Rounding is **up**, not to nearest | $10.46 → $10.50, as specified. Rounding to nearest would let the presentation rule quietly cost margin |
| Costs live on the item record | `products.labor_cost_cents` and `material_cost_cents`. Both optional: labour falls back to a per-category standard, materials to the rate table. A figure entered by a person always outranks the table |
| The working is filed with the price | `pricing.working` holds the steps, the layers and the percentages as they stood. Rates move; without this, a price set last year could never be explained again |
| The live feed fails soft, always | Unreachable, slow, or quoting a metal we do not hold → fall back to the base table and say so in the working. Pricing a piece must never depend on somebody else's uptime |
| Purity is applied to spot | A feed quotes fine metal; 18k is 75% gold. Without this the material cost would be overstated by a third |

### Client decisions recorded 2026-10-02

- **Reading service:** their choice of supplier for Phase 1, to be entered in
  Settings → AI & Automation. No code change: no supplier is named anywhere in
  the codebase, and `NoVendorReferencesTest` still enforces that.
- **Pilot cost visibility:** `ai_usage_log` records every call with its tokens
  and cost, priced from rates in Settings, reported monthly and **per piece
  catalogued** — the figure that actually answers whether the service earns its
  keep. Historic rows keep the rate they were charged at.
- **AI-maintained rate table:** the client is sending a separate specification.
  The seam is in place — the tables are settings, and Layer 4 already applies
  category, seasonal and inventory-age adjustments from them — but nothing is
  built against a spec we have not seen.

## Rate table layers, and the batch approval model

Client specification received 2026-10-05 (36 pages: formula confirmation, rate
table toggle design, and a quality-control gauge). The formula is approved as
built — *"Approved. Don't change the arithmetic."* Both of the inconsistencies
raised on 2026-10-02 were confirmed in our favour:

- **Live metals feed sits beneath the formula**, feeding material cost at
  Step 1. Confirmed per their §7.0; their §4.2 example was wrong.
- **The $6,945.00 figure was a demonstration error** and is withdrawn. Their
  corrected chain is now pinned end to end in `CorrectedWorkedExampleTest`:
  $1,395 → $1,641.18 → $1,823.53 → $3,647.50 → **$6,980.00**.

Their corrected example implies a material cost of **$1,215**, not the $1,245
a 3.75% move on the full $1,200 would give — consistent with the platinum
being roughly $400 of the materials and the stones the rest. Every
intermediate figure in their chain reconciles on that reading, so that is what
the test states. Worth confirming with them, but it changes nothing
structurally: material cost is an input.

### Built this round

| Change | Rationale |
|---|---|
| **Layer 4 requires Layer 3** | Their §6.3 dependency rule. Layer 4 reads the market; Layer 3 reads the piece. Adjusting for a soft market on a figure that has not yet accounted for maker or condition is adjusting a number that does not mean anything yet |
| **Market adjustments capped at ±30%** | Their §6.3. Four multipliers compounding reach +43% before anyone has looked at the piece. The cap is what stops one table edit moving the whole catalogue |
| **Regional adjustment added to Layer 4** | Their §6.2 |
| **Inventory age bands rebased** to their 31/61/91/120-day schedule | Theirs is far more aggressive than our placeholder (−20% at 120 days vs −5% at 180) and reflects how they actually trade |
| **Metal rates and purities extended** | 10K, 22K, fine silver, palladium and nickel, with their stated purities (24K now 0.999, 14K 0.585) |
| **Batch approval for rate proposals** | Their §4.0, and the direct answer to the question raised on 2026-10-02 |

### The batch approval model

`RateChangeProposal` + `RateProposalService` + a review screen at
`/admin/pricing/proposals`, gated on new permissions
`review-rate-proposals` / `approve-rate-proposals`, held by the appraiser
(inventory-specialist) and Super Admin — deliberately **not** tied to
`manage-settings`, since the appraiser holds the veto on rates but has no
business editing payment keys.

Three decisions inside it worth recording:

| Decision | Rationale |
|---|---|
| A proposal records the rate **as it stood when proposed** | So a human edit made in the meantime can be detected |
| A rate edited since the proposal is marked `stale`, not overwritten | A person's later decision outranks a machine's earlier opinion. Without this, clearing a day-old batch would silently undo deliberate work |
| A proposed value outside 0.1–10.0 never reaches the queue | A multiplier of 400 is a malfunction, not a suggestion. Nobody should have to spend attention rejecting it |

Approval writes **one audited settings change per table**, so the log reads as
the change the appraiser made rather than as eight separate edits.

### Not built — awaiting the client

Listed so the gap is explicit rather than discovered later:

- **Confidence and source columns on the multiplier tables.** Their §5.2 shows
  each multiplier carrying a confidence and a provenance ("auction data",
  "historical sales"). Our tables hold a scalar. This is a settings-shape
  change; it should be done once, alongside whatever writes those values.
- **Layer 2 operational detail**: provider list as data rows, the
  last-known-rate and hold-pricing failure modes (we have fall-back-to-base),
  consecutive-failure alerting, test-connection, last-fetch display.
- **Master control panel** (§7.0) — the per-layer status dashboard.
- **REST API** (§9.1). Ten endpoints against a server-rendered platform is a
  real scope item with its own authentication surface; it should be a
  deliberate decision, not a side effect.
- **The AI maintenance job itself.** The approval queue it feeds is built; what
  writes proposals into it waits on their step-by-step process.

## The appraiser's opening rate table

Received 2026-10-07 as a signed 13-page document with appraiser input, loaded
by `OpeningRateTableSeeder`. Unlike `SettingsSeeder` it **overwrites**: it is
the deliberate load of an agreed table, not a first-run default, and every
write is audited so a reload shows in the log.

Spot assumptions behind the metal figures — gold $2,650/oz, platinum
$1,050/oz, silver $32.00/oz, palladium $1,150/oz, at 31.1035 g per troy ounce.
Every metal rate in the document reconciles to spot ÷ 31.1035 × purity; all
eleven were checked.

### What the table forced us to change

| Change | Why it could not simply be loaded |
|---|---|
| **Diamonds priced by size band** | Their table runs $400/ct at melee to $18,000/ct above four carats. Our model held one flat rate per stone type. Pricing a 0.05ct stone at the 1ct rate overvalues it roughly sixfold; pricing a 3ct stone there undervalues it fivefold. This is not an approximation, it is a wrong answer by multiples |
| **Cut-specific diamond rates** | An old European or rose cut is priced on the cut, which in estate work frequently exceeds the size rate |
| **Clarity, colour, cut grade and treatment** on `gemstone_details` | Their §2.3 adjusts the rate by up to +60% / −50%, and none of those could be recorded, so none could be priced. Treatment especially: a lab-grown stone at 8% of natural is the difference between a $4,500 ruby and a $360 one |
| **Quality tiers for coloured stones** | "Fine Burmese sapphire, unheated" at $4,500/ct and "commercial Australian" at $400/ct are both sapphire. Tier keys are matched most-specific-first and fall through to the commercial rate |
| **Negotiation floor per category** | Their §10: 85% on rings, 75% on men's accessories. We held one global figure |
| **Insurance multiplier per category** | Their §11: 1.40 on rings, 1.50 on brooches. Same |

Stones the trade prices per gram (jade, turquoise, coral, lapis, malachite)
are entered per carat — a fifth of the per-gram figure — so the arithmetic
stays one shape rather than branching on unit.

### Flagged back, not silently resolved

**Layer 3 now compounds much harder than before.** Georgian is ×2.50 (was
×1.35) and condition can now add as well as subtract, so maker × period ×
condition reaches ×4.65 at the top. A worked case: a signed Georgian piece in
mint condition in New York prices at **15.4× materials and labour**.

That may well be right for genuinely rare work — but nothing checks that the
*combination* is possible. The system will happily price a "Georgian Van
Cleef", though the house was founded in 1906. A cap setting
(`pricing.multiplier_cap_percent`) now exists and is **off by default**:
capping the appraiser's signed judgement unasked would quietly overrule it,
so the control is there and the number is theirs to set.

The better fix, offered to the client: their period table already carries date
ranges, and maker founding dates are knowable, so the system could refuse — or
flag — a historically impossible combination rather than pricing it.

**Also flagged:** their assumptions page lists a 30–50% "retail markup on
metal… reflects fabrication, labor, overhead". The craftsman's formula already
applies overhead, design and both commissions on top of material cost. The
rates loaded are raw metal (their own note confirms this), so there is no
double count — but it is worth one line of confirmation, because loading
marked-up rates into Step 1 would charge for the same overhead twice.

## The quality gauge

Built to the design agreed on 2026-10-08, after the client accepted four
changes to his original specification.

### The change that mattered

His draft had **55 manual checks per item**. That sits directly against the
five-minute cataloguing target that is his own competitive advantage, and —
more importantly — a form of fifty-five boxes gets ticked without being read.
The gauge would then look authoritative while meaning nothing, which is worse
than no gauge.

So the system derives everything the record can answer and asks a person only
about what it cannot. The split lands at **~48 derived, 14 human** — hallmarks
under magnification, physical inspection before showing, condition approval,
and the appraiser's sign-off on price. Each of those now means something,
because somebody actually had to look.

A test pins the ratio, so a future check added as manual-by-default fails the
build rather than quietly eroding the design.

### Decisions

| Decision | Rationale |
|---|---|
| **Score is unweighted** (passed ÷ applicable) | His §3.1 defined it unweighted but his table assigned 3/2/1 points, and his own worked example counted unweighted. Weighting the score *and* using criticality to block would let a piece score well while missing something critical — the exact failure the gauge exists to prevent. Criticality governs blocking; the score counts |
| **No score and no stars for customers** | The number measures *record completeness*. A fair-condition piece with a complete record scores full marks, and a customer reads five stars as "excellent piece". In a trade where buyers rely on dealer representations that gap is a liability, not a theoretical concern. A test asserts no percentage and no star reaches the customer page |
| **Role and date publicly; name in the record** | Publishing a named individual's professional judgement on a commercial page attached to a high-value sale is their decision. `users.show_name_publicly` defaults off, with a per-staff opt-in, and the name is produced on request — which is what "full record available on request" promises |
| **"Not applicable" is a real answer** | A plain gold band must not sit amber forever waiting for gemstones it does not have, and nobody should be asked to inspect the arrival of a piece that has never been sent anywhere. Transfer, sale and post-sale checks are grey until they apply |
| **A human answer survives re-evaluation** | Derived checks are re-read every time so the gauge cannot drift from the truth; anything a person answered or overrode is left alone, exactly as on the item record |
| **Overruling a derived check is an override** | It renders blue and is audited, like every other override in this build |
| **Check ranking is a setting** | Whether a missing hallmark blocks a sale is a business decision, not a decision for the registry file (rule 3.1). `qc.check_types` can re-rank or disable any check |
| **QC is excluded from commission** | Agreed with the client. Paying on a partly self-reported metric creates an incentive to tick boxes, and sits badly beside the California commission rules already constraining this build |

### Also built

The verified-facts panel promises "full record available on request", so there
is an inbox behind it: `appraisal_requests`, reachable from the product page.
A promise with no inbox is worse than no promise.

### Deferred by agreement

The **appraisal record** — the client's original 55 checks, which were a good
specification filed under the wrong heading. It is the document the "Request
full appraisal" button should eventually produce.

## Historical consistency, and the multiplier ceiling

Both approved by the client on 2026-10-11, after we raised that the stack
would price a "Georgian Van Cleef" without objection — the house was founded
in 1906 and the period ended in 1837.

### The historical check

`HistoricalConsistency` compares the maker's founding and closing years
against the claimed period's date range. Two rules govern it:

| Rule | Why |
|---|---|
| **It only speaks when certain.** A maker absent from the table, a period with no dates, or a null year produces silence — never a guess | A false accusation against a genuine piece costs the business more than a missed check. The tests spend more effort on the silence than on the flagging |
| **It never decides.** It reports; the reviewer accepts, corrects or rejects | The maker dates are a starting table, not scholarship |

"Unsigned", "attributed", "retailer mark" and "unmarked" never flag: none of
them asserts that a particular workshop made the piece.

It surfaces in two places — as a warning on the price working, and as **QC
check 3.9, ranked critical**. Critical because the record is making a factual
claim that cannot be true, so a reviewer must resolve it before listing. The
existing override path handles acceptance: blue, reasoned and audited.

**The maker dates need the appraiser's eye.** Thirty founding years are
seeded from general knowledge. They are configuration, and a wrong one
produces a false flag, so they should be confirmed during the rate session
rather than trusted.

### The ceiling

Set to **×6.0** on the combined maker × period × condition multiplier, and
switched on — the client's values: under ×4.65 reasonable, ×4.65–6.0 rare but
conceivable, above ×6.0 a human decides.

It **flags and does not clamp**, which was the client's explicit instruction
("never block — just pause") and is also the right behaviour: clamping would
quietly overrule the appraiser on a genuinely rare piece. A test asserts the
price above the ceiling is byte-identical to the price with no ceiling at
all — only now a person has to look.

Ranked **standard** rather than critical as QC check 4.10, so it pauses
without blocking. This is the one place the two new checks differ, and
deliberately: an impossible maker/period pair is a factual error in the
record, while a high multiplier is only implausible.

The earlier `pricing.multiplier_cap_percent` — a ±% clamp, off by default —
is retired in favour of `pricing.multiplier_ceiling`. The Layer 4 market cap
(±30%) remains a genuine clamp, as agreed on 2026-10-05.

### Also confirmed

The metal rates are **raw** — spot × purity, no markup — so the craftsman's
formula applying overhead, design and commissions on top is not a double
count. Confirmed explicitly by the client.
