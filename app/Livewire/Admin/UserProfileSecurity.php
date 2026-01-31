<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

final class UserProfileSecurity extends Component
{
    public User $user;

    public $showQrCode = false;

    public $qrCodeUrl;

    public $secret;

    public $code;

    public $password;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    public function enableTwoFactor()
    {
        $google2fa = app('pragmarx.google2fa');

        $this->secret = $google2fa->generateSecretKey();
        $this->qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $this->user->email,
            $this->secret
        );

        $this->showQrCode = true;
    }

    public function confirmTwoFactor()
    {
        $this->validate([
            'code' => 'required|digits:6',
        ]);

        $google2fa = app('pragmarx.google2fa');

        if ($google2fa->verifyKey($this->secret, $this->code)) {
            $this->user->update([
                'two_factor_secret' => $this->secret,
                'two_factor_confirmed_at' => now(),
            ]);

            $this->showQrCode = false;
            $this->reset(['secret', 'code', 'qrCodeUrl']);

            session()->flash('success', 'Two-factor authentication has been enabled.');
        } else {
            $this->addError('code', 'The provided verification code was invalid.');
        }
    }

    public function disableTwoFactor()
    {
        $this->validate([
            'password' => 'required',
        ]);

        if (! Hash::check($this->password, auth()->user()->password)) {
            $this->addError('password', 'The provided password does not match our records.');

            return;
        }

        $this->user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $this->reset('password');
        session()->flash('success', 'Two-factor authentication has been disabled.');
    }

    public function render()
    {
        return view('livewire.admin.user-profile-security');
    }
}
