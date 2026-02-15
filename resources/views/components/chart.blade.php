@props(['id' => 'chart-' . uniqid(), 'type' => 'line', 'data' => [], 'options' => []])

<div class="relative w-full h-64">
    <canvas id="{{ $id }}" x-data="{
                chart: null,
                init() {
                    if (typeof Chart === 'undefined') {
                        return;
                    }

                    const existingChart = Chart.getChart(this.$el);
                    if (existingChart) {
                        existingChart.destroy();
                    }

                    const ctx = this.$el.getContext('2d');
                    if (!ctx) {
                        return;
                    }

                    const chartOptions = {{ Illuminate\Support\Js::from($options) }};

                    this.chart = new Chart(ctx, {
                        type: {{ Illuminate\Support\Js::from($type) }},
                        data: {{ Illuminate\Support\Js::from($data) }},
                        options: Array.isArray(chartOptions) ? {} : chartOptions
                    });
                },
                destroy() {
                    if (!this.chart) {
                        return;
                    }

                    this.chart.destroy();
                    this.chart = null;
                }
            }" x-init="init()" x-on:livewire:navigating.window="destroy()" x-on:turbo:before-cache.window="destroy()"></canvas>
</div>
