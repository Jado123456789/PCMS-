@extends('main')

@section('content')
@php
  $currentUnit = (float) ($meter?->unit ?? 0);
  $balanceState = $currentUnit <= 1 ? 'Critical' : ($currentUnit <= 3 ? 'Low' : 'Healthy');
  $balanceBadgeClass = $currentUnit <= 1 ? 'bg-gradient-danger' : ($currentUnit <= 3 ? 'bg-gradient-warning' : 'bg-gradient-success');
@endphp

<div class="container-fluid py-4">
  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
  @endif
  @error('payment_error')
    <div class="alert alert-danger border-0 shadow-sm">{{ $message }}</div>
  @enderror

  <div class="row g-4">

    {{-- Top-up Form --}}
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header pb-0 border-0">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Payment</p>
              <h5 class="mb-0">Purchase Electricity</h5>
            </div>
            <span class="badge {{ $balanceBadgeClass }}">{{ $balanceState }}</span>
          </div>
        </div>
        <div class="card-body">
          <form action="{{ route('payment') }}" method="post">
            @csrf
            <div class="mb-3">
              <label class="form-label">Phone Number</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-phone text-success"></i></span>
                <input type="text" name="phone" value="{{ old('phone', Auth::user()->telephone) }}" class="form-control" placeholder="078xxxxxxx">
              </div>
              @error('phone')<div class="text-danger text-sm mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
              <label class="form-label">Amount (RWF)</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-money-bill-wave text-warning"></i></span>
                <input type="text" name="amount" value="{{ old('amount') }}" class="form-control" placeholder="Enter amount in RWF">
              </div>
              @error('amount')<div class="text-danger text-sm mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="bg-light border rounded-3 p-3 mb-3">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-sm">Current balance</span>
                <strong>{{ number_format($currentUnit, 2) }} kWh</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-sm">Total paid (successful)</span>
                <strong>RWF {{ number_format($totalPayments, 0) }}</strong>
              </div>
            </div>
            <button type="submit" class="btn bg-gradient-primary w-100 mb-0">
              <i class="fas fa-plus me-2"></i>Submit Payment
            </button>
          </form>
        </div>
      </div>
    </div>

    {{-- Transaction History + Stats --}}
    <div class="col-lg-8">

      {{-- Stats --}}
      <div class="row g-3 mb-4">
        <div class="col-md-4">
          <div class="card">
            <div class="card-body py-3">
              <p class="text-sm text-uppercase text-muted mb-1">Successful</p>
              <h4 class="mb-0 text-success">{{ $successfulPaymentsCount }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body py-3">
              <p class="text-sm text-uppercase text-muted mb-1">Pending</p>
              <h4 class="mb-0 text-warning">{{ $pendingPaymentsCount }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body py-3">
              <p class="text-sm text-uppercase text-muted mb-1">Failed</p>
              <h4 class="mb-0 text-danger">{{ $failedPaymentsCount }}</h4>
            </div>
          </div>
        </div>
      </div>

      {{-- Recent Transactions --}}
      <div class="card">
        <div class="card-header pb-0">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Recent</p>
              <h5 class="mb-0">Last 10 Transactions</h5>
            </div>
            <a href="{{ route('bills') }}" class="btn btn-sm btn-outline-primary mb-0">
              <i class="fas fa-chart-bar me-1"></i> Monthly Billing
            </a>
          </div>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Units</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Invoice</th>
                </tr>
              </thead>
              <tbody>
                @forelse($bills as $bill)
                  @php
                    $statusClass = $bill->transaction_status === 'success'
                      ? 'bg-gradient-success'
                      : ($bill->transaction_status === 'pending' ? 'bg-gradient-warning' : 'bg-gradient-danger');
                  @endphp
                  <tr>
                    <td>
                      <div class="d-flex px-3 py-2 flex-column">
                        <h6 class="mb-0 text-sm">{{ \Carbon\Carbon::parse($bill->created_at)->format('M d, Y') }}</h6>
                        <span class="text-xs text-secondary">{{ \Carbon\Carbon::parse($bill->created_at)->format('H:i') }}</span>
                      </div>
                    </td>
                    <td class="text-sm font-weight-bold">RWF {{ number_format($bill->amount, 0) }}</td>
                    <td class="text-sm">{{ number_format($bill->unit ?? ($bill->amount * 0.01), 2) }} kWh</td>
                    <td><span class="badge badge-sm {{ $statusClass }}">{{ ucfirst($bill->transaction_status) }}</span></td>
                    <td>
                      <a href="{{ route('invoices.show', $bill->transaction_id) }}" class="btn btn-link text-primary text-gradient px-2 mb-0">
                        <i class="fas fa-eye me-1"></i>View
                      </a>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No transactions yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
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
@endsection
