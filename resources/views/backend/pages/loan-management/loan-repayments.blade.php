@extends('backend.index')
@section('page_title', 'Loan repayments')

@section('content')
  <loan-repayments :groups="{{ Js::from($groups) }}"></loan-repayments>
@endsection
