@extends('backend.index')
@section('page_title', $title)

@section('content')
  <lookup-manager :groups="{{ Js::from($groups) }}" title="{{ $title }}" endpoint="{{ $endpoint }}" :has-code="{{ $hasCode ? 'true' : 'false' }}" :has-interval="{{ $hasInterval ? 'true' : 'false' }}"></lookup-manager>
@endsection
