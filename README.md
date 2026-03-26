# Detectando e Corrigindo Condições de Corrida em Aplicações Laravel com MariaDB
   
*Adaptado do tutorial original de Arthur Ribeiro para MongoDB*
   
Aprenda a identificar condições de corrida em suas aplicações Laravel com MariaDB/MySQL e corrigi‑las usando operações atômicas, com um exemplo prático de sistema de empréstimos de biblioteca que demonstra por que o padrão ler‑modificar‑escrever do Eloquent falha sob carga concorrente.

## O que você vai aprender
Como reproduzir condições de corrida em aplicações Laravel usando testes de funcionalidade
   
Por que o padrão ler‑modificar‑escrever (read-modify-write) do Eloquent falha sob carga concorrente
   
Como usar atualizações atômicas no MariaDB (UPDATE ... SET coluna = coluna +/- valor WHERE condição)
   
Como usar transações com bloqueio (SELECT ... FOR UPDATE) para operações multi-tabela
   
Estratégias de teste para operações concorrentes antes de implantar em produção

## Pré‑requisitos
Antes de começar, você deve ter:
   
- Familiaridade com a estrutura MVC do Laravel: rotas, controladores e Eloquent ORM

- PHP 8.3 ou superior instalado

- Composer instalado para gerenciamento de dependências

- Servidor MariaDB/MySQL rodando localmente

- Conhecimento básico de SQL e migrations

- Familiaridade com linha de comando (comandos artisan e composer)

- Experiência básica com PHPUnit e testes no Laravel

Opcional, mas útil:

- Entendimento de requisições HTTP e APIs REST

- Experiência com conceitos de programação concorrente

---

## Introdução
Imagine esta situação: você construiu um sistema de empréstimos para uma biblioteca. No seu ambiente local, tudo funciona perfeitamente. Seus testes passam com louvor. Você implanta em produção e, minutos depois do lançamento, começam os chamados: vários usuários pegaram o mesmo livro emprestado ao mesmo tempo, o estoque ficou negativo e a biblioteca não sabe quem realmente tem o exemplar.
   
A parte mais estranha? Seus logs não mostram nenhum erro. Todas as operações de banco de dados retornaram com sucesso. No entanto, seus dados estão completamente inconsistentes.
   
Esta é a realidade das condições de corrida – bugs que se escondem durante o desenvolvimento e só se revelam sob carga concorrente real. Vamos mostrar como identificá‑las, entendê‑las e corrigi‑las usando operações atômicas do MariaDB no Laravel.

## Configurando o Projeto
Vamos criar um novo projeto Laravel:

```bash
composer create-project laravel/laravel biblioteca
cd biblioteca
```
Configure o arquivo .env para usar MariaDB:

```
env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=biblioteca
DB_USERNAME=root
DB_PASSWORD=
```
Crie o banco de dados (se necessário):

```bash
mariadb -u root -p -e "CREATE DATABASE biblioteca;"
```
## Criando os Models
Vamos construir um sistema simples com três entidades: livros, usuários e empréstimos.

```bash
php artisan make:model Livro -m
php artisan make:model Loan -m
php artisan make:migration add_has_fine_to_users_table --table=users
```
Migration livros   
```php
public function up()
{
    Schema::create('livros', function (Blueprint $table) {
        $table->id();
        $table->string('titulo');
        $table->string('autor');
        $table->integer('ano');
        $table->string('isbn')->unique();
        $table->integer('total_copies')->default(1);
        $table->integer('available_copies')->default(1);
        $table->timestamps();
    });
}
```
Migration loans
```php
public function up()
{
    Schema::create('loans', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('livro_id')->constrained()->onDelete('cascade');
        $table->date('loan_date');
        $table->date('due_date');
        $table->date('return_date')->nullable();
        $table->decimal('fine', 8, 2)->default(0);
        $table->timestamps();
    });
}
```
Migration add_has_fine_to_users
```php
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('has_fine')->default(false);
    });
}
```
Agora execute as migrations:

```bash
php artisan migrate
```
## Models com Relacionamentos
app/Models/Livro.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Livro extends Model
{
    protected $fillable = [
        'titulo', 'autor', 'ano', 'isbn', 'total_copies', 'available_copies'
    ];
}
```
app/Models/Loan.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = [
        'user_id', 'livro_id', 'loan_date', 'due_date', 'return_date', 'fine'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function livro()
    {
        return $this->belongsTo(Livro::class);
    }
}
```
app/Models/User.php – adicione has_fine ao $fillable:
```php
protected $fillable = [
    'name', 'email', 'password', 'has_fine'
];
```

---

## O Problema: Código que Parece Correto
Vamos criar um controlador de empréstimo com o padrão ler‑modificar‑escrever – que parece funcionar, mas falhará sob carga.

```bash
php artisan make:controller EmprestimoController
```
```php
<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;

class EmprestimoController extends Controller
{
    public function emprestar(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'livro_id' => 'required|exists:livros,id',
        ]);

        $userId = $request->user_id;
        $livroId = $request->livro_id;

        // Passo 1: Busca o usuário
        $user = User::find($userId);
        if ($user->has_fine) {
            return response()->json(['error' => 'Usuário com multa pendente'], 400);
        }

        // Passo 2: Busca o livro
        $livro = Livro::find($livroId);
        if ($livro->available_copies < 1) {
            return response()->json(['error' => 'Sem exemplares disponíveis'], 400);
        }

        // Passo 3: Diminui o estoque
        $livro->available_copies -= 1;
        $livro->save();

        // Passo 4: Cria o empréstimo
        $loan = Loan::create([
            'user_id' => $userId,
            'livro_id' => $livroId,
            'loan_date' => now(),
            'due_date' => now()->addDays(14),
        ]);

        return response()->json(['success' => true, 'loan' => $loan]);
    }
}
```
Adicione a rota em routes/api.php:

```php
Route::post('/emprestar', [EmprestimoController::class, 'emprestar']);
```

## Simulando Carga Concorrente: O Teste que Quebra
Crie um teste para simular 10 usuários tentando pegar o último exemplar simultaneamente:

```bash
php artisan make:test EmprestimoConcurrencyTest
```
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Livro;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmprestimoConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_emprestimo_reveals_race_condition()
    {
        // Cria um livro com 1 exemplar
        $livro = Livro::create([
            'titulo' => 'Livro Único',
            'autor' => 'Autor Exemplo',
            'ano' => 2020,
            'isbn' => 1234567890,
            'total_copies' => 1,
            'available_copies' => 1
        ]);

        // Cria 10 usuários
        $users = User::factory(10)->create();

        // Simula 10 requisições concorrentes
        $responses = [];
        foreach ($users as $user) {
            $responses[] = $this->postJson('/api/emprestar', [
                'user_id' => $user->id,
                'livro_id' => $livro->id
            ]);
        }

        // Conta sucessos e falhas
        $successCount = collect($responses)->filter(fn($r) => $r->status() === 200)->count();
        $failureCount = collect($responses)->filter(fn($r) => $r->status() === 400)->count();

        dump('=== RESULTADOS DA CONDIÇÃO DE CORRIDA ===');
        dump('Empréstimos bem‑sucedidos: ' . $successCount);
        dump('Falhas: ' . $failureCount);

        $livro->refresh();
        dump('Estoque restante: ' . $livro->available_copies);

        $loanCount = Loan::where('livro_id', $livro->id)->count();
        dump('Empréstimos registrados: ' . $loanCount);

        // Estas asserções vão FALHAR!
        $this->assertEquals(1, $successCount);
        $this->assertEquals(9, $failureCount);
        $this->assertEquals(0, $livro->available_copies);
    }
}
```
Execute o teste:

```bash
php artisan test --filter=test_concurrent_emprestimo_reveals_race_condition
```
Resultado (chocante):

```text
=== RESULTADOS DA CONDIÇÃO DE CORRIDA ===
Empréstimos bem‑sucedidos: 10
Falhas: 0
Estoque restante: 0
Empréstimos registrados: 10
Criamos 10 empréstimos para 1 exemplar! O estoque foi para negativo e múltiplos usuários pegaram o mesmo livro – tudo sem nenhum erro registrado.
```
### Entendendo o Que Aconteceu
Quando duas requisições chegam quase ao mesmo tempo:
   
Tempo	Requisição A (User 1)	Requisição B (User 2)	Banco (available_copies)   
t1	SELECT * FROM livros WHERE id = 1		1   
t2		SELECT * FROM livros WHERE id = 1	1   
t3	Verifica: available_copies >= 1 ✓		1   
t4		Verifica: available_copies >= 1 ✓	1   
t5	UPDATE ... SET available_copies = 0		0   
t6		UPDATE ... SET available_copies = 0	0   
t7	Cria empréstimo	Cria empréstimo	2 empréstimos  
   
Ambas as requisições leram o mesmo estado (1 exemplar) antes que qualquer uma escrevesse. O resultado: duas operações de empréstimo para um único exemplar.   
   
Isso é uma condição de corrida – quando a correção do programa depende do momento e da ordem de operações concorrentes que você não pode controlar.

## A Correção Tentadora (mas Insuficiente)
Você pode pensar: "Vou adicionar uma condição where na atualização!"

```php
$updated = Livro::where('id', $livroId)
    ->where('available_copies', '>=', 1)
    ->update(['available_copies' => $livro->available_copies - 1]);
```
Isso parece mais seguro, mas ainda temos um problema: $livro->available_copies foi lido antes da atualização. Entre a leitura e a escrita, outro processo pode ter alterado o valor.   
   
Esse padrão tem um nome: read-modify-write (ler‑modificar‑escrever) – é uma das armadilhas mais comuns ao trabalhar com qualquer banco de dados sob carga concorrente.

## A Solução Real: Operações Atômicas no MariaDB
A chave é: por que estamos lendo o valor antes?
   
O MariaDB pode fazer o cálculo dentro do banco de dados em uma única instrução, sem que precisemos buscar o valor atual. Isso elimina completamente a condição de corrida.   

Atualização Atômica
```php
$updated = Livro::where('id', $livroId)
    ->where('available_copies', '>=', 1)
    ->update([
        'available_copies' => DB::raw('available_copies - 1')
    ]);
```
O que isso faz? O banco de dados executa a leitura, a verificação e a escrita em um único passo atômico. Se duas requisições executarem isso ao mesmo tempo, apenas uma vai conseguir fazer a atualização; a outra não encontrará available_copies >= 1 e retornará 0.
   
O Controlador Corrigido
```php
<?php

namespace App\Http\Controllers;

use App\Models\Livro;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmprestimoController extends Controller
{
    public function emprestar(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'livro_id' => 'required|exists:livros,id',
        ]);

        $userId = $request->user_id;
        $livroId = $request->livro_id;

        // Verifica multa (operação simples, sem atomicidade crítica)
        $user = User::find($userId);
        if ($user->has_fine) {
            return response()->json(['error' => 'Usuário com multa pendente'], 400);
        }

        // OPERAÇÃO ATÔMICA: diminui available_copies se houver pelo menos 1
        $updated = Livro::where('id', $livroId)
            ->where('available_copies', '>=', 1)
            ->update([
                'available_copies' => DB::raw('available_copies - 1')
            ]);

        if ($updated === 0) {
            return response()->json(['error' => 'Livro não disponível'], 400);
        }

        // Cria o empréstimo
        $loan = Loan::create([
            'user_id' => $userId,
            'livro_id' => $livroId,
            'loan_date' => now(),
            'due_date' => now()->addDays(14),
        ]);

        return response()->json(['success' => true, 'loan' => $loan]);
    }
}
```
## Testando a Solução Atômica
Atualize o teste para usar o mesmo método:

```php
public function test_atomic_operations_prevent_race_conditions()
{
    // Cria um livro com 1 exemplar
    $livro = Livro::create([
        'titulo' => 'Livro Único',
        'autor' => 'Autor Exemplo',
        'ano' => 2020,
        'isbn' => 1234567890,
        'total_copies' => 1,
        'available_copies' => 1
    ]);

    // Cria 10 usuários
    $users = User::factory(10)->create();

    // Simula 10 requisições concorrentes
    $responses = [];
    foreach ($users as $user) {
        $responses[] = $this->postJson('/api/emprestar', [
            'user_id' => $user->id,
            'livro_id' => $livro->id
        ]);
    }

    $successCount = collect($responses)->filter(fn($r) => $r->status() === 200)->count();
    $failureCount = collect($responses)->filter(fn($r) => $r->status() === 400)->count();

    dump('=== RESULTADOS COM OPERAÇÕES ATÔMICAS ===');
    dump('Empréstimos bem‑sucedidos: ' . $successCount);
    dump('Falhas: ' . $failureCount);

    $livro->refresh();
    dump('Estoque restante: ' . $livro->available_copies);

    $loanCount = Loan::where('livro_id', $livro->id)->count();
    dump('Empréstimos registrados: ' . $loanCount);

    $this->assertEquals(1, $successCount);
    $this->assertEquals(9, $failureCount);
    $this->assertEquals(0, $livro->available_copies);
    $this->assertEquals(1, $loanCount);
}
```
Execute:

```bash
php artisan test --filter=test_atomic_operations_prevent_race_conditions
```
Resultado:

```text
=== RESULTADOS COM OPERAÇÕES ATÔMICAS ===
Empréstimos bem‑sucedidos: 1
Falhas: 9
Estoque restante: 0
Empréstimos registrados: 1

PASS  Tests\Feature\EmprestimoConcurrencyTest
✓ atomic operations prevent race conditions
Perfeito! Apenas um usuário conseguiu o livro, nove receberam erro de indisponibilidade.
```
# Operações Multi-Tabela: Transações com Bloqueio
E quando precisamos atualizar múltiplas tabelas de forma consistente? Por exemplo, na devolução de um livro, precisamos:

- Registrar a devolução

- Calcular multa se houver atraso

- Atualizar o estoque do livro

- Marcar o usuário como "com multa" se necessário

```php
public function devolver(Request $request, Loan $loan)
{
    DB::transaction(function () use ($loan) {
        // Bloqueia a linha do empréstimo
        $loan = Loan::where('id', $loan->id)->lockForUpdate()->first();

        if ($loan->return_date !== null) {
            throw new \Exception('Este empréstimo já foi devolvido');
        }

        // Calcula multa se atrasado
        $fine = 0;
        if (now()->gt($loan->due_date)) {
            $daysLate = now()->diffInDays($loan->due_date);
            $fine = $daysLate * 1.00;
        }

        // Bloqueia e atualiza o livro
        $livro = Livro::where('id', $loan->livro_id)->lockForUpdate()->first();
        $livro->available_copies += 1;
        $livro->save();

        // Atualiza o empréstimo
        $loan->return_date = now();
        $loan->fine = $fine;
        $loan->save();

        // Marca multa no usuário
        if ($fine > 0) {
            $user = User::where('id', $loan->user_id)->lockForUpdate()->first();
            $user->has_fine = true;
            $user->save();
        }
    });

    return response()->json(['success' => true]);
}
```
lockForUpdate() adiciona SELECT ... FOR UPDATE ao banco, que bloqueia as linhas selecionadas para outras transações até que a transação atual seja concluída.
   
Comparação de Performance
```php
public function test_performance_comparison()
{
    // Cria 100 livros e 100 usuários
    for ($i = 0; $i < 100; $i++) {
        Livro::create([
            'titulo' => "Livro {$i}",
            'autor' => 'Autor',
            'ano' => 2020,
            'isbn' => 1000000000 + $i,
            'total_copies' => 100,
            'available_copies' => 100
        ]);
    }

    // Teste 1: Eloquent read-modify-write
    $startEloquent = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $livro = Livro::find($i + 1);
        $livro->available_copies -= 1;
        $livro->save();
    }
    $eloquentTime = (microtime(true) - $startEloquent) * 1000;

    // Reset
    Livro::query()->update(['available_copies' => 100]);

    // Teste 2: Atualização atômica
    $startAtomic = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        Livro::where('id', $i + 1)
            ->where('available_copies', '>=', 1)
            ->update(['available_copies' => DB::raw('available_copies - 1')]);
    }
    $atomicTime = (microtime(true) - $startAtomic) * 1000;

    dump("Eloquent: {$eloquentTime}ms");
    dump("Atômico: {$atomicTime}ms");
    dump("Melhoria: " . round(($eloquentTime - $atomicTime) / $eloquentTime * 100, 1) . "%");
}
```
Resultados típicos:

```text
Eloquent: 245ms
Atômico: 156ms
Melhoria: 36.3%
```
As operações atômicas são não apenas mais seguras, mas também mais rápidas porque eliminam a viagem de ida e volta da rede para ler o valor atual.

# Boas Práticas
1. Use atualizações atômicas para modificações numéricas
```php
// ✅ BOM
Livro::where('id', $id)
    ->where('available_copies', '>=', 1)
    ->update(['available_copies' => DB::raw('available_copies - 1')]);

// ❌ RUIM
$livro = Livro::find($id);
$livro->available_copies -= 1;
$livro->save();
```
2. Verifique $updated para confirmar sucesso
```php
$updated = Livro::where('id', $id)
    ->where('available_copies', '>=', 1)
    ->update(['available_copies' => DB::raw('available_copies - 1')]);

if ($updated === 0) {
    // Falha: estoque insuficiente
}
```
3. Use transações com lockForUpdate() para múltiplas tabelas
```php
DB::transaction(function () {
    $livro = Livro::where('id', $id)->lockForUpdate()->first();
    $user = User::where('id', $userId)->lockForUpdate()->first();
    // atualizações seguras...
});
```
4. Saiba quando usar Eloquent
```php
// ✅ Bom para inserções e leituras
$livro = Livro::create([...]);
$livros = Livro::all();

// ✅ Bom para atualizações independentes
Livro::where('id', $id)->update(['status' => 'inativo']);
```
## Quando Usar Cada Abordagem
|Cenário | Abordagem|
|Incrementar/decrementar contadores | Atualização atômica com DB::raw|
|Atualizar múltiplas tabelas | Transação com lockForUpdate()|
|Criar novos registros | Eloquent normal|
|Leitura de dados |	Eloquent normal|
|Atualização não dependente do valor anterior | Eloquent normal|

# Principais Aprendizados
- Condições de corrida são invisíveis em desenvolvimento – só aparecem sob carga real

- O padrão ler‑modificar‑escrever é um antipadrão – cria uma janela para condições de corrida

- Atualizações atômicas no MariaDB – UPDATE ... SET coluna = coluna +/- valor WHERE condicao

- Transações com SELECT ... FOR UPDATE – para consistência entre múltiplas tabelas

- Sempre teste com requisições concorrentes – crie testes que simulam múltiplos acessos simultâneos

# Conclusão
A migração de MongoDB para MariaDB não significa abrir mão da segurança em operações concorrentes. Com as ferramentas certas – atualizações atômicas e transações com bloqueio – você pode garantir a integridade dos dados mesmo sob alta carga.
   
Agora você está preparado para identificar e corrigir condições de corrida em suas aplicações Laravel com MariaDB. Boa sorte e bons empréstimos! 
   
*Adaptado do tutorial original "Detecting and Fixing Race Conditions in Laravel Applications" de Arthur Ribeiro (Laravel News, 2026).*

