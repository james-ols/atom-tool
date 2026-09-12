
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
    const placeholder = selector.querySelector('.pipeline-selector__placeholder');
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

    const collisionsBox = document.getElementById('preflight-refno-collisions');
    const collisionsList = document.getElementById('preflight-refno-collisions-list');

    if (!content || !empty || !arc || !pctEl || !summaryEl || !unmappedEl || !provenanceEl) {
        return;
    }

    const R = 52;
    const CIRC = 2 * Math.PI * R; // circumference of the donut circle
    arc.style.strokeDasharray = String(CIRC);
    arc.style.strokeDashoffset = String(CIRC); // start empty

    let pinnedPlane = null;

    function showEmpty() {
        content.setAttribute('hidden', '');
        empty.removeAttribute('hidden');
        if (unpinBtn) unpinBtn.setAttribute('hidden', '');
    }

    function render(report) {
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
        if (m.authorisedBy) bits.push('approved by ' + m.authorisedBy);
        provenanceEl.textContent = bits.join(' · ');

        // RefNo collision warning (only shown when collisions were found).
        if (collisionsBox && collisionsList) {
            const col = report.refNoCollisions || {};
            const groups = Array.isArray(col.groups) ? col.groups : [];
            const count = typeof col.count === 'number' ? col.count : groups.length;

            if (count > 0) {
                collisionsList.innerHTML = '';
                groups.slice(0, 20).forEach(function (g) {
                    // variants is { rawRefNo: recordId }; show each on its own
                    // line as "RefNo - RecordID" (RecordID is the only value
                    // that lets a cataloguer find the record at source).
                    const variants = g && g.variants ? g.variants : {};
                    Object.keys(variants).forEach(function (refNo) {
                        const recordId = variants[refNo] || '';
                        const li = document.createElement('li');
                        li.textContent = recordId ? refNo + ' - ' + recordId : refNo;
                        collisionsList.appendChild(li);
                    });
                });
                if (groups.length > 20) {
                    const li = document.createElement('li');
                    li.textContent = '…and ' + (groups.length - 20) + ' more';
                    collisionsList.appendChild(li);
                }

                collisionsBox.removeAttribute('hidden');
            } else {
                collisionsBox.setAttribute('hidden', '');
            }
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