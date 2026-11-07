@extends('backend.index')
@section('page_title', 'Share outs')

@section('content')
  <all-share-outs :groups="{{ Js::from($groups) }}"></all-share-outs>
@endsection
