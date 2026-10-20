# Workspace pricing sheet

## Hybrid rate card and explicit adoption

Updated 20 October 2026. The public calculator estimates this model without changing an account. Actual billing and enforcement use a saved, immutable customer agreement. Existing cumulative agreements below remain in force until a landlord administrator explicitly records customer adoption for a billing-month boundary. There is no free Core Workspace offer in the proposed model.

Core Workspace is supplied through one required website subscription per organisation:

- **Website Essentials — GHS 300 / USD 25 per month:** one website, two website-only editor accounts, 5 GiB of website files, a 0.5 GiB shared website database and 25 GiB of outbound website bandwidth per calendar month. Email support covers platform faults; content edits are quoted separately.
- **Managed Business — GHS 600 / USD 50 per month:** one website, five website-only editor accounts, 20 GiB of website files, a 2 GiB shared website database and 100 GiB of outbound website bandwidth per calendar month. Includes email support plus 30 minutes of routine configuration help per month; content edits are quoted separately. No guaranteed response-time SLA is included.

Both plans cover the workspace, CMS, blogs, Forms & Surveys, shared hosting/database, SSL and connection of one customer-owned custom domain (including its www alias). Domain registration and renewal, mailboxes and additional sites are separate quotes. They replace the proposed GHS 100 / USD 10 core; the old core fee is not added again. Subscription remains payable without staff logins. Included editors incur no operational-seat surcharge for website-only editing. Editors who also use operational modules are counted in the operational category, not counted again as website-only editors. Additional website-only editors cost **GHS 25 / USD 2 each per month**, based on provisioned editor accounts, without volume discounts.

**One-time standard setup:** Essentials **GHS 500 / USD 50**; Managed Business **GHS 1,000 / USD 100**. It covers template activation, connection of one existing customer-owned domain and SSL configuration. Migration, custom design, content loading and domain purchase are separately scoped and quoted. The calculator includes setup by default for a new site and shows the recurring subtotal, setup and initial-month subtotal separately. Excluding setup means an existing, already configured site; it is not an offer to waive setup for a new site. These setup fees, allowances and extra-editor rates are configurable recommended defaults.

Website usage inputs are validated against the selected plan. Above any file/database/bandwidth allowance, the calculator withholds a fixed estimate and requires an upgrade or capacity quote. No automatic bandwidth or website overage tariff is promised. Website file/database allowances refer to stored footprint; bandwidth is outbound website pages and files delivered during a calendar month, counted once. A GiB is 1,073,741,824 bytes. Operational-module resources and dedicated hosting need a separate capacity review. The calculator does not itself change an account. For an active agreement, the application enforces included CMS/Forms file storage, logical database content and public response-body bandwidth. Shared database capacity is logical stored column bytes, excluding physical page/index overhead. Application delivery is counted once before sending; externally cached/CDN/static delivery is outside this meter and requires separately configured edge controls. Billing does not automate DNS configuration, SSL issuance or provider payments. Those hosting operations still require deployment acceptance.

Active users cost **GHS 5 / USD 0.50** for self-service or **GHS 25 / USD 2** for operational access each month. Self-service means personal requests, published documents, acknowledgements, forms and incident submission; operational access covers management, approvals, publishing, administration and operational reporting. Count a person once at the highest access level used during the month, independently of job title or module count. Public visitors and external customers are not staff seats. Access assignments add a restriction to existing role permissions. New agreements use a durable per-user monthly highest-access ledger; a same-day upgrade is recorded and a later downgrade or revocation does not erase earlier activity. Website-only editor capacity is purchased per agreement, not charged as additional monthly active staff.

Independent optional module subscriptions, per organisation per month:

- Intranet: **GHS 100 / USD 10**. CMS publishing and one custom domain are already in both website subscriptions and are not charged again through this add-on.
- Collaboration & Documents: **GHS 500 / USD 40**, for documents, memos, forums, projects, reports and workflow automation.
- CRM: **GHS 500 / USD 40**.
- HR Management: **GHS 1,000 / USD 80**.
- Quality & Safety: **GHS 1,000 / USD 80**, for incidents and quality monitoring.
- Facilities: **GHS 500 / USD 40**.
- Commerce: **GHS 750 / USD 60**, for Store and Vendor Marketplace, with commerce eligibility; no HR or quality prerequisite.
- Real Estate: **GHS 500 / USD 40**, independent of Commerce.

Module subscriptions remain payable while selected even with no logins. No per-module user surcharge is added. Core, access and module prices are recommendations, not a validated profitability or supplier-cost model; confirm delivery costs and support effort before making a customer offer. Onboarding, custom development, dedicated hosting/support commitments and payment-provider fees need separate scope and quotes. No clinical patient record, laboratory, prescribing or pharmacy system is implied.

Graduated discounts apply **separately within each access category**: users 1–100 at full price; 101–500 at 10% off; 501–1,500 at 20% off; above 1,500 at 30% off. Only users in a band receive its discount. Website subscriptions, additional editors, setup, module subscriptions and resource usage receive no automatic volume discount. Calculation uses minor units, with half-up rounding for each access-category subtotal.

Hospital example: assume 1,350 self-service and 150 operational monthly active users. Self-service costs GHS 500 + 1,800 + 3,400 = **GHS 5,700**; operational access costs GHS 2,500 + 1,125 = **GHS 3,625**. Add Website Essentials GHS 300 and collaboration, HR, quality and facilities subscriptions totalling GHS 3,000: **GHS 12,625/month**, before storage, email, optional dedicated database usage and applicable taxes. Independent USD pricing gives **USD 1,125/month**. The staff split is an assumption, not a measurement of a hospital. If all 1,500 users need operational access, the same selected packages cost **GHS 34,800 / USD 2,785**, before those additions.

The calculator reads `config/usage-pricing.php` under `hybrid`. Agreement adoption copies the selected plan, complete rate card, currency and module choices into an immutable snapshot. Invoices and entitlements resolve that snapshot by the relevant billing period, never by today’s catalogue. Global/package markups and generic legacy request, daily-user, storage and shared-database charges do not apply on top of the saved hybrid rates. The paid website subscriptions supersede the earlier zero-base and GHS 100 / USD 10 core recommendations, without silently changing stored customer contracts.

### Agreement management, billing and capacity

A landlord administrator opens **Tenants → Edit → Billing agreements & access**, reviews the human-readable prices and capacity, confirms customer adoption and schedules the agreement. Effective dates are the first day of a current or future billing month; no backdating, mid-month repricing, replacement of an existing snapshot or insertion before an already scheduled revision. Current-month adoption is rejected after usage or an invoice exists. Existing provider subscriptions must be terminated and their paid-through dates reconciled before transition. The application does not cancel external subscriptions automatically. Existing customer package records are retained. New module selection grants eligibility; modules must also be enabled and normal role permissions granted.

Assign website-editor, self-service or operational access to organisation members. Website-editor assignments cannot exceed purchased capacity, including a scheduled reduction. An unassigned member cannot use protected workspace functions; account recovery and logout remain available. Revocation frees the current editor assignment but preserves already accrued monthly activity. Platform support Superadmins are excluded from customer seat charges. Purchase of a module or licence does not confer its role permissions automatically.

After each transition takes effect, audit website file ownership using the readiness action or the existing daily `tenants:audit-storage` command (optionally `--tenant=<tenant-id>`). Uploads fail closed until an audit at or after the transition; the normal scheduled audit runs at 03:00. Migrate legacy website files on shared public disks to tenant-scoped storage first. Original files and derivatives are counted; deleting media removes its variants and releases actual bytes. Unknown ownership is rejected. Website mutations check tenant-owned CMS/Forms data, roll back excessive growth and remain available for capacity-reducing recovery. Limits do not create automatic overage invoices. One website and connected custom domain are structural per-tenant settings; tenant-specific CMS extensions may render only for their owning tenant.

Monthly invoice generation uses completed calendar months. It creates draft lines for the website subscription, purchased extra editor seats, selected modules, highest monthly active access after marginal discounts, first-agreement setup where selected, postpaid email and explicitly agreed dedicated database usage. Included website resources and prepaid email are not charged twice. The invoice keeps its agreement ID and currency, supports repeat generation without duplicates, and late legacy invoices use the legacy rates and markup for their own period. An administrator still reviews and issues the invoice; payment remains a separate checkout/provider confirmation.

### Self-Review Report

Touchpoints updated: migrations/models, explicit adoption and member licensing UI, navigation, middleware ordering, CMS/Forms file and database paths, public response metering, audits, rate and invoice services, billing forecasts, pricing calculator, configuration and regression tests. This sheet and AGENTS.md describe the new contract. No dependencies or environment variables were added. Existing landlord permissions protect agreement management; no new permission seed is required. Memory changes are not needed.

UI gap analysis: the agreement screen is linked from tenant editing, shows no-agreement/validation states, readable saved prices and a capacity-audit action. Members have labelled assignment and revocation controls. Saved agreements appear on customer billing pages and lower-access navigation is scoped to their permitted tasks. Existing customer agreements are unchanged without adoption. Visual browser review requires the shared profile to be available; automated route, component, access, billing and quota tests cover the implementation.

Solved: historical pricing and provider-renewal overlap, agreement/invoice locking, API authentication ordering, highest-access upgrades, included/editor double charging, file derivative and binary-byte accounting, upload/API ownership checks, streamed-response reservations, capacity recovery, and genuine employee self-service data boundaries.

Verification on 20 October 2026: the final full regression run passed **1,591 tests / 7,488 assertions** (454.54 seconds). PHPStan reports no errors, Pint passes, the frontend build succeeds and the diff has no whitespace errors. All 154 changed PHP source files pass PHP 8.4 syntax checks, matching production. The build retains its existing large-chunk advisory. No customer agreement or access assignment was created in the local development database. The owner has authorised transitioning existing active customer agreements and deploying after verification; CDN/server bandwidth enforcement is explicitly deferred. Transitions use the next billing-month boundary and preserve prior billing periods.

Operational limitations: CDN/cache/static bytes that do not traverse Laravel are not measured; configure edge restrictions before using the allowance as a network-wide guarantee. Unknown-length streams use temporary-disk staging. File storage and a separate tenant database cannot commit atomically across a process crash, so scheduled reconciliation audits remain necessary. DNS, SSL, backups, provider cancellation and production deployment must be verified operationally. No deployment or external subscription changes were performed.

Not gaps: generic legacy rates stay in place for unconverted customers; no mass agreement seeding or silent package transition occurs; draft generation does not debit a payment method; the platform is not sold as a clinical hospital system.

## Existing cumulative agreements

Configuration reviewed: 20 October 2026. Source of the recommended catalogue: `config/packages.php` and `config/usage-pricing.php`. These are independent retail GHS and USD prices, not exchange-rate conversions or FX quotations. Ghana customers use GHS; other customers use USD according to their billing settings. Verify the account's billing country, currency and installed rate card before issuing an invoice.

## Monthly plans and active-user rates

The five configured bundles have **no monthly base charge**. The following rates apply per distinct monthly active user, plus separately metered usage:

- **Community — GHS 0 / USD 0 per active user:** Core workspace, CMS Core and Forms & Surveys. Storage and eligible email usage can still incur charges; this is not an unlimited free-service promise.
- **Website — GHS 10 / USD 1 per active user:** Community plus Intranet and the custom-domains feature.
- **Team — GHS 25 / USD 2 per active user:** Website plus CRM, Document Management, Memos, Forums, Project Management, Reports and Workflow Automation.
- **Institution — GHS 50 / USD 4 per active user:** Team plus HRMS Core, Incident Management, Quality Monitoring and Facilities Management. A dedicated database is an available option, not automatically provisioned or included free.
- **Marketplace — GHS 85 / USD 7 per active user:** Institution plus Real Estate, Vendor Marketplace and Store, with the commerce feature.

A monthly active seat is a distinct user recorded active in that organisation during the billing month. Repeated activity on multiple days does not create multiple monthly seats. It is not a charge for every registered account, a daily-seat sum or a concurrent-user count. The current meter records authenticated workspace activity; it is not an attendance or employee headcount measure. Self-service plan changes are blocked once usage has been recorded in the current month. Contact an administrator to arrange a transition for the next billing period; the current plan and rates remain in place until that transition. No daily proration or automatic repricing of earlier usage is promised.

Bundle eligibility does not enable a module automatically. Administrators must enable modules and satisfy their dependencies, and users need the relevant permissions. Custom domains require setup. Refer to [Module quickstarts](MODULE_QUICKSTARTS.md) for supported tasks and product limits. SSO, automated file retention and custom-alert entitlements are retired from this catalogue; no such capability is sold here.

## Additional metered usage

- **File storage:** GHS 1.50 / USD 0.10 per GiB-month of average observed daily storage.
- **Dedicated database footprint:** GHS 3.00 / USD 0.20 per GiB-month of average observed daily database size. Shared-database size estimates are not billed.
- **Eligible email:** GHS 15.00 / USD 1.00 per 1,000 successful recipient deliveries recorded by the mail transport meter. For example, 2,000 eligible recipient deliveries add GHS 30.00 or USD 2.00 before applicable tax.

One GiB is 1,073,741,824 bytes. Storage/database quantities are the arithmetic mean of recorded daily gauge totals in the month, divided by that byte quantity. They are not the sum of daily sizes, a peak-size charge or decimal GB. A day without an observation is not automatically filled as zero: the current implementation averages observed days. Meter coverage and failed/missing audits therefore need review before invoice issue. File storage covers files discovered on the tenant-scoped storage disk; it is not a guarantee that every file on every application disk is metered.

There are no recommended request-count or background-job charges in this configuration. Counts may still be recorded for operations. Taxes, any explicit account rate overrides and any configured markup must be reviewed separately; the examples below assume the standard rates with no overrides or markup. Discounts and concessions are for a later policy decision and are not included here.

## What an email delivery means

The metered unit is a successful recipient delivery accepted by the configured SMTP/mail transport. It does **not** establish inbox placement, opening or reading, and it is not a guarantee against a later bounce or spam filtering. A message with multiple recipients can contribute multiple units.

Notifications paid using successfully debited prepaid email credits are excluded from the postpaid email quantity, preventing a second charge for the same prepaid unit. System transactional notifications, including password reset, verification, invoice and invitation messages, are exempt from this retail meter. An in-app notification alone is not an email delivery. Release approval remains pending verification of mail send paths, worker processing, retries, prepaid exclusions and transport reconciliation. Operators must complete those checks before enabling customer billing.

These are **retail service rates**, including service overhead, rather than a pass-through of a supplier's raw send cost. For comparison, AWS publishes an SES **à la carte outbound base price of USD 0.10 per 1,000 emails**; attachments, optional features and other SES plans can cost more. This does not change the workspace's USD 1.00 retail rate or establish its actual supplier configuration. Source: [Amazon SES pricing](https://aws.amazon.com/ses/pricing/), reviewed 20 October 2026.

## Ghana standard-rated tax treatment

For an applicable standard-rated supply, the configured Ghana structure is three separate, non-compounding lines on the same net base:

- VAT: **15%**.
- National Health Insurance Levy (NHIL): **2.5%**.
- GETFund Levy: **2.5%**.

Together these equal **20%** of the same taxable base. The COVID-19 Health Recovery Levy is abolished. GRA gives 1 January 2026 as the reform's effective date. Sources: [GRA notice to VAT-registered taxpayers](https://gra.gov.gh/news/portfolio/notice-to-all-vat-registered-taxpayers/) and [GRA VAT guidance](https://gra.gov.gh/domestic-tax/tax-types/vat/), reviewed 20 October 2026.

**Supplier activation is off pending registration and applicability approval.** `config/billing.php` defaults country-tax activation to false; the optional Ghana seeder creates inactive rules and is not registered in the main seeder. Do not apply the illustrative 20% merely because a customer's currency is GHS. Supplier registration, taxable/exempt/zero-rated status, the customer's country, active tax records and administrator-managed exemption settings matter. This review did not inspect production settings or stored tax records. Legacy global tax records can also affect calculations and need administrator review.

## Worked monthly example: Team in GHS

Assume ten distinct monthly active users, an average observed file-storage gauge of 2 GiB, no billable dedicated database usage, no eligible email usage and no discounts, markup or account-specific rate override:

1. Active users: 10 × GHS 25.00 = **GHS 250.00**.
2. File storage: 2 × GHS 1.50 = **GHS 3.00**.
3. Net usage subtotal: **GHS 253.00**.
4. If the standard Ghana taxes are applicable and activated: VAT = GHS 37.95; NHIL = GHS 6.33; GETFund = GHS 6.33, each calculated on GHS 253.00 and rounded independently to two decimal places (half-up).
5. Combined applicable tax: **GHS 50.61**. Total including that tax: **GHS 303.61**.

Each tax line is rounded half-up to the currency’s two decimal places before the rounded lines are summed. Each raw GHS 6.325 levy therefore becomes GHS 6.33; GHS 6.33 + GHS 6.33 + GHS 37.95 = GHS 50.61. This keeps displayed and stored invoice amounts consistent without a separate rounding adjustment. With country taxes disabled and no other applicable tax records, the example total is **GHS 253.00**.

## Invoices and payment

Monthly generation creates **DRAFT** invoices for review. An authorised administrator reviews usage, rates, tax applicability and totals, then explicitly issues the invoice. Generation and issue do not automatically debit a card or bank account. Payment is a separate customer/provider workflow, and payment status must be confirmed before a bill is treated as settled.

The no-base-charge policy applies to the new five-plan catalogue. Existing legacy package prices are preserved by the seeder, and billing services can still add a stored package base charge. Legacy aliases map module eligibility; they do not automatically convert an existing commercial account to the new zero-base rate. Any legacy customer moving to normal usage billing, needs an explicit reviewed account/rate transition; this sheet creates no complimentary arrangement or data change.

## Add-ons and operator release checklist

- **Prepaid email catalogue:** the locally implemented email-only seeder defines **1,000 email credits for GHS 15.00 or USD 1.00**, as separate currency-specific packs. Successful prepaid credit debits are excluded from postpaid email billing. The catalogue is stored centrally; dedicated tenant purchases reference it without a cross-database foreign key. Customer availability remains pending installation and checkout/credit-fulfillment acceptance. Operators must confirm the installed pack and payment currency before offering it. Existing catalogue records are preserved by the seeder, so verify their actual prices rather than assuming a seed overwrites them.
- **SMS and AI:** no SMS or AI offers or pack prices are included in this sheet. Sending credentials, provider integration and any separately configured legacy token packs are outside this email catalogue; no new model-cost billing is promised.
- **Vendor/service payments:** marketplace job prices, commissions, escrow and outside disbursements are separate transactions. This sheet supplies no commission percentage or external payout guarantee.
- **Rate installation:** recommended configuration is not proof that matching database rate cards are installed. Billing resolves tenant overrides, then package rates, then global rates. Before release, operators must verify installed rates, legacy account transitions, daily gauge coverage, monthly distinct-seat aggregation and email exclusions.
- **Operational verification:** no production configuration, live invoice, supplier credential, external payment or backup was accessed for this document. Customer billing release remains pending operator acceptance of the installed configuration, payment processing and invoice reconciliation.

_Maintainer evidence: `database/seeders/{PackageSeeder,RecommendedUsagePriceSeeder,RecommendedPlatformCreditPackageSeeder,GhanaTaxSeeder}.php`; `app/Services/Tenancy/{UsageService,PricingService,InvoicingService,StorageMeter}.php`; `app/Http/Middleware/TrackActiveUser.php`; `app/Listeners/{NotificationDeliveryCreditListener,Tenancy/MeterTenantMail}.php`; `app/Models/Tax.php`; `app/Http/Controllers/Admin/InvoiceController.php`; `config/{billing,llm,services}.php`._
