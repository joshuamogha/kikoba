@extends('backend.index')
@section('page_title', 'My Profile')

@section('content')
  <div class="row">
    <div class="col-md-4">
      <div class="card card-primary card-outline">
        <div class="card-body text-center">
          <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-bg-primary fw-bold mb-3" style="width: 90px; height: 90px; font-size: 32px;">{{ $user->initials() }}</span>
          <h4 class="mb-1">{{ $user->name }}</h4>
          <p class="text-muted mb-0">Member since {{ $user->created_at->format('F Y') }}</p>
        </div>
        <ul class="list-group list-group-flush">
          <li class="list-group-item"><i class="bi bi-telephone me-2"></i>{{ $user->phone_number }}</li>
          <li class="list-group-item">
            <i class="bi bi-envelope me-2"></i>{{ $user->email }}
            @if ($user->hasVerifiedEmail())
              <i class="bi bi-patch-check-fill text-success" title="Verified"></i>
            @endif
          </li>
          <li class="list-group-item"><i class="bi bi-clock-history me-2"></i>Last sign in: {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</li>
        </ul>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">My groups</h3></div>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr><th>Group</th><th>Member no.</th><th>Type</th><th>Roles</th><th>Joined</th></tr>
            </thead>
            <tbody>
              @forelse ($memberships as $membership)
                <tr>
                  <td>{{ $membership->group->name }}</td>
                  <td>{{ $membership->member_number }}</td>
                  <td>{{ $membership->membershipType->name }}</td>
                  <td>
                    @foreach ($membership->groupMemberRoles as $assignment)
                      <span class="badge text-bg-secondary">{{ $assignment->groupRole->name }}</span>
                    @endforeach
                  </td>
                  <td>{{ \Illuminate\Support\Carbon::parse($membership->joined_at)->format('d M Y') }}</td>
                </tr>
              @empty
                <tr><td colspan="5" class="text-center text-muted py-4">You are not a member of any group yet.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
@endsection
