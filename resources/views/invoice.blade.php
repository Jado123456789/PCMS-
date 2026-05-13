@extends('main')

@section('content')
<div class="container-fluid py-4">
  <div class="card mb-4">
    <div class="card-header pb-0">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
          <p class="text-sm text-uppercase text-muted mb-1">Invoice</p>
          <h5 class="mb-0">{{ $selectedDate ? 'Invoices for ' . \Carbon\Carbon::parse($selectedDate)->format('M d, Y') : 'Customer Invoices' }}</h5>
        </div>
        <form method="GET" action="{{ route('invoices') }}" class="d-flex align-items-center gap-2">
          <input type="date" name="date" class="form-control form-control-sm" value="{{ $selectedDate }}" max="{{ now()->toDateString() }}">
          <button type="submit" class="btn btn-sm bg-gradient-primary mb-0">View</button>
          @if ($selectedDate)
            <a href="{{ route('invoices') }}" class="btn btn-sm btn-outline-secondary mb-0">All</a>
          @endif
        </form>
        <div class="d-flex gap-2">
          <a href="{{ route('bills') }}" class="btn btn-outline-dark btn-sm mb-0">
            <i class="fas fa-file-invoice-dollar me-2"></i>Open Billing
          </a>
          <a href="{{ route('reports.invoices') }}" class="btn btn-outline-primary btn-sm mb-0">
            <i class="fas fa-file-csv me-2"></i>Download Invoice
          </a>
        </div>
      </div>
    </div>
    <div class="card-body px-0 pt-0 pb-2">
      <div class="table-responsive p-0">
        <table class="table align-items-center mb-0">
          <thead>
            <tr>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Invoice</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone Number</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Unit</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">View</th>
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
                    <h6 class="mb-0 text-sm">INV-{{ $row->transaction_id }}</h6>
                    <span class="text-xs text-secondary">Transaction #{{ $row->transaction_id }}</span>
                  </div>
                </td>
                <td>
                  <p class="text-sm font-weight-bold mb-0">{{ $row->date->format('M d, Y') }}</p>
                  <span class="text-xs text-secondary">{{ $row->date->format('H:i') }}</span>
                </td>
                <td class="text-sm">{{ Auth::user()->telephone ?? 'N/A' }}</td>
                <td class="text-sm">{{ number_format($row->unit, 2) }} kWh</td>
                <td class="text-sm font-weight-bold">RWF {{ number_format($row->amount, 0) }}</td>
                <td>
                  <span class="badge badge-sm {{ $statusClass }}">{{ ucfirst($row->status) }}</span>
                </td>
                <td>
                  <a href="{{ route('invoices.show', $row->transaction_id) }}" class="btn btn-link text-primary text-gradient px-2 mb-0">
                    <i class="fas fa-eye me-1"></i>View
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No invoice data available yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <footer class="footer pt-3">
    <div class="container-fluid">
      <div class="copyright text-center text-sm text-muted text-lg-start">
        &copy; <script>document.write(new Date().getFullYear())</script>, made by NIYO_7
      </div>
    </div>
  </footer>
</div>
@endsection
