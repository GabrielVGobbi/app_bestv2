<?php

namespace Tests\Unit;

use Illuminate\Support\Arr;
use Tests\TestCase;

class ValidationTranslationTest extends TestCase
{
    public function test_every_english_validation_message_has_a_portuguese_translation(): void
    {
        $english = array_filter(Arr::dot(require lang_path('en/validation.php')), fn (mixed $message): bool => $message !== []);
        $portuguese = Arr::dot(require lang_path('pt_BR/validation.php'));

        $this->assertSame([], array_keys(array_diff_key($english, $portuguese)));
    }
}
