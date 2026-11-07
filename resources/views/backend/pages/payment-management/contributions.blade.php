@extends('backend.index')
@section('page_title', 'Contributions')

@section('content')
  <contributions :groups="{{ Js::from($groups) }}"></contributions>
@endsection
