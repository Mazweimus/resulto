<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Zadejte prosím e-mail.',
            'email.email' => 'Zadejte platnou e-mailovou adresu.',
            'password.required' => 'Zadejte heslo.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();
            return redirect()->intended('/')->with('success', 'Vítejte zpět, ' . Auth::user()->name . '!');
        }

        return back()->withErrors([
            'email' => 'Zadané přihlašovací údaje nejsou správné.',
        ])->onlyInput('email');
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ], [
            'name.required' => 'Jméno je povinné.',
            'email.required' => 'E-mail je povinný.',
            'email.unique' => 'Uživatel s tímto e-mailem již existuje.',
            'password.required' => 'Heslo je povinné.',
            'password.min' => 'Heslo musí mít alespoň 6 znaků.',
            'password.confirmed' => 'Hesla se neshodují.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect('/')->with('success', 'Registrace proběhla úspěšně! Vítejte v systému Resulto.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Byli jste úspěšně odhlášeni.');
    }
}
