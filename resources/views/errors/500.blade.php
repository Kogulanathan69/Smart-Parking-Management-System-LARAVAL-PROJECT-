@extends('errors.layout')
@section('code', '500')
@section('title', 'Something went wrong')
@section('message', $exception?->getMessage() && in_array(500, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'The server could not complete the request. Nothing was changed by this error page. Try again, and tell the parking office if it continues.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
