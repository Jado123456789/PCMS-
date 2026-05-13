@extends('main')

@section('content')
<div class="container-fluid py-4">
  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="card mb-0">
        <div class="card-header pb-0">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Billing</p>
              <h5 class="mb-0">{{ $selectedDate ? 'Billing Records for ' . \Carbon\Carbon::parse($selectedDate)->format('M d, Y') : 'Billing Records' }}</h5>
            </div>
            <form method="GET" action="{{ route('bills') }}" class="d-flex align-items-center gap-2">
              <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}">
              <button type="submit" class="btn btn-sm bg-gradient-primary mb-0">View</button>
              @if ($selectedDate)
                <a href="{{ route('bills') }}" class="btn btn-sm btn-outline-secondary mb-0">All</a>
              @endif
            </form>
            <div class="d-flex gap-2">
              <a href="{{ route('reports.bills') }}" class="btn btn-outline-primary btn-sm mb-0">
                <i class="fas fa-file-csv me-2"></i>Download CSV
              </a>
              <a href="{{ route('payments') }}" class="btn btn-outline-success btn-sm mb-0">
                <i class="fas fa-wallet me-2"></i>Open Payment
              </a>
            </div>
          </div>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center justify-content-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Date</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Bill Number</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Units</th>
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
                    <td>
                      <p class="text-sm font-weight-bold mb-0">#{{ $row->transaction_id }}</p>
                    </td>
                    <td>
                      <span class="text-sm font-weight-bold mb-0">RWF {{ number_format($row->amount, 0) }}</span>
                    </td>
                    <td>
                      <span class="text-sm">{{ number_format($row->unit, 2) }} kWh</span>
                    </td>
                    <td>
                      <span class="badge badge-sm {{ $statusClass }}">{{ ucfirst($row->status) }}</span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No billing data available yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header pb-0">
          <p class="text-sm text-uppercase text-muted mb-1">Billing Summary</p>
          <h5 class="mb-0">{{ $selectedDate ? 'Selected Day' : 'Quick Totals' }}</h5>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-sm">Successful bills</span>
            <strong>{{ $successfulPaymentsCount }}</strong>
          </div>
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-sm">Pending bills</span>
            <strong>{{ $pendingPaymentsCount }}</strong>
          </div>
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-sm">Failed bills</span>
            <strong>{{ $failedPaymentsCount }}</strong>
          </div>
          <div class="d-flex justify-content-between py-2 border-bottom">
            <span class="text-sm">{{ $selectedDate ? 'Month total' : 'This month' }}</span>
            <strong>RWF {{ number_format($thisMonthTotal, 0) }}</strong>
          </div>
          <div class="d-flex justify-content-between py-2">
            <span class="text-sm">Total billed</span>
            <strong>RWF {{ number_format($totalPayments, 0) }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <footer class="footer pt-3">
    <div class="container-fluid">
      <div class="row align-items-center justify-content-lg-between">
        <div class="col-lg-6 mb-lg-0 mb-4">
          <div class="copyright text-center text-sm text-muted text-lg-start">
            &copy; <script>document.write(new Date().getFullYear())</script>, made by NIYO_7
          </div>
        </div>
      </div>
    </div>
  </footer>
</div>
@endsection
