@extends('main')

@section('content')
@include('partials.admin-styles')

<div class="container-fluid py-4">
  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
  @endif

  <div class="card admin-panel">
    <div class="card-header pb-0 d-flex justify-content-between align-items-center">
      <div>
        <p class="text-sm text-uppercase text-muted mb-1">Add-ons</p>
        <h5 class="mb-0">Payments</h5>
      </div>
      <a href="{{ route('admin.export.payments') }}" class="btn btn-sm bg-gradient-success mb-0">
        <i class="fas fa-download me-1"></i> Export CSV
      </a>
    </div>
    <div class="table-responsive">
      <table class="table align-items-center mb-0 admin-table">
        <thead>
          <tr>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Invoice</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Customer</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Date</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($recentBills as $bill)
            @php
              $billStatus = $bill->transaction_status ?? 'unknown';
              $billStatusClass = $billStatus === 'success'
                ? 'bg-gradient-success'
                : ($billStatus === 'pending' ? 'bg-gradient-warning' : 'bg-gradient-danger');
            @endphp
            <tr>
              <td>
                <p class="text-xs font-weight-bold mb-0">#{{ $bill->id }}</p>
                <p class="text-xxs text-muted mb-0">{{ $bill->transaction_id ?? 'No reference' }}</p>
              </td>
              <td>
                <p class="text-xs font-weight-bold mb-0">{{ $bill->customer_name ?? 'Customer #' . $bill->user_id }}</p>
                <p class="text-xxs text-muted mb-0">{{ $bill->customer_email ?? '' }}</p>
              </td>
              <td><p class="text-xs font-weight-bold mb-0">RWF {{ number_format($bill->amount, 0) }}</p></td>
              <td><span class="badge badge-sm {{ $billStatusClass }}">{{ ucfirst($billStatus) }}</span></td>
              <td><span class="text-xs">{{ \Carbon\Carbon::parse($bill->created_at)->format('M d, Y H:i') }}</span></td>
              <td>
                @if ($billStatus === 'pending')
                  <form method="POST" action="{{ route('admin.payments.confirm', $bill->user_id) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm bg-gradient-success mb-0">
                      <i class="fas fa-check me-1"></i>Confirm
                    </button>
                  </form>
                @else
                  <span class="text-xs text-muted">—</span>
                @endif
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-sm text-muted py-4">No payments recorded yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
