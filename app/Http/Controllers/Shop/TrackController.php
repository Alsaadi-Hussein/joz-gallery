<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\TrackOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrackController extends Controller
{
    public function show(): View
    {
        return view('shop.track');
    }

    /**
     * The same message for a wrong number and a wrong phone, so nobody can use this
     * form to find out which order numbers exist.
     */
    public function lookup(TrackOrderRequest $request): View|RedirectResponse
    {
        $order = Order::query()
            ->where('number', $request->validated('number'))
            ->where('phone', $request->validated('phone'))
            ->with('items')
            ->first();

        if (! $order) {
            return back()->withErrors(['number' => __('shop.track.not_found')])->withInput();
        }

        return view('shop.track', ['order' => $order]);
    }
}
