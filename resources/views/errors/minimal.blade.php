@php
    // Stands alone with no database or layout, because it must still work when those are down.
    $english = request()->is('en', 'en/*');
    app()->setLocale($english ? 'en' : 'ar');
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $english ? 'ltr' : 'rtl' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>@yield('title')</title>
<link rel="icon" type="image/png" href="/assets/img/brand/favicon.png">
<style>
  body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f8f3e0; color: #3b2a24; font-family: Tajawal, "Segoe UI", Tahoma, sans-serif; text-align: center; }
  main { padding: 24px; }
  img { width: 88px; height: 88px; }
  h1 { margin: 16px 0 4px; font-size: 22px; color: #5b3e34; }
  p { margin: 0 0 20px; color: #806c63; }
  a { display: inline-block; padding: 12px 24px; border-radius: 999px; background: #5b3e34; color: #f8f3e0; text-decoration: none; }
</style>
</head>
<body>
<main>
  <img src="/assets/img/brand/emblem-walnut.png" alt="">
  <h1>@yield('message')</h1>
  <p>@yield('code')</p>
  <a href="{{ $english ? '/en' : '/' }}">{{ __('Back to home') }}</a>
</main>
</body>
</html>
