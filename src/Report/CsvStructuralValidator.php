<?php
declare(strict_types=1);

namespace AtomTool\Report;

use RuntimeException;

/**
 * Offline reproduction of AtoM 2.10's *structural / format* CSV validators for
 * information-object (description) imports.
 *
 * atom-tool runs standalone, with no AtoM runtime and no database, so only the
 * checks that need neither are reproduced here. For those checks the verdicts
 * match AtoM byte-for-byte. Checks that require the AtoM ORM/DB (parentId,
 * legacyId, repository/term matching) are deliberately EXCLUDED and remain
 * AtoM-side at real import time.
 *
 * Row numbering follows AtoM: the header is row 1, so the first data row is
 * row 2.
 *
 * Usage:
 *   $report = (new CsvStructuralValidator())->validateFile('/path/to.csv');
 *   // or ->validateString($csvContents)
 *   // $report = ['ok'=>bool, 'warnCount'=>int, 'errorCount'=>int,
 *   //            'results'=>ValidationResult[], 'text'=>string]
 */
final class CsvStructuralValidator
{
    /** Event columns AtoM compares against each other for value parity. */
    public const EVENT_COLUMNS = [
        'eventTypes', 'eventDates', 'eventStartDates', 'eventEndDates',
        'eventActors', 'eventActorHistories', 'eventPlaces',
    ];

    /**
     * Recognized AtoM information-object CSV columns (AtoM 2.10) PLUS the three
     * OLS extended-field columns. Anything not here => "unrecognized" warning.
     *
     * NOTE: keep this list in sync with AtoM on upgrade
     * (source: lib/flatfile/config/QubitInformationObject.yml -> columnNames).
     */
    public const VALID_COLUMNS = [
        // Core identity / hierarchy
        'legacyId', 'parentId', 'qubitParentSlug', 'identifier', 'accessionNumber',
        // ISAD / descriptive
        'title', 'levelOfDescription', 'extentAndMedium', 'repository',
        'archivalHistory', 'acquisition', 'scopeAndContent', 'appraisal',
        'accruals', 'arrangement', 'accessConditions', 'reproductionConditions',
        'language', 'script', 'languageNote', 'physicalCharacteristics',
        'findingAids', 'locationOfOriginals', 'locationOfCopies',
        'relatedUnitsOfDescription', 'publicationNote', 'digitalObjectPath',
        'digitalObjectURI', 'generalNote', 'subjectAccessPoints',
        'placeAccessPoints', 'nameAccessPoints', 'genreAccessPoints',
        'descriptionIdentifier', 'institutionIdentifier', 'rules',
        'descriptionStatus', 'levelOfDetail', 'revisionHistory',
        'languageOfDescription', 'scriptOfDescription', 'sources',
        'archivistNote', 'publicationStatus', 'physicalObjectName',
        'physicalObjectLocation', 'physicalObjectType', 'alternativeIdentifiers',
        'alternativeIdentifierLabels', 'culture', 'radEdition',
        // Event columns
        'eventTypes', 'eventDates', 'eventStartDates', 'eventEndDates',
        'eventActors', 'eventActorHistories', 'eventPlaces',
        // OLS extended fields (our additions)
        'olsExtendedFieldNamespace', 'olsExtendedFieldName', 'olsExtendedFieldValue',
    ];

    public function __construct(
        private readonly string $separator = ',',
        private readonly string $enclosure = '"',
    ) {
    }

    /**
     * @return array{ok:bool,warnCount:int,errorCount:int,results:list<ValidationResult>,text:string}
     */
    public function validateFile(string $path): array
    {
        if (!is_readable($path)) {
            throw new RuntimeException(sprintf('CSV not readable: %s', $path));
        }
        return $this->validateString((string) file_get_contents($path));
    }

    /**
     * @return array{ok:bool,warnCount:int,errorCount:int,results:list<ValidationResult>,text:string}
     */
    public function validateString(string $csv): array
    {
        // Strip a UTF-8 BOM if present (AtoM tolerates/detects it).
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;

        [$header, $rows] = $this->parse($csv);

        $results = [
            $this->checkDuplicateColumns($header),
            $this->checkColumnNames($header),
            $this->checkColumnCount($header, $rows),
            $this->checkEmptyRows($rows),
            $this->checkEventValues($header, $rows),
            $this->checkLegacyIdUniqueness($header, $rows),
        ];

        $warnCount = 0;
        $errorCount = 0;
        foreach ($results as $r) {
            if ($r->status === ValidationResult::WARN) {
                $warnCount++;
            } elseif ($r->status === ValidationResult::ERROR) {
                $errorCount++;
            }
        }

        return [
            'ok'         => $warnCount === 0 && $errorCount === 0,
            'warnCount'  => $warnCount,
            'errorCount' => $errorCount,
            'results'    => $results,
            'text'       => $this->renderText($results),
        ];
    }

    // ---- Individual checks -------------------------------------------------

    /**
     * @param list<string> $header
     */
    private function checkDuplicateColumns(array $header): ValidationResult
    {
        $seen = [];
        $dupes = [];
        foreach ($header as $name) {
            $key = trim($name);
            if (isset($seen[$key])) {
                $dupes[$key] = true;
            }
            $seen[$key] = true;
        }

        if ($dupes !== []) {
            return new ValidationResult(
                'Duplicate Column Name Test',
                ValidationResult::ERROR,
                ['Duplicate column names found: ' . implode(', ', array_keys($dupes))],
            );
        }
        return new ValidationResult('Duplicate Column Name Test', ValidationResult::INFO);
    }

    /**
     * @param list<string> $header
     */
    private function checkColumnNames(array $header): ValidationResult
    {
        $unknown = [];
        foreach ($header as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (!in_array($name, self::VALID_COLUMNS, true)) {
                $unknown[] = $name;
            }
        }

        if ($unknown !== []) {
            return new ValidationResult(
                'Column Name Test',
                ValidationResult::WARN,
                ['Unrecognized column names (AtoM will ignore these): ' . implode(', ', $unknown)],
                ['If these are intentional (e.g. extended fields), add them to VALID_COLUMNS.'],
            );
        }
        return new ValidationResult('Column Name Test', ValidationResult::INFO);
    }

    /**
     * @param list<string>        $header
     * @param list<list<string>>  $rows
     */
    private function checkColumnCount(array $header, array $rows): ValidationResult
    {
        $expected = count($header);
        $badRows = [];
        $rowNumber = 1; // header = row 1
        foreach ($rows as $row) {
            $rowNumber++;
            if (count($row) !== $expected) {
                $badRows[] = $rowNumber;
            }
        }

        if ($badRows !== []) {
            return new ValidationResult(
                'Column Count Test',
                ValidationResult::ERROR,
                [sprintf('Rows with a different column count than the header (%d): %d', $expected, count($badRows))],
                [
                    'CSV row numbers where issues were found: ' . implode(', ', $badRows),
                    'Likely an unescaped delimiter or missing enclosure quote. Ensure enclosure is double-quote.',
                ],
            );
        }
        return new ValidationResult('Column Count Test', ValidationResult::INFO);
    }

    /**
     * @param list<list<string>> $rows
     */
    private function checkEmptyRows(array $rows): ValidationResult
    {
        $empties = [];
        $rowNumber = 1; // header = row 1
        foreach ($rows as $row) {
            $rowNumber++;
            $joined = trim(implode('', array_map('trim', $row)));
            if ($joined === '') {
                $empties[] = $rowNumber;
            }
        }

        if ($empties !== []) {
            return new ValidationResult(
                'Empty Row Test',
                ValidationResult::WARN,
                [sprintf('Empty rows found: %d', count($empties))],
                ['CSV row numbers where issues were found: ' . implode(', ', $empties)],
            );
        }
        return new ValidationResult('Empty Row Test', ValidationResult::INFO);
    }

    /**
     * The main check: reproduces AtoM's CsvEventValuesValidator.
     *
     * For each present event column: split on '|', trim, count NON-EMPTY values.
     * A column contributing zero non-empty values is excluded from that row's
     * comparison. Within a row, if the non-empty counts across present event
     * columns are not all equal, the row is a mismatch.
     *
     * @param list<string>       $header
     * @param list<list<string>> $rows
     */
    private function checkEventValues(array $header, array $rows): ValidationResult
    {
        $index = array_flip($header);
        $columnsFound = array_values(array_intersect(self::EVENT_COLUMNS, $header));

        if ($columnsFound === []) {
            return new ValidationResult(
                'Event Value Count Test',
                ValidationResult::INFO,
                ['No event columns to check.'],
            );
        }

        $mismatchRows = [];
        $rowNumber = 1; // header = row 1
        foreach ($rows as $row) {
            $rowNumber++;
            $counts = [];
            foreach ($columnsFound as $column) {
                $cell = $row[$index[$column]] ?? '';
                $values = array_map('trim', explode('|', (string) $cell));
                // Match AtoM: count non-empty values.
                $count = count(array_filter($values, static fn ($v) => $v !== ''));
                if ($count > 0) {
                    $counts[$column] = $count;
                }
            }
            if ($counts !== [] && count(array_unique($counts)) !== 1) {
                $mismatchRows[] = $rowNumber;
            }
        }

        $summary = ['Checking columns: ' . implode(',', $columnsFound)];
        if ($mismatchRows !== []) {
            $summary[] = sprintf('Event value mismatches found: %d', count($mismatchRows));
            return new ValidationResult(
                'Event Value Count Test',
                ValidationResult::WARN,
                $summary,
                ['CSV row numbers where issues were found: ' . implode(', ', $mismatchRows)],
            );
        }
        return new ValidationResult('Event Value Count Test', ValidationResult::INFO, $summary);
    }

            /**
         * Reproduces AtoM's "Rows with non-unique 'legacyId' values" check.
         *
         * AtoM requires legacyId to be unique across the CSV: it is the key it
         * uses to wire up parentId and to match on re-import. When two rows share
         * a legacyId, AtoM cannot tell them apart and blocks the import.
         *
         * Same shape of problem as our source-side Collisions report (two records
         * become indistinguishable downstream), but observed on the OUTPUT CSV
         * rather than the source XML, so it lives here.
         *
         * Empty legacyId values are skipped: AtoM auto-generates when blank and
         * treating blanks as collisions would produce misleading noise.
         *
         * @param list<string>       $header
         * @param list<list<string>> $rows
         */
        private function checkLegacyIdUniqueness(array $header, array $rows): ValidationResult
        {
            $index = array_flip($header);
            if (!isset($index['legacyId'])) {
                return new ValidationResult(
                    'Legacy ID Uniqueness Test',
                    ValidationResult::INFO,
                    ['No legacyId column present; nothing to check.'],
                );
            }

            $col = $index['legacyId'];

            /** @var array<string, list<int>> $byValue value => list of CSV row numbers */
            $byValue = [];
            $rowNumber = 1; // header = row 1
            foreach ($rows as $row) {
                $rowNumber++;
                $value = trim((string) ($row[$col] ?? ''));
                if ($value === '') {
                    continue;
                }
                $byValue[$value][] = $rowNumber;
            }

            $duplicates = array_filter($byValue, static fn (array $rowNums): bool => count($rowNums) > 1);
            if ($duplicates === []) {
                return new ValidationResult('Legacy ID Uniqueness Test', ValidationResult::INFO);
            }

            // Worst offenders (most repeats) first, tie-break by value for stability.
            uksort($duplicates, static function (string $a, string $b) use ($duplicates): int {
                return count($duplicates[$b]) <=> count($duplicates[$a]) ?: strcmp($a, $b);
            });

            $affectedRowCount = array_sum(array_map('count', $duplicates));

            // Keep the details line readable on very noisy CSVs.
            $maxShown = 20;
            $detailLines = [];
            $shown = 0;
            foreach ($duplicates as $value => $rowNums) {
                if ($shown >= $maxShown) {
                    $detailLines[] = sprintf(
                        '(+%d more duplicated legacyId values not shown)',
                        count($duplicates) - $shown,
                    );
                    break;
                }
                $detailLines[] = sprintf(
                    "legacyId '%s' appears on rows: %s",
                    $value,
                    implode(', ', $rowNums),
                );
                $shown++;
            }

            return new ValidationResult(
                'Legacy ID Uniqueness Test',
                ValidationResult::ERROR,
                [sprintf(
                    'Rows with non-unique legacyId values: %d (across %d distinct legacyId values)',
                    $affectedRowCount,
                    count($duplicates),
                )],
                $detailLines,
            );
        }

    // ---- Parsing / rendering ----------------------------------------------

    /**
     * @return array{0:list<string>,1:list<list<string>>} [header, rows]
     */
    private function parse(string $csv): array
    {
        $fh = fopen('php://temp', 'r+b');
        if ($fh === false) {
            throw new RuntimeException('Unable to open a temporary stream for CSV parsing.');
        }
        fwrite($fh, $csv);
        rewind($fh);

        $header = fgetcsv($fh, 0, $this->separator, $this->enclosure, '\\');
        if ($header === false) {
            fclose($fh);
            throw new RuntimeException('CSV appears to be empty (no header row).');
        }
        $header = array_map(static fn ($c) => trim((string) $c), $header);

        $rows = [];
        while (($row = fgetcsv($fh, 0, $this->separator, $this->enclosure, '\\')) !== false) {
            // fgetcsv returns [null] for a blank line; normalize to empty row.
            if ($row === [null]) {
                $row = [];
            }
            $rows[] = array_map(static fn ($c) => (string) $c, $row);
        }
        fclose($fh);

        return [$header, $rows];
    }

    /**
     * @param list<ValidationResult> $results
     */
    private function renderText(array $results): string
    {
        $lines = [];
        foreach ($results as $r) {
            $lines[] = sprintf('[%s] %s', strtoupper($r->status), $r->title);
            foreach ($r->results as $line) {
                $lines[] = '  ' . $line;
            }
            foreach ($r->details as $line) {
                $lines[] = '  - ' . $line;
            }
        }
        return implode("\n", $lines);
    }
}
