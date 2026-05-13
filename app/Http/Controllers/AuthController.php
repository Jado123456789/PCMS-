<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private SmsService $smsService)
    {
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);
        $remember = $request->boolean('remember', true);
        $result = Auth::attempt($credentials, $remember);
        if ($result) {
            $request->session()->regenerate();
            
            // Route based on user role
            $user = Auth::user();
            $this->ensureUserMeterExists($user->id);
            $welcomeMessage = null;

            if ($request->session()->pull('new_registration_user_id') === $user->id) {
                $registeredName = $request->session()->pull('new_registration_name', $user->name);
                $cashPowerNumber = $this->formatCashPowerNumber($user->id);
                $welcomeMessage = "Welcome {$registeredName}! Your Cash Power number is {$cashPowerNumber} and your starting balance is 0 units.";
            }

            if ($user->role_id == 1) {
                // Admin user
                return redirect()->route('admin.dashboard');
            } else {
                $this->activateRelayForUser($user->id);

                // Regular customer user (role_id = 2)
                return redirect()->route('dashboard')->with(
                    $welcomeMessage ? ['welcome' => $welcomeMessage] : []
                );
            }
        } else {
            return back()->withErrors([
                'error' => 'The provided credentials do not match our records.',
            ]);
        }
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user && (int) $user->role_id !== 1) {
            $this->deactivateRelayForUser($user->id);
        }

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'telephone' => ['required', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Check if passwords match (double validation)
        if ($request->password !== $request->password_confirmation) {
            return back()->withErrors([
                'password_confirmation' => 'Password confirmation does not match.',
            ])->withInput();
        }

        if (! $this->isRegistrationEmailVerified($request, $validated['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Please verify your email with the OTP code before setting your password.',
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
            'role_id' => 2,
            'status' => 'active',
        ]);

        // Create initial meter status with 0 units for new user
        DB::table('meter_status')->insert($this->newMeterData($user->id));
        $this->sendRegistrationSms($user->telephone, $user->name, $user->id);

        $request->session()->put('new_registration_user_id', $user->id);
        $request->session()->put('new_registration_name', $user->name);
        $request->session()->forget('registration_otp');

        $cashPowerNumber = $this->formatCashPowerNumber($user->id);

        return redirect()->route('login')
            ->with('registration_success', "Thank you for registering. Your account has been created successfully. Your Cash Power ID is {$cashPowerNumber}. Please sign in with your email and password.");
    }

    public function sendRegistrationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ], [
            'email.unique' => 'This email is already registered.',
        ]);

        $otp = (string) random_int(100000, 999999);

        $request->session()->put('registration_otp', [
            'email' => $validated['email'],
            'code' => $otp,
            'expires_at' => now()->addMinutes(10)->toDateTimeString(),
            'verified' => false,
        ]);

        try {
            Mail::raw(
                "Your NIYO_7 registration OTP is {$otp}. It will expire in 10 minutes.",
                function ($message) use ($validated) {
                    $message->to($validated['email'])
                        ->subject('Your NIYO_7 registration OTP');
                }
            );
        } catch (\Throwable $e) {
            Log::error('Registration OTP email could not be sent.', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'The OTP email could not be sent right now. Please check your mail settings and try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully. Please check your email.',
        ]);
    }

    public function verifyRegistrationOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'verification_code' => ['required', 'digits:6'],
        ]);

        $otpData = $request->session()->get('registration_otp');

        if (! $otpData || ($otpData['email'] ?? null) !== $validated['email']) {
            return response()->json([
                'message' => 'Please generate an OTP for this email first.',
            ], 422);
        }

        if (now()->gt($otpData['expires_at'])) {
            $request->session()->forget('registration_otp');

            return response()->json([
                'message' => 'The OTP has expired. Please generate a new one.',
            ], 422);
        }

        if (($otpData['code'] ?? null) !== $validated['verification_code']) {
            return response()->json([
                'message' => 'The verification code is incorrect.',
            ], 422);
        }

        $otpData['verified'] = true;
        $request->session()->put('registration_otp', $otpData);

        return response()->json([
            'message' => 'Email verified successfully. You can now create your password.',
        ]);
    }

    private function sendRegistrationSms(string $telephone, string $name, int $userId): void
    {
        $cashPowerNumber = $this->formatCashPowerNumber($userId);
        $message = "Hello {$name}, your account has been created successfully. Your Cash Power number is {$cashPowerNumber}. Your starting balance is 0 kWh. Please top up to begin using power.";

        if (! $this->smsService->send($telephone, $message)) {
            Log::warning('Registration SMS could not be sent.', [
                'telephone' => $telephone,
            ]);
        }
    }

    private function ensureUserMeterExists(int $userId): void
    {
        $meterExists = DB::table('meter_status')->where('user_id', $userId)->exists();

        if ($meterExists) {
            return;
        }

        DB::table('meter_status')->insert($this->newMeterData($userId));
    }

    private function newMeterData(int $userId): array
    {
        $data = [
            'user_id' => $userId,
            'unit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('meter_status', 'meter_number')) {
            $data['meter_number'] = $this->formatCashPowerNumberRaw($userId);
        }

        if (Schema::hasColumn('meter_status', 'device_name')) {
            $data['device_name'] = 'Meter ' . $this->formatCashPowerNumberRaw($userId);
        }

        if (Schema::hasColumn('meter_status', 'device_status')) {
            $data['device_status'] = 'offline';
        }

        return $data;
    }

    private function activateRelayForUser(int $userId): void
    {
        DB::table('meter_status')->update(['connected' => 0, 'updated_at' => now()]);

        $updates = [
            'connected' => 1,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('meter_status', 'device_status')) {
            $updates['device_status'] = 'online';
        }

        DB::table('meter_status')
            ->where('user_id', $userId)
            ->update($updates);
    }

    private function deactivateRelayForUser(int $userId): void
    {
        $updates = [
            'connected' => 0,
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('meter_status', 'device_status')) {
            $updates['device_status'] = 'offline';
        }

        DB::table('meter_status')
            ->where('user_id', $userId)
            ->update($updates);
    }

    private function formatCashPowerNumber(int $userId): string
    {
        $paddedId = $this->formatCashPowerNumberRaw($userId);

        return implode(' ', str_split($paddedId, 4));
    }

    private function formatCashPowerNumberRaw(int $userId): string
    {
        return str_pad((string) $userId, 16, '0', STR_PAD_LEFT);
    }

    private function isRegistrationEmailVerified(Request $request, string $email): bool
    {
        $otpData = $request->session()->get('registration_otp');

        return $otpData
            && ($otpData['email'] ?? null) === $email
            && (bool) ($otpData['verified'] ?? false) === true;
    }
}
