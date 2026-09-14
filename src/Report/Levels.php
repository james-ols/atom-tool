<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Flags CALM Level values that are not in AtoM's DEFAULT level-of-description
 * taxonomy. Sibling to the Orphans / Collisions / Dates scanners: same
 * observe()/summarise() shape, warnings only, never blocks a run, never changes
 * source data.
 *
 * A Level value that isn't in AtoM's shipped default set is NOT an error. It is
 * one of two things, and only the archivist can tell them apart:
 *
 *   - a TYPO / variant ("Flie", "series ", "ITEM") to fix at source, OR
 *   - a LEGITIMATE local term the repository genuinely uses ("Sub-sub-series",
 *     "Bound volume") that must be ADDED to their AtoM taxonomy BEFORE import,
 *     or AtoM will silently fail to match it.
 *
 * So this scanner surfaces CANDIDATES; it does not judge them. It reports the
 * CALM source Level value (source-data quality), with a distinct value, its
 * occurrence count, and a RefNo / RecordID sample so a cataloguer can find it.
 *
 * The reference set below is AtoM's DEFAULT taxonomy only. A repository CAN and
 * often DOES customise it, so "not in the default set" means "confirm this",
 * never "this is wrong". Matched case-insensitively; a value that matches only
 * after case-folding is still flagged as a casing difference, because AtoM's
 * term matching is case-sensitive.
 *
 * Mechanism only: no customer-specific judgement lives here.
 *
 * Source: AtoM default "Level of description" taxonomy. Keep in sync on AtoM
 * upgrade, exactly like CsvStructuralValidator::VALID_COLUMNS.
 */
final class Levels
{
    /** AtoM's default level-of-description terms (shipped out of the box). */
    private const ATOM_DEFAULT_LEVELS = [
        'Collection',
        'Fonds',
        'Subfonds',
        'Series',
        'Subseries',
        'File',
        'Item',
        'Part',
    ];

    /**
     * Distinct candidate Level values (not an exact default term).
     *
     * @var array<string, array{value:string, count:int, refNo:string, recordId:string, casingOnly:bool}>
     */
    private array $candidates = [];

    /**
     * Observe one parsed CALM record (childName => list<string>).
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $level = trim((string) (($record['Level'][0]) ?? ''));
        if ($level === '') {
            return;
        }

        // Exact match against a default term: nothing to flag.
        if (in_array($level, self::ATOM_DEFAULT_LEVELS, true)) {
            return;
        }

        // Case-insensitive match: it's a real default term but the casing differs
        // (AtoM matches case-sensitively, so this still needs the archivist's eye).
        $casingOnly = false;
        foreach (self::ATOM_DEFAULT_LEVELS as $term) {
            if (strcasecmp($level, $term) === 0) {
                $casingOnly = true;
                break;
            }
        }

        $refNo    = trim((string) (($record['RefNo'][0]) ?? ''));
        $recordId = trim((string) (($record['RecordID'][0]) ?? ''));

        if (isset($this->candidates[$level])) {
            $this->candidates[$level]['count']++;
            return;
        }

        $this->candidates[$level] = [
            'value'      => $level,
            'count'      => 1,
            'refNo'      => $refNo,
            'recordId'   => $recordId,
            'casingOnly' => $casingOnly,
        ];
    }

    /**
     * Build the summary. `count` is the number of DISTINCT candidate values so
     * the UI can hide the whole box when nothing fired.
     *
     * @return array{
     *   count:int,
     *   candidates:list<array{value:string,count:int,refNo:string,recordId:string,casingOnly:bool}>
     * }
     */
    public function summarise(): array
    {
        $list = array_values($this->candidates);

        // Most-frequent candidates first, then alphabetical — the biggest offenders
        // (likely a systematic typo or a widely-used local term) read at the top.
        usort($list, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp($a['value'], $b['value']);
        });

        return [
            'count'      => count($list),
            'candidates' => $list,
        ];
    }
}
