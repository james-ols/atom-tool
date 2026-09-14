<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Detects orphaned records across a single run: records whose RefNo implies a
 * parent (there is at least one '/' separator) but whose parent RefNo is NOT
 * itself present in this file.
 *
 * In CALM the hierarchy is carried by the RefNo path, e.g. "XCP75/7/3" is a
 * child of "XCP75/7", which is a child of "XCP75". AtoM builds its tree from
 * that chain (legacyId / parentId matching), so a record whose parent is
 * missing will import at the wrong level — or fail to attach at all.
 *
 * WARNINGS ONLY, BY DESIGN. CALM exports are frequently PARTIAL: a record may
 * legitimately lack its parent in this file and only gain it once the full set
 * is loaded into AtoM. So this detector never blocks a run; it flags candidates
 * for a human to confirm. It is the operator's job to decide whether a missing
 * parent is "expected (partial export)" or "a genuine gap / cataloguing error".
 *
 * Deliberately narrow, exactly like the Collisions scanner: RefNo path only,
 * last-segment stripping only. Normalises a trailing slash before deriving the
 * parent so "XCP75/7/" and "XCP75/7" behave identically (the Collisions scanner
 * separately warns when BOTH raw forms appear).
 *
 * Each orphan carries its CALM RecordID — the only value that lets a cataloguer
 * locate the exact record at source.
 *
 * Mechanism only: no archival or customer knowledge lives here.
 */
final class Orphans
{
    /** @var array<string,true> normalised RefNo keys present in this file */
    private array $present = [];

    /**
     * Observations pending a parent-presence check, keyed by normalised RefNo.
     *
     * @var array<string, array{refNo:string, recordId:string, parent:string}>
     */
    private array $candidates = [];

    /**
     * Observe one parsed CALM record (childName => list<string>).
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $refNo = trim((string) (($record['RefNo'][0]) ?? ''));
        if ($refNo === '') {
            return;
        }

        // Normalise a stray trailing slash so the key set and the derived
        // parent are consistent regardless of that separator.
        $key = rtrim($refNo, '/');
        if ($key === '') {
            return;
        }

        // Every observed RefNo is "present" and can therefore satisfy some
        // other record's parent requirement.
        $this->present[$key] = true;

        // Derive the expected parent: everything before the final '/' segment.
        // No '/' => this is a top-level (root) record and cannot be an orphan.
        $pos = strrpos($key, '/');
        if ($pos === false) {
            return;
        }

        $parent = substr($key, 0, $pos);
        if ($parent === '') {
            return;
        }

        $recordId = trim((string) (($record['RecordID'][0]) ?? ''));

        // Keep the first sighting for a given normalised RefNo.
        $this->candidates[$key] ??= [
            'refNo'    => $refNo,
            'recordId' => $recordId,
            'parent'   => $parent,
        ];
    }

    /**
     * Build the orphan summary.
     *
     * Note: because the whole file is observed before summarising, a parent
     * that appears AFTER its child in the export is still correctly recognised
     * as present.
     *
     * @return array{
     *   count:int,
     *   orphans:list<array{refNo:string, recordId:string, parent:string}>
     * }
     */
    public function summarise(): array
    {
        $out = [];
        foreach ($this->candidates as $key => $info) {
            if (!isset($this->present[$info['parent']])) {
                $out[] = $info;
            }
        }

        // Stable, useful order: shallowest first (a missing top-of-branch
        // parent usually explains several deeper orphans), then by RefNo.
        usort($out, static function (array $a, array $b): int {
            $da = substr_count($a['refNo'], '/');
            $db = substr_count($b['refNo'], '/');
            return $da <=> $db ?: strcmp($a['refNo'], $b['refNo']);
        });

        return [
            'count'   => count($out),
            'orphans' => $out,
        ];
    }
}