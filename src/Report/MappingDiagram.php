<?php
declare(strict_types=1);

namespace AtomTool\Report;

/**
 * Renders a customer pipeline's field mapping as a static SVG diagram.
 *
 * This is a live picture of mapping.php as it stands right now: "if this
 * pipeline were run this moment, here is what maps to what." It is NOT tied to
 * any upload or run — no coverage, no danglers. Left column = CALM source
 * elements; right column = AtoM target columns; elbow connectors show the
 * mapping, with many-to-one fan-in where several sources feed one column.
 *
 * Fixed 1280x800 canvas (deterministic layout); scales in the browser.
 * Pure string building — no image library, no fonts on disk.
 */
final class MappingDiagram
{
    private const W = 1280;
    private const H = 800;

    private const TITLE_Y = 38;
    private const TOP = 70;      // first row centre
    private const BOTTOM = 770;  // last row centre bound

    private const LEFT_X = 60;
    private const COL_W = 470;
    private const RIGHT_X = 750;

    private const BOX_H = 26;

    private const INK = '#1f2937';
    private const MUTED = '#6b7280';
    private const ORANGE = '#f28c28';
    private const BORDER = '#e5e7eb';
    private const GUTTER_MID = 640; // (LEFT_X+COL_W)=530 .. RIGHT_X=750 → mid ~640

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

        // Index target -> y for connector routing.
        $targetY = [];
        foreach ($targets as $i => $t) {
            $targetY[$t] = $rightY[$i];
        }

        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" font-family="Inter, system-ui, -apple-system, sans-serif">',
            self::W, self::H, self::W, self::H
        );
        $svg[] = sprintf('<rect x="0" y="0" width="%d" height="%d" fill="#ffffff"/>', self::W, self::H);

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
        foreach ($fields as $source => $target) {
            $si = array_search($source, $sources, true);
            if ($si === false || !isset($targetY[$target])) {
                continue;
            }
            $y1 = $leftY[$si];
            $y2 = $targetY[$target];
            $x1 = self::LEFT_X + self::COL_W;      // right edge of left box
            $x2 = self::RIGHT_X;                   // left edge of right box
            $mid = self::GUTTER_MID;

            // Orthogonal elbow: out, along gutter, in.
            $svg[] = sprintf(
                '<path d="M %d %d H %d V %d H %d" fill="none" stroke="%s" stroke-width="1.5" opacity="0.85"/>',
                $x1, $y1, $mid, $y2, $x2, self::ORANGE
            );
            // Little arrow head into the target.
            $svg[] = sprintf(
                '<path d="M %d %d l -6 -4 l 0 8 z" fill="%s"/>',
                $x2, $y2, self::ORANGE
            );
        }

        // Left boxes (sources).
        foreach ($sources as $i => $name) {
            $svg[] = $this->box(self::LEFT_X, $leftY[$i], self::COL_W, $name, self::ORANGE, self::INK, 'left');
        }
        // Right boxes (targets).
        foreach ($targets as $i => $name) {
            $svg[] = $this->box(self::RIGHT_X, $rightY[$i], self::COL_W, $name, self::BORDER, self::INK, 'right');
        }

        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * Evenly distribute N row centres between TOP and BOTTOM.
     *
     * @return list<int>
     */
    private function distribute(int $n): array
    {
        if ($n <= 0) {
            return [];
        }
        if ($n === 1) {
            return [(int) ((self::TOP + self::BOTTOM) / 2)];
        }
        $span = self::BOTTOM - self::TOP;
        $step = $span / ($n - 1);
        // If rows would overlap, cap the step to BOX_H + gap and top-align.
        $minPitch = self::BOX_H + 8;
        if ($step < $minPitch) {
            $step = $minPitch;
        }
        $ys = [];
        for ($i = 0; $i < $n; $i++) {
            $ys[] = (int) round(self::TOP + $i * $step);
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
