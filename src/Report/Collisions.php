<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Detects RefNo collisions across a single run: two or more source records
 * whose RefNo is identical once a trailing slash is disregarded — e.g.
 * "XCP75/7" and "XCP75/7/". In CALM a trailing slash is sometimes a stray
 * separator and sometimes load-bearing (a genuinely different record), so
 * when both forms appear they collapse to the same normalised key and cannot
 * be told apart downstream (legacyId / parentId matching). This is almost
 * always a cataloguing error that must be FIXED AT SOURCE, not in the mapping.
 *
 * Deliberately narrow: RefNo only, trailing-slash only. If more collidable
 * fields or rules are needed later, generalise then — not before.
 *
 * Each variant carries its CALM RecordID — the internal record identifier from
 * the import, which is the only value that lets a cataloguer locate the exact
 * offending record at source.
 *
 * Mechanism only: it groups raw RefNo values by their normalised key and
 * reports any group holding more than one DISTINCT raw value.
 */
final class Collisions
{
    /** @var array<string, array<string,string>> normalised key => (raw RefNo => RecordID) */
    private array $groups = [];

    /**
     * Observe one parsed CALM record (childName => list<string>). Reads the
     * record's RefNo (first value) and buckets it by its normalised key,
     * remembering the record's RecordID for later reporting.
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $refNo = trim((string) (($record['RefNo'][0]) ?? ''));
        if ($refNo === '') {
            return;
        }
        $key = rtrim($refNo, '/');
        if ($key === '') {
            return;
        }
        $recordId = trim((string) (($record['RecordID'][0]) ?? ''));

        // Keep the first RecordID seen for a given raw RefNo.
        $this->groups[$key][$refNo] ??= $recordId;
    }

    /**
     * Build the collision summary.
     *
     * @return array{
     *   count:int,
     *   groups:list<array{normalised:string,variants:array<string,string>}>
     * }
     */
    public function summarise(): array
    {
        $out = [];
        foreach ($this->groups as $key => $variants) {
            // A collision is when the same normalised key was reached by more
            // than one DISTINCT raw RefNo (e.g. with and without trailing '/').
            if (count($variants) > 1) {
                $out[] = [
                    'normalised' => (string) $key,
                    'variants'   => $variants,
                ];
            }
        }

        // Stable, useful order: worst offenders (most variants) first.
        usort($out, static fn (array $a, array $b): int => count($b['variants']) <=> count($a['variants']));

        return [
            'count'  => count($out),
            'groups' => $out,
        ];
    }
}