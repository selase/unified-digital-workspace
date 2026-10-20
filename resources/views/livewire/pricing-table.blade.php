<section id="pricing" class="mx-auto my-16 max-w-6xl space-y-8 rounded-2xl bg-white p-6 text-slate-900">
    <header class="space-y-3">
        <p class="text-sm font-semibold uppercase tracking-wide">Website plans and optional modules</p>
        <h2 class="text-3xl font-semibold">Hybrid pricing preview</h2>
        <p>Choose a website plan covering Core Workspace, hosting and included editors, then add operational access or module packages if needed.</p>
        <p class="text-sm">Does not change your subscription or invoice. Actual rates apply only through an explicitly adopted customer agreement; the calculator does not create one.</p>
    </header>

    <div class="space-y-4">
        <h3 class="text-xl font-semibold">Core Workspace — website plan required</h3>
        <p class="text-sm">CMS, websites, blogs, Forms &amp; Surveys, shared hosting and database, SSL and connection of one customer-owned custom domain. Subscription remains payable even with no staff logins. Domain registration and renewals are separate.</p>
        <div class="overflow-x-auto rounded-xl border border-slate-300">
            <table class="w-full text-left text-sm">
                <caption class="p-4 text-left font-semibold">Website plans and included usage per organisation</caption>
                <thead><tr class="border-y border-slate-300"><th scope="col" class="p-4">Allowance or fee</th>@foreach($hybrid['website_plans'] as $plan)<th scope="col" class="p-4">{{ $plan['name'] }}</th>@endforeach</tr></thead>
                <tbody>
                    <tr class="border-b border-slate-300"><th scope="row" class="p-4">Monthly subscription</th>@foreach($hybrid['website_plans'] as $plan)<td class="p-4">GHS {{ number_format($plan['GHS'], 2) }} / USD {{ number_format($plan['USD'], 2) }}</td>@endforeach</tr>
                    <tr class="border-b border-slate-300"><th scope="row" class="p-4">One-time standard setup</th>@foreach($hybrid['website_plans'] as $plan)<td class="p-4">GHS {{ number_format($plan['setup']['GHS'], 2) }} / USD {{ number_format($plan['setup']['USD'], 2) }}</td>@endforeach</tr>
                    @foreach(['sites' => 'Websites', 'editors' => 'Included website editors', 'domains' => 'Connected custom domains', 'files_gib' => 'File storage (GiB)', 'database_gib' => 'Logical website database data (GiB)', 'bandwidth_gib' => 'Website bandwidth (GiB/month)'] as $key => $label)
                        <tr wire:key="website-limit-{{ $key }}" class="border-b border-slate-300"><th scope="row" class="p-4">{{ $label }}</th>@foreach($hybrid['website_plans'] as $plan)<td class="p-4">{{ $plan[$key] }}</td>@endforeach</tr>
                    @endforeach
                    <tr><th scope="row" class="p-4">Support scope</th>@foreach($hybrid['website_plans'] as $plan)<td class="p-4">{{ $plan['support'] }}</td>@endforeach</tr>
                </tbody>
            </table>
        </div>
        <p class="text-sm">Standard setup covers template activation, connection of one existing domain and SSL configuration. Custom design, migration, content loading, domain purchase and email mailbox hosting are quoted separately. A www alias is part of the same domain.</p>
        <p class="text-sm">Usage above a website allowance requires a larger plan or an agreed capacity quote; there are no automatic website overage charges in this estimate. Bandwidth means outbound website pages and files delivered during a calendar month. One GiB is 1,073,741,824 bytes. Saved agreements enforce website file, logical database and application-served bandwidth limits. External CDN traffic and direct static hosting require separate edge controls.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-300">
        <table class="w-full text-left text-sm">
            <caption class="p-4 text-left font-semibold">Access rates per distinct monthly active user</caption>
            <thead><tr class="border-y border-slate-300"><th scope="col" class="p-4">Access</th><th scope="col" class="p-4">GHS / month</th><th scope="col" class="p-4">USD / month</th><th scope="col" class="p-4">Access scope</th></tr></thead>
            <tbody>
                @foreach($hybrid['access'] as $type => $access)
                    <tr wire:key="access-{{ $type }}" class="border-b border-slate-300">
                        <th scope="row" class="p-4">{{ $access['name'] }}</th>
                        <td class="p-4">{{ number_format($access['GHS'], 2) }}</td><td class="p-4">{{ number_format($access['USD'], 2) }}</td>
                        <td class="p-4">{{ $type === 'self_service' ? 'Own leave requests, published documents, acknowledgements, forms and incident submission.' : 'Manage operational records, approve requests, configure modules and use operational reporting.' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="text-sm">Website-only editors use the included allowance; do not count them again as operational users. Staff using operational modules count once at their highest access level, not again as website-only editors, regardless of job title or number of modules. Public visitors and external customers are not staff seats. An assigned access level and the existing role permissions are both required for protected workspace actions.</p>

    <div class="grid gap-8 lg:grid-cols-2">
        <div class="space-y-5">
            <h3 class="text-xl font-semibold">Build a monthly estimate</h3>
            <label class="block space-y-2" for="quote-currency"><span>Currency</span>
                <select id="quote-currency" wire:model.live="quoteCurrency" class="w-full rounded-lg border border-slate-300 bg-white p-3"><option value="GHS">GHS</option><option value="USD">USD</option></select>
            </label>
            <label class="block space-y-2" for="website-plan"><span>Website plan</span>
                <select id="website-plan" wire:model.live="websitePlan" class="w-full rounded-lg border border-slate-300 bg-white p-3">@foreach($hybrid['website_plans'] as $slug => $plan)<option value="{{ $slug }}">{{ $plan['name'] }}</option>@endforeach</select>
            </label>
            <label class="block space-y-2" for="website-editors"><span>Website-only editors</span>
                <input id="website-editors" type="number" min="0" max="100000" step="1" wire:model.live.debounce.400ms="websiteEditors" class="w-full rounded-lg border border-slate-300 bg-white p-3">
            </label>
            <p class="text-sm">Included editors incur no extra access charge. Additional website-only editors: GHS {{ number_format($hybrid['extra_editor']['GHS'], 2) }} / USD {{ number_format($hybrid['extra_editor']['USD'], 2) }} each per month, without volume discounts. Website editor counts are provisioned accounts, not monthly logins.</p>
            <fieldset class="space-y-3">
                <legend class="font-semibold">Expected website usage</legend>
                @foreach(['fileStorageGiB' => 'File storage (GiB)', 'databaseGiB' => 'Logical website database data (GiB)', 'bandwidthGiB' => 'Website bandwidth (GiB/month)'] as $field => $label)
                    <label wire:key="usage-{{ $field }}" for="website-{{ $field }}" class="block space-y-2"><span>{{ $label }}</span>
                        <input id="website-{{ $field }}" type="number" min="0" max="100000" step="any" wire:model.live.debounce.400ms="{{ $field }}" class="w-full rounded-lg border border-slate-300 bg-white p-3">
                    </label>
                @endforeach
            </fieldset>
            <label class="flex items-start gap-3"><input type="checkbox" wire:model.live="includeSetup" class="mt-1"><span>New site — include the required one-time standard setup fee. Uncheck only when estimating an existing, already configured site.</span></label>
            <label class="block space-y-2" for="self-service-users"><span>Self-service active users</span>
                <input id="self-service-users" type="number" min="0" max="100000" step="1" wire:model.live.debounce.400ms="selfServiceUsers" class="w-full rounded-lg border border-slate-300 bg-white p-3">
            </label>
            <label class="block space-y-2" for="operational-users"><span>Operational active users</span>
                <input id="operational-users" type="number" min="0" max="100000" step="1" wire:model.live.debounce.400ms="operationalUsers" class="w-full rounded-lg border border-slate-300 bg-white p-3">
            </label>
            <button type="button" wire:click="hospitalExample" wire:loading.attr="disabled" class="kt-btn kt-btn-outline">Use the 1,500-person hospital example</button>
            <p class="text-sm">Example assumption: 1,350 self-service staff and 150 operational users; collaboration, HR, quality and facilities selected. Adjust these counts to match actual access.</p>
            <fieldset class="space-y-3">
                <legend class="mb-3 font-semibold">Optional module packages — per organisation / month</legend>
                @foreach($hybrid['packages'] as $slug => $package)
                    <label wire:key="package-{{ $slug }}" class="flex items-start gap-3 rounded-lg border border-slate-300 p-3">
                        <input type="checkbox" wire:model.live="selectedPackages" value="{{ $slug }}" class="mt-1">
                        <span><span class="font-semibold">{{ $package['name'] }}</span><br>
                            <span class="text-sm">GHS {{ number_format($package['GHS'], 2) }} / USD {{ number_format($package['USD'], 2) }}</span><br>
                            <span class="text-sm">{{ implode(', ', array_map(fn ($module) => ucwords(str_replace('-', ' ', $module)), $package['modules'])) }}{{ in_array('custom-domains', $package['features'], true) ? '; custom domains' : '' }}</span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            <p class="text-sm">Packages are independent. Commerce includes Store and Vendor Marketplace; it does not require HR, quality or facilities. Module subscriptions remain payable while selected, even with no active users.</p>
        </div>

        <div class="space-y-5">
            <h3 class="text-xl font-semibold">Your estimated monthly subscription</h3>
            <div wire:loading role="status" class="text-sm">Updating estimate…</div>
            <div wire:offline role="status" class="text-sm">You are offline. Reconnect to update your estimate.</div>
            <div aria-live="polite" aria-atomic="true" wire:loading.class="opacity-50" class="rounded-xl border border-slate-300 bg-white p-6">
                @if($quote)
                    <dl class="space-y-4">
                        <div class="flex justify-between gap-3"><dt>{{ $quote['website_plan'] }}</dt><dd>{{ $quote['currency'] }} {{ number_format($quote['base_fee'], 2) }}</dd></div>
                        @if($quote['extra_editors'] > 0)
                            <div class="flex justify-between gap-3"><dt>Additional website editors × {{ $quote['extra_editors'] }}</dt><dd>{{ $quote['currency'] }} {{ number_format($quote['extra_editor_total'], 2) }}</dd></div>
                        @endif
                        @foreach($quote['access_lines'] as $line)
                            <div wire:key="quote-{{ $loop->index }}" class="flex justify-between gap-3"><dt>{{ $line['name'] }} × {{ number_format($line['quantity']) }}<br><span class="text-sm">After graduated discounts</span></dt><dd>{{ $quote['currency'] }} {{ number_format($line['subtotal'], 2) }}</dd></div>
                        @endforeach
                        @foreach($quote['package_lines'] as $line)
                            <div wire:key="quote-package-{{ $loop->index }}" class="flex justify-between gap-3"><dt>{{ $line['name'] }}</dt><dd>{{ $quote['currency'] }} {{ number_format($line['subtotal'], 2) }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-3 border-t border-slate-300 pt-4 font-semibold"><dt>Monthly subtotal</dt><dd>{{ $quote['currency'] }} {{ number_format($quote['total'], 2) }}</dd></div>
                    </dl>
                    <div class="mt-4 space-y-3 border-t border-slate-300 pt-4">
                        <p class="flex justify-between gap-3"><span>One-time setup {{ $includeSetup ? '' : '(existing site)' }}</span><span>{{ $quote['currency'] }} {{ number_format($quote['setup_fee'], 2) }}</span></p>
                        <p class="flex justify-between gap-3 font-semibold"><span>Initial month including setup</span><span>{{ $quote['currency'] }} {{ number_format($quote['first_month_total'], 2) }}</span></p>
                        <p class="text-sm">Later months exclude this one-time setup fee. {{ $quote['included_editors'] }} website editors included in the selected plan.</p>
                    </div>
                    <p class="mt-4 text-sm">Access discount already included: {{ $quote['currency'] }} {{ number_format($quote['access_discount'], 2) }}.</p>
                    <p class="mt-2 text-sm">Includes the selected website allowances. Excludes email, optional dedicated database usage, applicable taxes, custom migration/design and separately agreed services. Standard setup is shown separately above. Not an invoice or clinical hospital system quotation.</p>
                @else
                    <p class="font-semibold">Check your inputs to calculate an estimate</p>
                    <ul class="mt-3 space-y-2" role="alert">@foreach($quoteErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
                @endif
            </div>
            <h3 class="text-lg font-semibold">Graduated access discounts</h3>
            <p class="text-sm">Calculated separately for each access level. The first 100 users pay full rate; users 101–500 receive 10% off; users 501–1,500 receive 20% off; users above 1,500 receive 30% off. Discounts apply only to users within each band, not to website plans, additional website editors, setup, packages or resource usage.</p>
            <h3 class="text-lg font-semibold">Additional resource usage</h3>
            <p class="text-sm">Approved additional storage: GHS {{ number_format(config('usage-pricing.meters')['storage.bytes']['GHS'], 2) }} / USD {{ number_format(config('usage-pricing.meters')['storage.bytes']['USD'], 2) }} per GiB-month, subject to a separate capacity agreement. Dedicated database: GHS {{ number_format(config('usage-pricing.meters')['db.bytes']['GHS'], 2) }} / USD {{ number_format(config('usage-pricing.meters')['db.bytes']['USD'], 2) }} per GiB-month. Shared database estimates are not billed.</p>
            <p class="text-sm">Email: GHS {{ number_format(config('usage-pricing.meters')['emails.count']['GHS'], 2) }} / USD {{ number_format(config('usage-pricing.meters')['emails.count']['USD'], 2) }} per 1,000 recipients accepted by the mail transport; excludes prepaid and system mail. Prepaid email credits replace corresponding postpaid charges.</p>
            <p class="text-sm">Currencies are independently priced. Existing agreements are preserved. The preview neither enables modules nor assigns user access levels; an administrator must explicitly save an agreement and assign access before the new model takes effect.</p>
        </div>
    </div>
</section>
