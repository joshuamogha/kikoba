@extends('backend.index')
@section('page_title', 'Loan guarantors')

@section('content')
  <loan-guarantors :groups="{{ Js::from($groups) }}"></loan-guarantors>
@endsection
