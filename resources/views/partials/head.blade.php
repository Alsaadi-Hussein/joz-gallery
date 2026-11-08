<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', __('shop.meta.title'))</title>
<meta name="description" content="@yield('description', $defaultDescription)">
@hasSection('robots')<meta name="robots" content="@yield('robots')">@endif
<meta name="theme-color" content="#5b3e34">
<link rel="icon" type="image/png" href="{{ asset('assets/img/brand/favicon.png') }}">
<link rel="canonical" href="@yield('canonical', url()->current())">
<link rel="alternate" hreflang="ar" href="{{ alternate_url('ar', keepQuery: false) }}">
<link rel="alternate" hreflang="en" href="{{ alternate_url('en', keepQuery: false) }}">
<link rel="alternate" hreflang="x-default" href="{{ alternate_url('ar', keepQuery: false) }}">
<meta property="og:site_name" content="{{ __('shop.brand') }}">
<meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_IQ' : 'en_US' }}">
<meta property="og:type" content="@yield('og_type', 'website')">
<meta property="og:title" content="@yield('title', __('shop.meta.title'))">
<meta property="og:description" content="@yield('description', $defaultDescription)">
<meta property="og:image" content="@yield('og_image', asset('assets/img/og-default.jpg'))">
<meta property="og:url" content="@yield('canonical', url()->current())">
<meta name="twitter:card" content="summary_large_image">
@stack('meta')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Italiana&family=Tajawal:wght@300;400;500;700&display=swap">
<script src="{{ asset('assets/js/view-transition.js') }}"></script>
@vite($viteEntries ?? ['resources/css/app.css', 'resources/js/app.js'])
