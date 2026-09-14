
// AtoM Tool — client-side behaviour.
(function () {
    'use strict';

    const table = document.getElementById('files-table');
    const btnRun = document.getElementById('btn-run');
    const btnClear = document.getElementById('btn-clear');
    const runUploadId = document.getElementById('run-upload-id');
    const runPipeline = document.getElementById('run-pipeline');
    const deleteUploadId = document.getElementById('delete-upload-id');

    if (!table || !btnRun || !btnClear || !runUploadId || !runPipeline || !deleteUploadId) {
        return;
    }

    function selectedRow() {
        const radio = table.querySelector('input[name="selected_file"]:checked');
        return radio ? radio.closest('tr') : null;
    }

    function pipelineFor(row) {
        if (!row) return '';
        const sel = row.querySelector('.pipeline-select');
        return sel ? sel.value : '';
    }

    function refresh() {
        const row = selectedRow();
        const uploadId = row ? row.dataset.uploadId : '';
        const pipeline = pipelineFor(row);

        // Clear: enabled when a row is selected.
        btnClear.disabled = !row;
        deleteUploadId.value = uploadId;

        // Run: enabled when a row is selected AND its pipeline is chosen.
        btnRun.disabled = !row || pipeline === '';
        runUploadId.value = uploadId;
        runPipeline.value = pipeline;
    }

    // Radio change or pipeline change → refresh.
    table.addEventListener('change', function (e) {
        if (!e.target) return;
        if (e.target.name === 'selected_file' || e.target.classList.contains('pipeline-select')) {
            refresh();
        }
    });

    refresh();
})();

// Custom pipeline selector (Amazon AMI style).
document.querySelectorAll('.pipeline-selector').forEach(selector => {
    const trigger = selector.querySelector('.pipeline-selector__trigger');
    const dropdown = selector.querySelector('.pipeline-selector__dropdown');
    const hiddenInput = selector.querySelector('.pipeline-select');
    const options = selector.querySelectorAll('.pipeline-selector__option');

    if (!trigger || !dropdown || !hiddenInput) return;

    // Toggle dropdown
    trigger.addEventListener('click', () => {
        const isHidden = dropdown.hasAttribute('hidden');
        // Close all other open dropdowns first
        document.querySelectorAll('.pipeline-selector__dropdown').forEach(d => {
            if (d !== dropdown) d.setAttribute('hidden', '');
        });
        if (isHidden) {
            dropdown.removeAttribute('hidden');
        } else {
            dropdown.setAttribute('hidden', '');
        }
    });

    // Select option
    options.forEach(option => {
        option.addEventListener('click', () => {
            const value = option.dataset.value;
            const labelEl = option.querySelector('.pipeline-selector__label');
            const metaEl = option.querySelector('.pipeline-selector__meta');

            hiddenInput.value = value;

            // Update trigger to show selected value
            trigger.innerHTML = `
                <div class="pipeline-selector__label">${labelEl.textContent}</div>
                <div class="pipeline-selector__meta">${metaEl.textContent}</div>
            `;

            dropdown.setAttribute('hidden', '');

            // Trigger change event so existing form logic picks it up
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (!selector.contains(e.target)) {
            dropdown.setAttribute('hidden', '');
        }
    });
});

// Preflight panel: hover a row's plane to preview coverage, click to pin.
(function () {
    'use strict';

    const planes = document.querySelectorAll('.preflight-plane');
    const empty = document.getElementById('preflight-empty');
    const content = document.getElementById('preflight-content');
    const arc = document.getElementById('preflight-donut-arc');
    const pctEl = document.getElementById('preflight-pct');
    const summaryEl = document.getElementById('preflight-summary');
    const unmappedEl = document.getElementById('preflight-unmapped');
    const provenanceEl = document.getElementById('preflight-provenance');
    const unpinBtn = document.getElementById('preflight-unpin');
    const reportLink = document.getElementById('preflight-report-link');

    const collisionsBox = document.getElementById('preflight-refno-collisions');
    const collisionsList = document.getElementById('preflight-refno-collisions-list');
    const orphansBox = document.getElementById('preflight-orphans');
    const orphansList = document.getElementById('preflight-orphans-list');
    const datesBox = document.getElementById('preflight-dates');
    const datesList = document.getElementById('preflight-dates-list');
    const levelsBox = document.getElementById('preflight-levels');
    const levelsList = document.getElementById('preflight-levels-list');

    if (!content || !empty || !arc || !pctEl || !summaryEl || !unmappedEl || !provenanceEl) {
        return;
    }

    const R = 52;
    const CIRC = 2 * Math.PI * R; // circumference of the donut circle
    arc.style.strokeDasharray = String(CIRC);
    arc.style.strokeDashoffset = String(CIRC); // start empty

    const ALERT_LIMIT = 20; // max lines shown in any preflight alert box

    let pinnedPlane = null;

    // GO / NO GO is transient: whenever the preflight view changes (a different
    // plane is hovered, pinned, or the panel returns to empty), clear any prior
    // validation verdict so the operator must click "Run AtoM final validation"
    // again for the run now on screen. The lamp IIFE installs this hook.
    function resetGoNoGo() {
        if (typeof window.__resetGoNoGo === 'function') {
            window.__resetGoNoGo();
        }
    }

    function showEmpty() {
        content.setAttribute('hidden', '');
        empty.removeAttribute('hidden');
        if (unpinBtn) unpinBtn.setAttribute('hidden', '');
        if (reportLink) {
            reportLink.setAttribute('hidden', '');
            reportLink.removeAttribute('href');
        }
        resetGoNoGo();
    }

    // Shared renderer for every preflight warning box (collisions, orphans,
    // dates, levels, …). Given the box + its <ul>, an array of already-computed
    // display strings, and the true total, it fills the list (capped), appends
    // "...and N more" when truncated, and shows/hides the box. Adding a new
    // scanner box therefore means: compute its lines, then call this once.
    function renderAlertBox(box, list, lines, total) {
        if (!box || !list) {
            return;
        }
        const count = typeof total === 'number' ? total : lines.length;
        if (count <= 0) {
            box.setAttribute('hidden', '');
            return;
        }

        list.innerHTML = '';
        const shown = lines.slice(0, ALERT_LIMIT);
        shown.forEach(function (text) {
            const li = document.createElement('li');
            li.textContent = text;
            list.appendChild(li);
        });
        if (count > shown.length) {
            const li = document.createElement('li');
            li.textContent = '...and ' + (count - shown.length) + ' more';
            list.appendChild(li);
        }
        box.removeAttribute('hidden');
    }

    // "RefNo - RecordID" locator suffix (RecordID is what lets a cataloguer find
    // the record at source). Returns '' when neither is present.
    function locator(refNo, recordId) {
        if (recordId) {
            return refNo ? refNo + ' - ' + recordId : recordId;
        }
        return refNo || '';
    }

    function render(report) {
        resetGoNoGo();

        const cov = report.coverage || {};
        const pct = typeof cov.percentMapped === 'number' ? cov.percentMapped : 0;
        const present = cov.presentCount ?? 0;
        const mapped = cov.mappedCount ?? 0;
        const records = report.records ?? 0;

        // Donut arc.
        pctEl.textContent = pct.toFixed(1) + '%';
        const offset = CIRC * (1 - Math.max(0, Math.min(100, pct)) / 100);
        arc.style.strokeDashoffset = String(offset);

        // Summary line.
        summaryEl.textContent =
            mapped + ' of ' + present + ' populated fields mapped · ' + records + ' records';

        // Unmapped list (report.unmapped is { name: count }, already ranked).
        unmappedEl.innerHTML = '';
        const unmapped = report.unmapped || {};
        const names = Object.keys(unmapped);
        if (names.length === 0) {
            const li = document.createElement('li');
            li.textContent = 'Nothing populated is unmapped. 🎉';
            unmappedEl.appendChild(li);
        } else {
            names.forEach(function (name) {
                const li = document.createElement('li');
                const nameSpan = document.createElement('span');
                nameSpan.className = 'preflight-unmapped__name';
                nameSpan.textContent = name;
                const countSpan = document.createElement('span');
                countSpan.className = 'preflight-unmapped__count';
                countSpan.textContent = String(unmapped[name]);
                li.appendChild(nameSpan);
                li.appendChild(countSpan);
                unmappedEl.appendChild(li);
            });
        }

        // Provenance footer.
        const m = report.mapping || {};
        const bits = [];
        if (m.version) bits.push('Mapping v' + m.version);
        if (m.versionDate) bits.push(m.versionDate);
        if (m.authorisedBy) bits.push('by ' + m.authorisedBy);
        provenanceEl.textContent = bits.join(' · ');

        // Report download link: point at the stored <runId>.preflight.txt for
        // this run so the operator can grab it for audit / provenance.
        if (reportLink) {
            const runId = report.runId ? String(report.runId) : '';
            if (runId) {
                reportLink.href = '/report?runId=' + encodeURIComponent(runId);
                reportLink.removeAttribute('hidden');
            } else {
                reportLink.setAttribute('hidden', '');
                reportLink.removeAttribute('href');
            }
        }

        // RefNo collision warning (only shown when collisions were found). One
        // list line per raw RefNo variant, as "RefNo - RecordID".
        {
            const col = report.refNoCollisions || {};
            const groups = Array.isArray(col.groups) ? col.groups : [];
            const lines = [];
            let total = 0;
            groups.forEach(function (g) {
                const variants = g && g.variants ? g.variants : {};
                Object.keys(variants).forEach(function (refNo) {
                    total++;
                    const recordId = variants[refNo] || '';
                    lines.push(recordId ? refNo + ' - ' + recordId : refNo);
                });
            });
            renderAlertBox(collisionsBox, collisionsList, lines, total);
        }

        // Orphan warning (parent RefNo missing from this file). Warning only:
        // a partial CALM export legitimately lacks parents that arrive later.
        {
            const orph = report.orphans || {};
            const list = Array.isArray(orph.orphans) ? orph.orphans : [];
            const count = typeof orph.count === 'number' ? orph.count : list.length;
            const lines = list.map(function (o) {
                // "childRefNo → parentRefNo - RecordID"
                const refNo = o && o.refNo ? o.refNo : '';
                const parent = o && o.parent ? o.parent : '';
                const recordId = o && o.recordId ? o.recordId : '';
                let text = refNo + ' → ' + parent;
                if (recordId) {
                    text += ' - ' + recordId;
                }
                return text;
            });
            renderAlertBox(orphansBox, orphansList, lines, count);
        }

        // Date values to review. Warning only: populated CALM date values that
        // may not import cleanly to AtoM. Signal-only — nothing shown for empty
        // or clean values. Fix at source in CALM, or accept clean-up in AtoM.
        {
            const dates = report.dates || {};
            const dodgy = dates.dodgy || {};   // { field: [ {value, refNo, recordId} ] }
            const inverted = Array.isArray(dates.inverted) ? dates.inverted : [];
            const partial = Array.isArray(dates.partial) ? dates.partial : [];
            const count = typeof dates.count === 'number'
                ? dates.count
                : (inverted.length + partial.length);

            const lines = [];
            // Dodgy content, grouped per field: "Field: value  (RefNo - RecordID)".
            Object.keys(dodgy).forEach(function (field) {
                const entries = Array.isArray(dodgy[field]) ? dodgy[field] : [];
                entries.forEach(function (e) {
                    const value = e && e.value ? e.value : '';
                    const loc = locator(e && e.refNo, e && e.recordId);
                    lines.push(field + ': ' + value + (loc ? '  (' + loc + ')' : ''));
                });
            });
            // Range inversion: DateEarliest after DateLatest.
            inverted.forEach(function (e) {
                const earliest = e && e.earliest ? e.earliest : '';
                const latest = e && e.latest ? e.latest : '';
                const loc = locator(e && e.refNo, e && e.recordId);
                lines.push('Inverted range: ' + earliest + ' → ' + latest + (loc ? '  (' + loc + ')' : ''));
            });
            // Partial range: one of DateEarliest / DateLatest missing.
            partial.forEach(function (e) {
                const have = e && e.have ? e.have : '';
                const missing = e && e.missing ? e.missing : '';
                const loc = locator(e && e.refNo, e && e.recordId);
                lines.push('Partial range: have ' + have + ', missing ' + missing + (loc ? '  (' + loc + ')' : ''));
            });

            renderAlertBox(datesBox, datesList, lines, count);
        }

        // Levels to review. Warning only: CALM Level values not in AtoM's default
        // taxonomy — each is a candidate new term to add in AtoM, or a typo to fix
        // at source. Signal-only: nothing shown when every Level matches.
        {
            const levels = report.levels || {};
            const candidates = Array.isArray(levels.candidates) ? levels.candidates : [];
            const count = typeof levels.count === 'number' ? levels.count : candidates.length;
            const lines = candidates.map(function (c) {
                // "value ×N (casing differs)  (RefNo - RecordID)"
                const value = c && c.value ? c.value : '';
                const n = c && typeof c.count === 'number' ? c.count : 0;
                const casingOnly = !!(c && c.casingOnly);
                let text = value;
                if (n > 0) {
                    text += ' ×' + n;
                }
                if (casingOnly) {
                    text += ' (casing differs)';
                }
                const loc = locator(c && c.refNo, c && c.recordId);
                if (loc) {
                    text += '  (' + loc + ')';
                }
                return text;
            });
            renderAlertBox(levelsBox, levelsList, lines, count);
        }

        empty.setAttribute('hidden', '');
        content.removeAttribute('hidden');
    }

    function parseReport(plane) {
        try {
            return JSON.parse(plane.getAttribute('data-report') || '');
        } catch (e) {
            return null;
        }
    }

    planes.forEach(function (plane) {
        plane.addEventListener('mouseenter', function () {
            if (pinnedPlane) return; // don't override a pinned view on hover
            const report = parseReport(plane);
            if (report) render(report);
        });

        plane.addEventListener('mouseleave', function () {
            if (pinnedPlane) return;
            showEmpty();
        });

        plane.addEventListener('click', function () {
            const report = parseReport(plane);
            if (!report) return;

            if (pinnedPlane === plane) {
                // Clicking the pinned plane again unpins.
                pinnedPlane.classList.remove('is-active');
                pinnedPlane = null;
                showEmpty();
                return;
            }

            if (pinnedPlane) pinnedPlane.classList.remove('is-active');
            pinnedPlane = plane;
            plane.classList.add('is-active');
            render(report);
            if (unpinBtn) unpinBtn.removeAttribute('hidden');
        });
    });

    if (unpinBtn) {
        unpinBtn.addEventListener('click', function () {
            if (pinnedPlane) pinnedPlane.classList.remove('is-active');
            pinnedPlane = null;
            showEmpty();
        });
    }
})();

// Mapping diagram overlay: rail icon opens the current mapping.php diagram
// for that pipeline. Rail stays clickable, so you can switch pipelines while
// the overlay is open. Close via the X or by clicking the backdrop.
(function () {
    'use strict';

    const overlay = document.getElementById('diagram-overlay');
    const image = document.getElementById('diagram-image');
    const closeBtn = document.getElementById('diagram-close');
    const openers = document.querySelectorAll('.js-diagram-open');

    if (!overlay || !image) {
        return;
    }

    function open(pipeline) {
        // Cache-bust so an edited mapping.php shows immediately.
        image.src = '/diagram?pipeline=' + encodeURIComponent(pipeline) + '&t=' + Date.now();
        overlay.removeAttribute('hidden');
    }

    function close() {
        overlay.setAttribute('hidden', '');
        image.src = '';
    }

    openers.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const pipeline = btn.getAttribute('data-pipeline') || '';
            if (pipeline) open(pipeline);
        });
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', close);
    }

    // Click the dimmed backdrop (but not the card) to close.
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
    });

    // Esc closes too.
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !overlay.hasAttribute('hidden')) close();
    });
})();

// AtoM GO / NO GO final validation.
// Fully transient: the verdict is never retained. Any change to the preflight
// view (hover / pin a different plane, or the panel returning to empty) clears
// the lamps via window.__resetGoNoGo, so the operator must click the button
// again for whatever run is currently on screen. The run to validate is read at
// click time from the pinned plane (if any), else the selected table row's
// plane — never a cached value.
(function () {
    'use strict';

    const runBtn = document.getElementById('gonogo-run');
    const goLight = document.getElementById('gonogo-go');
    const noGoLight = document.getElementById('gonogo-nogo');

    if (!runBtn || !goLight || !noGoLight) {
        return;
    }

    function reset() {
        goLight.classList.remove('is-lit');
        noGoLight.classList.remove('is-lit');
        goLight.title = '';
        noGoLight.title = '';
        runBtn.classList.remove('is-busy');
    }

    // Let the preflight panel clear our verdict when its view changes.
    window.__resetGoNoGo = reset;

    // Read the report for the run the user is actually looking at:
    //   1) the pinned plane (is-active), if any;
    //   2) otherwise the selected table row's plane.
    function currentReport() {
        let plane = document.querySelector('.preflight-plane.is-active');

        if (!plane) {
            const radio = document.querySelector('input[name="selected_file"]:checked');
            const row = radio ? radio.closest('tr') : null;
            plane = row ? row.querySelector('.preflight-plane') : null;
        }

        if (!plane) {
            return null;
        }
        try {
            return JSON.parse(plane.getAttribute('data-report') || '');
        } catch (e) {
            return null;
        }
    }

    runBtn.addEventListener('click', function () {
        reset();

        const report = currentReport();
        const runId = report && report.runId ? report.runId : '';
        if (!runId) {
            noGoLight.classList.add('is-lit');
            noGoLight.title = 'Select a run first: choose a row (or pin its plane) that has been run.';
            return;
        }

        runBtn.classList.add('is-busy');

        const body = new URLSearchParams();
        body.set('runId', runId);

        fetch('/gonogo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                runBtn.classList.remove('is-busy');

                const when = new Date().toLocaleTimeString();

                if (data && data.ok) {
                    goLight.classList.add('is-lit');

                    // Prove it ran: show the full per-check report (each check's
                    // title + [INFO] status) plus a pass summary and timestamp.
                    const lines = [];
                    lines.push('GO — AtoM structural validation passed.');
                    lines.push('Checked at ' + when + ' · run ' + runId.slice(0, 8) + '…');
                    lines.push('Warnings: ' + (data.warnCount ?? 0) + ' · Errors: ' + (data.errorCount ?? 0));
                    if (data.text) {
                        lines.push('');
                        lines.push('Checks run:');
                        lines.push(data.text);
                    }
                    goLight.title = lines.join('\n');
                } else {
                    noGoLight.classList.add('is-lit');

                    const errs = (data && Array.isArray(data.errors)) ? data.errors : [];
                    const lines = [];
                    lines.push('NO GO — AtoM structural validation failed.');
                    lines.push('Checked at ' + when + ' · run ' + runId.slice(0, 8) + '…');
                    lines.push('Warnings: ' + (data.warnCount ?? 0) + ' · Errors: ' + (data.errorCount ?? 0));
                    if (errs.length) {
                        lines.push('');
                        lines.push(errs.join('\n'));
                    }
                    noGoLight.title = lines.join('\n');
                }
            })
            .catch(function () {
                runBtn.classList.remove('is-busy');
                noGoLight.classList.add('is-lit');
                noGoLight.title = 'NO GO: request failed.';
            });
    });
})();