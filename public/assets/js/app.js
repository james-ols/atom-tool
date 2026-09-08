// AtoM Tool — client-side behaviour.
(function () {
    'use strict';

    const table = document.getElementById('files-table');
    const btnRun = document.getElementById('btn-run');
    const btnClear = document.getElementById('btn-clear');
    const deleteName = document.getElementById('delete-name');

    if (!table || !btnRun || !btnClear || !deleteName) {
        return;
    }

    function selectedRow() {
        const radio = table.querySelector('input[name="selected_file"]:checked');
        return radio ? radio.closest('tr') : null;
    }

    function selectedPipeline(row) {
        if (!row) return '';
        const sel = row.querySelector('.pipeline-select');
        return sel && !sel.disabled ? sel.value : '';
    }

    function refresh() {
        const row = selectedRow();
        const name = row ? row.dataset.filename : '';
        const pipeline = selectedPipeline(row);

        btnClear.disabled = !row;
        deleteName.value = name;

        // Run needs both a selected row and a chosen pipeline.
        // Pipeline dropdown is disabled for now (Step 5c will enable it), so
        // btnRun stays disabled until then.
        btnRun.disabled = !row || pipeline === '' || pipeline === 'Select a pipeline…';
    }

    // Radio selection changes → refresh action buttons.
    table.addEventListener('change', function (e) {
        if (e.target && (e.target.name === 'selected_file' || e.target.classList.contains('pipeline-select'))) {
            refresh();
        }
    });

    refresh();
})();