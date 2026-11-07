@extends('backend.index')
@section('page_title', 'Group incomes')

@section('content')
  <group-incomes :groups="{{ Js::from($groups) }}"></group-incomes>
@endsection
