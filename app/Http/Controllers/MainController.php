<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Paypack\Paypack;
use Carbon\Carbon;

class MainController extends Controller
{
    private function formatExportDate($date): string
    {
        return Carbon::parse($date)->format('M d, Y H:i');
    }

    private function buildCustomerBillingData(int $userId, ?string $selectedDate = null): array
    {
        $power = DB::table('meter_status')->where('user_id', $userId)->first();
        $recentPayment = DB::table('bills')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->first();
        $billsQuery = DB::table('bills')->where('user_id', $userId);

        if ($selectedDate) {
            $billsQuery->whereDate('created_at', Carbon::parse($selectedDate)->toDateString());
        }

        $bills = $billsQuery->orderByDesc('created_at')->get();

        $successfulBills = $bills->where('transaction_status', 'success');
        $pendingBills = $bills->where('transaction_status', 'pending');
        $failedBills = $bills->where('transaction_status', 'fail');

        $thisMonthTotal = DB::table('bills')
            ->where('user_id', $userId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        $reportRows = $bills->map(function ($bill) {
            return (object) [
                'date' => Carbon::parse($bill->created_at),
                'transaction_id' => $bill->transaction_id,
                'amount' => (float) $bill->amount,
                'unit' => (float) ($bill->unit ?? ($bill->amount * 0.01)),
                'status' => $bill->transaction_status ?? 'unknown',
            ];
        });

        return [
            'meter' => $power,
            'recent_payment' => $recentPayment?->amount ?? 0,
            'bills' => $bills,
            'reportRows' => $reportRows,
            'totalPayments' => $successfulBills->sum('amount'),
            'successfulPaymentsCount' => $successfulBills->count(),
            'pendingPaymentsCount' => $pendingBills->count(),
            'failedPaymentsCount' => $failedBills->count(),
            'thisMonthTotal' => $thisMonthTotal,
            'selectedDate' => $selectedDate,
        ];
    }

    public function index()
    {
        DB::table('meter_status')->update(['connected' => 0]);
        DB::table('meter_status')
            ->where('user_id', Auth::id())
            ->update(['connected' => 1]);

        return view('dashboard', $this->buildCustomerBillingData(Auth::id()));
    }

    public function downloadBillsReport()
    {
        $user = Auth::user();
        $rows = DB::table('bills')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $filename = 'billing-report-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows, $user) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Customer', $user->name]);
            fputcsv($file, ['Email', $user->email]);
            fputcsv($file, ['Generated At', $this->formatExportDate(now())]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Transaction ID', 'Amount (RWF)', 'Units (kWh)', 'Status']);

            foreach ($rows as $row) {
                fputcsv($file, [
                    $this->formatExportDate($row->created_at),
                    $row->transaction_id,
                    number_format((float) $row->amount, 0, '.', ''),
                    number_format((float) ($row->unit ?? ($row->amount * 0.01)), 2, '.', ''),
                    ucfirst((string) ($row->transaction_status ?? 'unknown')),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function downloadInvoicesReport()
    {
        $user = Auth::user();
        $rows = DB::table('bills')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        $filename = 'invoice-report-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows, $user) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Customer', $user->name]);
            fputcsv($file, ['Phone Number', $user->telephone ?? 'N/A']);
            fputcsv($file, ['Email', $user->email]);
            fputcsv($file, ['Generated At', $this->formatExportDate(now())]);
            fputcsv($file, []);
            fputcsv($file, ['Invoice', 'Date', 'Phone Number', 'Unit (kWh)', 'Amount (RWF)', 'Status']);

            foreach ($rows as $row) {
                fputcsv($file, [
                    'INV-' . $row->transaction_id,
                    $this->formatExportDate($row->created_at),
                    $user->telephone ?? 'N/A',
                    number_format((float) ($row->unit ?? ($row->amount * 0.01)), 2, '.', ''),
                    number_format((float) $row->amount, 0, '.', ''),
                    ucfirst((string) ($row->transaction_status ?? 'unknown')),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function adminIndex()
    {
        $this->ensureAdmin();

        return view('admin-dashboard', array_merge(
            $this->adminOverviewData(),
            $this->adminChartData(),
            ['alerts' => $this->adminAlerts()]
        ));
    }

    public function adminDashboardLive()
    {
        $this->ensureAdmin();

        $overview = $this->adminOverviewData();
        $meters   = $this->adminLiveMeters();
        $alerts   = $this->adminAlerts();
        $recent   = DB::table('bills')
            ->leftJoin('users', 'bills.user_id', '=', 'users.id')
            ->select('bills.created_at', 'bills.amount', 'bills.transaction_status', 'users.name as customer_name')
            ->latest('bills.created_at')
            ->take(10)
            ->get()
            ->map(function ($r) {
                $r->created_at = Carbon::parse($r->created_at)->format('M d, H:i');
                return $r;
            });

        return response()->json([
            'overview' => $overview,
            'meters'   => $meters,
            'alerts'   => $alerts,
            'recent'   => $recent,
        ]);
    }

    private function adminLiveMeters(): array
    {
        return DB::table('meter_status')
            ->leftJoin('users', 'meter_status.user_id', '=', 'users.id')
            ->leftJoin(
                DB::raw('(SELECT user_id, current, voltage, power, created_at FROM usage WHERE (user_id, created_at) IN (SELECT user_id, MAX(created_at) FROM usage GROUP BY user_id)) as latest_reading'),
                'meter_status.user_id', '=', 'latest_reading.user_id'
            )
            ->select([
                'meter_status.user_id',
                'meter_status.unit',
                'meter_status.connected',
                'users.name as customer_name',
                DB::raw(Schema::hasColumn('meter_status', 'meter_number') ? 'meter_status.meter_number' : 'NULL as meter_number'),
                DB::raw(Schema::hasColumn('meter_status', 'device_status') ? 'meter_status.device_status' : 'NULL as device_status'),
                DB::raw(Schema::hasColumn('meter_status', 'last_seen_at') ? 'meter_status.last_seen_at' : 'NULL as last_seen_at'),
                'latest_reading.power',
                'latest_reading.current',
                'latest_reading.voltage',
            ])
            ->orderBy('users.name')
            ->get()
            ->map(function ($r) {
                $lastSeen = $r->last_seen_at ? Carbon::parse($r->last_seen_at) : null;
                $minutesAgo = $lastSeen ? $lastSeen->diffInMinutes(now()) : null;
                $r->is_offline = $minutesAgo !== null && $minutesAgo > 5;
                $r->last_seen_label = $lastSeen ? $lastSeen->diffForHumans() : 'Never';
                return $r;
            })
            ->toArray();
    }

    private function adminAlerts(): array
    {
        $alerts = [];

        $lowBalance = DB::table('meter_status')
            ->leftJoin('users', 'meter_status.user_id', '=', 'users.id')
            ->where('meter_status.unit', '<=', 3)
            ->where('meter_status.unit', '>', 0)
            ->select('users.name', 'meter_status.unit')
            ->get();

        foreach ($lowBalance as $m) {
            $alerts[] = [
                'type'    => 'warning',
                'message' => ($m->name ?? 'Unknown') . ' has low balance: ' . number_format($m->unit, 2) . ' kWh',
            ];
        }

        $zeroBalance = DB::table('meter_status')
            ->leftJoin('users', 'meter_status.user_id', '=', 'users.id')
            ->where('meter_status.unit', '<=', 0)
            ->select('users.name')
            ->get();

        foreach ($zeroBalance as $m) {
            $alerts[] = [
                'type'    => 'danger',
                'message' => ($m->name ?? 'Unknown') . ' has zero balance — power cut off',
            ];
        }

        if (Schema::hasColumn('meter_status', 'last_seen_at')) {
            $offline = DB::table('meter_status')
                ->leftJoin('users', 'meter_status.user_id', '=', 'users.id')
                ->where('meter_status.last_seen_at', '<', now()->subMinutes(5))
                ->orWhereNull('meter_status.last_seen_at')
                ->select('users.name')
                ->get();

            foreach ($offline as $m) {
                $alerts[] = [
                    'type'    => 'secondary',
                    'message' => ($m->name ?? 'Unknown') . ' meter is offline',
                ];
            }
        }

        $failedToday = DB::table('bills')
            ->leftJoin('users', 'bills.user_id', '=', 'users.id')
            ->where('bills.transaction_status', 'fail')
            ->whereDate('bills.created_at', now()->toDateString())
            ->select('users.name', 'bills.amount')
            ->get();

        foreach ($failedToday as $b) {
            $alerts[] = [
                'type'    => 'danger',
                'message' => 'Failed payment today: ' . ($b->name ?? 'Unknown') . ' — RWF ' . number_format($b->amount, 0),
            ];
        }

        return $alerts;
    }

    private function ensureAdmin(): void
    {
        abort_if((int) Auth::user()->role_id !== 1, 403);
    }

    private function adminOverviewData(): array
    {
        $totalUsers = DB::table('users')->where('role_id', 2)->count();
        $totalBills = DB::table('bills')->count();
        $successfulPayments = DB::table('bills')->where('transaction_status', 'success');
        $totalPayments = (clone $successfulPayments)->sum('amount');
        $totalUsage = DB::table('usage')->sum('kwh');
        $pendingBills = DB::table('bills')->where('transaction_status', 'pending')->count();
        $failedBills = DB::table('bills')->where('transaction_status', 'fail')->count();
        $totalMeters = DB::table('meter_status')->count();
        $lowBalanceMeters = DB::table('meter_status')->where('unit', '<=', 3)->count();
        $activeMeters = DB::table('meter_status')->where('connected', 1)->count();
        $onlineMeters = Schema::hasColumn('meter_status', 'device_status')
            ? DB::table('meter_status')->where('device_status', 'online')->count()
            : $activeMeters;
        $offlineMeters = max($totalMeters - $onlineMeters, 0);
        $thisMonthRevenue = DB::table('bills')
            ->where('transaction_status', 'success')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');
        $todayUsage = DB::table('usage')
            ->whereDate('created_at', now()->toDateString())
            ->sum('kwh');

        return [
            'totalUsers' => $totalUsers,
            'totalBills' => $totalBills,
            'totalPayments' => $totalPayments,
            'totalUsage' => $totalUsage,
            'totalMeters' => $totalMeters,
            'pendingBills' => $pendingBills,
            'failedBills' => $failedBills,
            'lowBalanceMeters' => $lowBalanceMeters,
            'activeMeters' => $activeMeters,
            'onlineMeters' => $onlineMeters,
            'offlineMeters' => $offlineMeters,
            'thisMonthRevenue' => $thisMonthRevenue,
            'todayUsage' => $todayUsage,
        ];
    }

    private function adminChartData(): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $revenueByDate = DB::table('bills')
            ->selectRaw('DATE(created_at) as bill_date, SUM(amount) as total')
            ->where('transaction_status', 'success')
            ->where('created_at', '>=', $startDate)
            ->groupBy('bill_date')
            ->pluck('total', 'bill_date');
        $usageByDate = DB::table('usage')
            ->selectRaw('DATE(created_at) as usage_date, SUM(kwh) as total')
            ->where('created_at', '>=', $startDate)
            ->groupBy('usage_date')
            ->pluck('total', 'usage_date');

        $dailyLabels = [];
        $dailyRevenue = [];
        $dailyUsage = [];
        for ($date = $startDate->copy(); $date->lte(now()); $date->addDay()) {
            $key = $date->toDateString();
            $dailyLabels[] = $date->format('M d');
            $dailyRevenue[] = (float) ($revenueByDate[$key] ?? 0);
            $dailyUsage[] = round((float) ($usageByDate[$key] ?? 0), 6);
        }

        $statusCounts = DB::table('bills')
            ->select('transaction_status', DB::raw('COUNT(*) as total'))
            ->groupBy('transaction_status')
            ->pluck('total', 'transaction_status');

        return [
            'dailyLabels' => $dailyLabels,
            'dailyRevenue' => $dailyRevenue,
            'dailyUsage' => $dailyUsage,
            'statusLabels' => ['Success', 'Pending', 'Failed'],
            'statusValues' => [
                (int) ($statusCounts['success'] ?? 0),
                (int) ($statusCounts['pending'] ?? 0),
                (int) ($statusCounts['fail'] ?? 0),
            ],
        ];
    }

    private function adminDeviceRows(?int $limit = null)
    {
        $query = DB::table('meter_status')
            ->leftJoin('users', 'meter_status.user_id', '=', 'users.id')
            ->select([
                'meter_status.user_id',
                'meter_status.unit',
                'meter_status.connected',
                'users.name as customer_name',
                'users.email as customer_email',
                'users.telephone as customer_phone',
                DB::raw(Schema::hasColumn('meter_status', 'meter_number') ? 'meter_status.meter_number' : 'NULL as meter_number'),
                DB::raw(Schema::hasColumn('meter_status', 'device_name') ? 'meter_status.device_name' : 'NULL as device_name'),
                DB::raw(Schema::hasColumn('meter_status', 'device_status') ? 'meter_status.device_status' : 'NULL as device_status'),
                DB::raw(Schema::hasColumn('meter_status', 'location') ? 'meter_status.location' : 'NULL as location'),
                DB::raw(Schema::hasColumn('meter_status', 'last_seen_at') ? 'meter_status.last_seen_at' : 'NULL as last_seen_at'),
            ])
            ->orderBy('users.name');

        return $limit ? $query->take($limit)->get() : $query->get();
    }

    private function adminRecentBills(?int $limit = null)
    {
        $query = DB::table('bills')
            ->leftJoin('users', 'bills.user_id', '=', 'users.id')
            ->select('bills.*', 'users.name as customer_name', 'users.email as customer_email')
            ->latest('bills.created_at');

        return $limit ? $query->take($limit)->get() : $query->get();
    }

    private function adminRecentUsers(?int $limit = null)
    {
        $query = DB::table('users')
            ->where('role_id', 2)
            ->latest();

        return $limit ? $query->take($limit)->get() : $query->get();
    }

    public function adminCustomers()
    {
        $this->ensureAdmin();

        return view('admin-customers', [
            'customers' => $this->adminRecentUsers(),
        ]);
    }

    public function adminDevices()
    {
        $this->ensureAdmin();

        return view('admin-devices', array_merge($this->adminOverviewData(), [
            'deviceRows' => $this->adminDeviceRows(),
        ]));
    }

    public function adminPayments()
    {
        $this->ensureAdmin();

        return view('admin-payments', [
            'recentBills' => $this->adminRecentBills(),
        ]);
    }

    public function adminReports()
    {
        $this->ensureAdmin();

        return view('admin-reports', array_merge(
            $this->adminOverviewData(),
            $this->adminChartData()
        ));
    }

    public function updateDevice(Request $request, int $userId)
    {
        abort_if((int) Auth::user()->role_id !== 1, 403);

        $validated = $request->validate([
            'meter_number' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/'],
            'device_name'   => ['nullable', 'string', 'max:255'],
            'device_status' => ['required', 'in:online,offline,maintenance,faulty'],
            'location'      => ['nullable', 'string', 'max:255'],
        ]);

        $meterNumber = $validated['meter_number'] ?? null;
        if ($meterNumber && Schema::hasColumn('meter_status', 'meter_number')) {
            $exists = DB::table('meter_status')
                ->where('meter_number', $meterNumber)
                ->where('user_id', '!=', $userId)
                ->exists();

            if ($exists) {
                return back()->withErrors(['meter_number' => 'This meter number is already assigned to another customer.']);
            }
        }

        $updates = ['updated_at' => now()];
        foreach (['meter_number', 'device_name', 'device_status', 'location'] as $column) {
            if (Schema::hasColumn('meter_status', $column)) {
                $updates[$column] = $validated[$column] ?? null;
            }
        }

        DB::table('meter_status')->where('user_id', $userId)->update($updates);

        return back()->with('success', 'Device assignment updated successfully.');
    }

    public function toggleRelay(int $userId)
    {
        $this->ensureAdmin();

        $meter = DB::table('meter_status')->where('user_id', $userId)->first();
        abort_if(! $meter, 404);

        $newState = (int) $meter->connected === 1 ? 0 : 1;
        DB::table('meter_status')->where('user_id', $userId)->update([
            'connected'  => $newState,
            'updated_at' => now(),
        ]);

        $label = $newState === 1 ? 'restored' : 'cut off';
        return back()->with('success', 'Power ' . $label . ' for customer #' . $userId . '.');
    }

    public function manualTopup(Request $request, int $userId)
    {
        $this->ensureAdmin();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        $kwh = (float) $validated['amount'] * 0.01;

        $meter = DB::table('meter_status')->where('user_id', $userId)->first();
        if ($meter) {
            DB::table('meter_status')->where('user_id', $userId)->update([
                'unit'       => $meter->unit + $kwh,
                'updated_at' => now(),
            ]);
        } else {
            DB::table('meter_status')->insert([
                'user_id'    => $userId,
                'unit'       => $kwh,
                'connected'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('bills')->insert([
            'user_id'            => $userId,
            'amount'             => $validated['amount'],
            'transaction_id'     => 'MANUAL-' . strtoupper(uniqid()),
            'transaction_status' => 'success',
            'unit'               => $kwh,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        return back()->with('success', 'Manually added ' . number_format($kwh, 2) . ' kWh (RWF ' . number_format($validated['amount'], 0) . ') to customer #' . $userId . '.');
    }

    public function forcePaymentConfirm(int $userId)
    {
        $this->ensureAdmin();

        $pending = DB::table('bills')
            ->where('user_id', $userId)
            ->where('transaction_status', 'pending')
            ->get();

        $confirmed = 0;
        foreach ($pending as $bill) {
            $kwh = (float) $bill->unit ?? ((float) $bill->amount * 0.01);
            $meter = DB::table('meter_status')->where('user_id', $userId)->first();
            if ($meter) {
                DB::table('meter_status')->where('user_id', $userId)->update([
                    'unit'       => $meter->unit + $kwh,
                    'updated_at' => now(),
                ]);
            }
            DB::table('bills')->where('id', $bill->id)->update(['transaction_status' => 'success']);
            $confirmed++;
        }

        return back()->with('success', $confirmed . ' pending payment(s) confirmed for customer #' . $userId . '.');
    }

    public function toggleCustomerStatus(int $userId)
    {
        $this->ensureAdmin();

        $user = DB::table('users')->where('id', $userId)->first();
        abort_if(! $user, 404);

        $newStatus = ($user->status ?? 'active') === 'active' ? 'inactive' : 'active';
        DB::table('users')->where('id', $userId)->update(['status' => $newStatus]);

        return back()->with('success', 'Customer account ' . $newStatus . '.');
    }

    public function adminExportPayments()
    {
        $this->ensureAdmin();

        $rows = DB::table('bills')
            ->leftJoin('users', 'bills.user_id', '=', 'users.id')
            ->select('bills.*', 'users.name as customer_name', 'users.email as customer_email')
            ->latest('bills.created_at')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="all-payments-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Customer', 'Email', 'Transaction ID', 'Amount (RWF)', 'Units (kWh)', 'Status', 'Date']);
            foreach ($rows as $r) {
                fputcsv($f, [
                    $r->customer_name ?? 'N/A',
                    $r->customer_email ?? 'N/A',
                    $r->transaction_id,
                    number_format((float) $r->amount, 0, '.', ''),
                    number_format((float) ($r->unit ?? $r->amount * 0.01), 2, '.', ''),
                    ucfirst($r->transaction_status ?? 'unknown'),
                    Carbon::parse($r->created_at)->format('M d, Y H:i'),
                ]);
            }
            fclose($f);
        }, 200, $headers);
    }

    public function adminExportCustomers()
    {
        $this->ensureAdmin();

        $rows = DB::table('users')
            ->leftJoin('meter_status', 'users.id', '=', 'meter_status.user_id')
            ->where('users.role_id', 2)
            ->select('users.name', 'users.email', 'users.telephone', 'users.status', 'users.created_at', 'meter_status.unit', 'meter_status.device_status')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="all-customers-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Name', 'Email', 'Phone', 'Status', 'Balance (kWh)', 'Device Status', 'Registered']);
            foreach ($rows as $r) {
                fputcsv($f, [
                    $r->name,
                    $r->email,
                    $r->telephone ?? 'N/A',
                    ucfirst($r->status ?? 'active'),
                    number_format((float) ($r->unit ?? 0), 2, '.', ''),
                    ucfirst($r->device_status ?? 'unknown'),
                    Carbon::parse($r->created_at)->format('M d, Y'),
                ]);
            }
            fclose($f);
        }, 200, $headers);
    }

    public function adminExportUsage()
    {
        $this->ensureAdmin();

        $rows = DB::table('usage')
            ->leftJoin('users', 'usage.user_id', '=', 'users.id')
            ->select('usage.*', 'users.name as customer_name')
            ->latest('usage.created_at')
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="all-usage-' . now()->format('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($rows) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Customer', 'Device', 'Energy (kWh)', 'Current (A)', 'Voltage (V)', 'Power (W)', 'Date']);
            foreach ($rows as $r) {
                fputcsv($f, [
                    $r->customer_name ?? 'N/A',
                    $r->device ?? 'N/A',
                    number_format((float) $r->kwh, 6, '.', ''),
                    number_format((float) ($r->current ?? 0), 2, '.', ''),
                    number_format((float) ($r->voltage ?? 0), 2, '.', ''),
                    number_format((float) ($r->power ?? 0), 2, '.', ''),
                    Carbon::parse($r->created_at)->format('M d, Y H:i:s'),
                ]);
            }
            fclose($f);
        }, 200, $headers);
    }
   

    private function selectedDateFromRequest(Request $request): ?string
    {
        $date = $request->query('date');

        if (! $date) {
            return null;
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function billingDaily(Request $request)
    {
        $year  = (int) $request->query('year',  now()->year);
        $month = (int) $request->query('month', now()->month);
        $rate  = 100;

        $rows = DB::table('bills')
            ->where('user_id', Auth::id())
            ->where('transaction_status', 'success')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total_amount, SUM(unit) as total_units, COUNT(*) as transactions')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) ASC')
            ->get()
            ->map(function ($row) use ($rate) {
                $row->charge = (float) $row->total_units * $rate;
                $row->label  = Carbon::parse($row->day)->format('M d, Y');
                return $row;
            });

        return response()->json($rows);
    }

    public function downloadDailyBillingReport(Request $request)
    {
        $year  = (int) $request->query('year',  now()->year);
        $month = (int) $request->query('month', now()->month);
        $rate  = 100;
        $user  = Auth::user();
        $label = Carbon::createFromDate($year, $month, 1)->format('F-Y');

        $rows = DB::table('bills')
            ->where('user_id', $user->id)
            ->where('transaction_status', 'success')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->selectRaw('DATE(created_at) as day, SUM(amount) as total_amount, SUM(unit) as total_units, COUNT(*) as transactions')
            ->groupByRaw('DATE(created_at)')
            ->orderByRaw('DATE(created_at) ASC')
            ->get();

        $filename = 'billing-' . $label . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows, $user, $label, $rate) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Customer', $user->name]);
            fputcsv($file, ['Period', $label]);
            fputcsv($file, ['Rate', 'RWF ' . $rate . ' per kWh']);
            fputcsv($file, ['Generated At', Carbon::now()->format('M d, Y H:i')]);
            fputcsv($file, []);
            fputcsv($file, ['Date', 'Units (kWh)', 'Amount Paid (RWF)', 'Charge (RWF)', 'Transactions']);
            foreach ($rows as $row) {
                fputcsv($file, [
                    Carbon::parse($row->day)->format('M d, Y'),
                    number_format((float) $row->total_units,  2, '.', ''),
                    number_format((float) $row->total_amount, 0, '.', ''),
                    number_format((float) $row->total_units * $rate, 0, '.', ''),
                    $row->transactions,
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function bill(Request $request)
    {
        $userId = Auth::id();
        $rate = 100;

        $rows = DB::table('bills')
            ->where('user_id', $userId)
            ->where('transaction_status', 'success')
            ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(amount) as total_amount, SUM(unit) as total_units, COUNT(*) as transactions')
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
            ->get()
            ->map(function ($row) use ($rate) {
                $row->charge = (float) $row->total_units * $rate;
                $row->label = Carbon::createFromDate($row->year, $row->month, 1)->format('F Y');
                return $row;
            });

        $meter = DB::table('meter_status')->where('user_id', $userId)->first();
        $thisMonth = $rows->first();

        return view('bill', [
            'monthlyRows'   => $rows,
            'thisMonth'     => $thisMonth,
            'currentUnit'   => (float) ($meter?->unit ?? 0),
            'rate'          => $rate,
        ]);
    }

    public function invoices(Request $request)
    {
        return view('invoice', $this->buildCustomerBillingData(Auth::id(), $this->selectedDateFromRequest($request)));
    }

    public function invoiceShow(string $transactionId)
    {
        $bill = DB::table('bills')
            ->where('user_id', Auth::id())
            ->where('transaction_id', $transactionId)
            ->first();

        abort_if(! $bill, 404);

        return view('invoice-show', [
            'bill' => $bill,
            'invoiceDate' => Carbon::parse($bill->created_at),
            'unit' => (float) ($bill->unit ?? ($bill->amount * 0.01)),
            'amount' => (float) $bill->amount,
            'status' => $bill->transaction_status ?? 'unknown',
        ]);
    }

    public function payments(Request $request)
    {
        $userId = Auth::id();
        $meter = DB::table('meter_status')->where('user_id', $userId)->first();

        $bills = DB::table('bills')
            ->where('user_id', $userId)
            ->latest()
            ->take(10)
            ->get();

        $allBills = DB::table('bills')->where('user_id', $userId);
        $successfulPaymentsCount = (clone $allBills)->where('transaction_status', 'success')->count();
        $pendingPaymentsCount    = (clone $allBills)->where('transaction_status', 'pending')->count();
        $failedPaymentsCount     = (clone $allBills)->where('transaction_status', 'fail')->count();
        $totalPayments           = (clone $allBills)->where('transaction_status', 'success')->sum('amount');

        return view('payment', [
            'meter'                   => $meter,
            'bills'                   => $bills,
            'totalPayments'           => $totalPayments,
            'successfulPaymentsCount' => $successfulPaymentsCount,
            'pendingPaymentsCount'    => $pendingPaymentsCount,
            'failedPaymentsCount'     => $failedPaymentsCount,
        ]);
    }

    private function buildUsageData(?string $selectedDate = null): array
    {
        $date = $selectedDate ?: now()->toDateString();
        $usageForDate = function (string $device) use ($date) {
            $query = DB::table('usage')->where('device', $device)->whereDate('created_at', '=', $date);

            if (Schema::hasColumn('usage', 'user_id')) {
                $query->where('user_id', Auth::id());
            }

            return $query->sum('kwh');
        };

        $buble = $usageForDate('buble');
        $socket = $usageForDate('socket');
        $hasElectricalReadings = Schema::hasColumn('usage', 'current')
            && Schema::hasColumn('usage', 'voltage')
            && Schema::hasColumn('usage', 'power');
        $latestReading = $hasElectricalReadings
            ? DB::table('usage')
                ->when(Schema::hasColumn('usage', 'user_id'), fn ($query) => $query->where('user_id', Auth::id()))
                ->whereDate('created_at', '=', $date)
                ->whereNotNull('current')
                ->whereNotNull('voltage')
                ->whereNotNull('power')
                ->latest()
                ->first()
            : null;
        $current = (float) ($latestReading?->current ?? 0);
        $voltage = (float) ($latestReading?->voltage ?? 0);
        $power = (float) ($latestReading?->power ?? ($current * $voltage));

        return [
            'buble' => $buble,
            'socket' => $socket,
            'current' => $current,
            'voltage' => $voltage,
            'power' => $power,
            'selectedDate' => $date,
        ];
    }

    public function usage(Request $request) {
        return view('usage', $this->buildUsageData($this->selectedDateFromRequest($request)));
    }

    public function usageLatest(Request $request)
    {
        return response()->json($this->buildUsageData($this->selectedDateFromRequest($request)));
    }

    public function usageHistory(Request $request)
    {
        $date = $this->selectedDateFromRequest($request) ?: now()->toDateString();
        $limit = max(1, min((int) $request->query('limit', 30), 100));

        $rows = DB::table('usage')
            ->where('user_id', Auth::id())
            ->whereDate('created_at', $date)
            ->whereNotNull('current')
            ->whereNotNull('voltage')
            ->whereNotNull('power')
            ->latest()
            ->limit($limit)
            ->get(['current', 'voltage', 'power', 'created_at']);

        $rows = $rows->reverse()->values();

        return response()->json([
            'labels'  => $rows->map(fn($r) => Carbon::parse($r->created_at)->format('H:i:s'))->values(),
            'current' => $rows->map(fn($r) => (float) $r->current)->values(),
            'voltage' => $rows->map(fn($r) => (float) $r->voltage)->values(),
            'power'   => $rows->map(fn($r) => (float) $r->power)->values(),
        ]);
    }

    public function downloadUsageReport(Request $request)
    {
        $date = $this->selectedDateFromRequest($request) ?: now()->toDateString();
        $user = Auth::user();

        $rows = DB::table('usage')
            ->where('user_id', $user->id)
            ->whereDate('created_at', $date)
            ->whereNotNull('current')
            ->whereNotNull('voltage')
            ->whereNotNull('power')
            ->oldest()
            ->get(['created_at', 'current', 'voltage', 'power', 'kwh']);

        $filename = 'usage-' . $date . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows, $user, $date) {
            $file = fopen('php://output', 'w');

            fputcsv($file, ['Customer', $user->name]);
            fputcsv($file, ['Email', $user->email]);
            fputcsv($file, ['Date', $date]);
            fputcsv($file, ['Generated At', Carbon::now()->format('M d, Y H:i')]);
            fputcsv($file, []);
            fputcsv($file, ['Time', 'Current (A)', 'Voltage (V)', 'Power (W)', 'Energy (kWh)']);

            foreach ($rows as $row) {
                fputcsv($file, [
                    Carbon::parse($row->created_at)->format('H:i:s'),
                    number_format((float) $row->current, 2, '.', ''),
                    number_format((float) $row->voltage, 2, '.', ''),
                    number_format((float) $row->power,   2, '.', ''),
                    number_format((float) $row->kwh,     6, '.', ''),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
