<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function edit()
    {
        return response()->view('user.edit', [
            'form' => auth()->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255',
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
        ];

        // Changing the sign-in email or password requires proving knowledge of the current
        // password. SSO-only accounts have no password, so there is nothing to confirm.
        $changingCredentials = $request->input('email') !== $user->email || $request->filled('password');
        if ($changingCredentials && $user->password !== null) {
            $rules['current_password'] = 'required|current_password';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()
                ->with('alert', 'Something went wrong, please check your input.')
                ->withErrors($validator)
                ->withInput($request->except('password', 'password_confirmation', 'current_password'));
        }

        $user->name = $request->input('name');
        $user->username = $request->input('username');
        $user->email = $request->input('email');
        if ($request->filled('password')) {
            $user->password = Hash::make($request->input('password'));
        }
        $user->save();

        return redirect()->back()->with('alert', 'Profile updated successfully.');
    }
}
