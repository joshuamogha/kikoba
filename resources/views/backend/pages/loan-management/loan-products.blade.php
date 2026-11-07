@extends('backend.index')
@section('page_title', 'Loan products')

@section('content')
  <loan-products :groups="{{ Js::from($groups) }}"></loan-products>
@endsection
