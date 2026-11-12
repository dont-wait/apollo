<?php

namespace App\Support\Moderation;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ToxicContentInspector
{
    public function __construct(
        private ToxicWordFilter $filter,
        private ToxicWordHighlighter $highlighter,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array{scope: string, label: string, markdown?: bool}>  $fields
     */
    public function inspect(
        array $data,
        array $fields,
        bool $withModeration = true,
        int $status = 422,
    ): ?ToxicContentException {
        $inspections = [];

        foreach ($fields as $path => $definition) {
            $field = Str::endsWith($path, '.*') ? Str::beforeLast($path, '.*') : $path;
            $value = data_get($data, $field);

            if (is_array($value)) {
                $value = implode("\n", array_filter($value, 'is_string'));
            }

            if (! is_string($value) || trim($value) === '') {
                continue;
            }

            $matches = $this->filter->find($value, $definition['scope']);
            $renderedOnly = false;

            if ($matches === [] && ($definition['markdown'] ?? false)) {
                $renderedMatches = $this->filter->find($this->markdownText($value), $definition['scope']);

                if ($renderedMatches !== []) {
                    $matches = $renderedMatches;
                    $renderedOnly = true;
                }
            }

            if ($matches === []) {
                continue;
            }

            $inspections[$field] = [
                'value' => $value,
                'matches' => $matches,
                'definition' => $definition,
                'rendered_only' => $renderedOnly,
            ];
        }

        if ($inspections === []) {
            return null;
        }

        $errors = [];
        $moderationFields = [];
        $termIds = [];
        $matchCount = 0;

        foreach ($inspections as $field => $inspection) {
            $definition = $inspection['definition'];
            $label = $definition['label'];
            $value = $inspection['value'];
            $matches = $inspection['matches'];

            $errors[$field][] = $label.' chứa từ ngữ không phù hợp. Vui lòng chỉnh sửa.';
            $matchCount += count($matches);
            $termIds = [...$termIds, ...array_map(fn (ToxicWordMatch $match): int => $match->termId, $matches)];

            if (! $withModeration) {
                continue;
            }

            $fieldPayload = [
                'count' => count($matches),
            ];

            $firstMatch = $matches[0];
            $hasOffsets = $firstMatch->start !== null && $firstMatch->end !== null;

            if ($definition['markdown'] ?? false) {
                $fieldPayload['preview_html'] = $this->highlighter->highlightMarkdown($value, $definition['scope']);

                if (! $inspection['rendered_only'] && $hasOffsets) {
                    $fieldPayload['overlay_html'] = $this->highlighter->highlightText($value, $matches);
                    $fieldPayload['first_offset'] = $this->utf16Offset($value, $firstMatch->start);
                }
            } elseif ($hasOffsets) {
                $fieldPayload['snippets'] = [$this->highlighter->highlightText($value, $matches)];
            }

            $moderationFields[$field] = $fieldPayload;
        }

        Log::info('toxic_words.blocked', [
            'fields' => array_keys($inspections),
            'term_ids' => array_values(array_unique($termIds)),
            'user_id' => auth()->id(),
            'moderation_attached' => $withModeration,
        ]);

        return ToxicContentException::fromMatches(
            $errors,
            [
                'summary' => 'Tìm thấy '.$matchCount.' từ ngữ không phù hợp trong '.count($inspections).' trường.',
                'fields' => $moderationFields,
            ],
            $withModeration,
            $status,
        );
    }

    private function markdownText(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);

        return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function utf16Offset(string $text, int $byteOffset): int
    {
        $prefix = substr($text, 0, $byteOffset);
        $encoded = mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8');

        return is_string($encoded) ? intdiv(strlen($encoded), 2) : mb_strlen($prefix, 'UTF-8');
    }
}
