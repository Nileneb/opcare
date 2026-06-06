<?php

namespace App\Domains\Identity\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-MFA (Track B): Secret-Erzeugung, QR-Code, Code-Verifikation und Recovery-Codes.
 * Kapselt google2fa + BaconQrCode, damit Livewire-Komponenten testbar bleiben.
 */
class TwoFactorAuthenticator
{
    public function __construct(private readonly Google2FA $engine = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey();
    }

    /** Verifiziert einen TOTP-Code gegen das Secret (±1 Zeitfenster Toleranz). */
    public function verify(string $secret, string $code): bool
    {
        $code = trim($code);

        return $code !== '' && $this->engine->verifyKey($secret, $code, 1);
    }

    /** otpauth-URI → SVG-QR-Code (inline einbettbar). */
    public function qrCodeSvg(string $accountEmail, string $secret, string $issuer = 'OPCare'): string
    {
        $uri = $this->engine->getQRCodeUrl($issuer, $accountEmail, $secret);

        $renderer = new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd);

        return (new Writer($renderer))->writeString($uri);
    }

    /**
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }
}
