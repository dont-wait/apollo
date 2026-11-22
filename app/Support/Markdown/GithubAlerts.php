<?php

namespace App\Support\Markdown;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Turns GitHub-style alert blockquotes (> [!NOTE]) in rendered HTML into styled alert boxes.
 */
class GithubAlerts
{
    private const TYPES = [
        'note' => 'Note',
        'tip' => 'Tip',
        'important' => 'Important',
        'warning' => 'Warning',
        'caution' => 'Caution',
    ];

    public static function apply(string $html): string
    {
        if (! str_contains($html, '[!')) {
            return $html;
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?><div id="alerts-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $root = $document->getElementById('alerts-root');

        if ($root === null) {
            return $html;
        }

        foreach (iterator_to_array($xpath->query('.//blockquote', $root)) as $quote) {
            $paragraph = $xpath->query('./p[1]', $quote)->item(0);

            if (! $paragraph instanceof DOMElement
                || ! preg_match('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/i', $paragraph->textContent, $match)) {
                continue;
            }

            // The marker is always the first text node of the paragraph.
            $first = $paragraph->firstChild;
            if ($first === null || $first->nodeType !== XML_TEXT_NODE) {
                continue;
            }
            $first->nodeValue = preg_replace('/^\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*/i', '', $first->nodeValue);
            if ($first->nodeValue === '') {
                $paragraph->removeChild($first);
                if ($paragraph->firstChild instanceof DOMElement && $paragraph->firstChild->tagName === 'br') {
                    $paragraph->removeChild($paragraph->firstChild);
                }
            }
            if (trim($paragraph->textContent) === '' && ! $paragraph->getElementsByTagName('*')->length) {
                $quote->removeChild($paragraph);
            }

            $type = strtolower($match[1]);
            $alert = $document->createElement('div');
            $alert->setAttribute('class', "md-alert md-alert--{$type}");
            $title = $document->createElement('p', self::TYPES[$type]);
            $title->setAttribute('class', 'md-alert__title');
            $alert->appendChild($title);

            while ($quote->firstChild) {
                $alert->appendChild($quote->firstChild);
            }
            $quote->parentNode->replaceChild($alert, $quote);
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }
}
