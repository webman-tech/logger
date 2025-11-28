<?php

namespace WebmanTech\Logger\Helper;

/**
 * @internal
 */
final class StringHelper
{
    public static function limit(string $value, int $limit): string
    {
        return (strlen($value) > $limit) ? (substr($value, 0, $limit) . '...') : $value;
    }

    /**
     * 遮蔽敏感字段
     * @param array<int, string> $sensitiveKeys
     */
    public static function maskSensitiveFields(string $content, array $sensitiveKeys, string $replacement = '***'): string
    {
        foreach ($sensitiveKeys as $key) {
            if (!str_contains($content, $key)) {
                continue;
            }
            if (str_starts_with($content, '{')) {
                $quotedKey = preg_quote($key, '/');
                $content = (string)preg_replace(
                    '/"(' . $quotedKey . ')"\s*:\s*".*?"/im',
                    '"$1":"' . $replacement . '"',
                    $content
                );
            } elseif (str_contains($content, '=')) {
                $quotedKey = preg_quote($key, '/');
                $content = (string)preg_replace(
                    '/(' . $quotedKey . ')=.+?(&|$)/im',
                    '$1=' . $replacement . '$2',
                    $content
                );
            } else {
                $content = "[Contain Sensitive {$key}]";
            }
        }

        return $content;
    }
}
