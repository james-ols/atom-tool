<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * RefNo collision scanner. Detects TWO distinct problems, both of which cause an
 * AtoM legacyId clash on import (legacyId is derived from RefNo):
 *
 *   1) NORMALISATION collisions — one logical record reached by more than one
 *      raw RefNo spelling that normalise to the same key (e.g. "XCP75/7" and
 *      "XCP75/7/"). The fix is to tidy the RefNo spelling at source.
 *
 *   2) DUPLICATE RefNo — two or more DISTINCT CALM records that genuinely share
 *      the SAME RefNo. This is a real source-data error: AtoM cannot import two
 *      records with the same legacyId. The only thing that tells the duplicates
 *      apart is their RecordID, so each duplicate's RecordID is reported so a
 *      cataloguer can open each record at source.
 *
 * Mechanism only: no customer-specific judgement lives here.
 */
final class Collisions
{
    /**
     * Normalisation buckets: normalised key => (raw RefNo => first RecordID seen).
     *
     * @var array<string, array<string,string>>
     */
    private array $groups = [];

    /**
     * Every RecordID seen per raw (trimmed) RefNo, in first-seen order, used to
     * detect genuine duplicate RefNos. A record with a blank RecordID is still
     * counted (as an empty-string entry) so the duplicate is not hidden.
     *
     * @var array<string, list<string>>
     */
    private array $refNoRecordIds = [];

    /**
     * Observe one parsed CALM record (childName => list<string>). Reads the
     * record's RefNo (first value) and RecordID, feeding both collision checks.
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $refNo = trim((string) (($record['RefNo'][0]) ?? ''));
        if ($refNo === '') {
            return;
        }
        $recordId = trim((string) (($record['RecordID'][0]) ?? ''));

        // (2) Duplicate RefNo: remember EVERY record under this exact raw RefNo.
        $this->refNoRecordIds[$refNo][] = $recordId;

        // (1) Normalisation collision: bucket by the slash-normalised key,
        // keeping the first RecordID seen for each raw spelling.
        $key = rtrim($refNo, '/');
        if ($key === '') {
            return;
        }
        $this->groups[$key][$refNo] ??= $recordId;
    }

    /**
     * Build the collision summary.
     *
     * @return array{
     *   count:int,
     *   groups:list<array{normalised:string,variants:array<string,string>}>,
     *   duplicateCount:int,
     *   duplicates:list<array{refNo:string,recordIds:list<string>}>
     * }
     */
    public function summarise(): array
    {
        // (1) Normalisation collisions: same normalised key reached by more than
        // one DISTINCT raw RefNo spelling.
        $out = [];
        foreach ($this->groups as $key => $variants) {
            if (count($variants) > 1) {
                $out[] = [
                    'normalised' => (string) $key,
                    'variants'   => $variants,
                ];
            }
        }
        usort($out, static fn (array $a, array $b): int => count($b['variants']) <=> count($a['variants']));

        // (2) Duplicate RefNo: same exact RefNo carried by two or more records.
        // Report each distinct RecordID (deduplicated, first-seen order) so the
        // cataloguer can open each offending record at source.
        $dupes = [];
        foreach ($this->refNoRecordIds as $refNo => $recordIds) {
            if (count($recordIds) < 2) {
                continue;
            }
            $distinct = [];
            foreach ($recordIds as $id) {
                if (!in_array($id, $distinct, true)) {
                    $distinct[] = $id;
                }
            }
            // A genuine duplicate is two or more RECORDS on the same RefNo. Even
            // if their RecordIDs happen to be blank/identical, it is still a
            // legacyId clash, so report it (the RecordID list just may be short).
            $dupes[] = [
                'refNo'     => (string) $refNo,
                'recordIds' => $distinct,
            ];
        }
        // Worst offenders (most records sharing the RefNo) first.
        usort($dupes, static fn (array $a, array $b): int => count($b['recordIds']) <=> count($a['recordIds']));

        return [
            'count'          => count($out),
            'groups'         => $out,
            'duplicateCount' => count($dupes),
            'duplicates'     => $dupes,
        ];
    }
}