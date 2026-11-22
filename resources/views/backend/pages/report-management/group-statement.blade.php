@extends('backend.index')
@section('page_title', 'Group statement')

@section('content')
  <report-group-statement :groups="{{ Js::from($groups) }}"></report-group-statement>
@endsection
