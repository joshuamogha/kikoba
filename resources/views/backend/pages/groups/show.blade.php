@extends('backend.index')
@section('page_title', $group->name)

@section('content')
  @include('backend.partials.group-tabs')

  <div class="row">
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="card-title mb-0">{{ $group->name }}</h3>
          @can('update', $group)
            <group-editor class="ms-auto" :group="{{ Js::from($group->only(['id', 'name', 'description', 'registration_number', 'established_date', 'maximum_members'])) }}"></group-editor>
          @endcan
        </div>
        <div class="card-body">
          @if ($group->description)
            <p>{{ $group->description }}</p>
          @endif
          <dl class="row mb-0">
            <dt class="col-sm-4">Type</dt>
            <dd class="col-sm-8">{{ $group->groupType->name }}</dd>
            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8"><span class="badge text-bg-{{ $group->status === 'active' ? 'success' : 'secondary' }}">{{ ucfirst($group->status) }}</span></dd>
            <dt class="col-sm-4">Registration number</dt>
            <dd class="col-sm-8">{{ $group->registration_number ?? '—' }}</dd>
            <dt class="col-sm-4">Established</dt>
            <dd class="col-sm-8">{{ $group->established_date ? \Illuminate\Support\Carbon::parse($group->established_date)->format('d M Y') : '—' }}</dd>
            <dt class="col-sm-4">Currency</dt>
            <dd class="col-sm-8">{{ $group->currency }}</dd>
            <dt class="col-sm-4">Members</dt>
            <dd class="col-sm-8">{{ $group->group_members_count }}@if ($group->maximum_members) of {{ $group->maximum_members }} allowed @endif</dd>
            <dt class="col-sm-4">Created by</dt>
            <dd class="col-sm-8">{{ $group->creator->name }}</dd>
          </dl>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header"><h3 class="card-title mb-0">Your roles</h3></div>
        <div class="card-body">
          @forelse ($myRoles as $role)
            <span class="badge text-bg-primary me-1">{{ $role }}</span>
          @empty
            <span class="text-muted">You have no roles in this group.</span>
          @endforelse
          <p class="text-muted small mt-3 mb-0">
            @if ($canManage)
              You can add members and manage roles for this group.
            @else
              Only the Chairperson, Vice Chairperson and Secretary can add members or change roles.
            @endif
          </p>
        </div>
      </div>
    </div>
  </div>
@endsection
