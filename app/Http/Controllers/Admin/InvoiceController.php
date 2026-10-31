<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __invoke(Order $order): View
    {
        Gate::authorize('view', $order);

        return view('admin.invoice', ['order' => $order->load(['items', 'governorate'])]);
    }
}
