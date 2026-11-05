@extends('errors.layout')
@section('code', '404')
@section('title', 'Page not found')
@section('message', $exception?->getMessage() && in_array(404, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'The page or record you are looking for does not exist or has been moved.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
