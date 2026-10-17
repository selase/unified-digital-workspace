@extends('layouts.metronic.app')

@section('title', 'Create Custom Report')

@section('content')
<div class="d-flex flex-column gap-7" x-data="reportBuilder({{ Js::from($availableSources) }})">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Custom Report Builder"
        description="Select a module data source, pick attributes, define date ranges, and save your reporting template.">
        <x-slot:actions>
            <a href="{{ route('reports.index') }}" class="btn btn-sm btn-light">
                <i class="ki-duotone ki-arrow-left fs-2"></i> Back to Reports
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if ($errors->any())
        <div class="alert alert-danger p-5 mb-5">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('reports.store') }}" method="POST">
        @csrf

        <div class="row g-7">
            {{-- General Info Card --}}
            <div class="col-lg-6">
                <x-metronic.card title="1. Report Details">
                    <div class="mb-5">
                        <label class="form-label required fw-bold">Report Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Q3 High Severity Incidents" required value="{{ old('name') }}">
                    </div>

                    <div class="mb-5">
                        <label class="form-label fw-bold">Description (Optional)</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Summary of this report's purpose...">{{ old('description') }}</textarea>
                    </div>

                    <div class="mb-5">
                        <label class="form-label required fw-bold">Data Source</label>
                        <select name="data_source" class="form-select" x-model="selectedSource" required>
                            <option value="">-- Choose Data Source --</option>
                            <template x-for="(source, key) in sources" :key="key">
                                <option :value="key" x-text="source.label"></option>
                            </template>
                        </select>
                        <div class="form-text">Data sources are strictly allowlisted and protected by module RBAC permissions.</div>
                    </div>

                    <div class="form-check form-switch form-check-custom form-check-solid mb-3">
                        <input class="form-check-input" type="checkbox" name="is_public" value="1" id="is_public" {{ old('is_public') ? 'checked' : '' }} />
                        <label class="form-check-label fw-semibold text-gray-700" for="is_public">
                            Share Report Template with all organization members
                        </label>
                    </div>
                </x-metronic.card>
            </div>

            {{-- Date Filters Card --}}
            <div class="col-lg-6">
                <x-metronic.card title="2. Date Filtering">
                    <div class="mb-5" x-show="selectedSource && sources[selectedSource]?.date_columns?.length > 0">
                        <label class="form-label fw-bold">Date Column to Filter</label>
                        <select name="date_column" class="form-select">
                            <option value="">-- No Date Filter --</option>
                            <template x-for="col in (sources[selectedSource]?.date_columns || [])" :key="col">
                                <option :value="col" x-text="col"></option>
                            </template>
                        </select>
                    </div>

                    <div class="row g-4 mb-5">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="{{ old('from_date') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="{{ old('to_date') }}">
                        </div>
                    </div>

                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4">
                        <i class="ki-duotone ki-information-5 fs-2x text-primary me-3"></i>
                        <div class="fs-7 text-gray-700">
                            Reports stream directly to CSV formatted for spreadsheet software with UTF-8 BOM encoding for special characters and dates.
                        </div>
                    </div>
                </x-metronic.card>
            </div>

            {{-- Column Picker Card --}}
            <div class="col-12" x-show="selectedSource">
                <x-metronic.card title="3. Select Columns to Include">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <span class="text-muted fs-7">Pick the fields to include in table previews and exported CSVs:</span>
                        <div>
                            <button type="button" class="btn btn-xs btn-light-primary me-2" @click="selectAllColumns()">Select All</button>
                            <button type="button" class="btn btn-xs btn-light" @click="deselectAllColumns()">Deselect All</button>
                        </div>
                    </div>

                    <div class="row g-3">
                        <template x-for="(label, col) in (sources[selectedSource]?.columns || {})" :key="col">
                            <div class="col-md-4 col-sm-6">
                                <label class="form-check form-check-custom form-check-solid p-3 rounded border border-gray-200 cursor-pointer">
                                    <input class="form-check-input" type="checkbox" name="selected_columns[]" :value="col" :checked="selectedColumns.includes(col)" @change="toggleColumn(col)" />
                                    <span class="form-check-label fw-semibold text-gray-800" x-text="label"></span>
                                </label>
                            </div>
                        </template>
                    </div>
                </x-metronic.card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-3 mt-7">
            <a href="{{ route('reports.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="ki-duotone ki-check fs-2"></i> Save & Run Report
            </button>
        </div>
    </form>
</div>

<script>
    function reportBuilder(sources) {
        return {
            sources: sources,
            selectedSource: Object.keys(sources)[0] || '',
            selectedColumns: [],
            init() {
                this.updateColumns();
                this.$watch('selectedSource', () => {
                    this.updateColumns();
                });
            },
            updateColumns() {
                if (this.selectedSource && this.sources[this.selectedSource]) {
                    this.selectedColumns = Object.keys(this.sources[this.selectedSource].columns);
                } else {
                    this.selectedColumns = [];
                }
            },
            selectAllColumns() {
                if (this.selectedSource && this.sources[this.selectedSource]) {
                    this.selectedColumns = Object.keys(this.sources[this.selectedSource].columns);
                }
            },
            deselectAllColumns() {
                this.selectedColumns = [];
            },
            toggleColumn(col) {
                const idx = this.selectedColumns.indexOf(col);
                if (idx > -1) {
                    this.selectedColumns.splice(idx, 1);
                } else {
                    this.selectedColumns.push(col);
                }
            }
        };
    }
</script>
@endsection

