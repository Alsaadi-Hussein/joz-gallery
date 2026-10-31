@php
    use App\Support\Money;
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:24px;background:#f8f3e0;font-family:Tahoma,Arial,sans-serif;color:#3b2a24;line-height:1.7">
  <div style="max-width:560px;margin:0 auto;background:#fffdf7;border-radius:18px;padding:28px">
    <h1 style="margin:0 0 4px;font-size:20px;color:#5b3e34">{{ __('orders.email.heading') }}</h1>
    <p style="margin:0 0 20px;color:#806c63">{{ $order->number }}</p>

    <p style="margin:0">
      <strong>{{ __('orders.fields.name') }}:</strong> {{ $order->name }}<br>
      <strong>{{ __('orders.fields.phone') }}:</strong> <span dir="ltr">{{ $order->phone }}</span><br>
      <strong>{{ __('orders.fields.governorate') }}:</strong> {{ $order->governorate->name_ar }} / {{ $order->area }}
      @if ($order->landmark)<br><strong>{{ __('orders.fields.landmark') }}:</strong> {{ $order->landmark }}@endif
      @if ($order->notes)<br><strong>{{ __('orders.fields.notes') }}:</strong> {{ $order->notes }}@endif
    </p>

    <table role="presentation" style="width:100%;margin:20px 0;border-collapse:collapse">
      @foreach ($order->items as $item)
        <tr>
          <td style="padding:8px 0;border-bottom:1px solid #ecdcd2">
            {{ $item->name_snapshot }}@if ($item->variant_snapshot) ({{ $item->variant_snapshot }})@endif × {{ $item->qty }}
          </td>
          <td style="padding:8px 0;border-bottom:1px solid #ecdcd2;text-align:left;white-space:nowrap">{{ Money::format($item->line_total, 'ar') }}</td>
        </tr>
      @endforeach
      @if ($order->discount)
        <tr>
          <td style="padding:8px 0">{{ __('orders.fields.discount') }}</td>
          <td style="padding:8px 0;text-align:left;white-space:nowrap">-{{ Money::format($order->discount, 'ar') }}</td>
        </tr>
      @endif
      <tr>
        <td style="padding:8px 0">{{ __('orders.fields.delivery_fee') }}</td>
        <td style="padding:8px 0;text-align:left;white-space:nowrap">{{ Money::format($order->delivery_fee, 'ar') }}</td>
      </tr>
      <tr>
        <td style="padding:8px 0;font-weight:bold;color:#5b3e34">{{ __('orders.fields.total') }}</td>
        <td style="padding:8px 0;text-align:left;white-space:nowrap;font-weight:bold;color:#5b3e34">{{ Money::format($order->total, 'ar') }}</td>
      </tr>
    </table>

    <a href="{{ $adminUrl }}" style="display:inline-block;padding:12px 24px;border-radius:999px;background:#5b3e34;color:#f8f3e0;text-decoration:none">{{ __('orders.email.open') }}</a>
  </div>
</body>
</html>
