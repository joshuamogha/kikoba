@extends('backend.index')
@section('page_title', $showAll ? 'All Groups' : 'My Groups')

@section('content')
  <groups
    :group-types="{{ Js::from($groupTypes) }}"
    :show-all="{{ $showAll ? 'true' : 'false' }}"
  ></groups>
@endsection
