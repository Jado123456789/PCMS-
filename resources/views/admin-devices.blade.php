@extends('main')

@section('content')
@include('partials.admin-styles')

<div class="container-fluid py-4">
  @if (session('success'))
    <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
  @endif

  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Online Devices</p>
          <h4 class="mb-0">{{ $onlineMeters }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Offline Devices</p>
          <h4 class="mb-0">{{ $offlineMeters }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card admin-stat-card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Low Balance</p>
          <h4 class="mb-0">{{ $lowBalanceMeters }}</h4>
        </div>
      </div>
    </div>
  </div>

  <div class="card admin-panel">
    <div class="card-header pb-0">
      <p class="text-sm text-uppercase text-muted mb-1">Management</p>
      <h5 class="mb-0">Devices & Meters</h5>
    </div>
    <div class="card-body px-0 pt-0 pb-2">
      <div class="table-responsive">
        <table class="table align-items-center mb-0 admin-table">
          <thead>
            <tr>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Meter</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Balance</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Last Seen</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Update</th>
              <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($deviceRows as $device)
              @php
                $deviceStatus = $device->device_status ?: ((int) $device->connected === 1 ? 'online' : 'offline');
                $deviceStatusClass = $deviceStatus === 'online'
                  ? 'bg-gradient-success'
                  : ($deviceStatus === 'maintenance' ? 'bg-gradient-warning' : ($deviceStatus === 'faulty' ? 'bg-gradient-danger' : 'bg-gradient-secondary'));
                $lastSeen = $device->last_seen_at ? \Carbon\Carbon::parse($device->last_seen_at)->diffForHumans() : 'Never';
              @endphp
              <tr>
                <td>
                  <div class="px-3 py-2">
                    <h6 class="mb-0 text-sm">{{ $device->customer_name ?? 'Unassigned customer' }}</h6>
                    <p class="text-xs text-muted mb-0">{{ $device->customer_phone ?? $device->customer_email ?? 'No contact' }}</p>
                  </div>
                </td>
                <td>
                  <p class="text-sm font-weight-bold mb-0">{{ $device->meter_number ?? str_pad((string) $device->user_id, 16, '0', STR_PAD_LEFT) }}</p>
                  <p class="text-xs text-muted mb-0">{{ $device->device_name ?? 'Smart meter' }}</p>
                </td>
                <td>
                  <p class="text-sm font-weight-bold mb-0">{{ number_format((float) $device->unit, 6) }} kWh</p>
                  @if ((float) $device->unit <= 3)
                    <span class="badge badge-sm bg-gradient-warning">Low balance</span>
                  @endif
                </td>
                <td>
                  <span class="badge badge-sm {{ $deviceStatusClass }}">{{ ucfirst($deviceStatus) }}</span>
                  <p class="text-xs text-muted mb-0 mt-1">{{ (int) $device->connected === 1 ? 'Relay connected' : 'Relay disconnected' }}</p>
                </td>
                <td>
                  <span class="text-sm">{{ $lastSeen }}</span>
                  <p class="text-xs text-muted mb-0">{{ $device->location ?? 'No location' }}</p>
                </td>
                <td>
                  <form method="POST" action="{{ route('admin.devices.update', $device->user_id) }}" class="d-flex flex-column gap-2" style="min-width: 260px;">
                    @csrf
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">No.</span>
                      <input type="text" name="meter_number" class="form-control" value="{{ $device->meter_number ?? str_pad((string) $device->user_id, 16, '0', STR_PAD_LEFT) }}">
                    </div>
                    <div class="input-group input-group-sm">
                      <span class="input-group-text">Name</span>
                      <input type="text" name="device_name" class="form-control" value="{{ $device->device_name ?? 'Smart meter' }}">
                    </div>
                    <div class="d-flex gap-2">
                      <select name="device_status" class="form-control form-control-sm">
                        @foreach (['online', 'offline', 'maintenance', 'faulty'] as $status)
                          <option value="{{ $status }}" @selected($deviceStatus === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                      </select>
                      <input type="text" name="location" class="form-control form-control-sm" value="{{ $device->location }}" placeholder="Location">
                    </div>
                    <button type="submit" class="btn btn-sm bg-gradient-primary mb-0">Save Device</button>
                  </form>
                </td>
                <td>
                  <div class="d-flex flex-column gap-2" style="min-width:160px">
                    {{-- Toggle Relay --}}
                    <form method="POST" action="{{ route('admin.devices.relay', $device->user_id) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm w-100 mb-0 {{ (int) $device->connected === 1 ? 'bg-gradient-danger' : 'bg-gradient-success' }}">
                        <i class="fas fa-power-off me-1"></i>
                        {{ (int) $device->connected === 1 ? 'Cut Power' : 'Restore Power' }}
                      </button>
                    </form>
                    {{-- Manual Top-up --}}
                    <form method="POST" action="{{ route('admin.devices.topup', $device->user_id) }}" class="d-flex gap-1">
                      @csrf
                      <input type="number" name="amount" class="form-control form-control-sm" placeholder="RWF" min="1" required>
                      <button type="submit" class="btn btn-sm bg-gradient-info mb-0 text-nowrap">
                        <i class="fas fa-plus"></i> Top Up
                      </button>
                    </form>
                    {{-- Force Confirm --}}
                    <form method="POST" action="{{ route('admin.payments.confirm', $device->user_id) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-warning w-100 mb-0">
                        <i class="fas fa-check me-1"></i>Confirm Pending
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-sm text-muted py-4">No meter devices have been assigned yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
