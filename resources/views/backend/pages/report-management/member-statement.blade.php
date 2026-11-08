@extends('backend.index')
@section('page_title', 'Member statement')

@section('content')
  <report-member-statement :groups="{{ Js::from($groups) }}"></report-member-statement>
@endsection
