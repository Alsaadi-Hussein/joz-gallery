@php
    use App\Support\Money;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>{{ __('orders.invoice.title') }} {{ $order->number }}</title>
<link rel="icon" type="image/png" href="{{ asset('assets/img/brand/favicon.png') }}">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap">
<style>
  :root { --walnut: #5b3e34; --ink: #3b2a24; --muted: #806c63; --line: #e4d9cd; }
  * { box-sizing: border-box; }
  body { margin: 0; background: #efe6d6; color: var(--ink); font-family: Tajawal, Tahoma, sans-serif; font-size: 14px; line-height: 1.6; }
  .sheet { width: min(100% - 24px, 210mm); margin: 16px auto; padding: 14mm; background: #fff; }
  .head { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding-bottom: 14px; border-bottom: 2px solid var(--walnut); }
  .head img { width: 90px; height: auto; }
  .head h1 { margin: 0; font-size: 22px; color: var(--walnut); }
  .head p { margin: 0; color: var(--muted); }
  .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 18px 0; }
  .parties h2 { margin: 0 0 4px; font-size: 13px; color: var(--muted); font-weight: 500; }
  .parties p { margin: 0; }
  table { width: 100%; border-collapse: collapse; }
  th { padding: 8px; background: #f7f1e6; color: var(--walnut); text-align: start; font-weight: 700; }
  td { padding: 8px; border-bottom: 1px solid var(--line); }
  .num { text-align: end; white-space: nowrap; font-variant-numeric: tabular-nums; }
  .totals { width: min(100%, 300px); margin: 14px 0 0 auto; }
  [dir="rtl"] .totals { margin: 14px auto 0 0; }
  .totals td { border: 0; padding: 4px 8px; }
  .totals .grand td { border-top: 2px solid var(--walnut); font-size: 16px; font-weight: 700; color: var(--walnut); }
  .foot { margin-top: 24px; padding-top: 12px; border-top: 1px solid var(--line); color: var(--muted); text-align: center; }
  .print { position: fixed; bottom: 16px; inset-inline-end: 16px; padding: 10px 24px; border: 0; border-radius: 999px; background: var(--walnut); color: #f8f3e0; font: inherit; cursor: pointer; }
  @media print {
    body { background: #fff; }
    .sheet { width: auto; margin: 0; padding: 0; }
    .print { display: none; }
    @page { size: A4; margin: 14mm; }
  }
</style>
</head>
<body>
<main class="sheet">
  <header class="head">
    <div>
      <h1>{{ __('orders.invoice.title') }}</h1>
      <p dir="ltr">{{ $order->number }}</p>
      <p>{{ $order->created_at->format('Y-m-d H:i') }}</p>
    </div>
    <img src="{{ asset('assets/img/brand/logo-walnut.png') }}" alt="{{ __('admin.brand') }}" width="90">
  </header>

  <section class="parties">
    <div>
      <h2>{{ __('orders.invoice.bill_to') }}</h2>
      <p><strong>{{ $order->name }}</strong></p>
      <p dir="ltr" style="text-align:start">{{ $order->phone }}</p>
      <p>{{ $order->governorate->name_ar }} / {{ $order->area }}</p>
      @if ($order->landmark)<p>{{ $order->landmark }}</p>@endif
    </div>
    <div>
      <h2>{{ __('orders.invoice.payment') }}</h2>
      <p>{{ __('orders.invoice.cash') }}</p>
      @if ($order->notes)
        <h2 style="margin-top:10px">{{ __('orders.fields.notes') }}</h2>
        <p>{{ $order->notes }}</p>
      @endif
    </div>
  </section>

  <table>
    <thead>
      <tr>
        <th>{{ __('orders.fields.item') }}</th>
        <th class="num">{{ __('orders.fields.unit_price') }}</th>
        <th class="num">{{ __('orders.fields.qty') }}</th>
        <th class="num">{{ __('orders.fields.line_total') }}</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($order->items as $item)
        <tr>
          <td>{{ $item->name_snapshot }}@if ($item->variant_snapshot) ({{ $item->variant_snapshot }})@endif</td>
          <td class="num">{{ Money::format($item->unit_price) }}</td>
          <td class="num">{{ $item->qty }}</td>
          <td class="num">{{ Money::format($item->line_total) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals">
    <tr><td>{{ __('orders.fields.subtotal') }}</td><td class="num">{{ Money::format($order->subtotal) }}</td></tr>
    @if ($order->discount)
      <tr><td>{{ __('orders.fields.discount') }}</td><td class="num">-{{ Money::format($order->discount) }}</td></tr>
    @endif
    <tr><td>{{ __('orders.fields.delivery_fee') }}</td><td class="num">{{ Money::format($order->delivery_fee) }}</td></tr>
    <tr class="grand"><td>{{ __('orders.fields.total') }}</td><td class="num">{{ Money::format($order->total) }}</td></tr>
  </table>

  <p class="foot">{{ __('orders.invoice.thanks') }}</p>
</main>

<button class="print" type="button" onclick="window.print()">{{ __('orders.invoice.print') }}</button>
</body>
</html>
