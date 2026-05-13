@extends('main')

@section('content')
@php
  $currentUnit = (float) ($meter?->unit ?? 0);
  $balanceState = $currentUnit <= 1 ? 'Critical' : ($currentUnit <= 3 ? 'Low' : 'Healthy');
  $balanceBadgeClass = $currentUnit <= 1 ? 'bg-gradient-danger' : ($currentUnit <= 3 ? 'bg-gradient-warning' : 'bg-gradient-success');
@endphp

<div class="container-fluid py-4">
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

  <div class="row g-4">
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
              @error('phone')
                <div class="text-danger text-sm mt-1">{{ $message }}</div>
              @enderror
            </div>

            <div class="mb-3">
              <label class="form-label">Amount</label>
              <div class="input-group">
                <span class="input-group-text"><i class="fas fa-money-bill-wave text-warning"></i></span>
                <input type="text" name="amount" value="{{ old('amount') }}" class="form-control" placeholder="Enter amount in RWF">
              </div>
              @error('amount')
                <div class="text-danger text-sm mt-1">{{ $message }}</div>
              @enderror
            </div>

            <div class="bg-light border rounded-3 p-3 mb-3">
              <div class="d-flex justify-content-between mb-2">
                <span class="text-sm">Current balance</span>
                <strong>{{ number_format($currentUnit, 2) }} kWh</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-sm">Successful top-ups</span>
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

    <div class="col-lg-8">
      <div class="card mb-4">
        <div class="card-header pb-0">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Payment</p>
              <h5 class="mb-0">{{ $selectedDate ? 'Payments for ' . \Carbon\Carbon::parse($selectedDate)->format('M d, Y') : 'Payment History' }}</h5>
            </div>
            <form method="GET" action="{{ route('payments') }}" class="d-flex align-items-center gap-2">
              <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}">
              <button type="submit" class="btn btn-sm bg-gradient-primary mb-0">View</button>
              @if ($selectedDate)
                <a href="{{ route('payments') }}" class="btn btn-sm btn-outline-secondary mb-0">All</a>
              @endif
            </form>
            <div class="d-flex gap-2">
              <a href="{{ route('bills') }}" class="btn btn-outline-dark btn-sm mb-0">
                <i class="fas fa-file-invoice me-2"></i>Open Billing
              </a>
            </div>
          </div>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Transaction ID</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($reportRows as $row)
                  @php
                    $statusClass = $row->status === 'success'
                      ? 'bg-gradient-success'
                      : ($row->status === 'pending' ? 'bg-gradient-warning' : 'bg-gradient-danger');
                  @endphp
                  <tr>
                    <td>
                      <div class="d-flex px-3 py-2 flex-column">
                        <h6 class="mb-0 text-sm">{{ $row->date->format('M d, Y') }}</h6>
                        <span class="text-xs text-secondary">{{ $row->date->format('H:i') }}</span>
                      </div>
                    </td>
                    <td class="text-sm font-weight-bold">#{{ $row->transaction_id }}</td>
                    <td class="text-sm">RWF {{ number_format($row->amount, 0) }}</td>
                    <td>
                      <span class="badge badge-sm {{ $statusClass }}">{{ ucfirst($row->status) }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-center text-muted py-4">No payment data available yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <p class="text-sm text-uppercase text-muted mb-1">Successful</p>
              <h4 class="mb-0">{{ $successfulPaymentsCount }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <p class="text-sm text-uppercase text-muted mb-1">Pending</p>
              <h4 class="mb-0">{{ $pendingPaymentsCount }}</h4>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <div class="card-body">
              <p class="text-sm text-uppercase text-muted mb-1">Failed</p>
              <h4 class="mb-0">{{ $failedPaymentsCount }}</h4>
            </div>
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
