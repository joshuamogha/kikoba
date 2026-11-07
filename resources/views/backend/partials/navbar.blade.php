<!--begin::Header-->
<nav class="app-header navbar navbar-expand bg-body">
  <div class="container-fluid">
    <!--begin::Start Navbar Links-->
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </a>
      </li>
    </ul>
    <!--end::Start Navbar Links-->
    <!--begin::End Navbar Links-->
    <ul class="navbar-nav ms-auto">
      <li class="nav-item dropdown user-menu">
        <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
          <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary fw-semibold" style="width: 34px; height: 34px; font-size: 13px;">{{ auth()->user()->initials() }}</span>
          <span class="d-none d-md-inline">{{ auth()->user()->name }}</span>
        </a>
        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
          <li class="user-header text-bg-primary">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-white text-primary fw-bold shadow" style="width: 90px; height: 90px; font-size: 32px;">{{ auth()->user()->initials() }}</span>
            <p>
              {{ auth()->user()->name }}
              <small>{{ auth()->user()->phone_number }}</small>
              <small>Member since {{ auth()->user()->created_at->format('M. Y') }}</small>
            </p>
          </li>
          <li class="user-footer">
            <a href="{{ route('profile.show') }}" class="btn btn-default btn-flat">
              <i class="bi bi-person me-1"></i> Profile
            </a>
            <form method="POST" action="{{ route('logout') }}" class="d-inline float-end">
              @csrf
              <button type="submit" class="btn btn-default btn-flat">
                <i class="bi bi-box-arrow-right me-1"></i> Sign out
              </button>
            </form>
          </li>
        </ul>
      </li>
    </ul>
    <!--end::End Navbar Links-->
  </div>
</nav>
<!--end::Header-->
