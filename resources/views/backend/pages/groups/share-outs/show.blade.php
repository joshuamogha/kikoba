@extends('backend.index')
@section('page_title', $group->name.' · '.$shareOut->share_out_number)

@section('content')
  @include('backend.partials.group-tabs')

  <share-out-detail
    :group-id="{{ $group->id }}"
    :share-out-id="{{ $shareOut->id }}"
    :can-manage="{{ $canManage ? 'true' : 'false' }}"
    :payment-methods="{{ Js::from($paymentMethods) }}"
  ></share-out-detail>
@endsection
