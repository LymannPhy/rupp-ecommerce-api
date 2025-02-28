@extends('layouts.app')

@section('content')
<div class="container">
    <h1 class="text-2xl font-bold">{{ $supplier->name }}</h1>
    <p><strong>Email:</strong> {{ $supplier->email }}</p>
    <p><strong>Phone:</strong> {{ $supplier->phone }}</p>
    <p><strong>Address:</strong> {{ $supplier->address }}</p>
    <img src="{{ $supplier->avatar }}" alt="Supplier Avatar" width="100">
</div>
@endsection
