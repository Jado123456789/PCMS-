@extends('main')

@section('content')
@php
  $collectionRate = $totalBills > 0 ? round(($statusValues[0] / $totalBills) * 100) : 0;
@endphp

@include('partials.admin-styles')

<div class="container-fluid py-4">

  {{-- Hero --}}
  <div class="card admin-hero mb-4">
    <div class="card-body p-4 position-relative">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <p class="text-white text-uppercase text-xs font-weight-bolder opacity-8 mb-2">Main overview</p>
          <h3 class="text-white mb-2">Admin Dashboard</h3>
          <p class="text-white opacity-8 mb-0">Live system health — auto-refreshes every 10 seconds.</p>
        </div>
        <div class="col-lg-4 mt-4 mt-lg-0">
          <div class="bg-white border-radius-lg p-3">
            <p class="text-xs text-uppercase text-muted mb-1">Collection Rate</p>
            <div class="d-flex align-items-end justify-content-between">
              <h2 class="mb-0" id="stat-collection">{{ $collectionRate }}%</h2>
              <span class="badge bg-gradient-success" id="stat-successful">{{ $statusValues[0] }} successful</span>
            </div>
            <div class="progress mt-3" style="height: 6px;">
              <div class="progress-bar bg-gradient-success" id="stat-progress" role="progressbar" style="width: {{ $collectionRate }}%;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Overview Cards --}}
  <div class="row g-4 mb-4">
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Customers</p>
          <h4 class="mb-0" id="stat-customers">{{ number_format($totalUsers) }}</h4>
          <span class="text-xs text-muted" id="stat-meters">{{ number_format($totalMeters) }} devices</span>
        </div>
      </div>
    </div>
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Online Meters</p>
          <h4 class="mb-0 text-success" id="stat-online">{{ $onlineMeters }}</h4>
          <span class="text-xs text-muted" id="stat-offline">{{ $offlineMeters }} offline</span>
        </div>
      </div>
    </div>
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Low Balance</p>
          <h4 class="mb-0 text-warning" id="stat-low">{{ $lowBalanceMeters }}</h4>
          <span class="text-xs text-muted">≤ 3 kWh remaining</span>
        </div>
      </div>
    </div>
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Total Revenue</p>
          <h4 class="mb-0" id="stat-revenue">RWF {{ number_format($totalPayments, 0) }}</h4>
          <span class="text-xs text-muted" id="stat-month-revenue">RWF {{ number_format($thisMonthRevenue, 0) }} this month</span>
        </div>
      </div>
    </div>
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Energy Sold</p>
          <h4 class="mb-0" id="stat-energy">{{ number_format($totalUsage, 2) }} kWh</h4>
          <span class="text-xs text-muted" id="stat-today-energy">{{ number_format($todayUsage, 4) }} kWh today</span>
        </div>
      </div>
    </div>
    <div class="col-xl-2 col-sm-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Bills</p>
          <h4 class="mb-0" id="stat-bills">{{ number_format($totalBills) }}</h4>
          <span class="text-xs text-muted" id="stat-pending">{{ $pendingBills }} pending</span>
        </div>
      </div>
    </div>
  </div>

  {{-- Alerts Panel --}}
  <div class="row mb-4" id="alerts-row" style="{{ count($alerts) === 0 ? 'display:none' : '' }}">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-header pb-0">
          <h6 class="mb-0"><i class="fas fa-exclamation-triangle text-warning me-2"></i>System Alerts</h6>
        </div>
        <div class="card-body pt-2" id="alerts-body">
          @foreach($alerts as $alert)
            <div class="alert alert-{{ $alert['type'] }} py-2 mb-2 text-sm">{{ $alert['message'] }}</div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Live Meter Table + Activity Feed --}}
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card admin-panel">
        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
          <div>
            <h6 class="mb-1">Live Meter Status</h6>
            <p class="text-sm text-muted mb-0">Auto-refreshes every 10 seconds</p>
          </div>
          <span class="badge bg-gradient-success" id="live-badge">● Live</span>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table admin-table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Meter</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Balance</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Power</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Seen</th>
                </tr>
              </thead>
              <tbody id="live-meters-body">
                <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card admin-panel h-100">
        <div class="card-header pb-0">
          <h6 class="mb-1">Recent Transactions</h6>
          <p class="text-sm text-muted mb-0">Last 10 across all customers</p>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table admin-table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                </tr>
              </thead>
              <tbody id="recent-body">
                <tr><td colspan="3" class="text-center text-muted py-3">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Charts --}}
  <div class="row g-4">
    <div class="col-lg-8">
      <div class="card admin-panel">
        <div class="card-header pb-0">
          <h6 class="mb-1">Revenue and Energy Trend</h6>
          <p class="text-sm text-muted mb-0">Last 7 days</p>
        </div>
        <div class="card-body admin-chart">
          <canvas id="adminTrendChart"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card admin-panel h-100">
        <div class="card-header pb-0">
          <h6 class="mb-1">Payment Status</h6>
          <p class="text-sm text-muted mb-0">All billing records</p>
        </div>
        <div class="card-body admin-chart">
          <canvas id="adminStatusChart"></canvas>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer pt-4">
    <div class="container-fluid px-0">
      <div class="copyright text-center text-sm text-muted text-lg-start">
        &copy; <script>document.write(new Date().getFullYear())</script>, made by NIYO_7
      </div>
    </div>
  </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

  {{-- Static Charts --}}
  const labels       = @json($dailyLabels);
  const revenue      = @json($dailyRevenue);
  const usage        = @json($dailyUsage);
  const statusLabels = @json($statusLabels);
  const statusValues = @json($statusValues);

  const trendCanvas = document.getElementById('adminTrendChart');
  if (trendCanvas && typeof Chart !== 'undefined') {
    new Chart(trendCanvas.getContext('2d'), {
      type: 'line',
      data: {
        labels,
        datasets: [
          { label: 'Revenue (RWF)', data: revenue, borderColor: '#2dce89', backgroundColor: 'rgba(45,206,137,0.12)', borderWidth: 3, fill: true, tension: 0.35, pointRadius: 3 },
          { label: 'Usage (kWh)',   data: usage,   borderColor: '#11cdef', backgroundColor: 'rgba(17,205,239,0.08)', borderWidth: 3, fill: true, tension: 0.35, pointRadius: 3, yAxisID: 'usage' },
        ],
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { intersect: false, mode: 'index' },
        plugins: { legend: { position: 'bottom' } },
        scales: {
          y:     { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.18)' }, ticks: { color: '#64748b' } },
          usage: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#64748b' } },
          x:     { grid: { display: false }, ticks: { color: '#64748b' } },
        },
      },
    });
  }

  const statusCanvas = document.getElementById('adminStatusChart');
  if (statusCanvas && typeof Chart !== 'undefined') {
    new Chart(statusCanvas.getContext('2d'), {
      type: 'doughnut',
      data: { labels: statusLabels, datasets: [{ data: statusValues, backgroundColor: ['#2dce89', '#fb6340', '#f5365c'], borderWidth: 0 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } }, cutout: '68%' },
    });
  }

  {{-- Live Refresh --}}
  const liveUrl = "{{ route('admin.dashboard.live') }}";

  function statusBadge(status) {
    const map = { online: 'bg-gradient-success', offline: 'bg-gradient-secondary', maintenance: 'bg-gradient-warning', faulty: 'bg-gradient-danger' };
    return '<span class="badge badge-sm ' + (map[status] || 'bg-gradient-secondary') + '">' + (status || 'unknown') + '</span>';
  }

  function balanceBadge(unit) {
    const u = parseFloat(unit || 0);
    const cls = u <= 0 ? 'text-danger' : (u <= 3 ? 'text-warning' : 'text-success');
    return '<span class="text-sm font-weight-bold ' + cls + '">' + u.toFixed(2) + ' kWh</span>';
  }

  function txBadge(status) {
    const map = { success: 'bg-gradient-success', pending: 'bg-gradient-warning', fail: 'bg-gradient-danger' };
    return '<span class="badge badge-sm ' + (map[status] || 'bg-gradient-secondary') + '">' + status + '</span>';
  }

  function refreshLive() {
    fetch(liveUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
      .then(r => r.json())
      .then(function (data) {

        {{-- Update meter table --}}
        const metersBody = document.getElementById('live-meters-body');
        if (data.meters && data.meters.length) {
          metersBody.innerHTML = data.meters.map(function (m) {
            return '<tr>' +
              '<td><div class="px-3 py-2"><h6 class="mb-0 text-sm">' + (m.customer_name || 'N/A') + '</h6></div></td>' +
              '<td class="text-xs text-muted">' + (m.meter_number || '—') + '</td>' +
              '<td>' + balanceBadge(m.unit) + '</td>' +
              '<td class="text-sm">' + (m.power ? parseFloat(m.power).toFixed(1) + ' W' : '—') + '</td>' +
              '<td>' + statusBadge(m.is_offline ? 'offline' : (m.device_status || 'online')) + '</td>' +
              '<td class="text-xs text-muted">' + (m.last_seen_label || '—') + '</td>' +
            '</tr>';
          }).join('');
        } else {
          metersBody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No meters found.</td></tr>';
        }

        {{-- Update recent transactions --}}
        const recentBody = document.getElementById('recent-body');
        if (data.recent && data.recent.length) {
          recentBody.innerHTML = data.recent.map(function (t) {
            return '<tr>' +
              '<td><div class="px-3 py-1"><h6 class="mb-0 text-sm">' + (t.customer_name || 'N/A') + '</h6><span class="text-xs text-muted">' + t.created_at + '</span></div></td>' +
              '<td class="text-sm font-weight-bold">RWF ' + parseInt(t.amount).toLocaleString() + '</td>' +
              '<td>' + txBadge(t.transaction_status) + '</td>' +
            '</tr>';
          }).join('');
        }

        {{-- Update alerts --}}
        const alertsRow  = document.getElementById('alerts-row');
        const alertsBody = document.getElementById('alerts-body');
        if (data.alerts && data.alerts.length) {
          alertsRow.style.display = '';
          alertsBody.innerHTML = data.alerts.map(function (a) {
            return '<div class="alert alert-' + a.type + ' py-2 mb-2 text-sm">' + a.message + '</div>';
          }).join('');
        } else {
          alertsRow.style.display = 'none';
        }

      })
      .catch(function (e) { console.warn('Live refresh failed:', e); });
  }

  refreshLive();
  setInterval(refreshLive, 10000);
});
</script>
@endsection
