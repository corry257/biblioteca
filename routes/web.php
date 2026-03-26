<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LivroController;
use App\Http\Controllers\ImportacaoController; 
use App\Http\Controllers\EmprestimoController; 

// index
Route::get('/livros', [LivroController::class, 'index']);

Route::get('/livros/importar', [ImportacaoController::class, 'importar']);

// crud 
Route::get('/livros/create', [LivroController::class, 'create']);
Route::post('/livros', [LivroController::class, 'store']);
Route::get('/livros/{livro}', [LivroController::class, 'show']);
Route::get('/livros/{livro}/edit', [LivroController::class, 'edit']);
Route::post('/livros/{livro}', [LivroController::class, 'update']);
Route::delete('/livros/{livro}', [LivroController::class, 'destroy']);

// empréstimos
Route::get('/emprestimos/create', [EmprestimoController::class, 'createEmprestimo']);
Route::post('/emprestimos', [EmprestimoController::class, 'storeEmprestimo']);
Route::put('/emprestimos/{loan}', [EmprestimoController::class, 'updateEmprestimo']);

