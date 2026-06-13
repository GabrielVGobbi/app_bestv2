<?php

declare(strict_types=1);

namespace App\Modules\Playlists\Http\Requests\MusicSubgenre;

use App\Modules\Playlists\Models\MusicSubgenre;
use App\Modules\Playlists\Models\Playlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UpdateMusicSubgenreRequest extends FormRequest
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

                $subgenre = $this->route('musicSubgenre');

                if (! $subgenre instanceof MusicSubgenre) {
                    return;
                }

                $exists = MusicSubgenre::query()
                    ->where('slug', $this->string('slug')->toString())
                    ->whereKeyNot($subgenre->id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('name', 'Já existe um subgênero com esse nome.');
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
