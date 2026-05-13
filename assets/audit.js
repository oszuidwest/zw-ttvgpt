/** Submit audit filters via navigation and lazy-load diff modals. */

const config = window.zwTTVGPTAudit;
if (!config) {
    console.warn(
        'zw-ttvgpt: audit config missing, audit handlers not attached',
    );
} else {
    attachFilterHandlers();
    attachDiffHandlers();
}

function attachFilterHandlers() {
    const date = document.getElementById('filter-by-date');
    const status = document.getElementById('filter-by-status');
    const change = document.getElementById('filter-by-change');
    const submit = document.getElementById('post-query-submit');

    function applyFilters() {
        const params = new URLSearchParams();
        params.set('page', config.pageSlug);

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

function attachDiffHandlers() {
    const links = document.querySelectorAll('.zw-audit-diff-link');
    const modalTitle = document.getElementById('zw-diff-modal-title');
    const loading = document.getElementById('zw-diff-modal-loading');
    const error = document.getElementById('zw-diff-modal-error');
    const errorText = error?.querySelector('p');
    const grid = document.getElementById('zw-diff-modal-grid');
    const before = document.getElementById('zw-diff-before');
    const after = document.getElementById('zw-diff-after');

    if (
        !links.length ||
        !modalTitle ||
        !loading ||
        !error ||
        !errorText ||
        !grid ||
        !before ||
        !after
    ) {
        return;
    }

    let activeRequest = 0;

    for (const link of links) {
        link.addEventListener('click', async (event) => {
            event.preventDefault();

            const postId = link.dataset.postId;
            if (!postId) {
                return;
            }

            const requestId = activeRequest + 1;
            activeRequest = requestId;

            link.setAttribute('aria-busy', 'true');
            modalTitle.textContent =
                config.strings?.loading ?? 'Verschillen laden...';
            before.textContent = '';
            after.textContent = '';
            setDiffModalState('loading');
            openDiffModal(link.href);

            try {
                const diff = await fetchDiff(postId);
                if (requestId !== activeRequest) {
                    return;
                }

                modalTitle.textContent =
                    typeof diff.title === 'string'
                        ? diff.title
                        : (config.strings?.diffTitle ?? 'Verschillen');
                before.innerHTML =
                    typeof diff.before === 'string' ? diff.before : '';
                after.innerHTML =
                    typeof diff.after === 'string' ? diff.after : '';
                setDiffModalState('ready');
            } catch (fetchError) {
                if (requestId !== activeRequest) {
                    return;
                }

                modalTitle.textContent =
                    config.strings?.diffTitle ?? 'Verschillen';
                errorText.textContent =
                    fetchError instanceof Error
                        ? fetchError.message
                        : (config.strings?.loadError ??
                          'Verschillen konden niet worden geladen.');
                setDiffModalState('error');
            } finally {
                link.removeAttribute('aria-busy');
            }
        });
    }

    function setDiffModalState(state) {
        loading.hidden = state !== 'loading';
        error.hidden = state !== 'error';
        grid.hidden = state !== 'ready';
    }
}

function openDiffModal(href) {
    if (typeof window.tb_show !== 'function') {
        return;
    }

    window.tb_show(
        config.strings?.diffTitle ?? 'Verschillen',
        href || '#TB_inline?width=800&height=600&inlineId=zw-diff-modal',
    );
}

async function fetchDiff(postId) {
    const body = new URLSearchParams();
    body.set('action', config.ajaxAction);
    body.set('nonce', config.nonce);
    body.set('post_id', postId);

    const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body,
    });
    const payload = await response.json().catch(() => null);

    if (!response.ok || !payload?.success) {
        throw new Error(
            payload?.data?.message ??
                config.strings?.requestFailed ??
                'De aanvraag is mislukt.',
        );
    }

    return payload.data ?? {};
}
