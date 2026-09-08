// AtoM Tool — client-side behaviour.
(function () {
    'use strict';

    const table = document.getElementById('files-table');
    const btnRun = document.getElementById('btn-run');
    const btnClear = document.getElementById('btn-clear');
    const runName = document.getElementById('run-name');
    const runPipeline = document.getElementById('run-pipeline');
    const deleteName = document.getElementById('delete-name');

    if (!table || !btnRun || !btnClear || !runName || !runPipeline || !deleteName) {
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
        const name = row ? row.dataset.filename : '';
        const pipeline = pipelineFor(row);

        // Clear: enabled when a row is selected.
        btnClear.disabled = !row;
        deleteName.value = name;

        // Run: enabled when a row is selected AND its pipeline is chosen.
        btnRun.disabled = !row || pipeline === '';
        runName.value = name;
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