@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Lista de Livros</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="mb-3">
        <a href="/livros/create" class="btn btn-primary">Novo Livro</a>
        <a href="/livros/importar" class="btn btn-secondary">Importar CSV</a>
    </div>

    @if($livros->count() == 0)
        <div class="alert alert-warning">Nenhum livro cadastrado. Clique em "Importar CSV" para carregar dados.</div>
    @else
        <ul class="list-group">
            @foreach($livros as $livro)
                <li class="list-group-item">
                    <a href="/livros/{{ $livro->id }}">{{ $livro->titulo }}</a>,
                    por <i>{{ $livro->autor }}</i>
                    publicado em {{ $livro->ano }}
                    <span class="badge bg-secondary">ISBN: {{ $livro->isbn }}</span>
                </li>
            @endforeach
        </ul>
        <div class="mt-3">
            {{ $livros->links('pagination::bootstrap-4') }}
        </div>
    @endif
</div>
@endsection