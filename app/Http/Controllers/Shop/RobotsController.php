<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class RobotsController extends Controller
{
    private const PRIVATE_PATHS = ['/admin', '/cart', '/checkout', '/order/', '/track', '/search'];

    /**
     * Served by a route rather than a file so the sitemap line uses the live domain.
     */
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        foreach (self::PRIVATE_PATHS as $path) {
            $lines[] = "Disallow: {$path}";
            $lines[] = "Disallow: /en{$path}";
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('sitemap.xml');

        return response(implode("\n", $lines)."\n")->header('Content-Type', 'text/plain');
    }
}
