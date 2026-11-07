@extends('backend.index')
@section('page_title', 'Member share outs')

@section('content')
  <all-member-share-outs :groups="{{ Js::from($groups) }}"></all-member-share-outs>
@endsection
