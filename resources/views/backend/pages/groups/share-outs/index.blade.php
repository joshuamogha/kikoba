@extends('backend.index')
@section('page_title', $group->name.' · Share outs')

@section('content')
  @include('backend.partials.group-tabs')

  <share-outs
    :group-id="{{ $group->id }}"
    :can-manage="{{ $canManage ? 'true' : 'false' }}"
    :cycles="{{ Js::from($cycles) }}"
  ></share-outs>
@endsection
