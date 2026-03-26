@extends('layouts.app')
@section('content')

<form method="POST" action="/livros">
    @csrf
    Título: <input type="text" name="titulo">
    Autor: <input type="text" name="autor">
    ISBN: <input type="integer" name="isbn">
    Ano: <input type="text" name="ano">
    <button type="submit">Enviar</button>
</form>

@endsection