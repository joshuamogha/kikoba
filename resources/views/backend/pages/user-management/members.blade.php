@extends('backend.index')
@section('page_title', 'Members')

@section('content')
  <members :groups="{{ Js::from($groups) }}"></members>
@endsection
