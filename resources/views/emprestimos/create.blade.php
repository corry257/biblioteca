@extends('layouts.app')

@section('content')
<div class="container">
    <h2>Realizar Empréstimo</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="/emprestimos">
        @csrf
        <div class="form-group">
            <label for="livro_id">Selecione o livro</label>
            <select name="livro_id" id="livro_id" class="form-control" required>
                <option value="">-- Escolha --</option>
                @foreach($livros as $livro)
                    <option value="{{ $livro->id }}">
                        {{ $livro->titulo }} - {{ $livro->autor }} ({{ $livro->available_copies }} disponíveis)
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Emprestar</button>
    </form>
</div>
@endsection