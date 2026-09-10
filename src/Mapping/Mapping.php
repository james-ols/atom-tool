<?php
declare(strict_types=1);

namespace AtomTool\Mapping;

use RuntimeException;

/**
 * Applies a single customer pipeline block (from the customer's mapping.php)
 * to parsed CALM records, producing rows keyed by AtoM CSV column.
 *
 * The engine stays generic: it knows how to apply 'fields', run per-field
 * 'clean' closures, and call named record-level 'functions'. All archival
 * judgement lives in the customer's mapping.php.
 *
 * Field rules:
 *   - 1:1 and many-to-one (several CALM elements -> one AtoM column).
 *   - Repeated CALM elements are collected.
 *   - Each individual value is cleaned (if a cleaner exists for the column)
 *     BEFORE joining, then non-empty values are joined with '|'.
 *
 * 'derived' columns are produced by running a CALM source through a named
 * 'functions' entry and writing the result to a target column. Any number of
 * derived arrows may be declared, each naming its own source, target and
 * function; the engine treats them uniformly.
 */
final class Mapping
{
    /**
     * @param array<string,string>                                 $fields    CALM element => AtoM column
     * @param list<array{source:string,target:string,via:string}>  $derived   Functioned arrows
     * @param array<string,callable>                               $clean     AtoM column => fn(string): string
     * @param array<string,callable>                               $functions name => callable
     */
    private function __construct(
        private readonly string $version,
        private readonly string $versionDate,
        private readonly string $authorisedBy,
        private readonly array $fields,
        private readonly array $derived,
        private readonly array $clean,
        private readonly array $functions,
    ) {
    }

    /**
     * Build from a customer mapping.php array for a given pipeline key.
     *
     * @param array<string,mixed> $allPipelines The full return value of mapping.php
     */
    public static function fromCustomerMapping(array $allPipelines, string $pipeline): self
    {
        if (!isset($allPipelines[$pipeline]) || !is_array($allPipelines[$pipeline])) {
            throw new RuntimeException("No mapping block for pipeline '{$pipeline}'.");
        }
        $block = $allPipelines[$pipeline];

        $fields = $block['fields'] ?? [];
        if (!is_array($fields) || $fields === []) {
            throw new RuntimeException("Pipeline '{$pipeline}' has no 'fields' map.");
        }

        return new self(
            version:      (string) ($block['version'] ?? ''),
            versionDate:  (string) ($block['versionDate'] ?? ''),
            authorisedBy: (string) ($block['authorisedBy'] ?? ''),
            fields:       $fields,
            derived:      is_array($block['derived'] ?? null) ? $block['derived'] : [],
            clean:        is_array($block['clean'] ?? null) ? $block['clean'] : [],
            functions:    is_array($block['functions'] ?? null) ? $block['functions'] : [],
        );
    }

    public function version(): string
    {
        return $this->version;
    }

    public function versionDate(): string
    {
        return $this->versionDate;
    }

    public function authorisedBy(): string
    {
        return $this->authorisedBy;
    }

    /**
     * The set of CALM source element names this mapping consumes: every source
     * named in 'fields', plus every 'derived' source. Used by Preflight to
     * decide which populated elements are "unmapped".
     *
     * @return list<string>
     */
    public function sourceKeys(): array
    {
        $keys = array_keys($this->fields);
        foreach ($this->derived as $arrow) {
            $source = (string) ($arrow['source'] ?? '');
            if ($source !== '' && !in_array($source, $keys, true)) {
                $keys[] = $source;
            }
        }
        return $keys;
    }

    /**
     * Map one parsed CALM record (childName => list<string>) to an AtoM row
     * (column => string value).
     *
     * @param array<string, list<string>> $record
     * @return array<string, string>
     */
    public function mapRecord(array $record): array
    {
        $row = [];

        // Apply the field map. Multiple CALM elements can target the same AtoM
        // column; collect all their (cleaned, non-empty) values, then join.
        /** @var array<string, list<string>> $collected */
        $collected = [];

        foreach ($this->fields as $calmElement => $atomColumn) {
            $values = $record[$calmElement] ?? [];
            foreach ($values as $value) {
                $value = $this->cleanValue($atomColumn, (string) $value);
                if ($value !== '') {
                    $collected[$atomColumn][] = $value;
                }
            }
        }

        foreach ($collected as $atomColumn => $values) {
            $row[$atomColumn] = implode('|', $values);
        }

        // Derived columns: run each declared source through its named function
        // and write the result to the declared target. Fully data-driven — the
        // engine has no knowledge of any particular derived column.
        $flat = null;
        foreach ($this->derived as $arrow) {
            $via = (string) ($arrow['via'] ?? '');
            $target = (string) ($arrow['target'] ?? '');
            if ($via === '' || $target === '' || !isset($this->functions[$via]) || !is_callable($this->functions[$via])) {
                continue;
            }
            $flat ??= $this->flattenForFunction($record);
            $result = ($this->functions[$via])($flat);
            if (is_string($result) && $result !== '') {
                $row[$target] = $result;
            }
        }

        return $row;
    }

    /**
     * Apply the per-field cleaner (if configured) to a single value.
     */
    private function cleanValue(string $atomColumn, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (isset($this->clean[$atomColumn]) && is_callable($this->clean[$atomColumn])) {
            $value = (string) ($this->clean[$atomColumn])($value);
        }
        return $value;
    }

    /**
     * Provide record-level functions with a convenient flattened view: each
     * CALM element as a single string (first value), while still passing the
     * full multi-value record under '_raw' for advanced needs.
     *
     * @param array<string, list<string>> $record
     * @return array<string, mixed>
     */
    private function flattenForFunction(array $record): array
    {
        $flat = [];
        foreach ($record as $name => $values) {
            $flat[$name] = $values[0] ?? '';
        }
        $flat['_raw'] = $record;
        return $flat;
    }
}