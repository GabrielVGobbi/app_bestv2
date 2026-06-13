<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\MusicSubgenre;

use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMusicSubgenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Playlist::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'music_subgenre_id' => ['nullable', 'integer', Rule::exists('music_subgenres', 'id')],
            'name' => ['nullable', 'required_without:music_subgenre_id', 'string', 'min:2', 'max:255'],
            'slug' => ['nullable', 'required_without:music_subgenre_id', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $genre = $this->route('musicGenre');

                if (! $genre instanceof MusicGenre) {
                    return;
                }

                $subgenreId = $this->integer('music_subgenre_id') ?: null;

                if ($subgenreId !== null) {
                    $alreadyLinked = $genre->subgenres()
                        ->whereKey($subgenreId)
                        ->exists();

                    if ($alreadyLinked) {
                        $validator->errors()->add('name', 'Este subgênero já está vinculado a este gênero.');
                    }

                    return;
                }

                $exists = MusicSubgenre::query()
                    ->where('slug', $this->string('slug')->toString())
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'Já existe um subgênero com esse nome. Selecione o existente para vincular ao gênero.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => filled($this->input('name')) ? Str::slug((string) $this->input('name')) : null,
        ]);
    }
}
