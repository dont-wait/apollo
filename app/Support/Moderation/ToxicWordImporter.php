<?php

namespace App\Support\Moderation;

use App\Models\ToxicWord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

class ToxicWordImporter
{
    public const int MAX_ROWS = 2000;

    public const array COLUMNS = ['term', 'scopes', 'match_mode', 'fold_diacritics', 'is_active', 'note'];

    public function __construct(private ToxicWordNormalizer $normalizer) {}

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file, int $userId): array
    {
        $rows = $this->readRows($file);
        $existing = array_flip(ToxicWord::query()->pluck('normalized_term')->all());
        $capacity = max(0, (int) config('toxic_words.max_terms') - count($existing));

        $toInsert = [];
        $skipped = 0;
        $errors = [];

        foreach ($rows as $line => $row) {
            $parsed = $this->parseRow($row);

            if (is_string($parsed)) {
                $errors[] = "Dòng {$line}: {$parsed}";

                continue;
            }

            if (isset($existing[$parsed['normalized_term']])) {
                $skipped++;

                continue;
            }

            if (count($toInsert) >= $capacity) {
                $errors[] = "Dòng {$line}: từ điển đã đạt giới hạn ".config('toxic_words.max_terms').' từ.';

                break;
            }

            $existing[$parsed['normalized_term']] = true;
            $toInsert[] = [
                ...$parsed,
                'scopes' => json_encode($parsed['scopes']),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($toInsert !== []) {
            DB::transaction(function () use ($toInsert): void {
                foreach (array_chunk($toInsert, 500) as $chunk) {
                    DB::table('toxic_words')->insertOrIgnore($chunk);
                }
            });
            ToxicWord::invalidateCache();
        }

        return ['created' => count($toInsert), 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * @return array<int, array<string, string>> keyed by spreadsheet line number
     */
    private function readRows(UploadedFile $file): array
    {
        $reader = $this->makeReader($file);
        $rows = [];

        try {
            $reader->open($file->getRealPath());

            foreach ($reader->getSheetIterator() as $sheet) {
                $header = null;
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map(
                        fn (mixed $cell): string => trim((string) ($cell instanceof \DateTimeInterface ? $cell->format('Y-m-d') : $cell)),
                        $row->toArray(),
                    );

                    if ($header === null) {
                        $header = array_map(fn (string $cell): string => mb_strtolower(ltrim($cell, "\u{FEFF}")), $cells);

                        if (! in_array('term', $header, true)) {
                            throw new RuntimeException('File phải có dòng tiêu đề đầu tiên chứa cột "term".');
                        }

                        continue;
                    }

                    if (implode('', $cells) === '') {
                        continue;
                    }

                    if (count($rows) >= self::MAX_ROWS) {
                        throw new RuntimeException('File vượt quá '.self::MAX_ROWS.' dòng. Hãy chia nhỏ file.');
                    }

                    $assoc = [];
                    foreach ($header as $index => $name) {
                        if (in_array($name, self::COLUMNS, true)) {
                            $assoc[$name] = $cells[$index] ?? '';
                        }
                    }
                    $rows[$line] = $assoc;
                }

                break; // chỉ đọc sheet đầu tiên
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    private function makeReader(UploadedFile $file): ReaderInterface
    {
        return strtolower($file->getClientOriginalExtension()) === 'csv'
            ? new CsvReader
            : new XlsxReader;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>|string parsed attributes, or an error message
     */
    private function parseRow(array $row): array|string
    {
        $term = $row['term'] ?? '';
        $allScopes = config('toxic_words.scopes');

        $scopes = $allScopes;
        if (($row['scopes'] ?? '') !== '') {
            $scopes = array_values(array_unique(array_filter(
                preg_split('/[\s,;|]+/', mb_strtolower($row['scopes'])) ?: [],
            )));
            $invalid = array_diff($scopes, $allScopes);

            if ($scopes === [] || $invalid !== []) {
                return 'scope không hợp lệ ('.implode(', ', $invalid ?: [$row['scopes']]).'). Cho phép: '.implode(', ', $allScopes).'.';
            }
        }

        $matchMode = strtoupper($row['match_mode'] ?? '') ?: ToxicWord::MATCH_WORD;

        $validator = Validator::make(
            ['term' => $term, 'match_mode' => $matchMode, 'note' => ($row['note'] ?? '') ?: null],
            [
                'term' => ['required', 'string', 'min:2', 'max:100'],
                'match_mode' => ['in:'.implode(',', ToxicWord::MATCH_MODES)],
                'note' => ['nullable', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $normalized = $this->normalizer->normalize($term);

        if (! preg_match('/[\p{L}\p{N}]/u', $normalized)) {
            return 'từ phải chứa ít nhất một chữ cái hoặc chữ số.';
        }

        if ($matchMode === ToxicWord::MATCH_LOOSE
            && mb_strlen(preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '') < 4) {
            return 'chế độ LOOSE chỉ áp dụng cho từ từ 4 ký tự.';
        }

        $foldDiacritics = $this->parseBool($row['fold_diacritics'] ?? '', false);
        $isActive = $this->parseBool($row['is_active'] ?? '', true);

        if ($foldDiacritics === null || $isActive === null) {
            return 'giá trị fold_diacritics/is_active phải là 1/0, true/false hoặc yes/no.';
        }

        return [
            'term' => trim($term),
            'normalized_term' => $normalized,
            'scopes' => $scopes,
            'match_mode' => $matchMode,
            'fold_diacritics' => $foldDiacritics,
            'is_active' => $isActive,
            'note' => ($row['note'] ?? '') ?: null,
        ];
    }

    private function parseBool(string $value, bool $default): ?bool
    {
        return match (mb_strtolower(trim($value))) {
            '' => $default,
            '1', 'true', 'yes', 'y', 'có', 'co' => true,
            '0', 'false', 'no', 'n', 'không', 'khong' => false,
            default => null,
        };
    }
}
