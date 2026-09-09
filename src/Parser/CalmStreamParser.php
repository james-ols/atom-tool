<?php
declare(strict_types=1);

namespace AtomTool\Parser;

use DOMDocument;
use DOMElement;
use Generator;
use RuntimeException;
use XMLReader;

/**
 * Streams a CALM (DScribe) XML export one record at a time.
 *
 * Memory-safe: uses XMLReader so multi-hundred-MB exports don't load into RAM.
 * Yields one record per <DScribeRecord>, as an associative array of:
 *
 *   childElementName => list<string> of that element's text values
 *
 * Repeated elements (e.g. several <AltRefNo> or <PlaceKey>) therefore appear as
 * multi-entry lists. The engine's Mapping layer decides how to join them.
 *
 * The parser is intentionally "dumb": it does not interpret, clean, or map
 * anything. All meaning is applied later, per customer.
 *
 * Input is a readable stream (e.g. from Storage::openRead), keeping the parser
 * decoupled from where the bytes live.
 */
final class CalmStreamParser
{
    public function __construct(
        private readonly string $recordElement = 'DScribeRecord',
    ) {
    }

    /**
     * @param resource $stream A readable stream of CALM XML.
     * @return Generator<int, array<string, list<string>>>
     */
    public function parse($stream): Generator
    {
        if (!is_resource($stream)) {
            throw new RuntimeException('CalmStreamParser::parse expects a readable stream.');
        }

        // Buffer the stream to a temp file so XMLReader can pull-parse it.
        // (XMLReader::open needs a URI/path; this keeps peak memory bounded to
        // the file on disk rather than the whole DOM.)
        $tmp = tempnam(sys_get_temp_dir(), 'calm_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file for CALM parsing.');
        }

        $to = fopen($tmp, 'wb');
        if ($to === false) {
            @unlink($tmp);
            throw new RuntimeException('Could not open temp file for CALM parsing.');
        }
        try {
            stream_copy_to_stream($stream, $to);
        } finally {
            fclose($to);
        }

        $reader = new XMLReader();
        if (@$reader->open($tmp) === false) {
            @unlink($tmp);
            throw new RuntimeException('Could not open CALM XML for reading.');
        }

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT
                    && $reader->localName === $this->recordElement) {
                    do {
                        yield $this->readRecord($reader);
                    } while ($reader->next($this->recordElement));
                    break;
                }
            }
        } finally {
            $reader->close();
            @unlink($tmp);
        }
    }

    /**
     * Read one record subtree into [childName => list<string>].
     *
     * @return array<string, list<string>>
     */
    private function readRecord(XMLReader $reader): array
    {
        $record = [];

        $node = $reader->expand(new DOMDocument());
        if ($node === false) {
            return $record;
        }

        foreach ($node->childNodes as $child) {
            if ($child->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            /** @var DOMElement $child */
            $name = $child->localName ?? $child->nodeName;
            $record[$name] ??= [];
            $record[$name][] = trim($child->textContent);
        }

        return $record;
    }
}