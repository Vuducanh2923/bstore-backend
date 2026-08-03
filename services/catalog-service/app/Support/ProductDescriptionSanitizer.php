<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class ProductDescriptionSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'ul', 'ol', 'li', 'strong', 'b', 'i', 'em', 'u',
        'h1', 'h2', 'h3', 'blockquote', 'a',
    ];

    private const REMOVE_WITH_CONTENT = [
        'script', 'iframe', 'style', 'object', 'embed', 'svg', 'form',
    ];

    public function sanitize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="bstore-description-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('bstore-description-root');
        if (! $root) {
            return '';
        }

        $this->sanitizeChildren($root);

        $sanitized = '';
        foreach ($root->childNodes as $child) {
            $sanitized .= $document->saveHTML($child);
        }

        return $sanitized;
    }

    private function sanitizeChildren(DOMNode $parent): void
    {
        for ($node = $parent->firstChild; $node !== null;) {
            $next = $node->nextSibling;

            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::REMOVE_WITH_CONTENT, true)) {
                    $parent->removeChild($node);
                    $node = $next;

                    continue;
                }

                $this->sanitizeChildren($node);

                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    $node = $next;

                    continue;
                }

                $this->sanitizeAttributes($node, $tag);
            }

            $node = $next;
        }
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            if ($tag !== 'a' || strtolower($attribute->name) !== 'href') {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag === 'a' && $element->hasAttribute('href')) {
            $href = trim(html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (! $this->isSafeHref($href)) {
                $element->removeAttribute('href');
            } else {
                $element->setAttribute('href', $href);
            }
        }
    }

    private function isSafeHref(string $href): bool
    {
        if ($href === '' || preg_match('/[\x00-\x20\x7f]/u', $href)) {
            return false;
        }

        if (str_starts_with($href, '#') || str_starts_with($href, '/') || str_starts_with($href, './') || str_starts_with($href, '../')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true);
    }
}
