<!doctype html>
<html lang="en">
  <!--begin::Head-->
 @include('backend.partials.header')
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <!--begin::App Wrapper-->
    <div class="app-wrapper">
      @include('backend.partials.navbar')
      <!--begin::Sidebar-->
    @include('backend.partials.sidebar')
      <!--end::Sidebar-->
      <!--begin::App Main-->
      <main class="app-main">
        <!--begin::App Content Header-->
        @if ($site->maintenance_banner)
          <div class="alert alert-warning rounded-0 mb-0 py-2 text-center">{{ $site->maintenance_banner }}</div>
        @endif
        <div class="app-content-header">
          <!--begin::Container-->
          <div class="container-fluid">
            <!--begin::Row-->
            <div class="row">
              <div class="col-sm-6"><h3 class="mb-0">@yield('page_title', 'Dashboard')</h3></div>
              <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                  <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
                  <li class="breadcrumb-item active" aria-current="page">@yield('page_title', 'Dashboard')</li>
                </ol>
              </div>
            </div>
            <!--end::Row-->
          </div>
          <!--end::Container-->
        </div>
        <div id="app" class="app-content">
          <!--begin::Container-->
          <div class="container-fluid">
           @yield('content')
          </div>
          <!--end::Container-->
        </div>
        <!--end::App Content-->
      </main>
      <!--end::App Main-->
      <!--begin::Footer-->
      <footer class="app-footer">
        <div class="float-end d-none d-sm-inline">
          @if ($site->footer_text){{ $site->footer_text }}@endif
          @if ($site->support_email)
            <a href="mailto:{{ $site->support_email }}" class="text-decoration-none ms-2">{{ $site->support_email }}</a>
          @endif
          @if ($site->terms_url)
            <a href="{{ $site->terms_url }}" class="text-decoration-none ms-2">Terms</a>
          @endif
          @if ($site->privacy_url)
            <a href="{{ $site->privacy_url }}" class="text-decoration-none ms-2">Privacy</a>
          @endif
        </div>
        <strong>Copyright &copy; {{ date('Y') }} {{ $site->copyright_holder ?: $site->get('name') }}.</strong>
        All rights reserved.
      </footer>
      <!--end::Footer-->
    </div>
    <!--end::App Wrapper-->
   @include('backend.partials.script')
  </body>
  <!--end::Body-->
</html>
