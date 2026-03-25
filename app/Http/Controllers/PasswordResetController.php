<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    private const GENERIC_REQUEST_MESSAGE = 'If an account exists for that email, we have sent password reset instructions.';

    public function showForgotForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        $this->logLinkRequest($request, $status);

        if ($status === Password::RESET_THROTTLED) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        return back()
            ->with('status', self::GENERIC_REQUEST_MESSAGE);
    }

    public function showResetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', old('email', '')),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) use ($request) {
                $user->password = Hash::make($password);
                $user->save();

                Log::info('Password reset completed.', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route('login')
                ->with('success', 'Your password has been reset. You can sign in with your new password.');
        }

        Log::warning('Password reset attempt failed.', [
            'email' => self::normalizedEmail($request),
            'ip' => $request->ip(),
            'broker_status' => $status,
        ]);

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }

    private function logLinkRequest(Request $request, string $status): void
    {
        $context = [
            'email' => self::normalizedEmail($request),
            'ip' => $request->ip(),
            'outcome' => match ($status) {
                Password::RESET_THROTTLED => 'throttled',
                Password::RESET_LINK_SENT => 'reset_link_sent',
                Password::INVALID_USER => 'no_matching_user',
                default => 'other',
            },
            'broker_status' => $status,
        ];

        $level = $status === Password::RESET_THROTTLED ? 'warning' : 'info';

        Log::log($level, 'Password reset link requested.', $context);
    }

    private static function normalizedEmail(Request $request): string
    {
        return strtolower((string) $request->input('email', ''));
    }
}
