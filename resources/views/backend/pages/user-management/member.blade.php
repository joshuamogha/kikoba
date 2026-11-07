@extends('backend.index')
@section('page_title', $member->name.' · Member')

@section('content')
  <member-profile :member-id="{{ $member->id }}"></member-profile>
@endsection
