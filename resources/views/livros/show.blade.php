@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Detalhes do Livro</h2>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-3">Título</dt>
                <dd class="col-sm-9">{{ $livro->titulo }}</dd>

                <dt class="col-sm-3">Autor</dt>
                <dd class="col-sm-9"><i>{{ $livro->autor }}</i></dd>

                <dt class="col-sm-3">Ano de Publicação</dt>
                <dd class="col-sm-9">{{ $livro->ano }}</dd>

                <dt class="col-sm-3">ISBN</dt>
                <dd class="col-sm-9">{{ $livro->isbn }}</dd>

                <dt class="col-sm-3">Exemplares</dt>
                <dd class="col-sm-9">
                    {{ $livro->available_copies }} de {{ $livro->total_copies }} disponíveis
                    @if($livro->available_copies > 0)
                        <span class="badge bg-success">Disponível</span>
                    @else
                        <span class="badge bg-danger">Indisponível</span>
                    @endif
                </dd>
            </dl>
        </div>
        <div class="card-footer">
            <a href="/livros/{{ $livro->id }}/edit" class="btn btn-primary">Editar</a>
            <form action="/livros/{{ $livro->id }}" method="post" style="display:inline-block;">
                @csrf
                @method('delete')
                <button type="submit" class="btn btn-danger" onclick="return confirm('Tem certeza?');">Apagar</button>
            </form>
            <a href="/livros" class="btn btn-secondary">Voltar</a>
        </div>
    </div>
</div>
@endsection