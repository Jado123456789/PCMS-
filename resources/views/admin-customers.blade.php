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
        <p class="text-sm text-uppercase text-muted mb-1">Management</p>
        <h5 class="mb-0">Customers</h5>
      </div>
      <a href="{{ route('admin.export.customers') }}" class="btn btn-sm bg-gradient-success mb-0">
        <i class="fas fa-download me-1"></i> Export CSV
      </a>
    </div>
    <div class="table-responsive">
      <table class="table align-items-center mb-0 admin-table">
        <thead>
          <tr>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Customer</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Phone</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Status</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Registered</th>
            <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Action</th>
          </tr>
        </thead>
        <tbody>
          @forelse($customers as $user)
            <tr>
              <td>
                <div class="px-3 py-2">
                  <h6 class="mb-0 text-sm">{{ $user->name }}</h6>
                  <p class="text-xs text-muted mb-0">{{ $user->email }}</p>
                </div>
              </td>
              <td><span class="text-sm">{{ $user->telephone ?? 'N/A' }}</span></td>
              <td>
                <span class="badge badge-sm bg-gradient-{{ $user->status === 'active' ? 'success' : 'secondary' }}">
                  {{ ucfirst($user->status) }}
                </span>
              </td>
              <td><span class="text-sm">{{ \Carbon\Carbon::parse($user->created_at)->format('M d, Y') }}</span></td>
              <td>
                <form method="POST" action="{{ route('admin.customers.toggle', $user->id) }}">
                  @csrf
                  @php $isActive = ($user->status ?? 'active') === 'active'; @endphp
                  <button type="submit" class="btn btn-sm mb-0 {{ $isActive ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    <i class="fas fa-{{ $isActive ? 'ban' : 'check' }} me-1"></i>
                    {{ $isActive ? 'Disable' : 'Enable' }}
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="4" class="text-center text-sm text-muted py-4">No customers registered yet.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
