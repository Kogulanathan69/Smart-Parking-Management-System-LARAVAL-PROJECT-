@extends('errors.layout')
@section('code', '429')
@section('title', 'Too many requests')
@section('message', $exception?->getMessage() && in_array(429, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'Please wait a minute before trying again.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
