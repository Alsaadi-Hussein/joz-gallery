<?php

namespace App\Http\Controllers\Shop;

use App\Exceptions\CheckoutFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutQuoteRequest;
use App\Http\Requests\CheckoutRequest;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\PriceService;
use App\Support\CartLine;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(): View
    {
        return view('shop.checkout', [
            'governorates' => Governorate::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * The order summary for the current cart, governorate and coupon. Runs each time
     * one of them changes, so the page only ever shows the server's figures.
     */
    public function quote(CheckoutQuoteRequest $request, Cart $cart, PriceService $prices): JsonResponse
    {
        $lines = $cart->lines($request->validated('items'));
        $subtotal = $lines->sum(fn (CartLine $line) => $line->total());
        $governorate = Governorate::query()->where('is_active', true)->find($request->validated('governorate_id'));

        $code = $request->validated('coupon');
        $coupon = filled($code) ? Coupon::query()->whereCode($code)->first() : null;
        $problem = filled($code) ? $prices->couponProblem($coupon, $subtotal) : null;

        $totals = $prices->totals($subtotal, $problem ? null : $coupon, $governorate->delivery_fee ?? 0);

        return response()->json([
            'lines' => $lines->map(fn (CartLine $line) => $line->toArray()),
            'count' => $lines->sum(fn (CartLine $line) => $line->qty),
            'subtotal_formatted' => Money::format($totals->subtotal),
            'discount' => $totals->discount,
            'discount_formatted' => Money::format($totals->discount),
            'delivery_fee_formatted' => $governorate ? Money::format($totals->deliveryFee) : null,
            'total_formatted' => Money::format($totals->total()),
            'coupon_applied' => $coupon !== null && $problem === null,
            'coupon_message' => $problem ?? (filled($code) ? __('shop.coupon.applied') : null),
        ]);
    }

    public function store(CheckoutRequest $request, Checkout $checkout): RedirectResponse
    {
        try {
            $order = $checkout->place(
                $request->safe()->only(['name', 'phone', 'governorate_id', 'area', 'landmark', 'notes']),
                $request->validated('items'),
                $request->validated('coupon'),
                app()->getLocale(),
                $request->ip(),
            );
        } catch (CheckoutFailed $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()])->withInput();
        }

        // Flashed so that reopening an old link never empties a newer cart.
        return redirect($order->thanksUrl())->with('order_placed', true);
    }

    public function thanks(Order $order): View
    {
        return view('shop.thanks', ['order' => $order->load(['items', 'governorate'])]);
    }
}
