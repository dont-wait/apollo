<?php

namespace App\Support\Moderation;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Str;

class ToxicWordHighlighter
{
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

        $this->highlightNode($document, $scope);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return $html;
        }

        $result = '';

        foreach ($body->childNodes as $child) {
            $result .= $document->saveHTML($child);
        }

        return $result;
    }

    private function highlightNode(DOMNode $node, string $scope): void
    {
        if ($node instanceof DOMText) {
            $this->highlightTextNode($node, $scope);

            return;
        }

        if (in_array($node->nodeName, ['script', 'style'], true)) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            $this->highlightNode($child, $scope);
        }
    }

    private function highlightTextNode(DOMText $node, string $scope): void
    {
        $text = $node->nodeValue ?? '';
        $matches = $this->filter->find($text, $scope);

        if ($matches === []) {
            return;
        }

        $fragment = $node->ownerDocument->createDocumentFragment();
        $cursor = 0;

        foreach ($matches as $match) {
            if ($match->start === null || $match->end === null || $match->start < $cursor) {
                continue;
            }

            $fragment->appendChild($node->ownerDocument->createTextNode(
                substr($text, $cursor, $match->start - $cursor),
            ));

            $mark = $node->ownerDocument->createElement('mark');
            $mark->setAttribute('class', 'toxic-word-hit');
            $mark->setAttribute('data-term-id', (string) $match->termId);
            $mark->appendChild($node->ownerDocument->createTextNode(
                substr($text, $match->start, $match->end - $match->start),
            ));
            $fragment->appendChild($mark);
            $cursor = $match->end;
        }

        $fragment->appendChild($node->ownerDocument->createTextNode(substr($text, $cursor)));

        $node->parentNode?->replaceChild($fragment, $node);
    }
}
