@extends('backend.index')
@section('page_title', $group->name.' · Members')

@section('content')
  @include('backend.partials.group-tabs')

  <group-members
    :group-id="{{ $group->id }}"
    :can-manage="{{ $canManage ? 'true' : 'false' }}"
    :membership-types="{{ Js::from($membershipTypes) }}"
    :assignable-roles="{{ Js::from($assignableRoles) }}"
  ></group-members>
@endsection
