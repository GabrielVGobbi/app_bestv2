<?php

namespace Tests\Unit;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class FortifyTranslationTest extends TestCase
{
    public function test_every_fortify_message_has_a_portuguese_translation(): void
    {
        $keys = [];

        foreach (Finder::create()->files()->name('*.php')->in(base_path('vendor/laravel/fortify/src')) as $file) {
            preg_match_all('~(?:__|trans)\(\s*\'([^\']+)\'~', $file->getContents(), $matches);
            array_push($keys, ...$matches[1]);
        }

        $this->assertNotEmpty($keys);

        $missing = array_values(array_filter(
            array_unique($keys),
            fn (string $key): bool => ! trans()->hasForLocale($key, 'pt_BR'),
        ));

        $this->assertSame([], $missing, 'Mensagens do Fortify sem tradução em lang/pt_BR');
    }
}
