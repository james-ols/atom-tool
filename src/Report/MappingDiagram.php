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
 * Where an AtoM column is transformed — either by a per-value cleaner
 * (mapping.php 'clean' block) or by a named function on a 'derived' arrow —
 * the flow into it carries a small filled "fn" dot near the arrowhead, a visual
 * cue that a treatment exists on that field. (No function name; the dot's
 * presence is the point.)
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

    // Where the "fn" dot sits along its own flow: a Bézier parameter near the
    // target end (1.0 = on the arrowhead). Keeping it close to the target keeps
    // it on its own line, clear of other flows crossing the middle.
    private const FN_DOT_T = 0.95;

    private const INK = '#1f2937';
    private const MUTED = '#6b7280';
    private const ORANGE = '#f28c28';
    private const NAVY = '#1e3a5f';
    private const BORDER = '#e5e7eb';

    // "fn" dot colours: cleaners (per-value, generic) vs derived (record-level
    // functions). Both are dark enough for the white "fn" label to stay legible.
    private const CLEAN_DOT = '#6b21a8';   // purple
    private const DERIVED_DOT = '#15803d'; // green

    /**
     * @param array<string,string>                                 $fields        CALM element => AtoM column
     * @param list<string>                                         $cleanColumns  AtoM columns that carry a treatment
     * @param list<array{source:string,target:string,via:string}>  $derived       Functioned arrows
     */
    public function render(
        array $fields,
        string $pipelineLabel,
        string $version = '',
        string $versionDate = '',
        string $authorisedBy = '',
        array $cleanColumns = [],
        array $derived = []
    ): string {
        $sources = array_keys($fields);
        $targets = [];
        foreach ($fields as $target) {
            if (!in_array($target, $targets, true)) {
                $targets[] = $target;
            }
        }

        // Derived arrows may introduce sources/targets not present in 'fields'
        // (e.g. parentId). Fold them in so every box has a home.
        foreach ($derived as $arrow) {
            $s = (string) ($arrow['source'] ?? '');
            $t = (string) ($arrow['target'] ?? '');
            if ($s !== '' && !in_array($s, $sources, true)) {
                $sources[] = $s;
            }
            if ($t !== '' && !in_array($t, $targets, true)) {
                $targets[] = $t;
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

            // Treatment marker: a small "fn" dot on this flow, just before the
            // arrowhead (kept off the crowded midpoint). Purple = per-value cleaner.
            if (isset($cleanSet[$target])) {
                [$dotX, $dotY] = $this->pointOnFlow($x1, $y1, $ctrl, $x2, $y2, self::FN_DOT_T);
                $svg[] = $this->fnDot($dotX, $dotY, self::CLEAN_DOT);
            }
        }

        // Derived arrows: an extra flow from source to target, always carrying
        // the "fn" dot (a named function sits on it by definition). Green = derived.
        foreach ($derived as $arrow) {
            $s = (string) ($arrow['source'] ?? '');
            $t = (string) ($arrow['target'] ?? '');
            $si = array_search($s, $sources, true);
            if ($si === false || !isset($targetY[$t])) {
                continue;
            }
            $y1 = $leftY[$si];
            $y2 = $targetY[$t];
            $svg[] = sprintf(
                '<path d="M %d %d C %d %d, %d %d, %d %d" fill="none" stroke="%s" stroke-width="1.5" opacity="0.85"/>',
                $x1, $y1, $x1 + $ctrl, $y1, $x2 - $ctrl, $y2, $x2, $y2, self::ORANGE
            );
            $svg[] = sprintf('<path d="M %d %d l -7 -4 l 0 8 z" fill="%s"/>', $x2, $y2, self::ORANGE);
            [$dotX, $dotY] = $this->pointOnFlow($x1, $y1, $ctrl, $x2, $y2, self::FN_DOT_T);
            $svg[] = $this->fnDot($dotX, $dotY, self::DERIVED_DOT);
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
     * Evaluate the flow's cubic Bézier at parameter $t (0 = source, 1 = target)
     * and return an integer [x, y] point that lies ON the curve. The flow uses
     * horizontal tangents, so control points are (x1+ctrl, y1) and (x2-ctrl, y2).
     *
     * @return array{int,int}
     */
    private function pointOnFlow(int $x1, int $y1, int $ctrl, int $x2, int $y2, float $t): array
    {
        $c1x = $x1 + $ctrl;
        $c2x = $x2 - $ctrl;
        $mt = 1.0 - $t;

        $b0 = $mt * $mt * $mt;
        $b1 = 3 * $mt * $mt * $t;
        $b2 = 3 * $mt * $t * $t;
        $b3 = $t * $t * $t;

        $x = $b0 * $x1 + $b1 * $c1x + $b2 * $c2x + $b3 * $x2;
        $y = $b0 * $y1 + $b1 * $y1  + $b2 * $y2  + $b3 * $y2;

        return [(int) round($x), (int) round($y)];
    }

    /**
     * A small filled navy circle with white "fn" — marks that a treatment
     * (cleaner/function) sits on this flow.
     */
    private function fnDot(int $cx, int $cy, string $fill = self::NAVY): string
    {
        $circle = sprintf(
            '<circle cx="%d" cy="%d" r="9" fill="%s"/>',
            $cx, $cy, $fill
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