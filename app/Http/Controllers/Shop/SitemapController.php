<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SitemapController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists('sitemap.xml'), 404);

        return Storage::disk('local')->response('sitemap.xml', headers: ['Content-Type' => 'application/xml']);
    }
}
