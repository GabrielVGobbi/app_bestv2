<?php

namespace Tests\Unit\Frontend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contraste WCAG dos pares de tokens de resources/css/app.css para qualquer --brand-hue.
 * Converte oklch para sRGB (OKLab, canais cortados em 0..1) e mede a luminância relativa.
 */
class TokenContrastTest extends TestCase
{
    /** Pares de texto (4.5:1): [texto, fundo]. */
    private const TEXT_PAIRS = [
        ['foreground', 'background'], ['foreground', 'card'], ['foreground', 'popover'],
        ['foreground', 'muted'], ['foreground', 'secondary'], ['foreground', 'accent'],
        ['muted-foreground', 'background'], ['muted-foreground', 'card'], ['muted-foreground', 'muted'],
        ['muted-foreground', 'secondary'], ['muted-foreground', 'accent'], ['muted-foreground', 'primary-soft'],
        ['primary-foreground', 'primary'], ['primary-foreground', 'primary-hover'],
        ['primary', 'card'], ['primary', 'background'],
        ['primary-soft-foreground', 'primary-soft'],
        ['sidebar-foreground', 'sidebar'], ['sidebar-foreground', 'sidebar-accent'],
        ['sidebar-muted-foreground', 'sidebar'], ['sidebar-primary-foreground', 'sidebar-primary'],
        ['success-foreground', 'success'], ['success-soft-foreground', 'success-soft'],
        ['warning-foreground', 'warning'], ['warning-soft-foreground', 'warning-soft'],
        ['destructive-foreground', 'destructive'], ['destructive-soft-foreground', 'destructive-soft'],
        ['destructive', 'card'],
        ['destructive-soft-foreground', 'card'], ['destructive-soft-foreground', 'background'],
        ['info-foreground', 'info'], ['info-soft-foreground', 'info-soft'],
    ];

    /** Pares de componente de interface (3:1): bordas de campo e anel de foco. */
    private const UI_PAIRS = [
        ['input', 'card'], ['input', 'input-surface'], ['ring', 'background'], ['ring', 'card'],
    ];

    /** @return iterable<string, array{int, string}> */
    public static function huesAndThemes(): iterable
    {
        for ($hue = 0; $hue < 360; $hue += 5) {
            foreach (['light', 'dark'] as $theme) {
                yield "hue {$hue} {$theme}" => [$hue, $theme];
            }
        }
    }

    #[DataProvider('huesAndThemes')]
    public function test_token_pairs_meet_wcag_aa_for_any_brand_hue(int $hue, string $theme): void
    {
        $tokens = self::tokens($theme);
        $failures = [];

        foreach ([[self::TEXT_PAIRS, 4.5], [self::UI_PAIRS, 3.0]] as [$pairs, $minimum]) {
            foreach ($pairs as [$front, $back]) {
                $ratio = self::contrast(self::rgb($tokens, $front, $hue), self::rgb($tokens, $back, $hue));

                if ($ratio < $minimum) {
                    $failures[] = sprintf('%s sobre %s: %.2f (mínimo %.1f)', $front, $back, $ratio, $minimum);
                }
            }
        }

        $this->assertSame([], $failures, "Contraste insuficiente com --brand-hue {$hue} no tema {$theme}");
    }

    public function test_conversion_matches_the_measured_reference_values(): void
    {
        $tokens = self::tokens('light');

        // Spec 2.1, hue 215: branco sobre primary 6.0:1, foreground sobre card 17.3:1.
        $this->assertEqualsWithDelta(6.0, self::contrast(self::rgb($tokens, 'primary-foreground', 215), self::rgb($tokens, 'primary', 215)), 0.15);
        $this->assertEqualsWithDelta(17.3, self::contrast(self::rgb($tokens, 'foreground', 215), self::rgb($tokens, 'card', 215)), 0.3);
    }

    /** @return array<string, string> */
    private static function tokens(string $theme): array
    {
        $css = file_get_contents(dirname(__DIR__, 3).'/resources/css/app.css');
        $block = fn (string $selector): array => self::declarations(preg_match('~(?:^|\n)'.preg_quote($selector, '~').'\s*\{(.*?)\n\}~s', $css, $m) ? $m[1] : '');
        $root = $block(':root');

        return $theme === 'dark' ? array_merge($root, $block('.dark')) : $root;
    }

    /** @return array<string, string> */
    private static function declarations(string $block): array
    {
        preg_match_all('~--([\w-]+):\s*([^;]+);~', $block, $matches, PREG_SET_ORDER);

        return collect($matches)->mapWithKeys(fn (array $m): array => [$m[1] => trim($m[2])])->all();
    }

    /** @return array{float, float, float} sRGB linear */
    private static function rgb(array $tokens, string $name, int $hue): array
    {
        $value = $tokens[$name] ?? throw new \RuntimeException("Token --{$name} ausente");

        if (preg_match('~^var\(--([\w-]+)\)$~', $value, $m)) {
            return self::rgb($tokens, $m[1], $hue);
        }

        if (! preg_match('~^oklch\(([\d.]+)\s+([\d.]+)\s+(var\(--brand-hue\)|[\d.]+)\)$~', $value, $m)) {
            throw new \RuntimeException("Valor não suportado em --{$name}: {$value}");
        }

        [$l, $c] = [(float) $m[1], (float) $m[2]];
        $h = deg2rad($m[3] === 'var(--brand-hue)' ? $hue : (float) $m[3]);
        [$a, $b] = [$c * cos($h), $c * sin($h)];

        $lms = [
            ($l + 0.3963377774 * $a + 0.2158037573 * $b) ** 3,
            ($l - 0.1055613458 * $a - 0.0638541728 * $b) ** 3,
            ($l - 0.0894841775 * $a - 1.2914855480 * $b) ** 3,
        ];
        $clip = fn (float $v): float => max(0.0, min(1.0, $v));

        return [
            $clip(4.0767416621 * $lms[0] - 3.3077115913 * $lms[1] + 0.2309699292 * $lms[2]),
            $clip(-1.2684380046 * $lms[0] + 2.6097574011 * $lms[1] - 0.3413193965 * $lms[2]),
            $clip(-0.0041960863 * $lms[0] - 0.7034186147 * $lms[1] + 1.7076147010 * $lms[2]),
        ];
    }

    private static function contrast(array $front, array $back): float
    {
        $luminance = fn (array $rgb): float => 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];
        [$hi, $lo] = [max($luminance($front), $luminance($back)), min($luminance($front), $luminance($back))];

        return ($hi + 0.05) / ($lo + 0.05);
    }
}
