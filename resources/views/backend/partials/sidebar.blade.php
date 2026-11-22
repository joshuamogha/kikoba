@php
    $isActiveRoute = function (?string $route, array $params = []): bool {
        if ($route === null || ! Route::has($route) || ! request()->routeIs($route.'*')) {
            return false;
        }

        // Links that differ only by query string (e.g. ?scope=all) are active only on a matching query.
        return collect($params)->every(fn ($value, $key) => (request()->route($key) ?? request()->query($key)) === $value)
            && ($params !== [] || request()->query('scope') !== 'all');
    };
    $linkFor = fn (?string $route, array $params = []): string => $route !== null && Route::has($route) ? route($route, $params) : '#';
    $dashboard = config('sidebar.dashboard');
    $myGroups = config('sidebar.my_groups');
@endphp
<style>
  .app-sidebar .text-teal { color: #20c997 !important; }
  .app-sidebar .text-purple { color: #a78bfa !important; }
</style>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
  <!--begin::Sidebar Brand-->
  <div class="sidebar-brand">
    <a href="{{ route('dashboard') }}" class="brand-link">
      @if ($logo = $site->fileUrl('logo_path'))
        <img src="{{ $logo }}" alt="{{ $site->get('name') }}" class="brand-image" style="max-height: 33px; width: auto;">
      @else
        <i class="bi bi-piggy-bank-fill brand-image fs-3 lh-1 text-warning"></i>
      @endif
      <span class="brand-text fw-light">{{ $site->short_name ?: $site->get('name') }}</span>
    </a>
  </div>
  <!--end::Sidebar Brand-->
  <!--begin::Sidebar Wrapper-->
  <div class="sidebar-wrapper">
    <nav class="mt-2">
      <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="true">
        <li class="nav-item">
          <a href="{{ $linkFor($dashboard['route']) }}" class="nav-link {{ $isActiveRoute($dashboard['route']) ? 'active' : '' }}">
            <i class="nav-icon bi {{ $dashboard['icon'] }} {{ $dashboard['color'] }}"></i>
            <p>{{ $dashboard['label'] }}</p>
          </a>
        </li>

        <li class="nav-item">
          <a href="{{ $linkFor($myGroups['route']) }}" class="nav-link {{ $isActiveRoute($myGroups['route']) ? 'active' : '' }}">
            <i class="nav-icon bi {{ $myGroups['icon'] }} {{ $myGroups['color'] }}"></i>
            <p>{{ $myGroups['label'] }}</p>
          </a>
        </li>

        @foreach (config('sidebar.menus') as $menu)
          @include('backend.partials.sidebar-menu')
        @endforeach

        @can('platform-admin')
          <li class="nav-header">PLATFORM ADMIN</li>
          @include('backend.partials.sidebar-menu', ['menu' => config('sidebar.platform')])
        @endcan
      </ul>
    </nav>
  </div>
  <!--end::Sidebar Wrapper-->
</aside>
