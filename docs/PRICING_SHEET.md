# Workspace pricing sheet

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
