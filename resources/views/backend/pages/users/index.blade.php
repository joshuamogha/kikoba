@extends('backend.index')
@section('page_title', 'Users')

@section('content')
  <div class="card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h3 class="card-title mb-0">All users <span class="badge text-bg-secondary">{{ $users->total() }}</span></h3>
      <form method="GET" action="{{ route('users.index') }}" class="d-flex gap-2">
        <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" placeholder="Search name, phone or email">
        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search"></i></button>
        @if ($search !== '')
          <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">Clear</a>
        @endif
      </form>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th class="text-center">Groups</th>
              <th>Status</th>
              <th>Last sign in</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($users as $user)
              <tr>
                <td>
                  <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary fw-semibold me-2" style="width: 32px; height: 32px; font-size: 12px;">{{ $user->initials() }}</span>
                  {{ $user->name }}
                </td>
                <td>{{ $user->phone_number }}</td>
                <td>
                  {{ $user->email }}
                  @if ($user->email_verified_at)
                    <i class="bi bi-patch-check-fill text-success" title="Email verified"></i>
                  @else
                    <i class="bi bi-exclamation-circle text-warning" title="Email not verified"></i>
                  @endif
                </td>
                <td class="text-center">{{ $user->group_members_count }}</td>
                <td>
                  <span class="badge text-bg-{{ $user->canSignIn() ? 'success' : 'secondary' }}">{{ $user->canSignIn() ? 'Active' : 'Inactive' }}</span>
                </td>
                <td>{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
    @if ($users->hasPages())
      <div class="card-footer">{{ $users->links() }}</div>
    @endif
  </div>
@endsection
