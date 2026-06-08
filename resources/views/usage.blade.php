@extends('main')
@section('content')
<div class="container-fluid py-4">
    <div class="row">
    <div class="col-12">
        <div class="card mb-4">
        <div class="card-header pb-0">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <p class="text-sm text-uppercase text-muted mb-1">Usage</p>
                    <h6 class="mb-0">Daily Usage</h6>
                </div>
                <form method="GET" action="{{ route('usage') }}" class="d-flex align-items-center gap-2">
                    <input type="date" id="usage-date" name="date" class="form-control form-control-sm" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}">
                    <button type="submit" class="btn btn-sm bg-gradient-primary mb-0">View</button>
                    <a id="usage-export-btn" href="{{ route('usage.export', ['date' => $selectedDate]) }}" class="btn btn-sm bg-gradient-success mb-0">Download Excel</a>
                    @if ($selectedDate !== now()->toDateString())
                        <a href="{{ route('usage') }}" class="btn btn-sm btn-outline-secondary mb-0">Today</a>
                    @endif
                </form>
            </div>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
            <div class="table-responsive p-0">
            <table class="table align-items-center justify-content-center mb-0">
                <thead>
                <tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Devices/ Load</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Reading</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>
                    <div class="d-flex px-2">
                        <div class="my-auto">
                        <h6 class="mb-0 text-sm">Bulb</h6>
                        </div>
                    </div>
                    </td>
                    <td>
                    <span id="usage-bulb" class="text-xs font-weight-bold">{{ number_format($buble, 6) }} kWh</span>
                    </td>
                </tr>
                <tr>
                    <td>
                    <div class="d-flex px-2">
                        <div class="my-auto">
                        <h6 class="mb-0 text-sm">Current</h6>
                        </div>
                    </div>
                    </td>
                    <td>
                    <span id="usage-current" class="text-xs font-weight-bold">{{ number_format($current, 2) }} A</span>
                    </td>
                </tr>
                <tr>
                    <td>
                    <div class="d-flex px-2">
                        <div class="my-auto">
                        <h6 class="mb-0 text-sm">Voltage</h6>
                        </div>
                    </div>
                    </td>
                    <td>
                    <span id="usage-voltage" class="text-xs font-weight-bold">{{ number_format($voltage, 2) }} V</span>
                    </td>
                </tr>
                <tr>
                    <td>
                    <div class="d-flex px-2">
                        <div class="my-auto">
                        <h6 class="mb-0 text-sm">Power</h6>
                        </div>
                    </div>
                    </td>
                    <td>
                    <span id="usage-power" class="text-xs font-weight-bold">{{ number_format($power, 2) }} W</span>
                    </td>
                </tr>
                </tbody>
            </table>
            </div>
        </div>
        </div>
    </div>
    </div>
    {{-- Real-time charts --}}
    <div class="row mt-2">
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header pb-0"><h6 class="mb-0">Current (A)</h6></div>
                <div class="card-body"><canvas id="chart-current" height="160"></canvas></div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header pb-0"><h6 class="mb-0">Voltage (V)</h6></div>
                <div class="card-body"><canvas id="chart-voltage" height="160"></canvas></div>
            </div>
        </div>
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header pb-0"><h6 class="mb-0">Power (W)</h6></div>
                <div class="card-body"><canvas id="chart-power" height="160"></canvas></div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header pb-0">
                    <h6 class="mb-0">Live Energy Waveform</h6>
                    <p class="text-sm text-muted mb-0">Variation of voltage and current over time collected from the smart prepaid energy meter.</p>
                </div>
                <div class="card-body"><canvas id="chart-waveform" height="80"></canvas></div>
            </div>
        </div>
    </div>

    <footer class="footer pt-3  ">
        <div class="container-fluid">
        <div class="row align-items-center justify-content-lg-between">
            <div class="col-lg-6 mb-lg-0 mb-4">
            <div class="copyright text-center text-sm text-muted text-lg-start">
                © <script>
                document.write(new Date().getFullYear())
                </script>,
                made by NIYO_7
            </div>
            </div>
        </div>
        </div>
    </footer>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const latestUrl = "{{ route('usage.latest') }}";
    const usageDate = document.getElementById('usage-date');
    const fields = {
        buble: document.getElementById('usage-bulb'),
        current: document.getElementById('usage-current'),
        voltage: document.getElementById('usage-voltage'),
        power: document.getElementById('usage-power'),
    };

    function formatReading(value, unit, decimals = 2) {
        const number = Number(value || 0);
        return number.toFixed(decimals) + ' ' + unit;
    }

    function refreshUsage() {
        const dateQuery = usageDate && usageDate.value ? '?date=' + encodeURIComponent(usageDate.value) : '';

        fetch(latestUrl + dateQuery, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
        })
            .then(function (response) {
                if (! response.ok) {
                    throw new Error('Unable to load latest usage data.');
                }

                return response.json();
            })
            .then(function (data) {
                fields.buble.textContent = formatReading(data.buble, 'kWh', 6);
                fields.current.textContent = formatReading(data.current, 'A');
                fields.voltage.textContent = formatReading(data.voltage, 'V');
                fields.power.textContent = formatReading(data.power, 'W');
            })
            .catch(function (error) {
                console.warn(error.message);
            });
    }

    refreshUsage();
    usageDate?.addEventListener('change', refreshUsage);
    setInterval(refreshUsage, 2000);

    const exportBtn = document.getElementById('usage-export-btn');
    const baseExportUrl = "{{ route('usage.export') }}";
    usageDate?.addEventListener('change', function () {
        exportBtn.href = baseExportUrl + '?date=' + encodeURIComponent(usageDate.value);
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const historyUrl = "{{ route('usage.history') }}";
    const usageDate = document.getElementById('usage-date');

    function makeChart(id, label, color) {
        return new Chart(document.getElementById(id), {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: label,
                    data: [],
                    borderColor: color,
                    backgroundColor: color + '22',
                    borderWidth: 2,
                    pointRadius: 2,
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                animation: false,
                responsive: true,
                scales: {
                    x: { ticks: { maxTicksLimit: 6 } },
                    y: { beginAtZero: false }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    const charts = {
        current: makeChart('chart-current', 'Current (A)', '#e74c3c'),
        voltage: makeChart('chart-voltage', 'Voltage (V)', '#3498db'),
        power:   makeChart('chart-power',   'Power (W)',   '#2ecc71'),
    };

    const waveform = new Chart(document.getElementById('chart-waveform'), {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Voltage (V)',
                    data: [],
                    borderColor: '#3498db',
                    backgroundColor: '#3498db22',
                    borderWidth: 2,
                    pointRadius: 2,
                    tension: 0.3,
                    fill: false,
                    yAxisID: 'yVoltage',
                },
                {
                    label: 'Current (A)',
                    data: [],
                    borderColor: '#e74c3c',
                    backgroundColor: '#e74c3c22',
                    borderWidth: 2,
                    pointRadius: 2,
                    tension: 0.3,
                    fill: false,
                    yAxisID: 'yCurrent',
                }
            ]
        },
        options: {
            animation: false,
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                x: { ticks: { maxTicksLimit: 8 } },
                yVoltage: {
                    type: 'linear',
                    position: 'left',
                    title: { display: true, text: 'Voltage (V)' },
                },
                yCurrent: {
                    type: 'linear',
                    position: 'right',
                    title: { display: true, text: 'Current (A)' },
                    grid: { drawOnChartArea: false },
                }
            },
            plugins: { legend: { display: true } }
        }
    });

    function refreshCharts() {
        const dateQuery = usageDate && usageDate.value ? '?date=' + encodeURIComponent(usageDate.value) : '';
        fetch(historyUrl + dateQuery, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        })
        .then(r => r.json())
        .then(data => {
            ['current', 'voltage', 'power'].forEach(key => {
                charts[key].data.labels = data.labels;
                charts[key].data.datasets[0].data = data[key];
                charts[key].update();
            });
            waveform.data.labels = data.labels;
            waveform.data.datasets[0].data = data.voltage;
            waveform.data.datasets[1].data = data.current;
            waveform.update();
        })
        .catch(e => console.warn(e));
    }

    refreshCharts();
    usageDate?.addEventListener('change', refreshCharts);
    setInterval(refreshCharts, 2000);
});
</script>
@endsection
