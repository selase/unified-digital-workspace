@extends('layouts.metronic.app')

@section('title', 'Custom Reports & Analytics')

@section('content')
<div class="d-flex flex-column gap-7">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Custom Reports & Analytics"
        description="Cross-module data reporting, dynamic filtering, and streaming exports.">
        <x-slot:actions>
            <a href="{{ route('reports.create') }}" class="btn btn-sm btn-primary">
                <i class="ki-duotone ki-plus fs-2"></i> Create Report
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if (session('status'))
        <div class="alert alert-{{ session('status') === 'success' ? 'success' : 'danger' }} d-flex align-items-center p-5 mb-5">
            <i class="ki-duotone ki-information-5 fs-2hx text-{{ session('status') === 'success' ? 'success' : 'danger' }} me-4"></i>
            <div class="d-flex flex-column">
                <span>{{ session('message') }}</span>
            </div>
        </div>
    @endif

    {{-- Reports Registry Card --}}
    <x-metronic.card title="Saved Reports ({{ $reports->total() }})">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        <th>Report Name</th>
                        <th>Data Source</th>
                        <th>Selected Columns</th>
                        <th>Created By</th>
                        <th>Runs</th>
                        <th>Created Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-600">
                    @forelse ($reports as $rep)
                        <tr>
                            <td>
                                <a href="{{ route('reports.show', $rep) }}" class="text-gray-900 text-hover-primary fw-bold">
                                    {{ $rep->name }}
                                </a>
                                @if ($rep->description)
                                    <div class="text-muted fs-7">{{ Str::limit($rep->description, 60) }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-light-primary fw-bold">{{ ucfirst(str_replace('_', ' ', $rep->data_source)) }}</span>
                            </td>
                            <td>
                                <span class="badge badge-light-secondary">{{ count($rep->selected_columns ?? []) }} columns</span>
                            </td>
                            <td>
                                {{ $rep->creator ? $rep->creator->name : 'System / Admin' }}
                            </td>
                            <td>
                                <span class="badge badge-light-info">{{ $rep->execution_logs_count }}</span>
                            </td>
                            <td>
                                {{ $rep->created_at ? $rep->created_at->format('d/m/Y') : '-' }}
                            </td>
                            <td class="text-end">
                                <a href="{{ route('reports.show', $rep) }}" class="btn btn-icon btn-light-primary btn-sm me-1" title="Run / View Preview">
                                    <i class="ki-duotone ki-eye fs-4"></i>
                                </a>
                                <a href="{{ route('reports.export.csv', $rep) }}" class="btn btn-icon btn-light-success btn-sm me-1" title="Download CSV">
                                    <i class="ki-duotone ki-file-down fs-4"></i>
                                </a>
                                <form action="{{ route('reports.destroy', $rep) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this report?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-icon btn-light-danger btn-sm" title="Delete">
                                        <i class="ki-duotone ki-trash fs-4"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-10 text-muted">
                                <i class="ki-duotone ki-chart-simple fs-3x text-muted mb-3 d-block"></i>
                                No custom reports created yet. Click "Create Report" to start building.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reports->links() }}
        </div>
    </x-metronic.card>
</div>
@endsection

