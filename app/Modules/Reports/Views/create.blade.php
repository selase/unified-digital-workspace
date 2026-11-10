@extends('layouts.metronic.app')

@section('title', 'Create Custom Report')

@section('content')
<div class="flex flex-col gap-6" x-data="reportBuilder({{ Js::from($availableSources) }})">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-foreground">Custom Report Builder</h1>
            <p class="text-xs text-muted-foreground">Pick a data source, choose the columns, set a date range, and save the report.</p>
        </div>
        <a href="{{ route('reports.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Back to reports</a>
    </div>

    @if ($errors->any())
        <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('reports.store') }}" method="POST" class="flex flex-col gap-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">1. Report details</h3></div>
                <div class="kt-card-content flex flex-col gap-4">
                    <label class="flex flex-col gap-1 text-sm font-medium">Report name
                        <input type="text" name="name" class="kt-input" placeholder="e.g. Q3 high severity incidents" required value="{{ old('name') }}">
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Description (optional)
                        <textarea name="description" class="kt-input" rows="2" placeholder="What this report is for">{{ old('description') }}</textarea>
                    </label>
                    <label class="flex flex-col gap-1 text-sm font-medium">Data source
                        <select name="data_source" class="kt-select" x-model="selectedSource" required>
                            <option value="">Choose a data source</option>
                            <template x-for="(source, key) in sources" :key="key"><option :value="key" x-text="source.label"></option></template>
                        </select>
                        <span class="text-xs font-normal text-muted-foreground">Only data sources you have permission to see are listed.</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_public" value="1" id="is_public" {{ old('is_public') ? 'checked' : '' }}> Share this report with everyone in the organisation</label>
                </div>
            </div>

            <div class="kt-card">
                <div class="kt-card-header"><h3 class="kt-card-title">2. Date filter</h3></div>
                <div class="kt-card-content flex flex-col gap-4">
                    <label class="flex flex-col gap-1 text-sm font-medium" x-show="selectedSource && sources[selectedSource]?.date_columns?.length > 0">Date column to filter
                        <select name="date_column" class="kt-select">
                            <option value="">No date filter</option>
                            <template x-for="col in (sources[selectedSource]?.date_columns || [])" :key="col"><option :value="col" x-text="col"></option></template>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex flex-col gap-1 text-sm font-medium">From<input type="date" name="from_date" class="kt-input" value="{{ old('from_date') }}"></label>
                        <label class="flex flex-col gap-1 text-sm font-medium">To<input type="date" name="to_date" class="kt-input" value="{{ old('to_date') }}"></label>
                    </div>
                    <p class="rounded-lg bg-muted/30 p-3 text-xs text-muted-foreground">Exports download as CSV that opens cleanly in spreadsheet programs, with special characters and dates intact.</p>
                </div>
            </div>
        </div>

        <div class="kt-card" x-show="selectedSource">
            <div class="kt-card-header">
                <h3 class="kt-card-title">3. Columns to include</h3>
                <div class="flex gap-2">
                    <button type="button" class="kt-btn kt-btn-xs kt-btn-outline" @click="selectAllColumns()">Select all</button>
                    <button type="button" class="kt-btn kt-btn-xs kt-btn-outline" @click="deselectAllColumns()">Deselect all</button>
                </div>
            </div>
            <div class="kt-card-content grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                <template x-for="(label, col) in (sources[selectedSource]?.columns || {})" :key="col">
                    <label class="flex items-center gap-2 rounded-lg bg-muted/30 p-3 text-sm cursor-pointer">
                        <input type="checkbox" name="selected_columns[]" :value="col" :checked="selectedColumns.includes(col)" @change="toggleColumn(col)">
                        <span x-text="label"></span>
                    </label>
                </template>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('reports.index') }}" class="kt-btn kt-btn-outline">Cancel</a>
            <button type="submit" class="kt-btn kt-btn-primary">Save & run report</button>
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

