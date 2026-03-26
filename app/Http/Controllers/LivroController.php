<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Livro;
use App\Http\Requests\LivroRequest;

class LivroController extends Controller
{
    // mostra view index
    public function index(){
        return view('livros.index',[
            'livros' => Livro::paginate(15)
        ]);
    }

    # crud
    // mostra form para salvar novo livro
    public function create(){
        return view('livros.create');
    }

    // salva novo livro no banco de dados 
    public function store(LivroRequest $request){
        $livro = new Livro;
        $livro->titulo = $request->titulo;
        $livro->autor = $request->autor;
        $livro->ano = $request->ano;
        $livro->isbn = $request->isbn;
        $livro->save();
        return redirect('/livros');
    }

    // mostra view de livro especifico
    public function show(Livro $livro){
        return view('livros.show',[
            'livro' => $livro
        ]);
    }

    // mostra form para editar um livro 
    public function edit(Livro $livro){
        return view('livros.edit',[
            'livro' => $livro
        ]);
    }

    // salva edição de um livro especifico 
    public function update(LivroRequest $request, Livro $livro){
        $livro->titulo = $request->titulo;
        $livro->autor = $request->autor;
        $livro->ano = $request->ano;
        $livro->isbn = $request->isbn;
        $livro->save();
        return redirect("/livros/{$livro->id}");
    }

    // deleta um livro especifico
    public function destroy(Livro $livro)
    {
        $livro->delete();
        return redirect('/livros');
    }


}
