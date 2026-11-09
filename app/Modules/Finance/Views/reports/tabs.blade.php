@php
    $reportTabs = [
        'finance.reports.profit-and-loss' => 'Profit and loss',
        'finance.reports.balance-sheet' => 'Balance sheet',
        'finance.reports.cash-flow' => 'Cash flow',
        'finance.reports.funds' => 'Funds',
        'finance.reports.aged-receivables' => 'Aged receivables',
        'finance.reports.aged-payables' => 'Aged payables',
        'finance.reports.trial-balance' => 'Trial balance',
    ];
@endphp
<div class="flex flex-wrap gap-2">
    @foreach($reportTabs as $route => $label)
        <a href="{{ route($route) }}" class="kt-btn kt-btn-sm {{ request()->routeIs($route) ? 'kt-btn-primary' : 'kt-btn-outline' }}">{{ $label }}</a>
    @endforeach
</div>
