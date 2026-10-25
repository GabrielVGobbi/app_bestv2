<?php

declare(strict_types=1);

namespace App\Modules\Timeline\Support;

use App\Modules\Timeline\Models\TimelineComment;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;

class TimelineCursor
{
    /**
     * @param  array<int, string>  $types
     * @return list<string>
     */
    public function types(array $types): array
    {
        $types = array_values(array_unique($types === [] ? ['activity', 'comment'] : $types));
        sort($types);

        return $types;
    }

    /**
     * @param  list<string>  $types
     * @return array{timestamp: ?string, rank: int, id: int, before: ?string}
     */
    public function decode(string $token, Model $subject, array $types): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw $this->invalid();
        }

        $keys = ['v', 'timestamp', 'rank', 'id', 'scope', 'types', 'before'];
        if (! is_array($payload) || count($payload) !== count($keys) || array_diff($keys, array_keys($payload)) !== []) {
            throw $this->invalid();
        }
        if ($payload['v'] !== 1
            || ! $this->validTimestamp($payload['timestamp'])
            || ! is_int($payload['rank']) || ! in_array($payload['rank'], [0, 1], true)
            || ! is_int($payload['id']) || $payload['id'] < 1
            || $payload['scope'] !== $this->scope($subject)
            || $payload['types'] !== $types
            || ! $this->validTimestamp($payload['before'])
            || ($payload['before'] !== null && $payload['timestamp'] === null)) {
            throw $this->invalid();
        }

        return ['timestamp' => $payload['timestamp'], 'rank' => $payload['rank'], 'id' => $payload['id'], 'before' => $payload['before']];
    }

    /** @param list<string> $types */
    public function encode(Model $anchor, Model $subject, array $types, ?string $before): string
    {
        return Crypt::encryptString(json_encode([
            'v' => 1,
            'timestamp' => $this->timestamp($anchor),
            'rank' => $this->rank($anchor),
            'id' => (int) $anchor->getKey(),
            'scope' => $this->scope($subject),
            'types' => $types,
            'before' => $before,
        ], JSON_THROW_ON_ERROR));
    }

    public function timestamp(Model $item): ?string
    {
        $timestamp = $item->getAttribute('created_at');

        return $timestamp instanceof DateTimeInterface ? $timestamp->format('Y-m-d H:i:s.u') : null;
    }

    public function rank(Model $item): int
    {
        return $item instanceof TimelineComment ? 0 : 1;
    }

    private function scope(Model $subject): string
    {
        return hash('sha256', $subject::class.'\0'.(string) $subject->getKey());
    }

    /** @phpstan-assert-if-true string|null $value */
    private function validTimestamp(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/D', $value)) {
            return false;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s.u', $value);

        return $date !== false && $date->format('Y-m-d H:i:s.u') === $value;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['cursor' => 'O cursor da timeline é inválido para estes filtros.']);
    }
}
