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