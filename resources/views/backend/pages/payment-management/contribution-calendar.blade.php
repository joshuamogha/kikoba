@extends('backend.index')
@section('page_title', 'Contribution calendar')

@section('content')
  <contribution-calendar :groups="{{ Js::from($groups) }}"></contribution-calendar>
@endsection
