@extends('main')

@section('content')
@php
  $currentUnit = (float) ($meter?->unit ?? 0);
  $balanceState = $currentUnit <= 1 ? 'Critical' : ($currentUnit <= 3 ? 'Low' : 'Healthy');
  $balanceBadgeClass = $currentUnit <= 1 ? 'bg-gradient-danger' : ($currentUnit <= 3 ? 'bg-gradient-warning' : 'bg-gradient-success');
  $formattedUserId = implode(' ', str_split(str_pad((string) Auth::id(), 16, '0', STR_PAD_LEFT), 4));
@endphp

<style>
  .dashboard-hero {
    position: relative;
    overflow: hidden;
    background: linear-gradient(135deg, #17324d 0%, #1f5f83 50%, #5fa8d3 100%);
    border-radius: 1.5rem;
    box-shadow: 0 1.5rem 3rem rgba(17, 37, 56, 0.16);
  }

  .dashboard-hero::after {
    content: "";
    position: absolute;
    inset: auto -8% -45% auto;
    width: 280px;
    height: 280px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
  }

  .metric-card {
    border: 1px solid rgba(15, 23, 42, 0.06);
    border-radius: 1rem;
    background: #fff;
    height: 100%;
  }

  .metric-icon {
    width: 52px;
    height: 52px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    font-size: 1.15rem;
  }

  .soft-surface {
    border-radius: 1rem;
    background: #f8fafc;
    border: 1px solid rgba(148, 163, 184, 0.2);
  }

  .account-summary {
    position: relative;
    z-index: 1;
    background: rgba(255, 255, 255, 0.94);
  }

  .account-reference {
    letter-spacing: 0.08em;
    font-weight: 700;
  }

  .section-link {
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 1rem;
    background: #fff;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }

  .section-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 1rem 1.75rem rgba(15, 23, 42, 0.08);
  }

</style>

<div class="container-fluid py-4">
  @if (session('welcome'))
    <div class="alert alert-info text-white bg-gradient-info border-0 shadow-sm">
      <strong>Welcome.</strong> {{ session('welcome') }}
    </div>
  @endif

  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm">
      {{ session('success') }}
    </div>
  @endif

  @error('payment_error')
    <div class="alert alert-danger border-0 shadow-sm">
      {{ $message }}
    </div>
  @enderror

  @error('message_er')
    <div class="alert alert-warning border-0 shadow-sm">
      {{ $message }}
    </div>
  @enderror

  <div class="card dashboard-hero border-0 mb-4">
    <div class="card-body p-4 p-lg-5 position-relative">
      <div class="row align-items-stretch">
        <div class="col-12">
          <div class="soft-surface account-summary p-4 p-lg-5">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
              <div>
                <p class="text-xs text-uppercase text-muted mb-1">Account Reference</p>
                <h3 class="account-reference mb-0">{{ $formattedUserId }}</h3>
              </div>
              <span class="badge {{ $balanceBadgeClass }}">{{ $balanceState }}</span>
            </div>
            <div class="row g-4">
              <div class="col-md-6">
                <p class="text-sm text-muted mb-1">Account Holder</p>
                <h5 class="mb-0">{{ Auth::user()->name }}</h5>
              </div>
              <div class="col-md-6">
                <p class="text-sm text-muted mb-1">Current Energy Balance</p>
                <h3 id="meter-unit" class="mb-0">{{ number_format($currentUnit, 6) }} kWh</h3>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-xl-3 col-sm-6">
      <div class="card metric-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Current Balance</p>
              <h4 class="mb-1" id="meter-unit-card">{{ number_format($currentUnit, 6) }} kWh</h4>
              <span class="text-xs text-muted">Available electricity units</span>
            </div>
            <span class="metric-icon bg-gradient-primary text-white">
              <i class="fas fa-bolt"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card metric-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Last Payment</p>
              <h4 class="mb-1">RWF {{ number_format($recent_payment, 0) }}</h4>
              <span class="text-xs text-muted">Most recent recharge amount</span>
            </div>
            <span class="metric-icon bg-gradient-success text-white">
              <i class="fas fa-wallet"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card metric-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Billing Records</p>
              <h4 class="mb-1">{{ $reportRows->count() }}</h4>
              <span class="text-xs text-muted">{{ $successfulPaymentsCount }} successful bills</span>
            </div>
            <span class="metric-icon bg-gradient-info text-white">
              <i class="fas fa-file-invoice"></i>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card metric-card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">This Month</p>
              <h4 class="mb-1">RWF {{ number_format($thisMonthTotal, 0) }}</h4>
              <span class="text-xs text-muted">{{ $pendingPaymentsCount }} pending, {{ $failedPaymentsCount }} failed</span>
            </div>
            <span class="metric-icon bg-gradient-warning text-white">
              <i class="fas fa-chart-line"></i>
            </span>
          </div>
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
  const refreshIntervalMs = 15000;

  function runTask() {
    return fetch("{{ route('run.task') }}", {
      method: 'GET',
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => response.json())
      .catch(error => console.error('Error confirming payment:', error));
  }

  function fetchMeterUnit() {
    return fetch('/meter-unit', {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(response => response.json())
      .then(data => {
        const formattedUnit = Number(data.unit || 0).toFixed(6) + ' kWh';
        document.getElementById('meter-unit').innerHTML = formattedUnit;
        document.getElementById('meter-unit-card').innerHTML = formattedUnit;
        return data;
      })
      .catch(error => console.error('Error fetching meter data:', error));
  }

  function refreshDashboardUnit() {
    Promise.allSettled([runTask(), fetchMeterUnit()]);
  }

  document.addEventListener('DOMContentLoaded', function () {
    refreshDashboardUnit();
    window.setInterval(refreshDashboardUnit, refreshIntervalMs);
  });
</script>
@endsection
