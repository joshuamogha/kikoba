@extends('backend.index')
@section('page_title', 'Group funds')

@section('content')
  <group-funds :groups="{{ Js::from($groups) }}"></group-funds>
@endsection
