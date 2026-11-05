@extends('errors.layout')
@section('code', '403')
@section('title', 'Access denied')
@section('message', $exception?->getMessage() && in_array(403, [403]) && ! str_contains($exception->getMessage(), 'Exception') ? $exception->getMessage() : 'You do not have permission to open this page. If you think this is a mistake, contact your administrator.')
@section('actions')<button type="button" onclick="history.back()" class="btn-secondary">Go back</button>@endsection
