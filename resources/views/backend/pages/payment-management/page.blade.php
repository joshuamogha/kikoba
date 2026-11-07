@extends('backend.index')
@section('page_title', $title)

@section('content')
  <setting-tabs :tabs="{{ Js::from($tabs) }}" :groups="{{ Js::from($groups) }}"></setting-tabs>
@endsection
