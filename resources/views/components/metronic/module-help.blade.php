@php
    $moduleHomeRoutes = [
        'document-management.index',
        'memos.index',
        'forums.hub',
        'incident-management.index',
        'hrms-core.index',
        'cms-core.index',
        'project-management.index',
        'quality-monitoring.index',
        'crm.index',
        'vendor-marketplace.index',
        'real-estate.index',
        'facilities-management.index',
        'intranet.index',
        'forms.index',
        'store.index',
        'reports.index',
        'workflow-automation.index',
    ];
@endphp

@if(in_array(request()->route()?->getName(), $moduleHomeRoutes, true))
    <aside aria-label="Getting started" class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-muted/30 p-4 text-sm">
        <p class="text-muted-foreground">New here or looking at an empty workspace? Start with the module guide.</p>
        <a href="{{ route('help.modules', [], false) }}" class="kt-btn kt-btn-sm kt-btn-outline">Module quickstarts</a>
    </aside>
@endif
