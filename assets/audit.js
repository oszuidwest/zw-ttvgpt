/** Submit audit filters via navigation to preserve WP tablenav behavior. */

const config = window.zwTTVGPTAudit;
if (!config) {
    console.warn(
        'zw-ttvgpt: audit config missing, filter handlers not attached',
    );
} else {
    const date = document.getElementById('filter-by-date');
    const status = document.getElementById('filter-by-status');
    const change = document.getElementById('filter-by-change');
    const submit = document.getElementById('post-query-submit');

    function applyFilters() {
        const params = new URLSearchParams();
        params.set('page', 'zw-ttvgpt-audit');

        const dateValue = date?.value ?? '';
        if (dateValue && dateValue !== '0') {
            params.set('year', dateValue.substring(0, 4));
            params.set(
                'month',
                String(parseInt(dateValue.substring(4, 6), 10)),
            );
        }
        if (status?.value) {
            params.set('status', status.value);
        }
        if (change?.value) {
            params.set('change', change.value);
        }

        window.location.href = `${config.baseUrl}?${params.toString()}`;
    }

    for (const el of [date, status, change]) {
        el?.addEventListener('change', applyFilters);
    }
    submit?.addEventListener('click', (e) => {
        e.preventDefault();
        applyFilters();
    });
}
