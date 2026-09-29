<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ], attributes: ['current_password' => 'mật khẩu hiện tại', 'password' => 'mật khẩu mới']);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Đã đổi mật khẩu.');
    }
}
