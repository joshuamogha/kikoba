@extends('backend.index')
@section('page_title', 'Cash reconciliation')

@section('content')
  <report-cash-reconciliation :groups="{{ Js::from($groups) }}"></report-cash-reconciliation>
@endsection
