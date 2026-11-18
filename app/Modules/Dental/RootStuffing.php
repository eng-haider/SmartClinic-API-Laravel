<?php

namespace App\Modules\Dental;

/**
 * A case's root canal record, stored as JSON in cases.root_stuffing.
 *
 * The shape is the one the old system saved, so imported cases open unchanged:
 *   {"access_opening": [[canal, working length, reference point, file used], ...],
 *    "oburation":      [[canal, last file size, sealer type & duration, gutta percha size], ...]}
 * ("oburation" is the old system's spelling and is kept on purpose.)
 * An optional "note" keeps free text that an old case held instead of JSON.
 */
class RootStuffing
{
    public const SECTIONS = ['access_opening', 'oburation'];

    public const COLUMNS = 4;

    public const MAX_ROWS = 20;

    /**
     * Validation rules for the save request.
     */
    public static function rules(): array
    {
        $rules = ['note' => 'nullable|string|max:2000'];

        foreach (self::SECTIONS as $section) {
            $rules[$section] = 'nullable|array|max:' . self::MAX_ROWS;
            $rules["{$section}.*"] = 'array|max:' . self::COLUMNS;
            $rules["{$section}.*.*"] = 'nullable|string|max:100';
        }

        return $rules;
    }

    /**
     * Turn validated input into the JSON string to store, or null when nothing is filled.
     * Rows are trimmed and padded to four cells; rows with every cell empty are dropped.
     */
    public static function encode(array $input): ?string
    {
        $record = [];
        foreach (self::SECTIONS as $section) {
            $record[$section] = self::cleanRows($input[$section] ?? []);
        }

        $note = trim((string) ($input['note'] ?? ''));
        if ($note !== '') {
            $record['note'] = $note;
        }

        $isEmpty = $note === '' && !array_filter(self::SECTIONS, fn ($s) => $record[$s] !== []);

        return $isEmpty ? null : json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function cleanRows(?array $rows): array
    {
        $clean = [];

        foreach ($rows ?? [] as $row) {
            $values = array_values((array) $row);
            $cells = [];
            for ($i = 0; $i < self::COLUMNS; $i++) {
                $cells[] = trim((string) ($values[$i] ?? ''));
            }

            if (implode('', $cells) !== '') {
                $clean[] = $cells;
            }
        }

        return $clean;
    }
}
