<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function store(Request $request)
{
    $request->merge([
        'name' => preg_replace('/\s+/', ' ', trim((string) $request->input('name'))),
        'email' => strtolower(trim((string) $request->input('email'))),
    ]);

    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:80',
        ],
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('users', 'email'),
        ],
        'password' => [
            'required',
            'string',
            'min:8',
            'confirmed',
        ],
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
        'email' => [
            'required',
            'email',
        ],
        'password' => [
            'required',
            'string',
            'min:8',
        ],
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

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful!',
        'user' => new UserResource($user),
        'token' => $token,
    ]);
}

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::where('email', $email)->first();

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
            Mail::raw(
                "Hello {$user->name},\n\n" .
                "Your Planora password reset code is: {$code}\n\n" .
                "This code will expire in 15 minutes.\n\n" .
                "If you did not request this, you can ignore this email.\n\n" .
                "Planora Team",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Planora Password Reset Code');
                }
            );
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
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = strtolower(trim($validated['email']));

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
            DB::table('password_reset_tokens')->where('email', $email)->delete();

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

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password reset successfully. You can now sign in.',
        ]);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->redirect();
    }

    public function handleGoogleCallback()
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

            $token = $user->createToken('google_auth_token')->plainTextToken;

            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200'));

            return redirect()->away(
                $frontendUrl . '/login?google_token=' . urlencode($token)
            );
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

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
        ]);

        $user->update([
            'name' => trim($validated['name']),
            'email' => strtolower(trim($validated['email'])),
        ]);

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
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

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

    /*
     * If the user already has a password, require the current password.
     * If the user came from Google and has no password yet, allow setting a password.
     */
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

    return response()->json([
        'message' => 'Password updated successfully.',
        'user' => new UserResource($user->fresh()),
    ]);
}
}