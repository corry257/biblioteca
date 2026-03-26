<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LivroRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
        public function rules(): array
    {
        $rules = [
            'titulo' => 'required|string|max:255',
            'autor'  => 'required|string|max:255',
            'ano'    => 'required|integer|digits:4',
            'isbn'   => ['required', 'integer'],
        ];

        // Adiciona regra unique para isbn
        if ($this->method() == 'PATCH' || $this->method() == 'PUT') {
            $livro = $this->route('livro'); // obtém o modelo Livro da rota
            $rules['isbn'][] = Rule::unique('livros', 'isbn')->ignore($livro->id);
        } else {
            $rules['isbn'][] = 'unique:livros,isbn';
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'isbn' => preg_replace('/[^0-9]/', '', $this->isbn),
        ]);
    }

    public function messages()
    {
        return [
            'isbn.unique' => 'Este ISBN já está cadastrado para outro livro',
            'ano.digits'  => 'O ano deve ter 4 dígitos',
        ];
    }
}
