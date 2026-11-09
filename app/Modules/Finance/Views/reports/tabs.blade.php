<div class="flex gap-2">
    <a href="{{ route('finance.reports.aged-receivables') }}" class="kt-btn kt-btn-sm {{ request()->routeIs('finance.reports.aged-receivables') ? 'kt-btn-primary' : 'kt-btn-outline' }}">Aged receivables</a>
    <a href="{{ route('finance.reports.aged-payables') }}" class="kt-btn kt-btn-sm {{ request()->routeIs('finance.reports.aged-payables') ? 'kt-btn-primary' : 'kt-btn-outline' }}">Aged payables</a>
    <a href="{{ route('finance.reports.trial-balance') }}" class="kt-btn kt-btn-sm {{ request()->routeIs('finance.reports.trial-balance') ? 'kt-btn-primary' : 'kt-btn-outline' }}">Trial balance</a>
</div>
