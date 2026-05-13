<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Paypack\Paypack;
use Carbon\Carbon;

class OperationController extends Controller
{
    private function configuredPaypack(): Paypack
    {
        $clientId = config('services.paypack.client_id');
        $clientSecret = config('services.paypack.client_secret');

        if (blank($clientId) || blank($clientSecret)) {
            throw new \RuntimeException('Paypack credentials are not configured.');
        }

        $paypack = new Paypack();
        $paypack->config([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'webhook_mode' => config('services.paypack.webhook_mode', 'development'),
        ]);

        return $paypack;
    }

    private function normalizePaypackPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 12 && str_starts_with($digits, '250')) {
            $digits = '0' . substr($digits, 3);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '0' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '07')) {
            return $digits;
        }

        return null;
    }

    private function resolveRequestedUserId(Request $request): ?int
    {
        if (Auth::check()) {
            return Auth::id();
        }

        $userId = $request->input('user_id', $request->query('user_id'));
        if ($userId !== null && is_numeric($userId)) {
            return (int) $userId;
        }

        $rawMeterNumber = trim((string) $request->input('meter_number', $request->query('meter_number', '')));
        $meterNumber = preg_replace('/\D/', '', $rawMeterNumber);
        if ($rawMeterNumber !== '') {
            $normalizedMeterNumber = ltrim($meterNumber, '0');
            if ($normalizedMeterNumber === '') {
                $normalizedMeterNumber = '0';
            }

            if (Schema::hasColumn('meter_status', 'meter_number')) {
                $meter = DB::table('meter_status')
                    ->where('meter_number', $rawMeterNumber)
                    ->orWhere('meter_number', $meterNumber)
                    ->orWhere('meter_number', str_pad($normalizedMeterNumber, 16, '0', STR_PAD_LEFT))
                    ->first();

                if ($meter) {
                    return (int) $meter->user_id;
                }
            }

            if ($meterNumber !== '') {
                return (int) $normalizedMeterNumber;
            }
        }

        $connectedMeter = DB::table('meter_status')
            ->where('connected', 1)
            ->orderByDesc('updated_at')
            ->first();

        if ($connectedMeter) {
            return (int) $connectedMeter->user_id;
        }

        return null;
    }

    private function wantsPlainTextResponse(Request $request): bool
    {
        return $request->query('format') === 'plain'
            || $request->query('arduino') === '1'
            || ! $request->expectsJson();
    }

    private function calculateKwhFromPower(float $power, ?string $lastReadingAt, Request $request): float
    {
        $elapsedSeconds = (float) $request->query('interval_seconds', 0);

        if ($elapsedSeconds <= 0 && $lastReadingAt) {
            $elapsedSeconds = Carbon::parse($lastReadingAt)->diffInSeconds(now());
        }

        $elapsedSeconds = max(1, min($elapsedSeconds, 60));

        return round(($power / 1000) * ($elapsedSeconds / 3600), 6);
    }

    private function meterActivityUpdates(): array
    {
        $updates = ['updated_at' => now()];

        if (Schema::hasColumn('meter_status', 'last_seen_at')) {
            $updates['last_seen_at'] = now();
        }

        if (Schema::hasColumn('meter_status', 'device_status')) {
            $updates['device_status'] = 'online';
        }

        return $updates;
    }

    private function relayIsConnected(object $meter): bool
    {
        return (int) ($meter->connected ?? 0) === 1;
    }

    public function payment(Request $request)
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:100'],
        ]);

        $phone = $this->normalizePaypackPhone($validated['phone']);

        if ($phone === null) {
            return back()
                ->withInput()
                ->withErrors([
                    'phone' => 'Enter a valid Rwanda mobile money number, for example 078xxxxxxx.',
                ]);
        }

        try {
            $paypack = $this->configuredPaypack();

            $cashin = $paypack->Cashin([
                'phone' => $phone,
                'amount' => (int) $validated['amount'],
            ]);
        } catch (\Throwable $e) {
            Log::error('Paypack cashin request failed.', [
                'user_id' => Auth::id(),
                'phone' => $phone,
                'amount' => $validated['amount'],
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'payment_error' => 'Payment request failed: ' . $e->getMessage(),
                ]);
        }

        $data_bill = [
            'user_id' => Auth::user()->id,
            'amount' => $validated['amount'],
            'transaction_id' => $cashin['ref'] ?? null,
            'transaction_status' => $cashin['status'] ?? 'pending',
            'unit' => $validated['amount'] * 0.01,
        ];

        if (blank($data_bill['transaction_id'])) {
            Log::error('Paypack cashin response did not include a transaction reference.', [
                'user_id' => Auth::id(),
                'response' => $cashin,
            ]);

            return back()
                ->withInput()
                ->withErrors([
                    'payment_error' => 'Payment request failed: Paypack did not return a transaction reference.',
                ]);
        }

        DB::table('bills')->insert($data_bill);

        return back()->with('success', 'Payment request sent to ' . $phone . '. Check your phone to approve it. Reference: ' . $data_bill['transaction_id']);
    }

    public function paymentConfirm(){

        try {
            $paypack = $this->configuredPaypack();
        } catch (\Throwable $e) {
            Log::error('Paypack configuration failed during payment confirmation.', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payment confirmation failed.',
                'error' => $e->getMessage(),
            ], 503);
        }
        
        $results = DB::table('bills')
            ->where('user_id', Auth::user()->id)
            ->where('transaction_status', 'pending')
            ->get();
        $transaction = [];

        $meter = DB::table('meter_status')
            ->where('user_id', Auth::id())
            ->first();

        $unit = $meter?->unit;

        if ($unit !== null && $unit <= 3 && $unit >= 2.5) {
            $this->sms(); 
        }
        
        foreach ($results as $row) {
            try {
                $transaction = $paypack->Transaction($row->transaction_id);
            } catch (\Throwable $e) {
                Log::error('Paypack transaction lookup failed.', [
                    'user_id' => Auth::id(),
                    'transaction_id' => $row->transaction_id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $createdAt = Carbon::parse($row->created_at);
            $now = Carbon::now()->addHours(2);
            $diffInMinutes = $createdAt->diffInMinutes($now);
            if (isset($transaction['ref'])) {
                $data_meter = [
                    'user_id' => Auth::user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                
                $current_meter = DB::table('meter_status')
                    ->where('user_id', Auth::user()->id)
                    ->first();
                
                if ($current_meter) {
                    $new_unit = $current_meter->unit + ($row->amount * 0.01);
                    
                    DB::table('meter_status')
                        ->where('user_id', Auth::user()->id)
                        ->update(array_merge(['unit' => $new_unit], $this->meterActivityUpdates()));
                } else {
                    $data_meter['unit'] = $row->amount * 0.01;
                    if (Schema::hasColumn('meter_status', 'meter_number')) {
                        $data_meter['meter_number'] = str_pad((string) Auth::id(), 16, '0', STR_PAD_LEFT);
                    }

                    if (Schema::hasColumn('meter_status', 'device_status')) {
                        $data_meter['device_status'] = 'online';
                    }
                    
                    DB::table('meter_status')->insert($data_meter);
                } 

                DB::table('bills')
                    ->where('transaction_id', $row->transaction_id)
                    ->update(['transaction_status' => 'success']);
            }else {
                if ($diffInMinutes>15) {
                    DB::table('bills')
                        ->where('transaction_id', $row->transaction_id)
                        ->update(['transaction_status' => 'fail']);
                }
            }   
            $transaction[]=$transaction;
        }
        return response()->json([
            'message' => 'Task run successfully!',
            'data' => $results
        ]);
    }

    function testPayment() {
        $paypack = $this->configuredPaypack();
        
        $transaction_id = "dbdb25c4-a068-4f7a-b7e2-64ac264d718e";
        $transaction = $paypack->Transaction($transaction_id);

        return $transaction;
    }

    public function checkBalance(Request $request){
        $userId = $this->resolveRequestedUserId($request);

        if (! $userId) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('0', 422)->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'User identifier is required.',
            ], 422);
        }

        $meter = DB::table('meter_status')->where('user_id', $userId)->first();

        if (! $meter) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('0', 404)->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'Meter status not found for the requested user.',
            ], 404);
        }

        if (! $this->relayIsConnected($meter)) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('0')->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'relay_off',
                'user_id' => $userId,
                'unit' => 0,
                'connected' => false,
            ]);
        }

        if ($this->wantsPlainTextResponse($request)) {
            return response((string) $meter->unit)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $userId,
            'unit' => $meter->unit,
        ]);
    }

    public function storeMeterReading(Request $request)
    {
        $validated = $request->validate([
            'current' => ['required', 'numeric', 'min:0'],
            'voltage' => ['required', 'numeric', 'min:0'],
            'power' => ['nullable', 'numeric', 'min:0'],
        ]);

        $userId = $this->resolveRequestedUserId($request);
        $current = (float) $validated['current'];
        $voltage = (float) $validated['voltage'];
        $power = isset($validated['power'])
            ? (float) $validated['power']
            : $current * $voltage;
        $requestedDevice = (string) $request->query('device', 'socket');
        $device = $requestedDevice === '1' ? 'buble' : 'socket';
        $meter = $userId
            ? DB::table('meter_status')->where('user_id', $userId)->first()
            : null;

        if ($meter && ! $this->relayIsConnected($meter)) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('0')->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'relay_off',
                'user_id' => $userId,
                'unit' => 0,
                'connected' => false,
                'kwh' => 0,
            ]);
        }

        $previousUnit = (float) ($meter?->unit ?? 0);
        $kwh = $previousUnit > 0
            ? $this->calculateKwhFromPower($power, $meter?->updated_at, $request)
            : 0.0;
        $kwh = min($kwh, $previousUnit);
        $newUnit = $meter ? max($previousUnit - $kwh, 0) : 0.0;
        $roundedUnit = round($newUnit, 6);

        if (
            ! Schema::hasColumn('usage', 'current')
            || ! Schema::hasColumn('usage', 'voltage')
            || ! Schema::hasColumn('usage', 'power')
        ) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Run php artisan migrate before sending ESP meter readings.',
            ], 500);
        }

        $reading = [
            'device' => $device,
            'kwh' => $kwh,
            'current' => $current,
            'voltage' => $voltage,
            'power' => $power,
        ];

        if (Schema::hasColumn('usage', 'user_id')) {
            $reading['user_id'] = $userId;
        }

        if (Schema::hasColumn('usage', 'meter_number')) {
            $reading['meter_number'] = $meter?->meter_number ?? str_pad((string) $userId, 16, '0', STR_PAD_LEFT);
        }

        if (Schema::hasColumn('usage', 'created_at')) {
            $reading['created_at'] = now();
        }

        if (Schema::hasColumn('usage', 'updated_at')) {
            $reading['updated_at'] = now();
        }

        DB::table('usage')->insert($reading);

        if ($meter) {
            DB::table('meter_status')
                ->where('user_id', $userId)
                ->update(array_merge(['unit' => $newUnit], $this->meterActivityUpdates()));
        }

        if ($this->wantsPlainTextResponse($request)) {
            return response((string) $roundedUnit)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $userId,
            'unit' => $roundedUnit,
            'kwh' => $kwh,
            'device' => $device,
            'current' => $current,
            'voltage' => $voltage,
            'power' => $power,
        ]);
    }

    // FIXED: Changed from 0.2 to 0.05 for 5 km/h
    public function operation(Request $request, $id){
        $userId = $this->resolveRequestedUserId($request);

        if (! $userId) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('fail', 422)->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'User identifier is required.',
            ], 422);
        }

        $meter = DB::table('meter_status')->where('user_id', $userId)->first();

        if (! $meter) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('fail', 404)->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'fail',
                'message' => 'No meter status found for the requested user.',
            ], 404);
        }

        if (! $this->relayIsConnected($meter)) {
            if ($this->wantsPlainTextResponse($request)) {
                return response('0')->header('Content-Type', 'text/plain');
            }

            return response()->json([
                'status' => 'relay_off',
                'user_id' => $userId,
                'unit' => 0,
                'connected' => false,
            ]);
        }

        $previousUnit = (float) $meter->unit;
        $previousRoundedUnit = round($previousUnit, 6);
        $requestedPower = $request->query('power');
        $kwh = is_numeric($requestedPower)
            ? $this->calculateKwhFromPower((float) $requestedPower, $meter->updated_at, $request)
            : 0.05;
        $kwh = min($kwh, $previousUnit);
        $newUnit = max($previousUnit - $kwh, 0);
        $roundedUnit = round($newUnit, 6);

        DB::table('meter_status')
            ->where('user_id', $userId)
            ->update(array_merge(['unit' => $newUnit], $this->meterActivityUpdates()));

        if ($previousRoundedUnit > 0) {
            $usageRow = [
                'device' => $id == 1 ? 'buble' : 'socket',
                'kwh' => $kwh,
            ];

            if (Schema::hasColumn('usage', 'user_id')) {
                $usageRow['user_id'] = $userId;
            }

            if (Schema::hasColumn('usage', 'meter_number')) {
                $usageRow['meter_number'] = $meter->meter_number ?? str_pad((string) $userId, 16, '0', STR_PAD_LEFT);
            }

            DB::table('usage')->insert($usageRow);

            $user = DB::table('users')->where('id', $userId)->first();
            if ($user) {
                $crossedWarningThreshold = $previousRoundedUnit > 1.0 && $roundedUnit <= 1.0 && $roundedUnit > 0.0;
                $crossedCutoffThreshold = $previousRoundedUnit > 0.0 && $roundedUnit <= 0.0;

                if ($crossedWarningThreshold) {
                    if (! empty($user->email)) {
                        $this->sendEmailNotification(
                            $user->email,
                            'Power warning',
                            'Your remaining balance is 1 kWh or less. Please recharge soon.'
                        );
                    }

                    if (! empty($user->telephone)) {
                        $this->sms(
                            $user->telephone,
                            'NIYO_7 alert: your remaining balance is 1 kWh or less. Please recharge soon.'
                        );
                    }
                } elseif ($crossedCutoffThreshold) {
                    if (! empty($user->email)) {
                        $this->sendEmailNotification(
                            $user->email,
                            'Power cut off',
                            'Your power has been cut off because the balance reached 0 kWh.'
                        );
                    }

                    if (! empty($user->telephone)) {
                        $this->sms(
                            $user->telephone,
                            'NIYO_7 alert: your balance reached 0 kWh and power has been cut off.'
                        );
                    }
                }
            }

        }

        if ($this->wantsPlainTextResponse($request)) {
            return response((string) $roundedUnit)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'status' => 'success',
            'user_id' => $userId,
            'unit' => $roundedUnit,
        ]);
    }

    private function sendEmailNotification(string $recipient, string $subject, string $messageText): void
    {
        try {
            Mail::raw($messageText, function ($message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send meter notification email', [
                'email' => $recipient,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sms(?string $to = null, ?string $messageText = null){
        $destination = $to ?: '250780551851';
        $text = $messageText ?: 'Hello, you remain 3 kwh only, try to fill';

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.mista.io/sms',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('to' => $destination,'from' => 'E-Notifier','unicode' => '0','sms' => $text,'action' => 'send-sms'),
        CURLOPT_HTTPHEADER => array(
            'x-api-key: 596|ytiiNvtEma7SUNEbVoJhHHzMqXrTjvOuQkY8JHvX'
        ),
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            Log::error('Failed to send SMS notification', [
                'to' => $destination,
                'error' => curl_error($curl),
            ]);
        }

        curl_close($curl);
    }
}
