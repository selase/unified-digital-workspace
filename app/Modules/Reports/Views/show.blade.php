@extends('layouts.metronic.app')

@section('title', 'Report: ' . $report->name)

@section('content')
<div class="d-flex flex-column gap-7">
    {{-- Page Header --}}
    <x-metronic.page-header
        :title="$report->name"
        :description="$report->description ?? 'Custom generated analytics report for ' . ucfirst(str_replace('_', ' ', $report->data_source))">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light me-2">
                <i class="ki-duotone ki-arrow-left fs-2"></i> All Reports
            </a>
            <a href="{{ route('reports.export.csv', $report) }}" class="btn btn-sm btn-success">
                <i class="ki-duotone ki-file-down fs-2"></i> Export CSV
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

    {{-- Metadata Summary Cards --}}
    <div class="row g-5">
        <div class="col-md-3">
            <div class="card card-flush py-4">
                <div class="card-body py-2">
                    <span class="text-gray-500 fs-7 fw-bold text-uppercase">Data Source</span>
                    <div class="fs-4 fw-bolder text-gray-800 mt-1">{{ ucfirst(str_replace('_', ' ', $report->data_source)) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush py-4">
                <div class="card-body py-2">
                    <span class="text-gray-500 fs-7 fw-bold text-uppercase">Total Matches</span>
                    <div class="fs-4 fw-bolder text-primary mt-1">{{ number_format($preview['total']) }} records</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush py-4">
                <div class="card-body py-2">
                    <span class="text-gray-500 fs-7 fw-bold text-uppercase">Columns</span>
                    <div class="fs-4 fw-bolder text-gray-800 mt-1">{{ count($preview['headers']) }} selected</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card card-flush py-4">
                <div class="card-body py-2">
                    <span class="text-gray-500 fs-7 fw-bold text-uppercase">Created At</span>
                    <div class="fs-4 fw-bolder text-gray-800 mt-1">{{ $report->created_at ? $report->created_at->format('d/m/Y') : '-' }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Preview Table Card --}}
    <x-metronic.card title="Report Preview (Showing first {{ count($preview['rows']) }} of {{ $preview['total'] }} records)">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-500 fw-bold fs-7 text-uppercase gs-0">
                        @foreach ($preview['headers'] as $col => $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="fw-semibold text-gray-700">
                    @forelse ($preview['rows'] as $row)
                        <tr>
                            @foreach ($preview['headers'] as $col => $label)
                                <td>
                                    @php $val = $row[$col] ?? null; @endphp
                                    @if ($val === null || $val === '')
                                        <span class="text-muted">-</span>
                                    @elseif (is_bool($val))
                                        <span class="badge badge-light-{{ $val ? 'success' : 'secondary' }}">{{ $val ? 'Yes' : 'No' }}</span>
                                    @elseif (str_contains($col, 'price') || str_contains($col, 'amount'))
                                        <span class="fw-bold">GHS {{ number_format(((float) $val) / 100, 2) }}</span>
                                    @elseif (str_contains($col, 'status') || str_contains($col, 'severity'))
                                        <span class="badge badge-light-primary">{{ ucfirst((string) $val) }}</span>
                                    @else
                                        {{ (string) $val }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ max(1, count($preview['headers'])) }}" class="text-center py-10 text-muted">
                                <i class="ki-duotone ki-information fs-3x text-muted mb-3 d-block"></i>
                                No records matched the specified report filters for this organization.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-metronic.card>
</div>
@endsection

