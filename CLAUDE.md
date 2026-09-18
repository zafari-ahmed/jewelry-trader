# CLAUDE.md — Jewelry Trader Platform

> **This file is the complete build specification.** It was placed in an empty folder. Read it fully before doing anything.
>
> **Your first action:** read this entire file, then run the Pre-Flight Check in Section 1. Do not scaffold, install, or write any code until you have completed the Pre-Flight Check and I have answered your questions.

---

## 0. What we are building

**Jewelry Trader** — a Point of Sale, inventory management, and e-commerce platform for dealers in antique, vintage, estate, designer, and fine jewelry. The business has multiple store locations and multiple salespeople.

The defining characteristic of this build: **almost nothing is hardcoded.** Payment providers, AI providers, commission structures, tax rates, required-field rules, feature availability — a Super Admin configures all of it from an admin panel, with no code changes and no redeploys. Sections 3 and 5 explain why that shapes every module.

We are building **Phase 1 only**. AI features, rental services, salesperson storefronts, and quarterly audit automation are Phase 2 — you will build the *seams* for them (interfaces, tables, feature flags) but not the functionality. Section 6 is explicit about the boundary.

---

## 1. Pre-Flight Check — do this first, before any code

Report back on all of the following, then **stop and wait for my answers**:

1. **Environment.** Run and show me the output of: `php -v`, `composer -V`, `node -v`, `npm -v`, `mysql --version`, `git --version`. Tell me if anything is missing or below the minimums in Section 2.

2. **Folder state.** `ls -la` the project root. Confirm whether it's genuinely empty or has anything in it.

3. **Design files.** This build has a design system in Claude Design. Check whether you have access to a `claude_design` MCP connector. Report which of these is true:
   - (a) The MCP is available and you can read the design project
   - (b) No MCP, but there's a `design-source/` folder with `.dc.html` files in it
   - (c) Neither — you have no design files

   If (c), tell me and I'll supply them. **Do not invent a design.** Section 7 has the token palette as a fallback reference, but the actual HTML files are the source of truth when they exist.

4. **Ask me anything unclear** about the spec below — particularly around business rules you'd otherwise have to guess at.

---

## 2. Stack

| Layer | Choice | Notes |
|---|---|---|
| Backend | Laravel 11, PHP 8.3+ | |
| Database | MySQL 8 | SQLite acceptable for dev if MySQL is unavailable |
| Cache / Queue | Redis + Laravel Horizon | Fall back to database driver if Redis isn't available |
| Admin panel | Livewire 3 + custom Blade components | **Not Filament** — see note below |
| POS | Livewire, full-screen layout inside the same app | Staff are already authenticated there |
| Storefront | Blade + Livewire | **Not Next.js** — see note below |
| Styling | Tailwind CSS via Vite | |
| Interactivity | Alpine.js | |
| Permissions | `spatie/laravel-permission` | |
| Auth | Laravel session auth (panel) + Sanctum (any future API) | |
| MFA | `pragmarx/google2fa-laravel` | TOTP |
| Payments | Stripe PHP SDK, behind our own abstraction | |
| Storage | S3-compatible, config-driven (local disk in dev) | |

**Why not Filament:** we have a custom design system. Filament brings its own opinionated UI that would have to be fought and re-themed at every screen. We lose free CRUD scaffolding and keep design fidelity — the right trade when the designs already exist.

**Why not a separate Next.js storefront:** the design system lives in Blade components. A second stack means maintaining two copies of every component. Keep one stack unless a hard SSR requirement appears.

---

## 3. Non-negotiable architectural rules

These apply to **every module**. When a rule conflicts with the fastest way to ship something, the rule wins.

### 3.1 Nothing is hardcoded that a Super Admin might reasonably need to change

Feature toggles, business rules (commission types, override reasons, lock types, discount thresholds), payment gateway selection, AI provider selection, tax rates, receipt footer text, required-field rules — all of it lives in a `settings` table or a dedicated config table, editable from the admin panel.

The only things in `.env` are genuine infrastructure secrets with no business reason to change from a UI: `APP_KEY`, database credentials, Redis connection, mail transport.

**Stripe keys are not infrastructure.** They go in the settings table. So does the OpenAI key, when AI arrives. If you find yourself reaching for `.env` or `config/services.php` for something a business user would plausibly want to change, that's the signal you're about to break this rule.

### 3.2 Every secret is encrypted at rest

Use Laravel's `encrypted` cast on the model attribute. Never store a plaintext secret. **Never echo a stored secret back into an edit form** — render a masked placeholder (`sk_live_••••4417`) and only overwrite when new input is submitted. Check this by viewing page source, not just by looking at the rendered form.

### 3.3 Provider abstraction for anything with multiple possible backends

Payments (Stripe now, others later) and AI (none now, several later) both follow the same pattern:

1. Define a PHP interface
2. Write concrete implementations
3. Register a service-container binding that resolves which implementation to use **by reading the settings table at runtime** — not from a compiled config file

Switching providers must be possible by editing an admin form and saving. Zero deploys, zero code changes.

### 3.4 Feature-flag everything that isn't Phase 1

Wrap Phase 2 features in a flag check. Tables, routes, and disabled UI entry points can exist now; flipping a flag in the admin panel turns the feature on without a redeploy. Section 6 lists the flags.

### 3.5 Every state-changing action is audit-logged

Who, what, when, before/after values, IP address. Not deferred to a later module — this is a Phase 1 critical requirement. Super Admin actions are logged too; no role is exempt.

### 3.6 RBAC is enforced at the policy layer

Laravel Policies plus `spatie/laravel-permission`. Hiding a button in the UI is a nicety, not a control. Every controller action and every Livewire component checks a permission.

### 3.7 Multi-location is first-class from day one

Every inventory, order, and staff record is scoped to a `location_id`. Do not build single-location and retrofit — retrofitting location scoping into an existing schema is expensive and error-prone.

### 3.8 Design system discipline

Once tokens are extracted into `tailwind.config.js`, **no arbitrary Tailwind values anywhere**. No `text-[#1C1F2E]`, no `p-[13px]`. Need a value? It becomes a named token in the config first. This is the rule that erodes fastest and it's what keeps screen twenty looking like screen one.

---

## 4. Build order

Build these **one at a time, in this order**. After each: run migrations, run tests, commit, and report back. Do not start the next module until I've confirmed.

| # | Module | Depends on |
|---|---|---|
| 0 | Project scaffold + design foundation | — |
| 1 | Super Admin Configuration Hub | 0 |
| 2 | AI Abstraction Layer (scaffold only) | 1 |
| 3 | Payment Abstraction (Stripe live) | 1 |
| 4 | Core Platform | 1 |
| 5 | Inventory Creation + Color-Coded Fields | 4 |
| 6 | Point of Sale | 3, 4 |
| 7 | Storefront | 3, 4 |
| 8 | Security: RBAC, MFA, Audit | 4 |
| 9 | Overrides & Inventory Locks | 8 |
| 10 | Commissions & Payroll | 4 |
| 11 | Phase 2 Placeholders | — |

After every module, report:
- New settings keys introduced (so they get wired into the settings UI and seeder)
- New permissions introduced (so they get added to the role seeder)
- New queue jobs introduced
- Any place you deviated from the design system, and why

---

## MODULE 0 — Scaffold + design foundation

### 0a. Create the Laravel project

Install Laravel 11 into this folder. Set up `.env` (DB, app key), create the database, run the initial migration, install the packages from Section 2, initialize git, and make an initial commit.

### 0b. Analyze the design — do not write code yet

Read the design files (from the MCP or `design-source/`, whichever the Pre-Flight Check established):
- `Point of Sale.dc.html`
- `Admin Panel.dc.html`
- `Component Library.dc.html`
- `Storefront.dc.html`
- `support.js`

Then report:

1. **Every design token actually used in the markup** — colors as hex, font families, font sizes, spacing values, border radii, shadows. Read what is literally in the files. Do not substitute "close enough" Tailwind defaults.
2. **Every component in `Component Library.dc.html`** with its variants and interaction states (hover, active, disabled, error).
3. **What `support.js` actually does** — which interactions are real behavior to reimplement in Alpine, versus demo scaffolding to drop.
4. **Screen-to-surface mapping** — which screens belong to admin, POS, and storefront.

**Stop here and show me.** Wrong tokens at this stage propagate into every screen.

### 0c. Convert to Blade

Once I confirm the analysis:

- **Tokens into `tailwind.config.js`** under `theme.extend`, named semantically (`brand-navy`, `brand-gold`, `status-required`) — never by hex value. After this, rule 3.8 applies.
- **Blade components** under `resources/views/components/ui/` — `<x-ui.button>`, `<x-ui.input>`, `<x-ui.badge>`, `<x-ui.card>`, `<x-ui.table>`, `<x-ui.modal>`. Each takes props for variants (`<x-ui.button variant="primary" size="lg">`), with variant-to-class mapping inside the component. Callers never pass raw classes.
- **`<x-ui.field-status color="red|yellow|green|gray|blue">`** as its own component — Module 5 uses it heavily and it needs one source of truth.
- **Three layouts**: `layouts/admin.blade.php` (sidebar + topbar), `layouts/pos.blade.php` (full-screen, minimal chrome, large targets), `layouts/storefront.blade.php` (public header/footer).
- **Shared partials** under `resources/views/partials/`.
- **Alpine, not copied `support.js`** — reimplement real behavior against Livewire/Alpine state; drop anything that only existed to fake demo data.
- **Static screens** — convert each design screen to a Blade view using the components and layouts. No controllers or database yet; use obvious placeholder data so I can review visual fidelity separately from backend work.

Then run `npm run build`, start the dev server, and compare the rendered output against the source design files. **Report every difference.** Where you had to deviate, say so and why — don't silently "improve" the design.

---

## MODULE 1 — Super Admin Configuration Hub

Build this before everything else; every later module reads from it.

### Database

`settings` — id, group (string: `payments`, `ai`, `commission`, `security`, `general`, `pos`), key (unique within group), value (text, nullable), is_encrypted (bool), type (enum: string, integer, boolean, json, encrypted_string), updated_by (FK users), updated_at.

A `Setting` model with static helpers:
```php
Setting::get('payments.active_gateway', 'stripe');   // decrypts automatically when is_encrypted
Setting::set('payments.active_gateway', 'stripe', $userId);
```

### Admin pages

Each a distinct page under a "Settings" nav section, gated on `manage-settings`.

**1. General** — company name, logo upload, timezone, default currency, tax rate per location, receipt footer text, business hours.

**2. Locations** — CRUD for store locations: name, address, phone, timezone, is_active. Feeds multi-location inventory.

**3. Payments** — active gateway selector (dropdown populated from a `payment_gateways` table, **not a hardcoded enum**, so adding PayPal later is a data row plus a class); Stripe publishable key, secret key, webhook secret (all masked and encrypted); accepted payment method toggles (card, cash, split); test mode toggle that switches between stored test and live key pairs.

**4. AI** — visible but inert in Phase 1. Master "AI Features" toggle, OFF by default, with tooltip "AI features are not active in Phase 1." Provider dropdown populated from an `ai_providers` table (seed with OpenAI; structured so Anthropic, local models, or others are rows, not code changes). Per-provider API key field, masked and encrypted. Per-capability toggles mapping to flags: `ai.vision`, `ai.description`, `ai.pricing`, `ai.search`. Model name as free text per capability, so swapping models needs no deploy.

**5. Security** — MFA enforcement per role; session timeout; password policy (stored as JSON, actually enforced by reading this config in validation rules); audit log retention days.

**6. Commission** — default commission type (dropdown, data-driven: sales-based, profit-based, tiered, split) and default rate/tiers, used as fallback when a salesperson has no individual override.

**7. Feature Flags** — list view of everything not covered by a dedicated page (`rental.enabled`, `salesperson_storefront.enabled`, `audit.quarterly_enabled`), all OFF by default, each with a one-line description of what it gates.

### Permissions
`manage-settings`, `manage-locations`, `manage-payments-config`, `manage-ai-config`, `manage-security-config`, `manage-commission-config`, `manage-feature-flags` — all on Super Admin by default.

### Acceptance
- A Super Admin can change the active gateway, rotate a Stripe key, and toggle any Phase 2 flag entirely from the UI, with no code change and no deploy.
- Every settings change writes an audit log entry, with encrypted values masked in the log.
- No secret appears in plaintext in any response — verify by viewing page source on the Payments page.

---

## MODULE 2 — AI Abstraction Layer (scaffold only)

Build the seam. **No working AI calls in Phase 1.**

### Interfaces — `app/Services/AI/Contracts/`

```php
interface AiVisionProvider {
    public function analyze(array $imagePaths, string $analysisType): AiAnalysisResult;
    // $analysisType: classification|style|material|gemstone|hallmark|condition
}
interface AiTextProvider {
    public function generate(string $prompt, array $context = []): string;
}
interface AiSearchProvider {
    public function search(string $naturalLanguageQuery): Collection;
}
```

### Value object
`AiAnalysisResult` — raw response (array), confidence score (0–100), reasoning summary, model used, captured at. This is what persists regardless of which provider produced it.

### Database (unused until Phase 2)
- `ai_providers` — id, name, slug, is_active, capabilities (json: which of vision/text/search)
- `ai_analysis_results` — id, product_id, analysis_type, raw_response (json), confidence_score, reasoning_summary, model_used, created_at
- `ai_correction_log` — id, product_id, field_name, original_value, final_value, corrected_by, reason (nullable), created_at

That last table is the training-signal audit trail. Build it now so no migration is needed later.

### Binding
Bind all three interfaces in a service provider resolving from `Setting::get('ai.provider')`. For Phase 1, bind `NullAiVisionProvider` / `NullAiTextProvider` / `NullAiSearchProvider` that throw `FeatureNotEnabledException` if called. The feature flags keep calling code from reaching them; the Null classes prove the seam works.

### Do not
Do not write an OpenAI HTTP client. Do not write prompts. Do not wire this into any UI beyond a **disabled "AI Auto-Fill" button** on the inventory creation screen with a tooltip ("Enable AI in Settings to use this"), bound to the `ai.vision` flag.

### Acceptance
- Toggling `ai.vision` on makes the AI Auto-Fill button clickable (it hits the Null provider — that's correct for Phase 1).
- The codebase has **zero** references to "OpenAI", "gpt-4o", or any vendor SDK.

---

## MODULE 3 — Payment Abstraction (Stripe fully live)

Unlike AI, implement this for real.

### Interface — `app/Services/Payments/Contracts/PaymentGatewayInterface.php`
```php
charge(int $amountCents, string $currency, string $paymentMethodToken, string $description, array $metadata = []): PaymentResult
refund(string $gatewayTransactionId, int $amountCents): PaymentResult
```
Plus partial capture and void as the POS return/exchange flow needs them.

`PaymentResult` — success (bool), gateway_transaction_id, raw_response, error_message (nullable).

### StripeGateway
Reads keys via `Setting::get('payments.stripe_secret_key')` — **never** `.env` or `config/services.php`, so rotation is a form submission. Supports card payments, cash (recorded, no gateway call), split payments across multiple methods on one order, refunds, and webhook handling (`payment_intent.succeeded`, `charge.refunded`) on a signed endpoint verified against the stored webhook secret.

### Factory
`PaymentGatewayFactory::make()` resolves from `Setting::get('payments.active_gateway')`. Even with only Stripe existing, **never** `new StripeGateway()` in a controller — always through the factory.

### Database
- `payments` — id, order_id, gateway, gateway_transaction_id, amount, currency, status (pending/succeeded/failed/refunded/partially_refunded), method (card/cash/split), raw_response (json), created_at
- `payment_splits` — id, payment_id, method, amount

### Acceptance
- Card, cash, and split card+cash sales all complete, each producing correct `payments` records and (for card) a real Stripe test charge.
- Refunds and exchanges reverse correctly via Stripe's refund API and update order status.
- An invalid `payments.active_gateway` value fails with a clear validation error in Settings, not a runtime crash at checkout.

---

## MODULE 4 — Core Platform

### Database

**`products`** — id, sku (unique), title, subtitle, category, subcategory, brand, style_period, metal_type, weight_grams, measurements, condition_notes, internal_description, customer_description, seo_description, marketplace_description, social_description, status (draft/pending_review/approved/listed/sold/archived), manually_overridden_fields (json), created_by, approved_by (nullable), submitted_for_review_at (nullable), created_at, updated_at.

**`product_images`** — id, product_id, type (front/back/side/hallmark/gemstone/clasp/movement/signature/packaging/certificate), file_path, is_primary, uploaded_at.

**`gemstone_details`** — id, product_id, stone_type, shape, cut, color, estimated_weight_ct, setting_style, is_primary.

**`pricing`** — id, product_id, acquisition_value, retail_price, insurance_value, negotiation_min, promo_price, priced_by (FK users, nullable — null when AI-set in Phase 2), created_at.

**`inventory_stock`** — id, product_id, location_id, quantity, status (in_stock/reserved/sold/transferred), updated_at. Model one-of-a-kind pieces as quantity=1 rows so the same structure handles future multi-quantity stock.

**`customers`** — id, name, email, phone, address (json), notes, created_at.

**`orders`** — id, order_number (unique, human-readable e.g. `ORD-2026-000123`), customer_id (nullable, walk-ins), location_id, channel (pos/web), subtotal, tax, discount_total, total, status (pending/paid/fulfilled/refunded/cancelled/partially_refunded), created_by (nullable for web), created_at.

**`order_items`** — id, order_id, product_id, price, quantity.

**`transfer_requests`** — id, product_id, from_location_id, to_location_id, requested_by, status (pending/in_transit/completed), created_at.

### Business logic
- Full CRUD for products, customers, orders — every list filterable by location, status, category.
- **When an order is paid, inventory flips to `sold` inside the same DB transaction** as the payment and order status update. No window where a sold item still reads as available.
- Location transfers with a simple approval workflow.

### Permissions
`manage-products`, `view-products`, `manage-customers`, `view-customers`, `manage-orders`, `view-orders`, `manage-inventory-transfers`.

### Acceptance
- CRUD works for all four core entities.
- Two staff cannot both sell the same one-of-a-kind item — write a concurrent-request test proving it.
- A Sales Staff user at Location A cannot see or edit Location B's inventory without an explicit cross-location permission.

---

## MODULE 5 — Inventory Creation + Color-Coded Field System

The centerpiece workflow. Fully manual in Phase 1, shaped so AI auto-fill drops in later without UI rework.

### Workflow (Livewire multi-step)
1. Upload 5–15 photos, each tagged by type.
2. Field entry — every field rendered with its traffic-light color.
3. Staff completes all Red fields.
4. "Save as Draft" anytime, or "Submit for Review" once Red fields are complete (sets `submitted_for_review_at`).
5. A reviewer with `approve-products` opens it, edits anything, clicks Approve → `approved`. A separate "List Item" action → `listed`. Two steps deliberately: an item can be approved for the record without being published for sale.

### Color-coded fields — config-driven, not hardcoded

`field_color_rules` table — id, field_name, model, color (red/yellow/green/gray/blue), is_required. Seed sensible defaults (title, category, metal_type, measurements = red; hallmark_text, style_period = yellow; internal notes = gray) but make these **editable from a Settings sub-page**, since required fields differ by category — a ring needs a size, a brooch doesn't.

| Color | Meaning | Phase 1 behavior |
|---|---|---|
| **Red** | Required | Cannot submit without it — enforce **server-side**, not just client-side |
| **Yellow** | Normally AI-suggested | Renders as a normal editable field with a "AI suggestion available in a future update" tooltip |
| **Green** | Complete and valid | Computed client-side once a valid value is entered |
| **Gray** | Not applicable | e.g. the gemstone section grays out entirely for a plain gold band |
| **Blue** | Human override of an AI suggestion | Behaves like a normal field now, but record the field name in `products.manually_overridden_fields` so Phase 2's correction logging has data from day one |

### Acceptance
- Photo upload through submit-for-review is achievable in well under 5 minutes on a representative item. Track it: `created_at` → `submitted_for_review_at`, surfaced as a metric to managers.
- Submission is blocked server-side while any Red field is empty.
- Field color rules are editable in Settings and take effect immediately, no deploy.

---

## MODULE 6 — Point of Sale

Full-screen Livewire, no admin sidebar.

- **Item lookup** — search by SKU, barcode, or title; results show thumbnail, price, stock status; add to cart.
- **Cart** — line items, running subtotal, tax from the location's configured rate, discount entry (percentage or fixed) gated on `Setting::get('pos.max_staff_discount_percent')` — above that threshold requires a role with `apply-discount-above-threshold`.
- **Checkout** — full card, full cash, or split, via Module 3.
- **Returns/Exchanges** — look up by order number, select items, choose refund method (card refunds follow Stripe's rules; cash is unrestricted), optionally exchange for a different item (new order line plus refund/credit adjustment in one transaction).
- **Receipt** — on-screen, printable (80mm thermal CSS), and emailed when a customer email is on file.

**Target: a complete sale in under 2 minutes.** Keep the happy path to minimum clicks — no unnecessary confirmation modals.

### Permissions
`use-pos`, `process-refunds`, `apply-discount-above-threshold`.

---

## MODULE 7 — Storefront

Public-facing. **Keyword search in Phase 1, not AI.**

- Product listing with filters (category, style period, metal, price range), sort by price/newest.
- Product detail — image gallery, `customer_description`, price, add to cart.
- Cart and checkout, using **the same payment code path as POS** (Module 3's factory), not a second implementation. Style the Stripe Payment Element to the brand palette rather than leaving it default.
- Customer accounts — register, login, order history.

### Search
Laravel Scout with the database/full-text driver, wrapped behind a `ProductSearchService` interface with `search(string $query, array $filters): Collection`. Module 2's `AiSearchProvider` becomes an alternate implementation later — build the service now so the endpoint contract never changes when AI search activates.

### Acceptance
- Browse, filter, search, cart, checkout with a real Stripe test payment, end to end.
- **Only `status = 'listed'` products with `inventory_stock.status = 'in_stock'` appear publicly.** Draft, pending, and sold items must never leak into public listings.

---

## MODULE 8 — Security: RBAC, MFA, Audit

### Six roles (seed these; granular permissions, not hardcoded role checks)

1. **Super Admin** — everything, including Settings and Overrides
2. **Store Manager** — inventory, orders, approve discounts/returns above staff threshold, reports for their location(s)
3. **Sales Staff** — POS, create/edit inventory subject to approval, view own commission
4. **Inventory Specialist / Appraiser** — create, edit, and approve inventory for listing; no POS sales
5. **Accountant / Bookkeeper** — financial and commission reports, payment/refund records; no inventory edits, no sales
6. **Customer Service** — view orders and customers, process returns; no discounting, no pricing edits

Use `spatie/laravel-permission` with granular permissions so a Super Admin can create custom roles later from a Roles & Permissions page.

### MFA
TOTP with QR enrollment on first login for roles listed in `Setting::get('security.mfa_required_roles')`. Single-use recovery codes generated at enrollment.

### Audit logging
`audit_logs` — id, user_id (nullable for system events), action (e.g. `product.approved`, `payment.refunded`, `setting.updated`), auditable_type, auditable_id, old_values (json), new_values (json), ip_address, user_agent, created_at.

Apply an `Auditable` trait with model observers to Product, Order, Payment, Setting, and User so logging is **automatic** — don't rely on remembering to call a helper. Use explicit manual logging only for non-model events: login, MFA pass/fail, override actions.

Retention: a scheduled job purges beyond `Setting::get('security.audit_retention_days')`. **Note in a code comment** that financial audit records may need a longer retention than general activity logs (7-year IRS recordkeeping applies to financial records), and make retention configurable per log category if that distinction matters.

### Permissions
`view-audit-log`, `manage-roles`, `manage-mfa-settings`.

### Acceptance
- Each of the 6 roles can only perform its permitted actions — one policy test per role per protected action.
- MFA is enforced on first login for configured roles; login is blocked without a valid code thereafter.
- Every create/update/delete on the five audited models produces a queryable entry with correct before/after values.

---

## MODULE 9 — Overrides & Inventory Locks

Deliberately high-friction and high-visibility — this exists for fraud prevention and legal holds.

### Overrides
`overrides` — id, order_id (nullable), product_id (nullable), override_type (late_fee/damage/deposit/id_verification/rental/discount/price), reason (text, **required**), requested_by, approved_by, amount (nullable), created_at.

Any staff member can *request*; it takes effect only after approval by someone with `approve-overrides`. **A Super Admin performing their own override still goes through reason-required and logging** — no silent bypass.

### Inventory locks
`inventory_locks` — id, product_id, lock_type, reason, locked_by, locked_at, unlocked_by (nullable), unlocked_at (nullable).

| Lock type | Effect |
|---|---|
| **Full** | Frozen — no sale, no edit, no rental, hidden from storefront |
| **Sales** | Cannot be sold or carted; can still be edited internally (re-photographed, re-appraised) |
| **Rental** | Cannot be rented (Phase 2 relevance — add the enum value now so no migration later) |
| **Edit** | Cannot be edited (mid-dispute); still viewable/sellable unless also locked otherwise |
| **View** | Hidden from storefront and POS search; exists internally (legal hold, investigation) |

**Enforce at the service/policy layer, not the UI.** The order-creation service checks `product->isLockedFor('sales')` regardless of whether POS or the storefront is calling.

### Step-up challenge for high-risk Super Admin actions

The requirements call this "hieroglyphic security." Implement it as a **configurable second-factor step-up challenge** required for high-risk actions (performing an override, changing payment keys, disabling MFA enforcement):

- `hieroglyphic_challenges` table storing symbol-image → answer pairs, seeded by a Super Admin through a Settings sub-page
- The flow presents 2–3 random symbols and requires correct answers before the action commits
- **Both the symbol set and which actions require the challenge are configurable**

Treat this as functionally a second factor with a custom skin — the security comes from it being a secret shared-knowledge test, not from the symbols being hieroglyphs. It's a brand-identity feature layered on a standard step-up-auth pattern, not a recognized security primitive. Build it so it can be swapped for plain TOTP step-up with a config change if that turns out to be needed.

### Permissions
`request-override`, `approve-overrides`, `lock-inventory`, `unlock-inventory`, `manage-hieroglyphic-challenges`.

### Acceptance
- No override takes effect without both a reason and an approval.
- Each lock type blocks exactly its restricted actions — test every lock type against every restricted action.
- High-risk Super Admin actions are blocked until the challenge passes; every attempt, pass or fail, is audit-logged.

---

## MODULE 10 — Commissions & Payroll

### Database
- `commission_plans` — id, name, type (sales_based/profit_based/tiered/split), config (json; shape varies by type — flat percent, array of {threshold, rate}, or split percentages across staff)
- `staff_commission_assignments` — id, user_id, commission_plan_id, effective_from, effective_to (nullable), terms_text or terms_document_path
- `commissions` — id, order_id, user_id, commission_plan_id, amount, status (pending/approved/paid), calculated_at, approved_by (nullable), paid_at (nullable)

### Calculation
A `CommissionCalculator` service resolved per plan type, run as a **queued job** when an order is marked paid — never synchronously, so calculation never blocks checkout.

**Split commissions must sum correctly across all involved staff.** The classic bug is rounding causing splits not to sum to the pool — write a test for it.

### Reports
Daily/weekly/monthly, filterable by staff and location, CSV exportable. Payroll export column mapping is **configurable** via a `payroll_export_column_mapping` JSON setting, since the target payroll system's format isn't known yet.

### California compliance notes
- **Labor Code §2751** requires written commission agreements — store the agreed terms alongside each assignment record, not just the numeric plan.
- **§204 and §221** govern wage timing and deductions. Do not build automatic clawback deductions from commission. Any commission reduction routes through Module 9's override system so it's reasoned, approved, and logged.

### Permissions
`view-own-commission`, `view-all-commissions`, `manage-commission-plans`, `export-payroll`.

---

## MODULE 11 — Phase 2 placeholders

For each: create **only** the feature flag (OFF by default), a nav item showing a "Coming in Phase 2" placeholder when the flag is off, and the core table migrations. **No business logic.**

**Quarterly Audit** — flag `audit.quarterly_enabled`; tables `audit_reports` (id, period_start, period_end, type, status, generated_at), `audit_exceptions` (id, audit_report_id, description, status, investigated_by).

**Rental Services** — flag `rental.enabled`; tables `rental_agreements` (id, product_id, customer_id, start_date, end_date, deposit_amount, deductible_option, status), `rental_claims` (id, rental_agreement_id, description, status).

**Salesperson Storefronts** — flag `salesperson_storefront.enabled`; tables `salesperson_storefronts` (id, user_id, subdomain, branding_config json, status), `storefront_inventory_requests` (id, salesperson_storefront_id, product_id, status).

---

## 5. Compliance context

Not all of this is code, but it shapes decisions. Flag anything below that a module's design would violate.

| Area | Reference |
|---|---|
| Card processing | PCI DSS — never store raw card data; Stripe tokenization only |
| Privacy | CCPA, and GDPR if applicable |
| Wage payment | California Labor Code §204 |
| Wage deductions | California Labor Code §221 |
| Commission agreements | California Labor Code §2751 |
| Recordkeeping | IRS 7-year retention on financial records |

---

## 6. Phase 1 / Phase 2 boundary

**Build now (Phase 1):** Modules 0–11 as specified — core platform, manual inventory creation with color-coded fields, POS, storefront with keyword search, Stripe payments, full security, overrides and locks, commissions.

**Do not build (Phase 2):** working AI vision, AI description generation, AI pricing, AI semantic search, rental pricing and claims logic, salesperson storefront rendering and commission routing, quarterly audit report generation.

For every Phase 2 item, the interface, tables, and feature-flagged entry point exist so activation is implementing behind a seam — not re-architecting.

---

## 7. Design token fallback

**Use the actual design files when you have them.** This palette is a reference for cross-checking extraction, and a fallback only if no design files exist at all — in which case ask me before proceeding.

| Role | Hex |
|---|---|
| Primary (Navy) | `#1C1F2E` |
| Panel Navy | `#262B3D` |
| Accent (Gold) | `#C9A24B` |
| Gold Tint | `#E4CE8C` |
| Background (Ivory) | `#F7F4EE` |
| Surface (White) | `#FFFFFF` |
| Ink | `#24242A` |
| Muted | `#6B6B72` |

Field status colors — deliberately distinct from the brand palette so they read as status, not decoration:

| Status | Hex |
|---|---|
| Red (required) | `#C0392B` |
| Yellow (AI-suggested) | `#D4A017` |
| Green (complete) | `#2E7D32` |
| Gray (N/A) | `#9CA3AF` |
| Blue (overridden) | `#2563EB` |

Typography: serif for headings (authority, warmth — signals "jeweler," not "software"), clean sans for body and UI. POS prices and totals oversized (32pt+) — they must be readable from arm's length at a counter.

---

## 8. Working agreement

- **One module per session.** Start fresh for each; this file reloads automatically, so you keep the rules and drop the accumulated conversation.
- **Stop and ask** rather than guessing at a business rule. A wrong assumption baked into Module 4 costs far more than a question.
- **Run `php artisan test` after every module**, not just at the end.
- **Commit after every module** with a clear message. When something breaks three modules later, the diff between module commits is how we find it.
- **Tell me when you deviate** from the design or the spec, and why. Don't silently improve things.
- **If you catch yourself reaching for `.env`** for something a business user would want to change — stop. That's rule 3.1, and it's the one that erodes first.

---

## 9. Definition of done for Phase 1

- [ ] Every Super-Admin-relevant setting lives in Settings, not `.env` or a config file
- [ ] Stripe works for card, cash, split, refunds, and exchanges
- [ ] Zero hardcoded AI vendor references anywhere — all routed through Module 2's interfaces
- [ ] All 6 roles enforce permissions at the policy layer
- [ ] MFA and audit logging cannot be bypassed by any role, Super Admin included
- [ ] Location scoping correctly restricts cross-location visibility per role
- [ ] Photo upload → submit-for-review achievable in under 5 minutes
- [ ] A POS sale completes in under 2 minutes
- [ ] All Phase 2 feature flags exist, default OFF, visible in Settings
- [ ] No arbitrary Tailwind values anywhere in the codebase
