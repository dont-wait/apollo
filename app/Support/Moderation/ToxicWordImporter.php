<?php

namespace App\Support\Moderation;

use App\Models\ToxicWord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

class ToxicWordImporter
{
    public const int MAX_ROWS = 2000;

    public const int MAX_TERM_LENGTH = 100;

    public const string COMMENT_PREFIX = '#';

    public const array COLUMNS = ['term', 'scopes', 'match_mode', 'fold_diacritics', 'is_active', 'note'];

    public function __construct(private ToxicWordNormalizer $normalizer) {}

    /**
     * Import words from the first sheet of an xlsx/csv file. Rows whose term starts with "#" are ignored.
     *
     * Everything runs under a lock on the cache-version row so concurrent imports cannot exceed the
     * dictionary limit, and the cache version is bumped in the same transaction as the inserts.
     *
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function import(UploadedFile $file, int $userId): array
    {
        $rows = $this->readRows($file);

        return DB::transaction(function () use ($rows, $userId): array {
            DB::table('toxic_word_cache_versions')->insertOrIgnore([
                'id' => 1,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('toxic_word_cache_versions')->where('id', 1)->lockForUpdate()->first();

            $existing = array_flip(ToxicWord::query()->pluck('normalized_term')->all());
            $activeCount = ToxicWord::query()->active()->count();
            $maxTerms = (int) config('toxic_words.max_terms');

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

                if ($parsed['is_active'] && $activeCount >= $maxTerms) {
                    $errors[] = "Dòng {$line}: từ điển đã đạt giới hạn {$maxTerms} từ đang bật.";

                    continue;
                }

                $activeCount += $parsed['is_active'] ? 1 : 0;
                $existing[$parsed['normalized_term']] = true;
                $toInsert[] = [
                    ...$parsed,
                    'scopes' => json_encode($parsed['scopes']),
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $created = 0;

            foreach (array_chunk($toInsert, 500) as $chunk) {
                $created += DB::table('toxic_words')->insertOrIgnore($chunk);
            }

            if ($created > 0) {
                DB::table('toxic_word_cache_versions')
                    ->where('id', 1)
                    ->increment('version', 1, ['updated_at' => now()]);
            }

            return ['created' => $created, 'skipped' => $skipped, 'errors' => $errors];
        }, attempts: 3);
    }

    /**
     * Read the first sheet and return its rows keyed by spreadsheet line number.
     *
     * @return array<int, array{term?: string, scopes?: string, match_mode?: string, fold_diacritics?: string, is_active?: string, note?: string}>
     */
    private function readRows(UploadedFile $file): array
    {
        $reader = $this->makeReader($file);
        $rows = [];
        $header = null;

        try {
            $reader->open($file->getRealPath());

            foreach ($reader->getSheetIterator() as $sheet) {
                $line = 0;

                foreach ($sheet->getRowIterator() as $row) {
                    $line++;
                    $cells = array_map($this->cellToString(...), $row->toArray());

                    if ($header === null) {
                        $header = array_map(fn (string $cell): string => mb_strtolower(ltrim($cell, "\u{FEFF}")), $cells);

                        if (! in_array('term', $header, true)) {
                            throw new RuntimeException('File phải có dòng tiêu đề đầu tiên chứa cột "term".');
                        }

                        continue;
                    }

                    if (implode('', $cells) === '' || str_starts_with($cells[array_search('term', $header, true)] ?? '', self::COMMENT_PREFIX)) {
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

                break;
            }
        } finally {
            $reader->close();
        }

        if ($header === null) {
            throw new RuntimeException('File rỗng. Hãy dùng file mẫu có dòng tiêu đề "term".');
        }

        return $rows;
    }

    private function cellToString(mixed $cell): string
    {
        $value = match (true) {
            is_bool($cell) => $cell ? '1' : '0',
            $cell instanceof \DateTimeInterface => $cell->format('Y-m-d'),
            default => (string) $cell,
        };

        return preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $value) ?? $value;
    }

    private function makeReader(UploadedFile $file): ReaderInterface
    {
        if (strtolower($file->getClientOriginalExtension()) !== 'csv') {
            return new XlsxReader;
        }

        return new CsvReader(new CsvOptions(FIELD_DELIMITER: $this->detectDelimiter((string) $file->getRealPath())));
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'rb');
        $firstLine = $handle === false ? '' : (string) fgets($handle);

        if ($handle !== false) {
            fclose($handle);
        }

        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return array_key_first($counts) !== null && reset($counts) > 0 ? array_key_first($counts) : ',';
    }

    /**
     * @param  array{term?: string, scopes?: string, match_mode?: string, fold_diacritics?: string, is_active?: string, note?: string}  $row
     * @return array{term: string, normalized_term: string, scopes: list<string>, match_mode: string, fold_diacritics: bool, is_active: bool, note: string|null}|string parsed attributes, or an error message
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
        $note = ($row['note'] ?? '') === '' ? null : $row['note'];

        $validator = Validator::make(
            ['term' => $term, 'match_mode' => $matchMode, 'note' => $note],
            [
                'term' => ['required', 'string', 'min:2', 'max:'.self::MAX_TERM_LENGTH],
                'match_mode' => ['in:'.implode(',', ToxicWord::MATCH_MODES)],
                'note' => ['nullable', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $normalized = $this->normalizer->normalize($term);

        if (mb_strlen($normalized) < 2 || mb_strlen($normalized) > self::MAX_TERM_LENGTH) {
            return 'từ sau khi chuẩn hoá phải dài 2–'.self::MAX_TERM_LENGTH.' ký tự.';
        }

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
            'term' => $term,
            'normalized_term' => $normalized,
            'scopes' => $scopes,
            'match_mode' => $matchMode,
            'fold_diacritics' => $foldDiacritics,
            'is_active' => $isActive,
            'note' => $note,
        ];
    }

    private function parseBool(string $value, bool $default): ?bool
    {
        return match (mb_strtolower($value)) {
            '' => $default,
            '1', 'true', 'yes', 'y', 'có', 'co' => true,
            '0', 'false', 'no', 'n', 'không', 'khong' => false,
            default => null,
        };
    }
}
