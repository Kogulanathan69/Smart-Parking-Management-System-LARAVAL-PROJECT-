@extends('errors.layout')
@section('code', '503')
@section('title', 'Temporarily unavailable')
@section('message', $exception?->getMessage() && in_array(503, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'ParkFlow is under maintenance or a dependent service is unavailable. Please try again shortly.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
