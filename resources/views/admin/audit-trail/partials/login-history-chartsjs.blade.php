<script>
    const chartConfigs = [
        {
            elementId: 'browser-session',
            seriesName: 'Browser',
            data: [
                @foreach ($loggedInBrowserChartForCurrentMonth as $key => $value)
                    { value: {{ (int) $value }}, name: @json($key) },
                @endforeach
            ],
        },
        {
            elementId: 'location-session',
            seriesName: 'Locations',
            data: [
                @foreach ($loggedInLocationChartForCurrentMonth as $key => $value)
                    { value: {{ (int) $value }}, name: @json($key) },
                @endforeach
            ],
        },
        {
            elementId: 'platform-session',
            seriesName: 'Platform/OS',
            data: [
                @foreach ($loggedInPlatformChartForCurrentMonth as $key => $value)
                    { value: {{ (int) $value }}, name: @json($key) },
                @endforeach
            ],
        },
        {
            elementId: 'client-device-session',
            seriesName: 'Devices',
            data: [
                @foreach ($loggedInClientDeviceChartForCurrentMonth as $key => $value)
                    { value: {{ (int) $value }}, name: @json(strtoupper($key)) },
                @endforeach
            ],
        },
    ];

    const chartInstances = [];

    const createDonutChart = (elementId, seriesName, data) => {
        const chartElement = document.getElementById(elementId);
        if (!chartElement) {
            return null;
        }

        const chart = echarts.init(chartElement);
        chart.setOption({
            tooltip: {
                trigger: 'item',
            },
            legend: {
                top: '5%',
                left: 'center',
            },
            series: [
                {
                    name: seriesName,
                    type: 'pie',
                    radius: ['40%', '70%'],
                    avoidLabelOverlap: false,
                    itemStyle: {
                        borderRadius: 10,
                        borderColor: '#fff',
                        borderWidth: 2,
                    },
                    label: {
                        show: false,
                        position: 'center',
                    },
                    emphasis: {
                        label: {
                            show: true,
                            fontSize: 32,
                            fontWeight: 'bold',
                        },
                    },
                    labelLine: {
                        show: false,
                    },
                    data,
                },
            ],
        });

        return chart;
    };

    chartConfigs.forEach(({ elementId, seriesName, data }) => {
        const chart = createDonutChart(elementId, seriesName, data);
        if (chart) {
            chartInstances.push(chart);
        }
    });

    window.addEventListener('resize', () => {
        chartInstances.forEach((chart) => {
            chart.resize();
        });
    });
</script>
