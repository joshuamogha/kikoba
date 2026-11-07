@extends('backend.index')
@section('page_title', 'Financial cycles')

@section('content')
  <financial-cycles :groups="{{ Js::from($groups) }}"></financial-cycles>
@endsection
