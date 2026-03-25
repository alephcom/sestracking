<?php

namespace App\Http\Controllers;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(
        protected Google2FA $google2fa
    ) {}

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get('login.two_factor_pending_user_id');
        if (! $userId) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your authentication session expired. Please sign in again.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user || ! $user->requiresInAppTwoFactor() || ! $user->hasConfirmedTwoFactor()) {
            $request->session()->forget('login.two_factor_pending_user_id');

            return redirect()->route('login')->withErrors([
                'email' => 'Your authentication session expired. Please sign in again.',
            ]);
        }

        return view('auth.two-factor-challenge', [
            'emailHint' => $user->email,
        ]);
    }

    public function confirmChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('login.two_factor_pending_user_id');
        if (! $userId) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your authentication session expired. Please sign in again.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (! $user || ! $user->requiresInAppTwoFactor() || ! $user->hasConfirmedTwoFactor()) {
            $request->session()->forget('login.two_factor_pending_user_id');

            return redirect()->route('login')->withErrors([
                'email' => 'Your authentication session expired. Please sign in again.',
            ]);
        }

        $code = strtoupper(trim((string) $request->input('code')));

        if ($this->verifyRecoveryCode($user, $code)) {
            $user->save();
        } elseif (! $this->google2fa->verifyKey((string) $user->two_factor_secret, $code, 2)) {
            return back()->withErrors([
                'code' => 'Invalid authentication code.',
            ])->withInput();
        }

        $remember = (bool) $request->session()->pull('login.two_factor_remember', false);
        $request->session()->forget('login.two_factor_pending_user_id');
        Auth::login($user, remember: $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard.index'));
    }

    public function showSetup(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->requiresInAppTwoFactor()) {
            return redirect()->route('dashboard.index');
        }

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dashboard.index');
        }

        if (! $user->two_factor_secret) {
            $user->two_factor_secret = $this->google2fa->generateSecretKey();
            $user->save();
        }

        $secret = (string) $user->two_factor_secret;
        $otpauthUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret,
        );

        return view('auth.two-factor-setup', [
            'qrSvg' => $this->qrCodeSvg($otpauthUrl),
            'secret' => $secret,
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->requiresInAppTwoFactor()) {
            return redirect()->route('dashboard.index');
        }

        if ($user->hasConfirmedTwoFactor()) {
            return redirect()->route('dashboard.index');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        if (! $user->two_factor_secret) {
            return redirect()->route('two-factor.setup');
        }

        $code = trim((string) $request->input('code'));
        $secret = (string) $user->two_factor_secret;

        if (! $this->google2fa->verifyKey($secret, $code, 2)) {
            return back()->withErrors([
                'code' => 'Invalid verification code. Try again.',
            ])->withInput();
        }

        [$plainRecoveryCodes, $storedHashes] = $this->makeRecoveryCodes();
        $user->two_factor_recovery_codes = $storedHashes;
        $user->two_factor_confirmed_at = now();
        $user->save();

        return redirect()
            ->route('two-factor.recovery-codes')
            ->with('recovery_codes_display', $plainRecoveryCodes);
    }

    public function showRecoveryCodes(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes_display');
        if (! is_array($codes) || $codes === []) {
            return redirect()->route('dashboard.index');
        }

        return view('auth.two-factor-recovery-codes', [
            'recoveryCodes' => $codes,
        ]);
    }

    public function cancelChallenge(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'login.two_factor_pending_user_id',
            'login.two_factor_remember',
        ]);

        return redirect()->route('login');
    }

    protected function verifyRecoveryCode(User $user, string $code): bool
    {
        $normalized = str_replace(' ', '', $code);
        $normalized = strtoupper($normalized);

        $hashes = $user->two_factor_recovery_codes;
        if (! is_array($hashes) || $hashes === []) {
            return false;
        }

        foreach ($hashes as $index => $hash) {
            if (Hash::check($normalized, $hash)) {
                unset($hashes[$index]);
                $user->two_factor_recovery_codes = array_values($hashes);

                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    protected function makeRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];
        for ($i = 0; $i < $count; $i++) {
            $segment = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $c = substr($segment, 0, 4).'-'.substr($segment, 4, 4);
            $plain[] = $c;
            $hashed[] = Hash::make($c);
        }

        return [$plain, $hashed];
    }

    protected function qrCodeSvg(string $payload): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString($payload);
    }
}
