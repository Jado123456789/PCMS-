@extends('main')

@section('content')
@php
  $collectionRate = $totalBills > 0 ? round(($statusValues[0] / $totalBills) * 100) : 0;
@endphp

@include('partials.admin-styles')

<div class="container-fluid py-4">
  <div class="row g-4 mb-4">
    <div class="col-md-3">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Collection Rate</p>
          <h4 class="mb-0">{{ $collectionRate }}%</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Revenue</p>
          <h4 class="mb-0">RWF {{ number_format($totalPayments, 0) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Usage</p>
          <h4 class="mb-0">{{ number_format($totalUsage, 6) }} kWh</h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Bills</p>
          <h4 class="mb-0">{{ number_format($totalBills) }}</h4>
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

    const labels = @json($dailyLabels);
    const revenue = @json($dailyRevenue);
    const usage = @json($dailyUsage);
    const statusLabels = @json($statusLabels);
    const statusValues = @json($statusValues);

    const trendCanvas = document.getElementById('adminTrendChart');
    if (trendCanvas) {
      new Chart(trendCanvas.getContext('2d'), {
        type: 'line',
        data: {
          labels,
          datasets: [
            { label: 'Revenue (RWF)', data: revenue, borderColor: '#2dce89', backgroundColor: 'rgba(45, 206, 137, 0.12)', borderWidth: 3, fill: true, tension: 0.35 },
            { label: 'Usage (kWh)', data: usage, borderColor: '#11cdef', backgroundColor: 'rgba(17, 205, 239, 0.08)', borderWidth: 3, fill: true, tension: 0.35, yAxisID: 'usage' },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { intersect: false, mode: 'index' },
          plugins: { legend: { position: 'bottom' } },
          scales: {
            y: { beginAtZero: true },
            usage: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
            x: { grid: { display: false } },
          },
        },
      });
    }

    const statusCanvas = document.getElementById('adminStatusChart');
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
