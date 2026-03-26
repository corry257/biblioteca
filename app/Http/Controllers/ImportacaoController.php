<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ImportacaoController extends Controller
{
    public function importar()
    {
        // Chama o comando de importação
        Artisan::call('livros:importar', ['arquivo' => storage_path('app/books.csv')]);

        $output = Artisan::output();

        return redirect('/livros')->with('status', 'Importação concluída: ' . $output);
    }
}