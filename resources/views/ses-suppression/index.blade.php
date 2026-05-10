@extends('layouts.master')

@section('site-title')
    SES suppression list
@endsection

@section('h1')
    <h1 class="h2">SES suppression list: {{ $project->name }}</h1>
@endsection

@section('page-content')
    @include('ses-suppression._body', [
        'project' => $project,
        'destinations' => $destinations,
        'error' => $error,
        'indexRoute' => $indexRoute,
        'storeRoute' => $storeRoute,
        'destroyRoute' => $destroyRoute,
        'backUrl' => $backUrl,
        'backLabel' => $backLabel,
    ])
@endsection
