@extends('main')

@section('content')
@php
  $statusClass = $status === 'success'
    ? 'bg-gradient-success'
    : ($status === 'pending' ? 'bg-gradient-warning' : 'bg-gradient-danger');
@endphp

<div class="container-fluid py-4">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="card">
        <div class="card-header pb-0">
          <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Invoice Details</p>
              <h4 class="mb-0">INV-{{ $bill->transaction_id }}</h4>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
              <a href="{{ route('invoices') }}" class="btn btn-outline-dark btn-sm mb-0">
                <i class="fas fa-arrow-left me-2"></i>Back
              </a>
              <button type="button" class="btn btn-outline-primary btn-sm mb-0" onclick="window.print()">
                <i class="fas fa-print me-2"></i>Print
              </button>
            </div>
          </div>
        </div>
        <div class="card-body">
          <div class="row g-4 mb-4">
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <p class="text-sm text-uppercase text-muted mb-2">Customer</p>
                <h6 class="mb-1">{{ Auth::user()->name }}</h6>
                <p class="text-sm mb-1">{{ Auth::user()->email }}</p>
                <p class="text-sm mb-0">{{ Auth::user()->telephone ?? 'N/A' }}</p>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <p class="text-sm text-uppercase text-muted mb-2">Invoice</p>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-sm">Date</span>
                  <strong class="text-sm">{{ $invoiceDate->format('M d, Y H:i') }}</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-sm">Status</span>
                  <span class="badge badge-sm {{ $statusClass }}">{{ ucfirst($status) }}</span>
                </div>
                <div class="d-flex justify-content-between">
                  <span class="text-sm">Transaction</span>
                  <strong class="text-sm">#{{ $bill->transaction_id }}</strong>
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Description</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Unit</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <div class="py-2">
                      <h6 class="mb-0 text-sm">Electricity recharge</h6>
                      <span class="text-xs text-secondary">Power consumption meter top-up</span>
                    </div>
                  </td>
                  <td class="text-sm">{{ number_format($unit, 2) }} kWh</td>
                  <td class="text-sm font-weight-bold">RWF {{ number_format($amount, 0) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end mt-4">
            <div class="col-md-4 px-0">
              <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-sm">Subtotal</span>
                <strong>RWF {{ number_format($amount, 0) }}</strong>
              </div>
              <div class="d-flex justify-content-between py-2">
                <span class="text-sm">Total</span>
                <h5 class="mb-0">RWF {{ number_format($amount, 0) }}</h5>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
