@extends('main')

@section('content')
@php
  $collectionRate = $totalBills > 0 ? round(($statusValues[0] / $totalBills) * 100) : 0;
@endphp

@include('partials.admin-styles')

<div class="container-fluid py-4">
  <div class="card admin-hero mb-4">
    <div class="card-body p-4 position-relative">
      <div class="row align-items-center">
        <div class="col-lg-8">
          <p class="text-white text-uppercase text-xs font-weight-bolder opacity-8 mb-2">Main overview</p>
          <h3 class="text-white mb-2">Admin Dashboard</h3>
          <p class="text-white opacity-8 mb-0">Monitor the full system health without mixing management tables into this page.</p>
        </div>
        <div class="col-lg-4 mt-4 mt-lg-0">
          <div class="bg-white border-radius-lg p-3">
            <p class="text-xs text-uppercase text-muted mb-1">Collection Rate</p>
            <div class="d-flex align-items-end justify-content-between">
              <h2 class="mb-0">{{ $collectionRate }}%</h2>
              <span class="badge bg-gradient-success">{{ $statusValues[0] }} successful</span>
            </div>
            <div class="progress mt-3" style="height: 6px;">
              <div class="progress-bar bg-gradient-success" role="progressbar" style="width: {{ $collectionRate }}%;"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
      <div class="card admin-stat-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Customers</p>
              <h4 class="mb-1">{{ number_format($totalUsers) }}</h4>
              <span class="text-xs text-muted">{{ number_format($totalMeters) }} assigned devices</span>
            </div>
            <span class="admin-stat-icon bg-gradient-primary text-white"><i class="fas fa-users"></i></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card admin-stat-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Revenue</p>
              <h4 class="mb-1">RWF {{ number_format($totalPayments, 0) }}</h4>
              <span class="text-xs text-muted">RWF {{ number_format($thisMonthRevenue, 0) }} this month</span>
            </div>
            <span class="admin-stat-icon bg-gradient-success text-white"><i class="fas fa-money-bill-wave"></i></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card admin-stat-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Energy Usage</p>
              <h4 class="mb-1">{{ number_format($totalUsage, 6) }} kWh</h4>
              <span class="text-xs text-muted">{{ number_format($todayUsage, 6) }} kWh today</span>
            </div>
            <span class="admin-stat-icon bg-gradient-info text-white"><i class="fas fa-bolt"></i></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card admin-stat-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Devices</p>
              <h4 class="mb-1">{{ $onlineMeters }} online</h4>
              <span class="text-xs text-muted">{{ $offlineMeters }} offline, {{ $lowBalanceMeters }} low balance</span>
            </div>
            <span class="admin-stat-icon bg-gradient-warning text-white"><i class="fas fa-microchip"></i></span>
          </div>
        </div>
      </div>
    </div>
  </div>

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
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
      return;
    }

    const trendCanvas = document.getElementById('adminTrendChart');
    const statusCanvas = document.getElementById('adminStatusChart');
    const labels = @json($dailyLabels);
    const revenue = @json($dailyRevenue);
    const usage = @json($dailyUsage);
    const statusLabels = @json($statusLabels);
    const statusValues = @json($statusValues);

    if (trendCanvas) {
      new Chart(trendCanvas.getContext('2d'), {
        type: 'line',
        data: {
          labels,
          datasets: [
            {
              label: 'Revenue (RWF)',
              data: revenue,
              borderColor: '#2dce89',
              backgroundColor: 'rgba(45, 206, 137, 0.12)',
              borderWidth: 3,
              fill: true,
              tension: 0.35,
              pointRadius: 3,
            },
            {
              label: 'Usage (kWh)',
              data: usage,
              borderColor: '#11cdef',
              backgroundColor: 'rgba(17, 205, 239, 0.08)',
              borderWidth: 3,
              fill: true,
              tension: 0.35,
              pointRadius: 3,
              yAxisID: 'usage',
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { intersect: false, mode: 'index' },
          plugins: { legend: { position: 'bottom' } },
          scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(148, 163, 184, 0.18)' }, ticks: { color: '#64748b' } },
            usage: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { color: '#64748b' } },
            x: { grid: { display: false }, ticks: { color: '#64748b' } },
          },
        },
      });
    }

    if (statusCanvas) {
      new Chart(statusCanvas.getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: statusLabels,
          datasets: [{ data: statusValues, backgroundColor: ['#2dce89', '#fb6340', '#f5365c'], borderWidth: 0 }],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'bottom' } },
          cutout: '68%',
        },
      });
    }
  });
</script>
@endsection
