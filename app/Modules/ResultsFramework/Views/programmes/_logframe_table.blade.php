<table class="kt-table table-auto kt-table-border">
    <thead><tr>@foreach($headings as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
    <tbody>
        @forelse($rows as $row)
            @foreach($row->indicators ?: [null] as $entry)
                <tr>
                    <td>{{ ucfirst($row->level) }}</td>
                    <th scope="row" style="padding-left: {{ 6 + 12 * $row->depth }}px">{{ $row->title }}</th>
                    <td>{{ $entry['name'] ?? '' }}</td>
                    <td>{{ $entry['baseline'] ?? '' }}</td>
                    <td>{{ $entry['target'] ?? '' }}</td>
                    <td>
                        {{ $entry['current'] ?? '' }}
                        @foreach($entry['breakdowns'] ?? [] as $dimension => $buckets)
                            <div>
                                <strong>{{ \App\Modules\ResultsFramework\Support\Dimensions::LABELS[$dimension] }}</strong>
                                @if($buckets === null)
                                    <div>{{ \App\Modules\ResultsFramework\Services\BreakdownService::HIDDEN_NOTE }}</div>
                                @else
                                    @foreach($buckets as $bucket => $value)
                                        <div>{{ \App\Modules\ResultsFramework\Support\Dimensions::bucketLabel($dimension, (string) $bucket) }}: {{ $value }}</div>
                                    @endforeach
                                @endif
                            </div>
                        @endforeach
                    </td>
                    <td>{{ $entry['status'] ?? '' }}</td>
                    <td>{{ $entry['means_of_verification'] ?? '' }}</td>
                    <td>{{ $row->assumptions }}</td>
                    <td>{{ $row->risks }}</td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="{{ count($headings) }}">No results have been added to this programme.</td></tr>
        @endforelse
    </tbody>
    @if($omissions !== null)
        <tfoot><tr><td colspan="{{ count($headings) }}">{{ $omissions }}</td></tr></tfoot>
    @endif
</table>
