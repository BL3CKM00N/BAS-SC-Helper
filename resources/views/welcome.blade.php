@extends('layouts.app')

@section('content')
    <img src="/logo.svg?v={{ filemtime(public_path('logo.svg')) }}" alt="BAS World" class="h-9 w-auto sm:h-12">
@endsection
