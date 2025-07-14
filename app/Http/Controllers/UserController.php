<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    public function edit(Request $request)
    {
        $user = auth()->user();

        if($request->has('submit')) {

        // Validate the request data
            $validator = Validator::make($request->all(),[
                    'name' => 'required|string',
                    'username' => 'required|string',
                    'email' => 'required|email|string|max:255',
                    'password' => 'nullable|string|min:5|confirmed',
                ]);

                if ($validator->fails()) {
                        return redirect()->back()
                            ->with('alert','Something went wrong, please check your input.')
                            ->withInput();  
                }


            $user->name = $request->input('name');
            $user->username = $request->input('username');
            $user->email = $request->input('email');
            if($request->input('password')) {
                $user->password = Hash::make($request->input('password'));
            }   
            $user->save();

            return redirect()->back()->with('alert', 'Profile updated successfully.');
        }

        return response()->view('user.edit', [
            'form' => $user,
        ]);

    }
}
