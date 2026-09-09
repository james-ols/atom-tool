<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Accumulates, across a single run, which CALM element names actually carried
 * a non-empty value, then reports coverage against a mapping's source keys.
 *
 * The point of Preflight:
 *   an element is "unmapped" when it is POPULATED in this run (non-empty in at
 *   least one record) AND is not one of the mapping's source keys.
 *
 * Always-empty elements are ignored entirely (no data => no risk of loss).
 * Mapped-but-empty elements never count against coverage.
 *
 * Generic mechanism only: it counts element-name occupancy. No archival or
 * customer knowledge lives here.
 */
final class Coverage
{
    /** @var array<string,int> element name => count of records with a non-empty value */
    private array $populated = [];

    private int $records = 0;

    /**
     * Observe one parsed record (childName => list<string>).
     *
     * @param array<string, list<string>> $record
     */
    public function observe(array $record): void
    {
        $this->records++;
        foreach ($record as $name => $values) {
            foreach ($values as $value) {
                if (trim((string) $value) !== '') {
                    $this->populated[$name] = ($this->populated[$name] ?? 0) + 1;
                    break; // count the element once per record
                }
            }
        }
    }

    public function records(): int
    {
        return $this->records;
    }

    /**
     * Build the coverage summary against the given mapped source keys.
     *
     * @param list<string> $sourceKeys element names the mapping consumes
     * @return array{
     *   records:int,
     *   presentCount:int,
     *   mappedCount:int,
     *   unmappedCount:int,
     *   percentMapped:float,
     *   mapped:list<string>,
     *   unmapped:array<string,int>
     * }
     */
    public function summarise(array $sourceKeys): array
    {
        $mappedSet = array_fill_keys($sourceKeys, true);

        $mapped = [];
        /** @var array<string,int> $unmapped element name => occupancy count */
        $unmapped = [];

        foreach ($this->populated as $name => $count) {
            if (isset($mappedSet[$name])) {
                $mapped[] = $name;
            } else {
                $unmapped[$name] = $count;
            }
        }

        $present = count($this->populated);
        $mappedCount = count($mapped);

        sort($mapped);
        arsort($unmapped); // most-populated unmapped fields first

        return [
            'records'       => $this->records,
            'presentCount'  => $present,
            'mappedCount'   => $mappedCount,
            'unmappedCount' => count($unmapped),
            'percentMapped' => $present > 0 ? round(($mappedCount / $present) * 100, 1) : 100.0,
            'mapped'        => $mapped,
            'unmapped'      => $unmapped,
        ];
    }
}