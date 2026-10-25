<?php

namespace App\Support\Moderation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Str;

class ToxicWordHighlighter
{
    private const string BLOCK_SEPARATOR = "\u{E000}";

    /** @var list<string> */
    private const array BLOCK_TAGS = [
        'address', 'article', 'aside', 'blockquote', 'div', 'figcaption', 'figure',
        'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li', 'main',
        'nav', 'ol', 'p', 'pre', 'section', 'table', 'tbody', 'td', 'tfoot', 'th',
        'thead', 'tr', 'ul',
    ];

    public function __construct(private ToxicWordFilter $filter) {}

    /** @param list<ToxicWordMatch> $matches */
    public function highlightText(string $text, array $matches): string
    {
        $matches = array_values(array_filter(
            $matches,
            fn (ToxicWordMatch $match): bool => $match->start !== null && $match->end !== null,
        ));

        usort($matches, fn (ToxicWordMatch $left, ToxicWordMatch $right): int => $left->start <=> $right->start);

        $html = '';
        $cursor = 0;

        foreach ($matches as $match) {
            $start = $match->start;
            $end = $match->end;

            if ($start < $cursor || $end <= $start) {
                continue;
            }

            $html .= e(substr($text, $cursor, $start - $cursor));
            $html .= '<mark class="toxic-word-hit" data-term-id="'.$match->termId.'">'
                .e(substr($text, $start, $end - $start))
                .'</mark>';
            $cursor = $end;
        }

        return $html.e(substr($text, $cursor));
    }

    public function highlightMarkdown(string $markdown, string $scope): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;

        return $this->highlightHtml($html, $scope);
    }

    public function highlightHtml(string $html, string $scope): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return $html;
        }

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return $html;
        }

        $text = '';
        $segments = [];
        $this->collectTextSegments($body, $text, $segments);
        $matches = $this->filter->find($text, $scope);

        if ($matches !== []) {
            $this->applyMatches($segments, $matches);
        }

        $result = '';

        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    /**
     * @param  list<array{node: DOMText, start: int, end: int}>  $segments
     */
    private function collectTextSegments(DOMNode $node, string &$text, array &$segments): void
    {
        if ($node instanceof DOMText) {
            $value = $node->nodeValue ?? '';

            if ($value === '') {
                return;
            }

            $start = strlen($text);
            $text .= $value;
            $segments[] = ['node' => $node, 'start' => $start, 'end' => strlen($text)];

            return;
        }

        if (in_array(strtolower($node->nodeName), ['script', 'style'], true)) {
            return;
        }

        $tag = strtolower($node->nodeName);

        if ($tag === 'br') {
            $text .= self::BLOCK_SEPARATOR;

            return;
        }

        $isBlock = in_array($tag, self::BLOCK_TAGS, true);

        if ($isBlock) {
            $text .= self::BLOCK_SEPARATOR;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->collectTextSegments($child, $text, $segments);
        }

        if ($isBlock) {
            $text .= self::BLOCK_SEPARATOR;
        }
    }

    /**
     * @param  list<array{node: DOMText, start: int, end: int}>  $segments
     * @param  list<ToxicWordMatch>  $matches
     */
    private function applyMatches(array $segments, array $matches): void
    {
        usort($matches, fn (ToxicWordMatch $left, ToxicWordMatch $right): int => ($left->start ?? PHP_INT_MAX) <=> ($right->start ?? PHP_INT_MAX));

        $rangesByNode = [];
        $cursor = 0;

        foreach ($matches as $match) {
            if ($match->start === null || $match->end === null || $match->end <= $match->start || $match->start < $cursor) {
                continue;
            }

            $cursor = $match->end;

            foreach ($segments as $segment) {
                if ($segment['end'] <= $match->start) {
                    continue;
                }

                if ($segment['start'] >= $match->end) {
                    break;
                }

                $start = max($match->start, $segment['start']) - $segment['start'];
                $end = min($match->end, $segment['end']) - $segment['start'];

                if ($end <= $start) {
                    continue;
                }

                $rangesByNode[spl_object_id($segment['node'])][] = [
                    'start' => $start,
                    'end' => $end,
                    'term_id' => $match->termId,
                ];
            }
        }

        foreach ($segments as $segment) {
            $ranges = $rangesByNode[spl_object_id($segment['node'])] ?? [];

            if ($ranges === []) {
                continue;
            }

            $node = $segment['node'];
            $text = $node->nodeValue ?? '';
            $fragment = $node->ownerDocument->createDocumentFragment();
            $cursor = 0;

            foreach ($ranges as $range) {
                if ($range['start'] < $cursor) {
                    continue;
                }

                $fragment->appendChild($node->ownerDocument->createTextNode(
                    substr($text, $cursor, $range['start'] - $cursor),
                ));

                $mark = $node->ownerDocument->createElement('mark');
                $mark->setAttribute('class', 'toxic-word-hit');
                $mark->setAttribute('data-term-id', (string) $range['term_id']);
                $mark->appendChild($node->ownerDocument->createTextNode(
                    substr($text, $range['start'], $range['end'] - $range['start']),
                ));
                $fragment->appendChild($mark);
                $cursor = $range['end'];
            }

            $fragment->appendChild($node->ownerDocument->createTextNode(substr($text, $cursor)));

            $node->parentNode?->replaceChild($fragment, $node);
        }
    }
}
