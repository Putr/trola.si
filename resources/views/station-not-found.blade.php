@extends('layout')

@section('title', 'Postaja ni najdena | Trola.si')

@section('description', 'Iskana avtobusna postaja ni bila najdena. Poskusite z drugim iskalnim nizom ali preverite
seznam vseh postaj.')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="bg-white shadow-md rounded p-6">
        <h1 class="text-2xl font-bold text-red-600 mb-4">Postaje nismo našli</h1>
        <p class="text-gray-700 mb-4">Žal postaje s tem ID-jem ne najdemo v sistemu.</p>
        <a href="{{ route('index') }}" class="text-blue-500 hover:text-blue-700">
            ← Nazaj na prvo stran
        </a>
    </div>
</div>
@endsection
