@extends('backend.index')
@section('page_title', 'Monthly contributions')

@section('content')
  <report-monthly-contributions :groups="{{ Js::from($groups) }}"></report-monthly-contributions>
@endsection
