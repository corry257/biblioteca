<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Livro;
use App\Models\Loan;
use App\Models\User;

class EmprestimoController extends Controller
{
    // Exibe formulário de empréstimo
    public function createEmprestimo()
    {
        $livros = Livro::where('available_copies', '>', 0)->get();
        return view('emprestimos.create', compact('livros'));
    }

    // Processa empréstimo
    public function storeEmprestimo(Request $request)
    {
        $request->validate([
            'livro_id' => 'required|exists:livros,id',
        ]);

        $livroId = $request->livro_id;
        $userId = auth()->id();

        // Fallback para teste sem autenticação (remova depois)
        if (!$userId) {
            $user = User::firstOrCreate(['email' => 'teste@example.com'], [
                'name' => 'Teste',
                'password' => bcrypt('123456')
            ]);
            $userId = $user->id;
        }

        $user = User::find($userId);
        if ($user->has_fine) {
            return back()->withErrors(['erro' => 'Usuário com multa pendente']);
        }

        // Atualização atômica do estoque
        $updated = Livro::where('id', $livroId)
            ->where('available_copies', '>=', 1)
            ->update([
                'available_copies' => DB::raw('available_copies - 1')
            ]);

        if ($updated === 0) {
            return back()->withErrors(['erro' => 'Livro não disponível no momento']);
        }

        // Cria o empréstimo
        Loan::create([
            'user_id' => $userId,
            'livro_id' => $livroId,
            'loan_date' => now(),
            'due_date' => now()->addDays(14),
        ]);

        return redirect('/emprestimos/create')->with('success', 'Empréstimo realizado com sucesso!');
    }

    // Processa devolução
    public function updateEmprestimo(Request $request, Loan $loan)
    {
        DB::transaction(function () use ($loan) {
            $loan = Loan::where('id', $loan->id)->lockForUpdate()->first();

            if ($loan->return_date !== null) {
                throw new \Exception('Este empréstimo já foi devolvido');
            }

            // Calcula multa
            $fine = 0;
            if (now()->gt($loan->due_date)) {
                $daysLate = now()->diffInDays($loan->due_date);
                $fine = $daysLate * 1.00;
            }

            // Atualiza o livro
            $livro = Livro::where('id', $loan->livro_id)->lockForUpdate()->first();
            $livro->available_copies += 1;
            $livro->save();

            // Atualiza o empréstimo
            $loan->return_date = now();
            $loan->fine = $fine;
            $loan->save();

            if ($fine > 0) {
                $user = User::where('id', $loan->user_id)->lockForUpdate()->first();
                $user->has_fine = true;
                $user->save();
            }
        });

        return back()->with('success', 'Devolução registrada');
    }
}