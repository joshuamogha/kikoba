@extends('backend.index')
@section('page_title', 'Loans')

@section('content')
  <loans :groups="{{ Js::from($groups) }}"></loans>
@endsection
