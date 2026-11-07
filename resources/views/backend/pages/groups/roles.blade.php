@extends('backend.index')
@section('page_title', $group->name.' · Roles')

@section('content')
  @include('backend.partials.group-tabs')

  <group-roles :group-id="{{ $group->id }}" :can-manage="{{ $canManage ? 'true' : 'false' }}"></group-roles>
@endsection
