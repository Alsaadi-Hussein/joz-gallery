<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const HSTS_SECONDS = 31536000;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Keeps signed order links out of what third parties such as Google Fonts see.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($request));

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::HSTS_SECONDS);
        }

        return $response;
    }

    /**
     * The shop runs only its own built scripts, so an injected one cannot run there.
     * Filament and Livewire write inline scripts and use eval, so the admin cannot
     * have that protection until they support it.
     */
    private function contentSecurityPolicy(Request $request): string
    {
        $admin = $request->is('admin', 'admin/*');

        $policy = [
            "default-src 'self'",
            'script-src '.($admin ? "'self' 'unsafe-inline' 'unsafe-eval'" : "'self'"),
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ];

        // The image uploader shrinks photos in a worker it builds in the browser. Without
        // this every upload stays on "uploading" forever and shows no error.
        if ($admin) {
            $policy[] = "worker-src 'self' blob:";
        }

        if ($request->secure()) {
            $policy[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $policy);
    }
}
