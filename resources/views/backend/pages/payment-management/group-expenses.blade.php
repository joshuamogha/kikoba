@extends('backend.index')
@section('page_title', 'Group expenses')

@section('content')
  <group-expenses :groups="{{ Js::from($groups) }}"></group-expenses>
@endsection
