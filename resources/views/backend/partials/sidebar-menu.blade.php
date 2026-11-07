@php
    $menuIsActive = collect($menu['items'])->contains(fn ($item) => $isActiveRoute($item['route'], $item['params'] ?? []));
@endphp
<li class="nav-item {{ $menuIsActive ? 'menu-open' : '' }}">
  <a href="#" class="nav-link {{ $menuIsActive ? 'active' : '' }}">
    <i class="nav-icon bi {{ $menu['icon'] }} {{ $menu['color'] }}"></i>
    <p>
      {{ $menu['label'] }}
      <i class="nav-arrow bi bi-chevron-right"></i>
    </p>
  </a>
  <ul class="nav nav-treeview">
    @foreach ($menu['items'] as $item)
      <li class="nav-item">
        <a href="{{ $linkFor($item['route'], $item['params'] ?? []) }}" class="nav-link {{ $isActiveRoute($item['route'], $item['params'] ?? []) ? 'active' : '' }}">
          <i class="nav-icon bi {{ $item['icon'] }} {{ $item['color'] ?? $menu['color'] }}"></i>
          <p>{{ $item['label'] }}</p>
        </a>
      </li>
    @endforeach
  </ul>
</li>
