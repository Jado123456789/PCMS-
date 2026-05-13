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
});
</script>
@endsection
