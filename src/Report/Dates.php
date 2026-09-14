<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Flags date values that are unlikely to import well into AtoM. Sibling to the
 * Orphans / Collisions scanners: same observe()/summarise() shape, warnings only,
 * never blocks a run, never changes source data.
 *
 * CALM date fields are frequently free text ("c.1850", "Lady Day 1850", "n.d.").
 * AtoM stores a display date (eventDates) happily, but needs ISO values in the
 * structured start/end columns (eventStartDates / eventEndDates) to build a
 * sortable, searchable range. A non-ISO structured date imports silently and
 * simply produces no sortable range — a quiet quality loss neither the CSV
 * structural validator nor AtoM's import flags.
 *
 * This scanner is SIGNAL-ONLY. It says nothing about empty fields (almost every
 * record leaves most date fields blank) and nothing about clean values. It only
 * surfaces DODGY CONTENT and cross-field inconsistencies, each with a distinct
 * value and a RefNo / RecordID sample so a cataloguer can find it at source and
 * decide whether to fix it in CALM or accept clean-up in AtoM later.
 *
 * Five fields are observed. Two of them are held to a STRICT (ISO) standard
 * because they feed AtoM's structured range; three are display-tolerant:
 *
 *   STRICT   : DateEarliest, DateLatest  (must be ISO to be useful)
 *   TOLERANT : Date, DateText, PubDate   (ISO, plain year, year range, decade OK)
 *
 * Mechanism only: no customer-specific judgement lives here.
 */
final class Dates
{
    /** Display-tolerant fields: ISO, plain year, year range, or decade is fine. */
    private const TOLERANT_FIELDS = ['Date', 'DateText', 'PubDate'];

    /** Strict fields: must be ISO (they feed AtoM's structured range). */
    private const STRICT_FIELDS = ['DateEarliest', 'DateLatest'];

    /**
     * Distinct dodgy values per field.
     *
     * @var array<string, array<string, array{value:string, refNo:string, recordId:string}>>
     */
    private array $dodgy = [];

    /** @var list<array{refNo:string, recordId:string, earliest:string, latest:string}> */
    private array $inverted = [];

    /** @var list<array{refNo:string, recordId:string, have:string, missing:string}> */
    private array $partial = [];

    /**
     * Observe one parsed CALM record (childName => list<string>).
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $refNo    = trim((string) (($record['RefNo'][0]) ?? ''));
        $recordId = trim((string) (($record['RecordID'][0]) ?? ''));

        // Per-field dodgy-content scan. Empty and clean values are ignored.
        foreach (self::TOLERANT_FIELDS as $field) {
            $value = trim((string) (($record[$field][0]) ?? ''));
            if ($value !== '' && !$this->isCleanTolerant($value)) {
                $this->recordDodgy($field, $value, $refNo, $recordId);
            }
        }
        foreach (self::STRICT_FIELDS as $field) {
            $value = trim((string) (($record[$field][0]) ?? ''));
            if ($value !== '' && !$this->isIso($value)) {
                $this->recordDodgy($field, $value, $refNo, $recordId);
            }
        }

        // Cross-field consistency on the structured range.
        $earliest = trim((string) (($record['DateEarliest'][0]) ?? ''));
        $latest   = trim((string) (($record['DateLatest'][0]) ?? ''));

        // Partial range: exactly one of the two is populated.
        if ($earliest !== '' && $latest === '') {
            $this->partial[] = [
                'refNo' => $refNo, 'recordId' => $recordId,
                'have' => 'DateEarliest', 'missing' => 'DateLatest',
            ];
        } elseif ($latest !== '' && $earliest === '') {
            $this->partial[] = [
                'refNo' => $refNo, 'recordId' => $recordId,
                'have' => 'DateLatest', 'missing' => 'DateEarliest',
            ];
        }

        // Inversion: both parse to a year and earliest is after latest.
        $ey = $this->leadingYear($earliest);
        $ly = $this->leadingYear($latest);
        if ($ey !== null && $ly !== null && $ey > $ly) {
            $this->inverted[] = [
                'refNo' => $refNo, 'recordId' => $recordId,
                'earliest' => $earliest, 'latest' => $latest,
            ];
        }
    }

    /**
     * Build the summary. `count` is the total number of flagged issues so the
     * UI can hide the whole box when nothing fired.
     *
     * @return array{
     *   count:int,
     *   dodgy:array<string,list<array{value:string,refNo:string,recordId:string}>>,
     *   inverted:list<array{refNo:string,recordId:string,earliest:string,latest:string}>,
     *   partial:list<array{refNo:string,recordId:string,have:string,missing:string}>
     * }
     */
    public function summarise(): array
    {
        $dodgy = [];
        $dodgyCount = 0;
        foreach ($this->dodgy as $field => $byValue) {
            $list = array_values($byValue);
            // Most-varied fields aside, a simple alphabetical value order reads
            // predictably in the panel.
            usort($list, static fn (array $a, array $b): int => strcmp($a['value'], $b['value']));
            $dodgy[$field] = $list;
            $dodgyCount += count($list);
        }

        return [
            'count'    => $dodgyCount + count($this->inverted) + count($this->partial),
            'dodgy'    => $dodgy,
            'inverted' => $this->inverted,
            'partial'  => $this->partial,
        ];
    }

    // ---- classification helpers -------------------------------------------

    /**
     * Record the first sighting of a distinct dodgy value for a field.
     */
    private function recordDodgy(string $field, string $value, string $refNo, string $recordId): void
    {
        $this->dodgy[$field][$value] ??= [
            'value'    => $value,
            'refNo'    => $refNo,
            'recordId' => $recordId,
        ];
    }

    /**
     * Strict ISO test: YYYY, YYYY-MM, or YYYY-MM-DD.
     */
    private function isIso(string $value): bool
    {
        return preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $value) === 1;
    }

    /**
     * Display-tolerant "clean" test: ISO, a plain year, a simple year range
     * ("1847-1852" / "1847/1852"), or a decade ("1850s"). Anything with
     * qualifiers ("c.", "?", "[", "circa", "fl.") or free text is dodgy.
     */
    private function isCleanTolerant(string $value): bool
    {
        if ($this->isIso($value)) {
            return true;
        }
        // Plain year range with '-' or '/' separator.
        if (preg_match('#^\d{4}\s*[-/]\s*\d{4}$#', $value) === 1) {
            return true;
        }
        // Decade, e.g. "1850s".
        if (preg_match('/^\d{4}s$/', $value) === 1) {
            return true;
        }
        return false;
    }

    /**
     * Extract a leading 4-digit year for inversion comparison, or null if the
     * value doesn't begin with a clear year.
     */
    private function leadingYear(string $value): ?int
    {
        if (preg_match('/^\s*(\d{4})/', $value, $m) === 1) {
            return (int) $m[1];
        }
        return null;
    }
}