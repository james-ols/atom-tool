<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * One structural-validation check's outcome, mirroring AtoM's per-test result
 * shape (title + status + human-readable lines). Pure value object.
 *
 * Status is one of INFO (passed / nothing to report), WARN (AtoM would import
 * but flag something), or ERROR (AtoM would reject the file / row).
 */
final class ValidationResult
{
    public const INFO = 'info';
    public const WARN = 'warn';
    public const ERROR = 'error';

    /**
     * @param list<string> $results Primary human-readable summary lines.
     * @param list<string> $details Secondary lines (e.g. offending row numbers).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $status,
        public readonly array $results = [],
        public readonly array $details = [],
    ) {
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array{title:string,status:string,results:list<string>,details:list<string>}
     */
    public function toArray(): array
    {
        return [
            'title'   => $this->title,
            'status'  => $this->status,
            'results' => $this->results,
            'details' => $this->details,
        ];
    }
}