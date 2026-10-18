<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Làm sạch HTML do ADMIN soạn trước khi in thô.
 * Chỉ giữ thẻ định dạng cơ bản, bỏ script/iframe/on* và URL không an toàn.
 */
final class SafeHtml
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li',
        'a', 'img', 'h2', 'h3', 'h4', 'blockquote', 'span', 'div', 'figure',
        'figcaption', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const ALLOWED_ATTRS = [
        'href', 'src', 'title', 'alt', 'class', 'target', 'rel',
        'colspan', 'rowspan', 'width', 'height',
    ];

    public static function admin(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        if (!class_exists('\DOMDocument')) {
            return strip_tags($html, '<p><br><strong><b><em><i><u><s><ul><ol><li><a><h2><h3><h4><blockquote><span><div>');
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="ff-root">' . $html . '</div>',
            LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return '';
        }

        $xp = new \DOMXPath($doc);
        $root = $xp->query('//*[@id="ff-root"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $nodes = [];
        foreach ($xp->query('//*') as $node) {
            $nodes[] = $node;
        }
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                if ($node->parentNode !== null) {
                    $node->parentNode->removeChild($node);
                }
                continue;
            }
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->name);
                $value = trim($attr->value);
                if (str_starts_with($name, 'on')) {
                    $node->removeAttribute($attr->name);
                    continue;
                }
                if (!in_array($name, self::ALLOWED_ATTRS, true)) {
                    $node->removeAttribute($attr->name);
                    continue;
                }
                if ($name === 'href' || $name === 'src') {
                    $safe = self::safeUrl($value);
                    if ($safe === null) {
                        $node->removeAttribute($attr->name);
                    } else {
                        $attr->value = $safe;
                    }
                }
            }
            if ($tag === 'a') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= (string) $doc->saveHTML($child);
        }
        return $out;
    }

    private static function safeUrl(string $value): ?string
    {
        if ($value === '' || str_starts_with($value, '#')) {
            return $value;
        }
        $safe = SafeUrl::httpsAny($value);
        if ($safe !== null) {
            return $safe;
        }
        if (str_starts_with($value, '/') && !str_starts_with($value, '//') && !str_contains($value, '..')) {
            return $value;
        }
        return null;
    }
}
