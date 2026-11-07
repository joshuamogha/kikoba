@extends('backend.index')
@section('page_title', 'Payment transactions')

@section('content')
  <payment-transactions :groups="{{ Js::from($groups) }}"></payment-transactions>
@endsection
