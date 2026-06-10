<?php

declare(strict_types=1);

namespace App\Modules\Slack\DTOs;

use App\Modules\Slack\Enums\SlackAlertLevel;

readonly class SlackMessageDTO
{
    /**
     * @param  array<int, array{title: string, value: string, short?: bool}>  $fields
     */
    public function __construct(
        public string $text,
        public ?string $title = null,
        public SlackAlertLevel $level = SlackAlertLevel::Info,
        public array $fields = [],
        public ?string $footer = null,
        public ?string $fallback = null,
    ) {}

    /**
     * Serialize to Slack Incoming Webhook payload format (attachment-based).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $attachment = [
            'fallback' => $this->fallback ?? $this->text,
            'color' => $this->level->color(),
            'text' => $this->text,
            'ts' => time(),
        ];

        if ($this->title !== null) {
            $attachment['title'] = $this->level->emoji().' '.$this->title;
        }

        if (! empty($this->fields)) {
            $attachment['fields'] = array_map(fn (array $field) => [
                'title' => $field['title'],
                'value' => $field['value'],
                'short' => $field['short'] ?? true,
            ], $this->fields);
        }

        if ($this->footer !== null) {
            $attachment['footer'] = $this->footer;
        }

        return ['attachments' => [$attachment]];
    }
}
