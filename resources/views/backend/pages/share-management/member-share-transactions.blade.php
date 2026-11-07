@extends('backend.index')
@section('page_title', 'Member share transactions')

@section('content')
  <all-member-share-transactions :groups="{{ Js::from($groups) }}"></all-member-share-transactions>
@endsection
