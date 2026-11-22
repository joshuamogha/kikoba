{{--
  The head tags every layout shares, from the platform's site settings: the tab title, the favicon, the SEO and share tags and
  the brand colours. Pass $pageTitle (the page's name, shown before the site name) and $public (true for pages search engines
  may index: the landing page).
--}}
@php
  use App\Support\BrandColor;

  $pageTitle = trim($pageTitle ?? '');
  $public = $public ?? false;
  $primary = $site->get('brand_primary');
  $title = $public && $pageTitle === '' ? $site->seoTitle() : ($pageTitle !== '' ? $pageTitle.' · '.$site->get('name') : $site->get('name'));
  $shareImage = $site->fileUrl('og_image_path') ?? $site->fileUrl('logo_path');
@endphp
<title>{{ $title }}</title>
@if ($public)
  <meta name="description" content="{{ $site->seoDescription() }}">
  @if ($site->seo_keywords)
    <meta name="keywords" content="{{ $site->seo_keywords }}">
  @endif
  <link rel="canonical" href="{{ url('/') }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $site->get('name') }}">
  <meta property="og:title" content="{{ $site->seoTitle() }}">
  <meta property="og:description" content="{{ $site->seoDescription() }}">
  <meta property="og:url" content="{{ url('/') }}">
  @if ($shareImage)
    <meta property="og:image" content="{{ $shareImage }}">
    <meta name="twitter:card" content="summary_large_image">
  @endif
@elseif ($site->noindex_dashboard !== false)
  <meta name="robots" content="noindex, nofollow">
@endif
@if ($favicon = $site->fileUrl('favicon_path'))
  <link rel="icon" href="{{ $favicon }}">
@endif
<style>
  :root {
    --brand-primary: {{ $primary }};
    --brand-secondary: {{ $site->get('brand_secondary') }};
    --brand-accent: {{ $site->get('brand_accent') }};
    --brand-background: {{ $site->get('brand_background') }};
    --brand-text: {{ $site->get('brand_text') }};
    --brand-on-primary: {{ BrandColor::onColor($primary) }};
    --bs-primary: {{ $primary }};
    --bs-primary-rgb: {{ BrandColor::rgb($primary) }};
    --bs-link-color: {{ $primary }};
    --bs-link-color-rgb: {{ BrandColor::rgb($primary) }};
    --bs-link-hover-color: {{ BrandColor::shade($primary, -0.2) }};
  }
  .btn-primary {
    --bs-btn-bg: {{ $primary }};
    --bs-btn-border-color: {{ $primary }};
    --bs-btn-color: {{ BrandColor::onColor($primary) }};
    --bs-btn-hover-bg: {{ BrandColor::shade($primary, -0.15) }};
    --bs-btn-hover-border-color: {{ BrandColor::shade($primary, -0.2) }};
    --bs-btn-hover-color: {{ BrandColor::onColor($primary) }};
    --bs-btn-active-bg: {{ BrandColor::shade($primary, -0.2) }};
    --bs-btn-active-border-color: {{ BrandColor::shade($primary, -0.25) }};
    --bs-btn-active-color: {{ BrandColor::onColor($primary) }};
    --bs-btn-disabled-bg: {{ $primary }};
    --bs-btn-disabled-border-color: {{ $primary }};
  }
  .btn-outline-primary {
    --bs-btn-color: {{ $primary }};
    --bs-btn-border-color: {{ $primary }};
    --bs-btn-hover-bg: {{ $primary }};
    --bs-btn-hover-border-color: {{ $primary }};
    --bs-btn-hover-color: {{ BrandColor::onColor($primary) }};
    --bs-btn-active-bg: {{ $primary }};
    --bs-btn-active-border-color: {{ $primary }};
    --bs-btn-active-color: {{ BrandColor::onColor($primary) }};
  }
  .text-bg-primary { background-color: {{ $primary }} !important; color: {{ BrandColor::onColor($primary) }} !important; }
  .bg-primary { background-color: {{ $primary }} !important; }
  .text-primary { color: {{ $primary }} !important; }
  .border-primary { border-color: {{ $primary }} !important; }
  .page-item.active .page-link { background-color: {{ $primary }}; border-color: {{ $primary }}; color: {{ BrandColor::onColor($primary) }}; }
  .page-link { color: {{ $primary }}; }
  .form-check-input:checked { background-color: {{ $primary }}; border-color: {{ $primary }}; }
  .form-control:focus, .form-select:focus { border-color: {{ BrandColor::shade($primary, 0.4) }}; box-shadow: 0 0 0 .25rem rgba({{ BrandColor::rgb($primary) }}, .25); }
</style>
