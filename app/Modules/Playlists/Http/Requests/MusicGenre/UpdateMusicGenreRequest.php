<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\MusicGenre;

use App\Modules\Playlists\Models\MusicGenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateMusicGenreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', new Playlist) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('name')) {
                    return;
                }

                $genre = $this->route('musicGenre');

                $exists = MusicGenre::query()
                    ->where('slug', $this->string('slug')->toString())
                    ->when($genre instanceof MusicGenre, fn ($query) => $query->whereKeyNot($genre->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'Já existe um gênero musical com esse nome.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => Str::slug((string) $this->input('name')),
        ]);
    }
}
