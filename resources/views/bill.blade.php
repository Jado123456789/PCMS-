@extends('main')

@section('content')
<div class="container-fluid py-4">

  {{-- Current Month Summary Cards --}}
  <div class="row g-4 mb-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">This Month Units</p>
          <h4 class="mb-0">{{ number_format($thisMonth?->total_units ?? 0, 2) }} kWh</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">This Month Charge</p>
          <h4 class="mb-0">RWF {{ number_format($thisMonth?->charge ?? 0, 0) }}</h4>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <div class="card-body">
          <p class="text-sm text-uppercase text-muted mb-1">Remaining Balance</p>
          <h4 class="mb-0">{{ number_format($currentUnit, 2) }} kWh</h4>
        </div>
      </div>
    </div>
  </div>

  {{-- Monthly Billing Table --}}
  <div class="row">
    <div class="col-12">
      <div class="card">
        <div class="card-header pb-0">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <p class="text-sm text-uppercase text-muted mb-1">Billing</p>
              <h5 class="mb-0">Monthly Charges</h5>
              <p class="text-xs text-muted mb-0">Rate: RWF {{ number_format($rate, 0) }} per kWh</p>
            </div>
            <a href="{{ route('payments') }}" class="btn btn-sm bg-gradient-primary mb-0">
              <i class="fas fa-wallet me-1"></i> Make Payment
            </a>
          </div>
        </div>
        <div class="card-body px-0 pt-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Month</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Units (kWh)</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Amount Paid (RWF)</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Charge (RWF)</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Transactions</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Details</th>
                </tr>
              </thead>
              <tbody>
                @forelse($monthlyRows as $row)
                  <tr class="month-row" style="cursor:pointer"
                      data-year="{{ $row->year }}"
                      data-month="{{ $row->month }}"
                      data-label="{{ $row->label }}">
                    <td>
                      <div class="d-flex px-3 py-2 align-items-center gap-2">
                        <i class="fas fa-chevron-right text-xs text-muted toggle-icon"></i>
                        <h6 class="mb-0 text-sm">{{ $row->label }}</h6>
                      </div>
                    </td>
                    <td class="text-sm">{{ number_format($row->total_units, 2) }} kWh</td>
                    <td class="text-sm font-weight-bold">RWF {{ number_format($row->total_amount, 0) }}</td>
                    <td class="text-sm font-weight-bold text-success">RWF {{ number_format($row->charge, 0) }}</td>
                    <td class="text-sm">{{ $row->transactions }}</td>
                    <td>
                      <a href="{{ route('bills.daily.export', ['year' => $row->year, 'month' => $row->month]) }}"
                         class="btn btn-link text-primary px-2 mb-0 text-xs"
                         onclick="event.stopPropagation()">
                        <i class="fas fa-download me-1"></i>CSV
                      </a>
                    </td>
                  </tr>
                  <tr class="daily-expand d-none" id="expand-{{ $row->year }}-{{ $row->month }}">
                    <td colspan="6" class="p-0">
                      <div class="px-4 py-3 bg-gray-100">
                        <table class="table table-sm mb-0">
                          <thead>
                            <tr>
                              <th class="text-uppercase text-secondary text-xxs opacity-7">Date</th>
                              <th class="text-uppercase text-secondary text-xxs opacity-7">Units (kWh)</th>
                              <th class="text-uppercase text-secondary text-xxs opacity-7">Amount Paid (RWF)</th>
                              <th class="text-uppercase text-secondary text-xxs opacity-7">Charge (RWF)</th>
                              <th class="text-uppercase text-secondary text-xxs opacity-7">Transactions</th>
                            </tr>
                          </thead>
                          <tbody id="daily-body-{{ $row->year }}-{{ $row->month }}">
                            <tr><td colspan="5" class="text-center text-muted py-2">Loading...</td></tr>
                          </tbody>
                        </table>
                      </div>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="5" class="text-center text-muted py-4">No billing records yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const dailyUrl = "{{ route('bills.daily') }}";
    const loaded = {};

    document.querySelectorAll('.month-row').forEach(function (row) {
        row.addEventListener('click', function () {
            const year    = row.dataset.year;
            const month   = row.dataset.month;
            const key     = year + '-' + month;
            const expand  = document.getElementById('expand-' + key);
            const body    = document.getElementById('daily-body-' + key);
            const icon    = row.querySelector('.toggle-icon');
            const isOpen  = !expand.classList.contains('d-none');

            if (isOpen) {
                expand.classList.add('d-none');
                icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
                return;
            }

            expand.classList.remove('d-none');
            icon.classList.replace('fa-chevron-right', 'fa-chevron-down');

            if (loaded[key]) return;
            loaded[key] = true;

            fetch(dailyUrl + '?year=' + year + '&month=' + month, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
            })
            .then(r => r.json())
            .then(function (rows) {
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-2">No daily data.</td></tr>';
                    return;
                }
                body.innerHTML = rows.map(function (r) {
                    return '<tr>' +
                        '<td class="text-sm">' + r.label + '</td>' +
                        '<td class="text-sm">' + parseFloat(r.total_units).toFixed(2) + ' kWh</td>' +
                        '<td class="text-sm font-weight-bold">RWF ' + parseInt(r.total_amount).toLocaleString() + '</td>' +
                        '<td class="text-sm text-success font-weight-bold">RWF ' + parseInt(r.charge).toLocaleString() + '</td>' +
                        '<td class="text-sm">' + r.transactions + '</td>' +
                    '</tr>';
                }).join('');
            })
            .catch(function () {
                body.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-2">Failed to load.</td></tr>';
            });
        });
    });
});
</script>
@endsection
