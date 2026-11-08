@php
    $tabs = [
        'finance.index' => ['Overview', 'finance.index'],
        'finance.invoices' => ['Invoices', 'finance.invoices.index'],
        'finance.quotes' => ['Quotes', 'finance.invoices.index', ['type' => 'quote']],
        'finance.recurring' => ['Recurring', 'finance.recurring.index'],
        'finance.payments' => ['Payments', 'finance.payments.index'],
        'finance.contacts' => ['Customers', 'finance.contacts.index'],
        'finance.items' => ['Items', 'finance.items.index'],
        'finance.accounts' => ['Chart of accounts', 'finance.accounts.index'],
        'finance.journals' => ['Journals', 'finance.journals.index'],
        'finance.reports' => ['Reports', 'finance.reports.aged-receivables'],
        'finance.settings' => ['Settings', 'finance.settings.edit'],
    ];
    $current = request()->route()?->getName() ?? '';
    $isQuotes = str_starts_with($current, 'finance.invoices') && request('type') === 'quote';
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-1 rounded-lg bg-muted/30 p-1">
        @foreach($tabs as $key => $tab)
            @php
                $active = $key === 'finance.quotes'
                    ? $isQuotes
                    : (! $isQuotes && ($key === 'finance.index' ? $current === 'finance.index' : str_starts_with($current, $key)));
            @endphp
            <a href="{{ route($tab[1], $tab[2] ?? []) }}" class="kt-btn kt-btn-sm {{ $active ? 'kt-btn-primary' : 'kt-btn-ghost' }}">{{ $tab[0] }}</a>
        @endforeach
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-success/10 p-3 text-sm text-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
