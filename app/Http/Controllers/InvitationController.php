<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class InvitationController extends Controller
{
    /**
     * Show the invitation acceptance form
     */
    public function show(Request $request, string $token)
    {
        $email = $request->query('email');

        if (! $email) {
            return redirect()->route('login')
                ->with('error', 'Invalid invitation link. Please contact an administrator.');
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'User not found. Please contact an administrator.');
        }

        // Tokens are stored with the app hasher (e.g. bcrypt), not SHA-256 — use the password broker.
        if (! Password::broker()->tokenExists($user, $token)) {
            return redirect()->route('login')
                ->with('error', 'This invitation link is invalid or has expired. Please contact an administrator.');
        }

        return view('auth.accept-invitation', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * Handle invitation acceptance and password setup
     */
    public function accept(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return back()->withErrors(['email' => 'User not found.'])->withInput();
        }

        // Use Laravel's password reset to verify token and set password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            // Automatically log the user in after setting password
            auth()->login($user);

            return redirect()->route('dashboard.index')
                ->with('success', 'Your password has been set successfully. Welcome!');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
