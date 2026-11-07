@extends('backend.index')
@section('page_title', $group->name.' · Shares')

@section('content')
  @include('backend.partials.group-tabs')

  <group-shares :group-id="{{ $group->id }}" :can-manage="{{ $canManage ? 'true' : 'false' }}"></group-shares>
@endsection
