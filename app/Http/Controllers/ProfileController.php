<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController extends Controller
{
    public function index()
    {
        return view('profile', ['user' => auth()->user()]);
    }

    public function edit()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $possibleFields = ['username','email','gender','dob','password'];

        $rules = [];
        if ($request->has('username')) {
            $rules['username'] = 'required|string|max:255|unique:users,username,' . $user->id;
        }
        if ($request->has('email')) {
            $rules['email'] = 'required|email|max:255|unique:users,email,' . $user->id;
        }
        if ($request->has('gender')) {
            $rules['gender'] = 'required|in:Male,Female';
        }
        if ($request->has('dob')) {
            $rules['dob'] = 'nullable|date|before:today';
        }
        if ($request->filled('password')) {
            $rules['password'] = [
                'required',
                'string',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{7,}$/'
            ];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $updated = [];

        foreach ($possibleFields as $field) {
            if ($field === 'password') {
                if ($request->filled('password')) {
                    // Prevent same password
                    if (Hash::check($request->password, $user->password)) {
                        return redirect()->back()
                            ->withErrors(['password' => 'New password must be different from current password'])
                            ->withInput();
                    }
                    $user->password = Hash::make($request->password);
                    $updated[] = 'password';
                }
                continue;
            }

            if (! $request->has($field)) {
                continue;
            }

            $newVal = $request->input($field);
            $curVal = $user->{$field};

            // normalize both for comparison
            if ($field === 'dob' && $newVal) {
                $newVal = \Carbon\Carbon::parse($newVal)->toDateString();
                $curVal = $user->dob ? (string) $user->dob : null;
            }

            if ($curVal != $newVal) {
                $user->{$field} = $newVal;
                if ($field === 'dob' && \Schema::hasColumn('users', 'age')) {
                    $user->age = \Carbon\Carbon::parse($newVal)->age;
                }
                $updated[] = $field;
            }
        }

        if (empty($updated)) {
            return redirect()->back()->with('info', 'No changes detected.');
        }

        $user->save();

        return redirect()->route('profile.index')
            ->with('success', 'Updated: ' . implode(', ', $updated));
    }

}
