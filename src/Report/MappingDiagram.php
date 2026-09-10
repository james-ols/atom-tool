<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Renders a customer pipeline's field mapping as a static SVG diagram.
 *
 * Live picture of mapping.php as it stands right now. Left column = CALM source
 * elements; right column = AtoM target columns; smooth Bézier connectors show
 * the mapping, with many-to-one fan-in where several sources feed one column.
 *
 * Where an AtoM column is transformed (mapping.php 'clean' block), the flow
 * into it carries a small filled "fn" dot at its midpoint — a visual cue that a
 * treatment exists on that field, to be discussed with the client. (No function
 * name; the dot's presence is the point.)
 *
 * Fixed 1280 WIDTH; data-driven HEIGHT (rows at a fixed pitch, never
 * compressed). Pure string building — no image library, no fonts on disk.
 */
final class MappingDiagram
{
    private const W = 1280;

    private const TITLE_Y = 38;
    private const TOP = 100;          // first row centre
    private const ROW_PITCH = 34;     // fixed vertical spacing per field
    private const BOTTOM_MARGIN = 30;

    private const LEFT_X = 60;
    private const COL_W = 300;
    private const RIGHT_X = 920;

    private const BOX_H = 26;

    private const INK = '#1f2937';
    private const MUTED = '#6b7280';
    private const ORANGE = '#f28c28';
    private const NAVY = '#1e3a5f';
    private const BORDER = '#e5e7eb';

    /**
     * @param array<string,string> $fields        CALM element => AtoM column
     * @param list<string>         $cleanColumns  AtoM columns that carry a treatment
     */
    public function render(
        array $fields,
        string $pipelineLabel,
        string $version = '',
        string $versionDate = '',
        string $authorisedBy = '',
        array $cleanColumns = []
    ): string {
        $sources = array_keys($fields);
        $targets = [];
        foreach ($fields as $target) {
            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        $leftY = $this->distribute(count($sources));
        $rightY = $this->distribute(count($targets));

        $rows = max(count($sources), count($targets));
        $height = self::TOP + (max(0, $rows - 1) * self::ROW_PITCH) + self::BOX_H + self::BOTTOM_MARGIN;
        if ($height < 400) {
            $height = 400;
        }

        $targetY = [];
        foreach ($targets as $i => $t) {
            $targetY[$t] = $rightY[$i];
        }

        $cleanSet = array_fill_keys($cleanColumns, true);

        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" font-family="Inter, system-ui, -apple-system, sans-serif">',
            self::W, $height, self::W, $height
        );
        $svg[] = sprintf('<rect x="0" y="0" width="%d" height="%d" fill="#ffffff"/>', self::W, $height);

        // Title + column headers.
        $svg[] = sprintf(
            '<text x="%d" y="%d" font-size="18" font-weight="700" fill="%s">%s mapping</text>',
            self::LEFT_X, self::TITLE_Y, self::INK, $this->esc($pipelineLabel)
        );
        $prov = trim(($version !== '' ? 'v' . $version : '') . ($versionDate !== '' ? ' · ' . $versionDate : '') . ($authorisedBy !== '' ? ' · ' . $authorisedBy : ''), ' ·');
        if ($prov !== '') {
            $svg[] = sprintf(
                '<text x="%d" y="%d" font-size="12" fill="%s">%s</text>',
                self::RIGHT_X, self::TITLE_Y, self::MUTED, $this->esc($prov)
            );
        }
        $svg[] = sprintf('<text x="%d" y="%d" font-size="11" font-weight="600" fill="%s" letter-spacing="0.05em">CALM SOURCE</text>', self::LEFT_X, 58, self::MUTED);
        $svg[] = sprintf('<text x="%d" y="%d" font-size="11" font-weight="600" fill="%s" letter-spacing="0.05em">AtoM COLUMN</text>', self::RIGHT_X, 58, self::MUTED);

        // Connectors first (so boxes sit on top of line ends).
        $x1 = self::LEFT_X + self::COL_W;   // right edge of left box
        $x2 = self::RIGHT_X;                // left edge of right box
        $ctrl = (int) round(($x2 - $x1) * 0.45);
        $midX = (int) round(($x1 + $x2) / 2);

        foreach ($fields as $source => $target) {
            $si = array_search($source, $sources, true);
            if ($si === false || !isset($targetY[$target])) {
                continue;
            }
            $y1 = $leftY[$si];
            $y2 = $targetY[$target];

            // Single smooth Bézier, horizontal tangents at both ends.
            $svg[] = sprintf(
                '<path d="M %d %d C %d %d, %d %d, %d %d" fill="none" stroke="%s" stroke-width="1.5" opacity="0.85"/>',
                $x1, $y1,
                $x1 + $ctrl, $y1,
                $x2 - $ctrl, $y2,
                $x2, $y2,
                self::ORANGE
            );
            // Arrow head into the target.
            $svg[] = sprintf(
                '<path d="M %d %d l -7 -4 l 0 8 z" fill="%s"/>',
                $x2, $y2, self::ORANGE
            );

            // Treatment marker: a small filled "fn" dot at the flow midpoint.
            if (isset($cleanSet[$target])) {
                $midY = (int) round(($y1 + $y2) / 2);
                $svg[] = $this->fnDot($midX, $midY);
            }
        }

        // Left boxes (sources) — CALM, navy outline.
        foreach ($sources as $i => $name) {
            $svg[] = $this->box(self::LEFT_X, $leftY[$i], self::COL_W, $name, self::NAVY, self::INK, 'left');
        }
        // Right boxes (targets) — AtoM, orange outline.
        foreach ($targets as $i => $name) {
            $svg[] = $this->box(self::RIGHT_X, $rightY[$i], self::COL_W, $name, self::ORANGE, self::INK, 'right');
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * @return list<int>
     */
    private function distribute(int $n): array
    {
        $ys = [];
        for ($i = 0; $i < $n; $i++) {
            $ys[] = self::TOP + $i * self::ROW_PITCH;
        }
        return $ys;
    }

    /**
     * A small filled navy circle with white "fn" — marks that a treatment
     * (cleaner/function) sits on this flow.
     */
    private function fnDot(int $cx, int $cy): string
    {
        $circle = sprintf(
            '<circle cx="%d" cy="%d" r="9" fill="%s"/>',
            $cx, $cy, self::NAVY
        );
        $text = sprintf(
            '<text x="%d" y="%d" font-size="8" font-weight="700" fill="#ffffff" text-anchor="middle" dominant-baseline="central">fn</text>',
            $cx, $cy
        );
        return $circle . $text;
    }

    private function box(int $x, int $cy, int $w, string $label, string $stroke, string $textColor, string $align): string
    {
        $y = $cy - (int) (self::BOX_H / 2);
        $rect = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="6" fill="#ffffff" stroke="%s" stroke-width="1.5"/>',
            $x, $y, $w, self::BOX_H, $stroke
        );
        if ($align === 'left') {
            $tx = $x + $w - 10;
            $anchor = 'end';
        } else {
            $tx = $x + 10;
            $anchor = 'start';
        }
        $text = sprintf(
            '<text x="%d" y="%d" font-size="13" fill="%s" text-anchor="%s" dominant-baseline="central">%s</text>',
            $tx, $cy, $textColor, $anchor, $this->esc($label)
        );
        return $rect . $text;
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}