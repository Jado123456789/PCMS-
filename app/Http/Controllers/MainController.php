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
            $this->adminChartData()
        ));
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
            'device_name' => ['nullable', 'string', 'max:255'],
            'device_status' => ['required', 'in:online,offline,maintenance,faulty'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $meterNumber = $validated['meter_number'] ?? null;
        if ($meterNumber && Schema::hasColumn('meter_status', 'meter_number')) {
            $exists = DB::table('meter_status')
                ->where('meter_number', $meterNumber)
                ->where('user_id', '!=', $userId)
                ->exists();

            if ($exists) {
                return back()->withErrors([
                    'meter_number' => 'This meter number is already assigned to another customer.',
                ]);
            }
        }

        $updates = ['updated_at' => now()];
        foreach (['meter_number', 'device_name', 'device_status', 'location'] as $column) {
            if (Schema::hasColumn('meter_status', $column)) {
                $updates[$column] = $validated[$column] ?? null;
            }
        }

        DB::table('meter_status')
            ->where('user_id', $userId)
            ->update($updates);

        return back()->with('success', 'Device assignment updated successfully.');
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

    public function bill(Request $request)
    {
        return view('bill', $this->buildCustomerBillingData(Auth::id(), $this->selectedDateFromRequest($request)));
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
        return view('payment', $this->buildCustomerBillingData(Auth::id(), $this->selectedDateFromRequest($request)));
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
}
