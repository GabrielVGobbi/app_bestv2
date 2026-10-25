<?php

namespace App\Supports;

use Throwable;

class SensitiveDataRedactor
{
    private const REDACTED = '[redacted]';

    /** @param list<string> $secrets */
    public function text(string $text, array $secrets = []): string
    {
        return $this->redactText($text, $this->knownSecrets($secrets));
    }

    public function url(string $url): string
    {
        if (preg_match('//u', $url) !== 1 || preg_match('/[\x00-\x20\x7f]/', $url) === 1) {
            return self::REDACTED;
        }
        $parts = parse_url($url);
        if ($parts === false || (isset($parts['scheme']) && ! in_array(strtolower($parts['scheme']), ['http', 'https'], true))) {
            return self::REDACTED;
        }
        $path = $parts['path'] ?? '';
        if (isset($parts['host']) && strtolower($parts['host']) === 'hooks.slack.com') {
            $path = '/[redacted]';
        }
        $safe = isset($parts['host'])
            ? (isset($parts['scheme']) ? strtolower($parts['scheme']).'://' : '//').$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').$path
            : $path;

        return $this->replaceSecrets($safe, $this->knownSecrets([]));
    }

    /** @param list<string> $secrets */
    public function value(mixed $value, ?string $key = null, array $secrets = []): mixed
    {
        return $this->redactValue($value, $key, $this->knownSecrets($secrets), 0);
    }

    /** @param list<string> $secrets */
    private function redactValue(mixed $value, ?string $key, array $secrets, int $depth): mixed
    {
        if ($key !== null && $this->sensitiveKey($key)) {
            return self::REDACTED;
        }
        if ($value instanceof Throwable) {
            return ['type' => $value::class, 'message' => 'Exception details omitted.'];
        }
        if (is_object($value) || is_resource($value) || (is_float($value) && ! is_finite($value)) || $depth >= 8) {
            return self::REDACTED;
        }
        if (is_array($value)) {
            $result = [];
            $sensitiveField = isset($value['title'], $value['value']) && is_string($value['title']) && $this->sensitiveKey($value['title']);
            foreach ($value as $name => $item) {
                $safeName = is_string($name) ? $this->redactText($name, $secrets) : $name;
                $result[$safeName] = $name === 'value' && $sensitiveField
                    ? self::REDACTED
                    : $this->redactValue($item, is_string($name) ? $name : null, $secrets, $depth + 1);
            }

            return $result;
        }

        return is_string($value) ? $this->redactText($value, $secrets) : $value;
    }

    /** @param list<string> $secrets */
    private function replaceSecrets(string $text, array $secrets): string
    {
        $replacements = array_fill_keys($secrets, self::REDACTED);

        return implode(self::REDACTED, array_map(
            static fn (string $part): string => strtr($part, $replacements),
            explode(self::REDACTED, $text),
        ));
    }

    private function sensitiveKey(string $key): bool
    {
        $key = strtolower(str_replace(['-', ' '], '_', $key));

        return $key === 'key' || preg_match('/authorization|cookie|password|passwd|token|secret|api_?key|access_?key|private_?key|webhook|credential|client_id|body|payload|trace/i', $key) === 1;
    }

    /** @param list<string> $secrets */
    private function redactText(string $text, array $secrets): string
    {
        if (preg_match('//u', $text) !== 1) {
            return self::REDACTED;
        }
        $text = $this->replaceSecrets($text, $secrets);
        $text = preg_replace_callback('~https?://[^\s<>"\'\x60|]+~iu', fn (array $match): string => $this->url($match[0]), $text) ?? self::REDACTED;
        $text = preg_replace('/\b(Bearer|Basic)\s+[^\s,;]+/i', '$1 '.self::REDACTED, $text) ?? self::REDACTED;
        $text = preg_replace('/(\b(?:authorization|cookie|set-cookie)\s*:\s*)[^\r\n]+/i', '$1'.self::REDACTED, $text) ?? self::REDACTED;

        return preg_replace_callback(
            '~((?:["\']?)(?:password|passwd|token|access_token|refresh_token|secret|client_secret|api[_-]?key|access[_-]?key|private[_-]?key|authorization|cookie|webhook_url)(?:["\']?)\s*[:=]\s*)("(?:\\\\.|[^"\\\\])*"|\'(?:\\\\.|[^\'\\\\])*\'|\[redacted\]|[^\s,;}\]]+)~iu',
            static function (array $match): string {
                $quote = in_array($match[2][0], ['"', "'"], true) ? $match[2][0] : '';

                return $match[1].$quote.self::REDACTED.$quote;
            },
            $text,
        ) ?? self::REDACTED;
    }

    /**
     * @param  list<string>  $additional
     * @return list<string>
     */
    private function knownSecrets(array $additional): array
    {
        $secrets = [...$additional];
        foreach ([config('app.key'), config('slack.webhook_url'), config('logging.channels.slack.url')] as $value) {
            if (is_string($value) && $value !== '') {
                $secrets[] = $value;
            }
        }
        foreach (['services', 'external-apis.modules', 'database.connections'] as $namespace) {
            $this->collectSecrets(config($namespace, []), $secrets, 0);
        }
        $variants = [];
        foreach ($secrets as $secret) {
            if ($secret === '') {
                continue;
            }
            $variants[] = $secret;
            $variants[] = rawurlencode($secret);
            $variants[] = urlencode($secret);
        }
        $variants = array_values(array_unique($variants));
        usort($variants, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        return $variants;
    }

    /** @param list<string> $secrets */
    private function collectSecrets(mixed $values, array &$secrets, int $depth): void
    {
        if (! is_array($values) || $depth >= 8) {
            return;
        }
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->sensitiveKey($key) && is_string($value) && $value !== '') {
                $secrets[] = $value;
            } elseif (is_array($value)) {
                $this->collectSecrets($value, $secrets, $depth + 1);
            }
        }
    }
}
