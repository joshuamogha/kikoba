@extends('backend.index')
@section('page_title', 'Loan applications')

@section('content')
  <loan-applications :groups="{{ Js::from($groups) }}"></loan-applications>
@endsection
