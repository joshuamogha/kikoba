@extends('backend.index')
@section('page_title', $group->name.' · Settings')

@section('content')
  @include('backend.partials.group-tabs')

  <group-settings :group-id="{{ $group->id }}" :can-manage="{{ $canManage ? 'true' : 'false' }}"></group-settings>
@endsection
