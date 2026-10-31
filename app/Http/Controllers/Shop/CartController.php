<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\CartPreviewRequest;
use App\Services\Cart;
use App\Support\CartLine;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function show(): View
    {
        return view('shop.cart');
    }

    public function preview(CartPreviewRequest $request, Cart $cart): JsonResponse
    {
        $lines = $cart->lines($request->validated('items'));
        $subtotal = $lines->sum(fn (CartLine $line) => $line->total());

        return response()->json([
            'lines' => $lines->map(fn (CartLine $line) => $line->toArray()),
            'count' => $lines->sum(fn (CartLine $line) => $line->qty),
            'subtotal' => $subtotal,
            'subtotal_formatted' => Money::format($subtotal),
        ]);
    }
}
