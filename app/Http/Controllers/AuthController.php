<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function store(Request $request)
    {
        $request->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'name.required' => 'Full name is required.',
            'name.max' => 'Full name must not exceed 80 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'User registered successfully!',
            'user' => new UserResource($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !$user->password || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password. Please check your credentials.',
            ], 401);
        }

        if ($user->two_factor_enabled) {
            $temporaryToken = Str::random(80);

            DB::table('two_factor_login_tokens')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'token' => Hash::make($temporaryToken),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return response()->json([
                'message' => 'Two-factor authentication is required.',
                'requires_2fa' => true,
                'two_factor_token' => $temporaryToken,
            ]);
        }

        // Establish a first-party session (httpOnly cookie) instead of a bearer token.
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful!',
            'requires_2fa' => false,
            'user' => new UserResource($user),
        ]);
    }

    public function verifyTwoFactorLogin(Request $request)
    {
        $validated = $request->validate([
            'two_factor_token' => 'required|string',
            'code' => 'required|string',
        ]);

        $records = DB::table('two_factor_login_tokens')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->get();

        $matchedRecord = null;

        foreach ($records as $record) {
            if (Hash::check($validated['two_factor_token'], $record->token)) {
                $matchedRecord = $record;
                break;
            }
        }

        if (!$matchedRecord) {
            return response()->json([
                'message' => 'Invalid or expired 2FA login session. Please sign in again.',
            ], 401);
        }

        $user = User::find($matchedRecord->user_id);

        if (!$user || !$user->two_factor_enabled || !$user->two_factor_secret) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled for this account.',
            ], 422);
        }

        $isValidCode = $this->verifyTwoFactorCode($user, $validated['code']);

        if (!$isValidCode) {
            return response()->json([
                'message' => 'Invalid authenticator code.',
            ], 422);
        }

        DB::table('two_factor_login_tokens')
            ->where('user_id', $user->id)
            ->delete();

        // 2FA passed — establish the first-party session.
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Login successful!',
            'user' => new UserResource($user),
        ]);
    }

    public function setupTwoFactor(Request $request)
    {
        $user = $request->user();

        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $issuer = config('app.name', 'Planora');

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            $issuer,
            $user->email,
            $secret
        );

        $qrCodeSvg = $this->generateQrCodeSvg($qrCodeUrl);

        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json([
            'message' => 'Scan the QR code using Microsoft Authenticator, then enter the 6-digit code to confirm.',
            'qr_code_svg' => $qrCodeSvg,
            'manual_setup_key' => $secret,
        ]);
    }

    public function confirmTwoFactor(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => 'required|string',
        ]);

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Please start 2FA setup first.',
            ], 422);
        }

        $isValidCode = $this->verifyTwoFactorCode($user, $validated['code']);

        if (!$isValidCode) {
            return response()->json([
                'message' => 'Invalid authenticator code. Please try again.',
            ], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication has been enabled successfully.',
            'recovery_codes' => $recoveryCodes,
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (!$user->password || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect.',
            ], 422);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'message' => 'Two-factor authentication has been disabled.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $user = $request->user();

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'Two-factor authentication is not enabled.',
            ], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        return response()->json([
            'message' => 'Recovery codes regenerated successfully.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
        ]);

        $email = $validated['email'];
        $user = User::where('email', $email)->first();

        /*
         * Security reason:
         * We do not reveal if the email exists or not.
         * This prevents people from checking registered emails.
         */
        if (!$user) {
            return response()->json([
                'message' => 'If this email exists in Planora, a reset code has been sent.',
            ]);
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            Mail::send([], [], function ($message) use ($email, $user, $code) {
                $message->to($email)
                    ->subject('Planora Password Reset Code')
                    ->html($this->passwordResetEmailHtml($user->name, $code));
            });
        } catch (\Throwable $e) {
            Log::error('Password reset email failed', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send reset code right now. Please try again later.',
            ], 500);
        }

        return response()->json([
            'message' => 'If this email exists in Planora, a reset code has been sent.',
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->merge([
            'email' => strtolower(trim((string) $request->input('email'))),
            'code' => preg_replace('/\D/', '', (string) $request->input('code')),
        ]);

        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'code.required' => 'Reset code is required.',
            'code.size' => 'Reset code must be 6 digits.',
            'password.required' => 'New password is required.',
            'password.min' => 'New password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
        ]);

        $email = $validated['email'];

        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$resetRecord) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        $createdAt = $resetRecord->created_at
            ? Carbon::parse($resetRecord->created_at)
            : null;

        if (!$createdAt || $createdAt->lt(now()->subMinutes(15))) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            throw ValidationException::withMessages([
                'code' => ['Reset code has expired. Please request a new one.'],
            ]);
        }

        if (!Hash::check($validated['code'], $resetRecord->token)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid reset code.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No account found for this email.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        /*
         * Delete old login tokens so anyone previously logged in must sign in again.
         */
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now sign in.',
        ]);
    }

    private function passwordResetEmailHtml(string $name, string $code): string
    {
        $safeName = e($name);
        $safeCode = e($code);

        return "
            <div style='margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;color:#111827;'>
                <div style='max-width:560px;margin:0 auto;padding:32px 16px;'>
                    <div style='background:#ffffff;border-radius:18px;padding:32px;border:1px solid #e5e7eb;'>
                        <div style='display:inline-block;background:#111827;color:#ffffff;border-radius:12px;padding:10px 14px;font-weight:700;margin-bottom:20px;'>
                            Planora
                        </div>

                        <h2 style='margin:0 0 12px;font-size:24px;color:#111827;'>
                            Password Reset Code
                        </h2>

                        <p style='margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;'>
                            Hello {$safeName},
                        </p>

                        <p style='margin:0 0 20px;font-size:15px;line-height:1.6;color:#374151;'>
                            You requested to reset your Planora password. Use the 6-digit code below to create a new password.
                        </p>

                        <div style='text-align:center;margin:28px 0;'>
                            <div style='display:inline-block;background:#f9fafb;border:1px solid #e5e7eb;border-radius:14px;padding:18px 28px;font-size:32px;font-weight:800;letter-spacing:8px;color:#111827;'>
                                {$safeCode}
                            </div>
                        </div>

                        <p style='margin:0 0 12px;font-size:14px;line-height:1.6;color:#4b5563;'>
                            This code will expire in 15 minutes.
                        </p>

                        <p style='margin:0 0 24px;font-size:14px;line-height:1.6;color:#4b5563;'>
                            If you did not request this, you can safely ignore this email.
                        </p>

                        <p style='margin:0;font-size:14px;color:#111827;font-weight:700;'>
                            Planora Team
                        </p>
                    </div>
                </div>
            </div>
        ";
    }

    private function verifyTwoFactorCode(User $user, string $code): bool
    {
        $cleanCode = preg_replace('/\s+/', '', trim($code));

        if (!$user->two_factor_secret) {
            return false;
        }

        $secret = Crypt::decryptString($user->two_factor_secret);

        $google2fa = new Google2FA();

        return $google2fa->verifyKey($secret, $cleanCode, 2);
    }

    private function generateQrCodeSvg(string $qrCodeUrl): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(260),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => strtoupper(Str::random(5) . '-' . Str::random(5)))
            ->toArray();
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->user();

            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user) {
                $user->update([
                    'google_id' => $user->google_id ?: $googleUser->getId(),
                    'avatar' => $user->avatar ?: $googleUser->getAvatar(),
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'password' => null,
                ]);
            }

            if ($user->two_factor_enabled) {
                $temporaryToken = Str::random(80);

                DB::table('two_factor_login_tokens')->updateOrInsert(
                    ['user_id' => $user->id],
                    [
                        'token' => Hash::make($temporaryToken),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );

                $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200'));

                return redirect()->away(
                    $frontendUrl . '/login?requires_2fa=1&two_factor_token=' . urlencode($temporaryToken)
                );
            }

            // Establish the first-party session cookie (no token in the URL).
            Auth::login($user);
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200'));

            return redirect()->away($frontendUrl . '/dashboard');
        } catch (\Throwable $e) {
            Log::error('Google login failed', [
                'message' => $e->getMessage(),
            ]);

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200'));

            return redirect()->away(
                $frontendUrl . '/login?google_error=' . urlencode('Google sign in failed. Please try again.')
            );
        }
    }

    public function getProfile(Request $request)
    {
        return response()->json([
            'message' => 'Profile fetched successfully.',
            'user' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->merge([
            'name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name'))),
            'email' => strtolower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'timezone' => ['nullable', 'string', Rule::in(timezone_identifiers_list())],
            'preferences' => 'nullable|array',
            'preferences.theme' => 'nullable|string|in:light,dark,system',
            'preferences.email_notifications' => 'nullable|boolean',
            'preferences.default_board_view' => 'nullable|string|in:board,backlog',
        ]);

        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if (array_key_exists('timezone', $validated)) {
            $payload['timezone'] = $validated['timezone'];
        }

        if (array_key_exists('preferences', $validated)) {
            // Merge so partial updates don't wipe other preference keys.
            $payload['preferences'] = array_merge($user->preferences ?? [], $validated['preferences'] ?? []);
        }

        $user->update($payload);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function updateAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($user->avatar && !str_starts_with($user->avatar, 'http://') && !str_starts_with($user->avatar, 'https://')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $validated['avatar']->store('avatars', 'public');

        $user->update([
            'avatar' => $path,
        ]);

        return response()->json([
            'message' => 'Profile picture updated successfully.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();

        if ($user->avatar && !str_starts_with($user->avatar, 'http://') && !str_starts_with($user->avatar, 'https://')) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'avatar' => null,
        ]);

        return response()->json([
            'message' => 'Profile picture removed successfully.',
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke a bearer token if one was used; otherwise tear down the session.
        $token = $request->user()?->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $rules = [
            'password' => 'required|string|min:8|confirmed',
        ];

        if ($user->password) {
            $rules['current_password'] = 'required|string';
        }

        $validated = $request->validate($rules);

        if ($user->password && !Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if ($user->password && Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from your current password.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password updated successfully. Please log in again.',
            'user' => new UserResource($user->fresh()),
        ]);
    }
}