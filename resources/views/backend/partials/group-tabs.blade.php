<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.show') ? 'active' : '' }}" href="{{ route('groups.show', $group) }}">
      <i class="bi bi-info-circle me-1"></i> Overview
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.members.*') ? 'active' : '' }}" href="{{ route('groups.members.index', $group) }}">
      <i class="bi bi-person-lines-fill me-1"></i> Members
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.roles.*') ? 'active' : '' }}" href="{{ route('groups.roles.index', $group) }}">
      <i class="bi bi-person-badge me-1"></i> Roles
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.shares.*') ? 'active' : '' }}" href="{{ route('groups.shares.index', $group) }}">
      <i class="bi bi-wallet2 me-1"></i> Shares
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.share-outs.*') ? 'active' : '' }}" href="{{ route('groups.share-outs.index', $group) }}">
      <i class="bi bi-pie-chart me-1"></i> Share outs
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link {{ request()->routeIs('groups.settings.*') ? 'active' : '' }}" href="{{ route('groups.settings.show', $group) }}">
      <i class="bi bi-gear me-1"></i> Settings
    </a>
  </li>
</ul>
