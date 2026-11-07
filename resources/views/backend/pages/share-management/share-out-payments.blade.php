@extends('backend.index')
@section('page_title', 'Share out payments')

@section('content')
  <all-share-out-payments :groups="{{ Js::from($groups) }}"></all-share-out-payments>
@endsection
