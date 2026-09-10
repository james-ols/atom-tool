<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Renders a customer pipeline's field mapping as a static SVG diagram.
 *
 * This is a live picture of mapping.php as it stands right now: "if this
 * pipeline were run this moment, here is what maps to what." It is NOT tied to
 * any upload or run — no coverage, no danglers. Left column = CALM source
 * elements; right column = AtoM target columns; smooth Bézier connectors show
 * the mapping, with many-to-one fan-in where several sources feed one column.
 *
 * Fixed 1280 WIDTH; data-driven HEIGHT (rows laid out at a fixed pitch, never
 * compressed). The browser fills the panel width and scrolls vertically for
 * long field lists. Pure string building — no image library, no fonts on disk.
 */
final class MappingDiagram
{
    private const W = 1280;

    private const TITLE_Y = 38;
    private const TOP = 100;          // first row centre
    private const ROW_PITCH = 34;    // fixed vertical spacing per field
    private const BOTTOM_MARGIN = 30;

    // Narrower columns leave a wide central gutter for the curves.
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
     * @param array<string,string> $fields  CALM element => AtoM column
     */
    public function render(array $fields, string $pipelineLabel, string $version = '', string $versionDate = '', string $authorisedBy = ''): string
    {
        // Left = source element names (in mapping order). Right = distinct
        // target columns, first-seen order.
        $sources = array_keys($fields);
        $targets = [];
        foreach ($fields as $target) {
            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        $leftY = $this->distribute(count($sources));
        $rightY = $this->distribute(count($targets));

        // Canvas height driven by the taller column (never compress rows).
        $rows = max(count($sources), count($targets));
        $height = self::TOP + (max(0, $rows - 1) * self::ROW_PITCH) + self::BOX_H + self::BOTTOM_MARGIN;
        if ($height < 400) {
            $height = 400; // sensible floor for tiny mappings
        }

        // Index target -> y for connector routing.
        $targetY = [];
        foreach ($targets as $i => $t) {
            $targetY[$t] = $rightY[$i];
        }

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
        // Control-point horizontal offset: push out ~45% of the gutter so the
        // curve leaves/enters each box horizontally and bows gently through
        // the middle.
        $ctrl = (int) round(($x2 - $x1) * 0.45);

        foreach ($fields as $source => $target) {
            $si = array_search($source, $sources, true);
            if ($si === false || !isset($targetY[$target])) {
                continue;
            }
            $y1 = $leftY[$si];
            $y2 = $targetY[$target];

            // Smooth cubic Bézier: horizontal tangents at both ends.
            $svg[] = sprintf(
                '<path d="M %d %d C %d %d, %d %d, %d %d" fill="none" stroke="%s" stroke-width="1.5" opacity="0.85"/>',
                $x1, $y1,
                $x1 + $ctrl, $y1,
                $x2 - $ctrl, $y2,
                $x2, $y2,
                self::ORANGE
            );
            // Arrow head into the target (pointing right).
            $svg[] = sprintf(
                '<path d="M %d %d l -7 -4 l 0 8 z" fill="%s"/>',
                $x2, $y2, self::ORANGE
            );
        }

        // Left boxes (sources).
        foreach ($sources as $i => $name) {
            $svg[] = $this->box(self::LEFT_X, $leftY[$i], self::COL_W, $name, self::NAVY, self::INK, 'left');
        }
        // Right boxes (targets).
        foreach ($targets as $i => $name) {
            $svg[] = $this->box(self::RIGHT_X, $rightY[$i], self::COL_W, $name, self::ORANGE, self::INK, 'right');
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Place N row centres at a fixed pitch from TOP downward (never compressed).
     *
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

    private function box(int $x, int $cy, int $w, string $label, string $stroke, string $textColor, string $align): string
    {
        $y = $cy - (int) (self::BOX_H / 2);
        $rect = sprintf(
            '<rect x="%d" y="%d" width="%d" height="%d" rx="6" fill="#ffffff" stroke="%s" stroke-width="1.5"/>',
            $x, $y, $w, self::BOX_H, $stroke
        );
        // Left column labels right-aligned (toward the gutter); right column
        // labels left-aligned. Reads naturally toward the arrows.
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