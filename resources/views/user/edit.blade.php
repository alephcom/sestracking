@extends('layouts.master')


@section('site-title')
    Account Settings
@endsection

@section('h1')
    <h1 class="h2">
        Account Settings
    </h1>
@endsection

@section('page-content')
    @include('user.form', ['form' => $form])
@endsection
