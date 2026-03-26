<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Livro;
use League\Csv\Reader;

class ImportaLivros extends Command
{
    protected $signature = 'livros:importar {arquivo?}';
    protected $description = 'Importa livros a partir de um arquivo CSV (colunas: isbn, title, authors, original_publication_year)';

    public function handle()
    {
        $arquivo = $this->argument('arquivo') ?? storage_path('app/books.csv');

        if (!file_exists($arquivo)) {
            $this->error("Arquivo não encontrado: $arquivo");
            return 1;
        }

        $csv = Reader::createFromPath($arquivo, 'r');
        $csv->setHeaderOffset(0); // primeira linha como cabeçalho

        $importados = 0;
        $ignorados = 0;

        foreach ($csv->getRecords() as $record) {
            // Mapeia colunas
            $isbn = preg_replace('/[^0-9]/', '', $record['isbn'] ?? '');
            $titulo = trim($record['title'] ?? '');
            $autor = trim($record['authors'] ?? '');
            $ano = (int) ($record['original_publication_year'] ?? 0);

            // Validações básicas
            if (empty($isbn) || empty($titulo) || empty($autor) || $ano <= 0) {
                $this->warn("Ignorado (dados incompletos): " . json_encode($record));
                $ignorados++;
                continue;
            }

            // Verifica duplicidade
            if (Livro::where('isbn', $isbn)->exists()) {
                $this->warn("ISBN $isbn já cadastrado, ignorado.");
                $ignorados++;
                continue;
            }

            // Cria o livro
            Livro::create([
                'isbn' => $isbn,
                'titulo' => $titulo,
                'autor' => $autor,
                'ano' => $ano,
                'total_copies' => 1,
                'available_copies' => 1,
            ]);

            $importados++;
            $this->line("Importado: $titulo");
        }

        $this->info("Importação concluída. Importados: $importados | Ignorados: $ignorados");
        return 0;
    }
}