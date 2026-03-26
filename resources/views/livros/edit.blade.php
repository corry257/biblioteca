@extends('layouts.app')
@section('content')

<form method="POST" action="/livros">
    @csrf
    Título: <input type="text" name="titulo" value="{{ $livro->titulo }}">
    Autor: <input type="text" name="autor" value="{{ $livro->autor }}">
    ISBN: <input type="integer" name="isbn" value="{{ $livro->isbn }}">
    Ano: <input type="text" name="ano" value="{{ $livro->ano }}">
    <button type="submit">Enviar</button>
</form>

@endsection