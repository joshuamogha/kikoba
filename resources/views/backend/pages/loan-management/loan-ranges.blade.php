@extends('backend.index')
@section('page_title', 'Loan ranges')

@section('content')
  <loan-ranges :groups="{{ Js::from($groups) }}"></loan-ranges>
@endsection
