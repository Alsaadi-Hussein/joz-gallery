<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    /**
     * The landing page: the background video, the brand and a way into the store.
     */
    public function __invoke(): View
    {
        return view('shop.welcome');
    }
}
