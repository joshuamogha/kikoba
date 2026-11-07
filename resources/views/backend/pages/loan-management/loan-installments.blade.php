@extends('backend.index')
@section('page_title', 'Loan installments')

@section('content')
  <loan-installments :groups="{{ Js::from($groups) }}"></loan-installments>
@endsection
