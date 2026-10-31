<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function __invoke(Page $page): View
    {
        abort_unless($page->is_active, 404);

        return view('shop.page', ['page' => $page]);
    }
}
