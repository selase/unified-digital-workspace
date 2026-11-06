<p>The monthly invoice run for <strong>{{ $period }}</strong> could not create invoices for the tenants below. Nobody has been billed for them yet.</p>
<ul>
@foreach($failures as $tenant => $message)
    <li><strong>{{ $tenant }}</strong>: {{ $message }}</li>
@endforeach
</ul>
<p>Fix the cause (for example a missing price) and run <code>php artisan billing:generate-invoices --month={{ $period }}</code>; tenants that already have an invoice for the period are skipped.</p>
