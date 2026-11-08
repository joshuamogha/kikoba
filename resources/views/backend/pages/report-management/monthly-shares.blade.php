@extends('backend.index')
@section('page_title', 'Monthly shares')

@section('content')
  <report-monthly-shares :groups="{{ Js::from($groups) }}"></report-monthly-shares>
@endsection
