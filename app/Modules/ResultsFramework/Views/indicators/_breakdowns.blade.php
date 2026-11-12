@if($breakdowns !== [])
    <section class="kt-card">
        <div class="kt-card-header"><h2 class="kt-card-title">Breakdowns</h2></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($breakdowns as $dimension => $buckets)
                <div>
                    <h3 class="font-semibold">{{ \App\Modules\ResultsFramework\Support\Dimensions::LABELS[$dimension] }}</h3>
                    <dl class="mt-2 space-y-2">
                        @foreach($buckets as $bucket => $value)
                            <div class="flex justify-between gap-2">
                                <dt>{{ \App\Modules\ResultsFramework\Support\Dimensions::bucketLabel($dimension, (string) $bucket) }}</dt>
                                <dd>{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endforeach
        </div>
    </section>
@endif
