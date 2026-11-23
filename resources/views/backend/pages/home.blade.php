@extends('backend.index')
@section('page_title', 'Dashboard')

@section('content')
  <dashboard :groups="{{ Js::from($groups) }}" :platform="{{ Js::from($platform) }}"></dashboard>
@endsection
