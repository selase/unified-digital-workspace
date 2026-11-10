@php
    $reportTabs = [
        'finance.reports.profit-and-loss' => 'Profit and loss',
        'finance.reports.balance-sheet' => 'Balance sheet',
        'finance.reports.cash-flow' => 'Cash flow',
        'finance.reports.funds' => 'Funds',
        'finance.reports.vat' => 'VAT',
        'finance.reports.aged-receivables' => 'Aged receivables',
        'finance.reports.aged-payables' => 'Aged payables',
        'finance.reports.trial-balance' => 'Trial balance',
        'finance.reports.general-ledger' => 'General ledger',
    ];
@endphp
<div class="flex flex-wrap gap-2">
    @foreach($reportTabs as $route => $label)
        <a href="{{ route($route) }}" class="kt-btn kt-btn-sm {{ request()->routeIs($route) ? 'kt-btn-primary' : 'kt-btn-outline' }}">{{ $label }}</a>
    @endforeach
</div>
<div class="flex gap-2 print:hidden">
    <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="kt-btn kt-btn-sm kt-btn-ghost">Download CSV</a>
    <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank" class="kt-btn kt-btn-sm kt-btn-ghost">Download PDF</a>
    <button type="button" class="kt-btn kt-btn-sm kt-btn-ghost" onclick="window.print()">Print / save as PDF</button>
</div>
