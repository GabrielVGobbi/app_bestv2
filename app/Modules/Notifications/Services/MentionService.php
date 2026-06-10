<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class MentionService
{
    /**
     * Extract all unique usernames mentioned with @username pattern from text.
     *
     * @return array<int, string>
     */
    public function extractUsernames(string $text): array
    {
        preg_match_all('/@([\w.-]+)/u', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Resolve mentioned usernames to User models.
     * Excludes the author to avoid self-notifications.
     *
     * @return Collection<int, User>
     */
    public function usersFromText(string $text, ?User $author = null): Collection
    {
        $usernames = $this->extractUsernames($text);

        if (empty($usernames)) {
            return collect();
        }

        $query = User::whereIn('name', $usernames);

        if ($author !== null) {
            $query->where('id', '!=', $author->id);
        }

        return $query->get()->unique('id')->values();
    }
}
