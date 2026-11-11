<?php
declare(strict_types=1);

namespace OneEngine\Security;

final class Headers
{
    /** No proxy headers are trusted. TLS must be asserted by the configured web server. */
    public static function defaults(bool $https): array
    {
        $headers = [
            'Content-Security-Policy' => "default-src 'none'; style-src 'self'; img-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cache-Control' => 'no-store',
        ];
        if ($https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $headers;
    }
}
