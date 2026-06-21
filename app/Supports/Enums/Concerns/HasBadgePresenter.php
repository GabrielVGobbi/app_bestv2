<?php

declare(strict_types=1);

namespace App\Supports\Enums\Concerns;

trait HasBadgePresenter
{
    public function getValue(): string
    {
        return $this->value;
    }

    public function getLabelText(): string
    {
        return self::PRESENTERS[$this->value]['label'];
    }

    public function getLabelColor(): string
    {
        return self::PRESENTERS[$this->value]['color'];
    }

    public function getIcon(): string
    {
        return self::PRESENTERS[$this->value]['icon'];
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn (self $case): array => [
                'value' => $case->getValue(),
                'label' => $case->getLabelText(),
            ])
            ->all();
    }

    /** @return array{value: string, label: string, color: string, icon: string} */
    public function toPresenterArray(): array
    {
        return [
            'value' => $this->getValue(),
            'label' => $this->getLabelText(),
            'color' => $this->getLabelColor(),
            'icon' => $this->getIcon(),
        ];
    }
}
