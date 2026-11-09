@extends('errors.layout')
@section('code', '419')
@section('title', 'Session expired')
@section('message', $exception?->getMessage() && in_array(419, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'Your session expired for security reasons. Go back, reload the page and try again.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
