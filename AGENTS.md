<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.16
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v3
- larastan/larastan (LARASTAN) - v3
- laravel/breeze (BREEZE) - v2
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- pestphp/pest (PEST) - v4
- phpunit/phpunit (PHPUNIT) - v12
- rector/rector (RECTOR) - v2
- alpinejs (ALPINEJS) - v3
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3
- tailwindcss (TAILWINDCSS) - v4

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `livewire-development` — Develops reactive Livewire 3 components. Activates when creating, updating, or modifying Livewire components; working with wire:model, wire:click, wire:loading, or any wire: directives; adding real-time updates, loading states, or reactivity; debugging component behavior; writing Livewire tests; or when the user mentions Livewire, component, counter, or reactive UI.
- `pest-testing` — Tests applications using the Pest 4 PHP framework. Activates when writing tests, creating unit or feature tests, adding assertions, testing Livewire components, browser testing, debugging test failures, working with datasets or mocking; or when the user mentions test, spec, TDD, expects, assertion, coverage, or needs to verify functionality works.
- `tailwindcss-development` — Styles applications using Tailwind CSS v4 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd and will be available at: `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs for the user.
- You must not run any commands to make the site available via HTTP(S). It is always available through Laravel Herd.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### User foreign keys in modules

- `users.id` is bigint, `users.uuid` is a parallel UUID column. Module tables conventionally store user FKs (`user_id`, `author_id`, `owner_id`, `sender_id`, `moderator_id`, `assigned_by_id`, `uploaded_by_id`, etc.) as `$table->uuid(...)` — referencing `users.uuid`, not the bigint PK.
- When defining a `belongsTo(User::class, ...)` against such a column, you **must** pass `'uuid'` as the third argument: `$this->belongsTo(User::class, 'user_id', 'uuid')`. Omitting the third arg defaults the owner key to `users.id` (bigint) and produces a hidden 500 (postgres) or silent join failure (sqlite).
- When writing user FK values from controllers, use `$request->user()?->uuid`, not `?->id`.
- When eager-loading with partial column selects (`->with(['author:uuid,first_name,last_name'])`), include `uuid` in the column list so Eloquent can map results back. `id` alone won't work.
- `HrmsCore::Employee` is the lone exception — its `user_id` is a bigint via `foreignId('user_id')`, so its `belongsTo(User::class)` correctly uses the default.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### Permission naming convention

Permission names follow the `module.action.scope` convention. Two namespaces:

- `core.*` — tenant baseline (every Org Superadmin / Org Admin gets these). Examples: `core.dashboard.access`, `core.users.read`, `core.settings.manage`.
- `admin.*` — landlord scope, Superadmin only, cross-tenant operations. Examples: `admin.tenants.create`, `admin.users.impersonate`, `admin.audit-trail.read`.
- Module permissions own their slug as the prefix. Examples: `cms.posts.view`, `hrms.employees.view`, `incidents.view`.

When adding a new permission:

1. Pick the right prefix (`core.*` for tenant-baseline, `admin.*` for landlord, `<module-slug>.*` for a module).
2. Use `verb` or `action.scope` as the suffix — `read`, `create`, `update`, `delete`, or domain verbs like `manage`, `impersonate`, `view`, `moderate`.
3. Module permissions belong in the module's `Config/module.php` under `permissions`; tenant baseline belongs in `App\Models\Permission::BASELINE_TENANT_PERMISSIONS`.
4. Never invent new "verb noun" names like `read user` — that legacy convention is being removed (see `App\Services\Auth\AbilityAliasService`).

The alias service still bridges legacy names to the new ones at `Gate::before` time during the migration window. Once Phase 3.4 ships, only new-style names will exist in the DB; legacy callers will silently fail. Always use new-style names in new code.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the new Laravel structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces using only PHP — no JavaScript required.
- Instead of writing frontend code in JavaScript frameworks, you use Alpine.js to build the UI when client-side interactions are required.
- State lives on the server; the UI reflects it. Validate and authorize in actions (they're like HTTP requests).
- IMPORTANT: Activate `livewire-development` every time you're working with Livewire-related tasks.

=== pint/core rules ===

# Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.
- CRITICAL: ALWAYS use `search-docs` tool for version-specific Pest documentation and updated code examples.
- IMPORTANT: Activate `pest-testing` every time you're working with a Pest or testing-related task.

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.
</laravel-boost-guidelines>

## Hybrid billing agreements

`config/usage-pricing.php` contains the opt-in hybrid catalogue. `HybridPricingService` remains estimate-only. Actual customer rates live in immutable `TenantBillingAgreement` snapshots and are resolved by effective date through `BillingAgreementService`; no package seeder or default signup silently converts existing agreements. Adoption is landlord-only, at a billing month boundary, and requires existing recurring provider subscriptions to be reconciled. New invoices use closed months and preserve historical legacy rates/markups when adoption happened later.

`BillingAccessService` manages explicit member licensing separately from RBAC, including purchased editor capacity, revocation and durable highest monthly access. Billing middleware must run after API-key authentication and tenant resolution and before capacity/metering; never bypass this order. `HybridCapacityService` counts CMS/Forms tenant-owned bytes and application-served public response bodies, not physical shared database size or external CDN traffic. Storage audits must be at or after the effective transition; the scheduled audit and landlord readiness action initialise/reconcile counters. Preserve capacity-reducing recovery, byte-based accounting, file rollback and the no-double-billing rules when extending upload or response paths. No production migration, provider action or deployment is implied by local implementation.

## Billing communications and payment records

`billing:send-reminders` runs daily at 08:00 with overlap protection. Cycle estimates (7/1 days before month start) are not payable invoices; invoices remain monthly in arrears and require explicit issue. Issued invoice reminders run 7/3/0 days before due and 7/14 days after. `BillingMessage` is the landlord delivery outbox: deduplicate by contact and milestone, retry safely, suppress stale contact/paid/void requests, and distinguish transport acceptance from inbox delivery. Use `meta.billing_email`, falling back to the organisation email; never email all tenant members. Payment-request mail is blocked until its provider is configured. These system messages are exempt from retail email metering.

Guest invoice links are temporary signed URLs on the canonical `app.url` host; never use queue-worker ambient tenant URLs. Hosted checkout callbacks/webhooks must verify exact amount, currency, invoice and tenant before fulfillment. Invoice checkout metadata includes `application=udworkspace`; the shared-account Paystack receiver acknowledges foreign applications and malformed or unknown invoice IDs without settlement. Legacy untagged checkouts remain supported for known local invoices, subject to full settlement validation. Subscribe this endpoint only to `charge.success`. Global provider/reference uniqueness protects the payment ledger; receipt snapshots are created once after verified settlement and emailed after commit. Do not create clinical or payment-provider claims from sample UI content. The platform root homepage is explicitly domain-bound so global customer CMS routes cannot shadow it.

Platform Paystack attempts are recorded through `LogPaymentWebhook` in the dedicated `payment_webhooks` daily JSON channel (`storage/logs/payment-webhooks-YYYY-MM-DD.log`, 30 files, mode 0600). Correlate received/completed records and the `X-Webhook-Attempt` response header by attempt UUID. Log only bounded validated identifiers, payload SHA-256, signature result, outcome, HTTP status, duration and exception class; never raw payloads, signatures, authorization data, customer contact details or exception messages. A completed `duplicate` does not create a new receipt or ledger charge; an additional distinct payment is `reconciliation_required`. A self-signed controlled replay verifies our receiver and deduplication, not delivery from Paystack. Provider-origin verification requires a provider resend/delivery record or new provider-origin notification. Tenant merchant endpoints are separate from this platform invoice receiver.

Paystack invoice initialization returns a signed first-party checkout status URL bound to its provider reference/access code. `InvoiceCheckoutStatusRequest` validates query parameters only; POST bodies must never override the signed reference. Keep the first-party page open while native hosted checkout opens separately, clear the popup's opener before navigating, and verify payment server-side through signed/CSRF-protected/rate-limited POST checks. Poll at ten-second intervals for up to ten minutes, with a manual/no-JavaScript check fallback. Do not trust browser success signals or reinitialize payments during status checks. The exact `checkout`/`check` controller routes are billing-recovery exemptions, without granting a paid access level or recording monthly activity. Existing callback verification remains active; Stripe checkout is unchanged.

An early Paystack failed/abandoned status is not a permanent client polling stop: keep checking within the bounded window so later success can recover. Opening hosted checkout restarts that window. Exercise the actual Blade JavaScript with the native Node regression suite through `tests/NoDatabase/PaymentCheckoutClientTest.php`; markup-only assertions do not prove browser transitions.

## Backup coverage

Forge shared `storage/app` may live outside the release root. Backup sources must explicitly include that canonical directory while keeping general symlink traversal disabled; ordinary local storage must not be included twice. `BackupFileCoverageTest` exercises the real file selector. This covers local application files only: tenant S3 objects require separate verified protection, and an existing ZIP or successful upload does not prove restorability. Restore drills use a new verification-only database, never the live database.

Daily full backups append shared S3 tenant media through the synchronous `SnapshotTenantMedia` listener on Spatie's `BackupManifestWasCreated` event. Each ZIP carries `tenant-media/index.json` mapping tenant IDs and source keys to private, hashed archive paths, sizes and SHA-256 values. Include inactive tenants; never list outside `tenants/{slug}`. Partial reads, size mismatches, invalid scopes or unsupported BYO buckets fail the backup and remove partial media staging. Preserve DB-only command semantics. No bucket policy/versioning changes are needed; restoring customer objects requires explicit target selection and checksum verification.

## Production asset builds

The shared production server must not run `npm ci` or Vite during deployment. Run `npm ci` and `npm run build` on a build machine with locked Composer dependencies installed, and commit all of `public/build`, including `build-metadata.json`. The standalone `scripts/prebuilt-assets.php` gate runs after production Composer installation and before release activation; it checks source and vendor-pagination input hashes, the Vite manifest and every compiled file. Rebuild after any tracked project change. CI validates the committed artifacts before rebuilding. Shared runtime storage/cache and Forge's root `auth.json` are deliberately excluded; never commit authentication files. Forge's deployment-only PHP configuration and Composer limit cap deployment processes at 256 MiB, retain normal extensions and are removed afterward. Keep the existing atomic release activation, migrations and CMS audience guard. Run an explicit `artisan queue:restart` after activation; Forge’s queue macro alone does not reload the existing unassociated Supervisor worker.

## Public workspace theme

The platform homepage and public pricing page share `layouts.workspace-public`: preserve its responsive header, contained preview-table scrolling, focus outlines and disabled-registration enquiry fallback. Only the pricing page enables compiled Tailwind assets and Livewire through `$workspaceCalculator`; do not load a runtime Tailwind CDN or a second Alpine instance. Published website prices and setup allowances come from `usage-pricing.hybrid.website_plans`, never duplicate numeric rates in marketing copy. Keep testimonials absent unless real customer approval/evidence is supplied; the calculator never adopts an agreement or changes billing.
