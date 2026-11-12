@if($trend !== [])
    <section class="kt-card">
        <div class="kt-card-header"><h2 class="kt-card-title">Indicator trend</h2></div>
        <div class="kt-card-content">
            <x-chart id="indicator-trend-chart" :data="$trend" :options="[
                'maintainAspectRatio' => false,
                'scales' => [
                    'y' => ['title' => ['display' => true, 'text' => $indicator->unit]],
                    'elapsed' => [
                        'position' => 'right', 'min' => 0, 'max' => 100,
                        'title' => ['display' => true, 'text' => 'Time elapsed (%)'],
                        'grid' => ['drawOnChartArea' => false],
                    ],
                ],
            ]" />
        </div>
    </section>
@endif
