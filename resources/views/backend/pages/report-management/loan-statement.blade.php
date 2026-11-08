@extends('backend.index')
@section('page_title', 'Loan statement')

@section('content')
  <report-loan-statement :groups="{{ Js::from($groups) }}"></report-loan-statement>
@endsection
