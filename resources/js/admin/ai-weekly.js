document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-ai-form]');
    if (! form) return;

    event.preventDefault();
    if (form.dataset.submitting === 'true') return;
    form.dataset.submitting = 'true';
    window.dispatchEvent(new CustomEvent('ai-run-submitting', { detail: { type: form.dataset.runType, value: true } }));
    const feedback = form.querySelector('[data-ai-feedback]');
    const button = form.querySelector('[type="submit"]');
    const data = Object.fromEntries(new FormData(form));
    delete data._token;
    button.disabled = true;
    if (feedback) feedback.textContent = 'Đang xử lý...';

    try {
        const response = await fetch(form.action, {
            method: form.dataset.method || form.method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            body: JSON.stringify(data),
        });
        const result = await response.json();
        if (! response.ok) {
            if (result.moderation) {
                window.applyToxicHighlights?.(result.moderation);
            }

            const errors = Object.values(result.errors || {}).flat();
            throw new Error(errors.join(' ') || result.message || 'Thao tác thất bại.');
        }

        if (result.run_id) {
            window.dispatchEvent(new CustomEvent('ai-run-started', { detail: result }));
        }
        if (form.dataset.reload === 'true') {
            window.location.reload();
            return;
        }
        if (feedback) feedback.textContent = result.run_id ? `${result.reused ? 'Job đã được chạy trước đó' : 'Đã tạo job'} #${result.run_id}. Theo dõi tiến trình tại Pipeline.` : 'Đã lưu.';
    } catch (error) {
        if (feedback) feedback.textContent = error.message;
    } finally {
        delete form.dataset.submitting;
        window.dispatchEvent(new CustomEvent('ai-run-submitting', { detail: { type: form.dataset.runType, value: false } }));
        button.disabled = false;
    }
});


window.aiPipeline = (initialRuns, url, initialListing = null) => ({
    listing: initialListing,
    runs: initialRuns,
    submitting: {},
    pollError: false,
    loading: false,
    stopped: false,
    timer: null,
    controller: null,
    revision: 0,

    init() {
        this.schedule();
    },

    destroy() {
        this.stopped = true;
        window.clearTimeout(this.timer);
        this.controller?.abort();
    },

    isActive(run) {
        return ['PENDING', 'RUNNING'].includes(run.status);
    },

    busy(type) {
        return Boolean(this.submitting[type]) || this.runs.some(run => run.run_type === type && this.isActive(run));
    },

    setSubmitting({ type, value }) {
        if (type) this.submitting = { ...this.submitting, [type]: value };
    },

    track(result) {
        this.revision++;
        if (result.state) {
            this.runs = [result.state, ...this.runs.filter(run => run.id !== result.state.id)];
            if (this.listing) {
                const exists = this.listing.records.some(run => run.id === result.state.id);
                if (exists) {
                    this.listing.records = this.listing.records.map(run => run.id === result.state.id ? result.state : run);
                } else if (!result.reused && this.listing.page === 1 && !this.listing.filtered) {
                    this.listing.records = [result.state, ...this.listing.records].slice(0, this.listing.per_page ?? 30);
                    this.listing.total++;
                    this.listing.last_page = Math.max(1, Math.ceil(this.listing.total / (this.listing.per_page ?? 30)));
                }
            }
        }
        this.refresh();
    },

    schedule() {
        window.clearTimeout(this.timer);
        if (!this.stopped) this.timer = window.setTimeout(() => this.refresh(), 3000);
    },

    async refresh() {
        if (this.loading || this.stopped) return;
        window.clearTimeout(this.timer);
        this.loading = true;
        const revision = this.revision;
        this.controller = new AbortController();
        const timeout = window.setTimeout(() => this.controller.abort(), 10000);
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error('Không thể cập nhật tiến trình.');
            const result = await response.json();
            if (!this.stopped && revision === this.revision) {
                this.runs = result.current_runs ?? [result.state];
                if (result.listing) this.listing = result.listing;
                this.pollError = false;
            }
        } catch (error) {
            if (!this.stopped) this.pollError = true;
        } finally {
            window.clearTimeout(timeout);
            this.loading = false;
            this.schedule();
        }
    },

    pageUrl(page) {
        const address = new URL(this.listing.page_url);
        address.searchParams.set('page', String(page));
        return address.href;
    },

    typeLabel(type) {
        return { COLLECT: 'Thu thập nguồn', ANALYZE: 'Phân tích bài viết', GENERATE_REVIEW: 'Tạo review' }[type] ?? type;
    },

    statusLabel(status) {
        return { PENDING: 'Đang chờ', RUNNING: 'Đang chạy', SUCCESS: 'Hoàn tất', PARTIAL: 'Có lỗi một phần', FAILED: 'Thất bại' }[status] ?? status;
    },
});

// Pick-and-analyze: tick a few collected articles, then analyze only those.
const selectionForm = () => document.querySelector('form[data-ai-select-form]');

const refreshSelection = (form) => {
    const boxes = [...form.querySelectorAll('input[name="article_ids[]"]')];
    const checked = boxes.filter((box) => box.checked);
    const max = Number(form.dataset.max);

    form.querySelector('[data-selection-count]').textContent = max > 0 ? `${checked.length} / ${max} selected` : `${checked.length} selected`;
    form.querySelector('[data-selection-submit]').disabled = checked.length === 0 || form.dataset.submitting === 'true';
    form.querySelector('[data-selection-clear]').hidden = checked.length === 0;
    // With a limit (max > 0), the other boxes lock once it is reached until something is unticked.
    boxes.forEach((box) => {
        box.disabled = box.dataset.unavailable === 'true' || (max > 0 && ! box.checked && checked.length >= max);
    });
};

document.addEventListener('change', (event) => {
    const form = event.target.closest?.('form[data-ai-select-form]');

    if (form) refreshSelection(form);
});

document.addEventListener('click', (event) => {
    if (! event.target.closest?.('[data-selection-clear]')) return;

    const form = selectionForm();
    form.querySelectorAll('input[name="article_ids[]"]').forEach((box) => { box.checked = false; });
    refreshSelection(form);
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-ai-select-form]');
    if (! form) return;

    event.preventDefault();
    if (form.dataset.submitting === 'true') return;

    const ids = [...form.querySelectorAll('input[name="article_ids[]"]:checked')].map((box) => Number(box.value));
    if (ids.length === 0) return;

    form.dataset.submitting = 'true';
    refreshSelection(form);
    const feedback = form.querySelector('[data-ai-feedback]');
    feedback.textContent = 'Working...';

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            body: JSON.stringify({ article_ids: ids }),
        });
        const result = await response.json();

        if (! response.ok) {
            throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The request failed.');
        }

        feedback.textContent = `${result.reused ? 'Already queued as job' : 'Created job'} #${result.run_id}. `;
        const link = document.createElement('a');
        link.href = form.dataset.runsUrl;
        link.className = 'underline';
        link.textContent = 'Track it in Pipeline';
        feedback.append(link);
        form.querySelectorAll('input[name="article_ids[]"]').forEach((box) => { box.checked = false; });
    } catch (error) {
        feedback.textContent = error.message;
    } finally {
        delete form.dataset.submitting;
        refreshSelection(form);
    }
});

// "Choose articles to analyze" dialog on the Pipeline page.
window.analyzePicker = ({ listUrl, analyzeUrl, csrf }) => ({
    open: false,
    loading: false,
    submitting: false,
    error: '',
    query: '',
    filter: 'pending',
    items: [],
    nextPage: null,
    total: 0,
    selected: {},
    searchTimer: null,

    get count() {
        return Object.keys(this.selected).length;
    },

    openPicker() {
        this.open = true;
        this.error = '';
        if (this.items.length === 0) this.load(true);
    },

    close() {
        this.open = false;
    },

    search() {
        window.clearTimeout(this.searchTimer);
        this.searchTimer = window.setTimeout(() => this.load(true), 300);
    },

    setFilter(filter) {
        this.filter = filter;
        this.load(true);
    },

    async load(reset = false) {
        if (this.loading) return;
        this.loading = true;
        this.error = '';
        const page = reset ? 1 : this.nextPage;

        try {
            const params = new URLSearchParams({ picker: '1', filter: this.filter, q: this.query, page: String(page) });
            const response = await fetch(`${listUrl}?${params}`, { headers: { Accept: 'application/json' } });
            if (! response.ok) throw new Error('Không tải được danh sách bài.');
            const result = await response.json();
            this.items = reset ? result.data : [...this.items, ...result.data];
            this.nextPage = result.next_page;
            this.total = result.total;
        } catch (error) {
            this.error = error.message;
        } finally {
            this.loading = false;
        }
    },

    isSelected(item) {
        return Object.prototype.hasOwnProperty.call(this.selected, item.id);
    },

    toggle(item) {
        if (! item.selectable) return;
        const next = { ...this.selected };
        if (this.isSelected(item)) delete next[item.id]; else next[item.id] = item.title;
        this.selected = next;
    },

    selectVisible() {
        const next = { ...this.selected };
        this.items.filter((item) => item.selectable).forEach((item) => { next[item.id] = item.title; });
        this.selected = next;
    },

    clearSelection() {
        this.selected = {};
    },

    async send(body) {
        this.submitting = true;
        this.error = '';

        try {
            const response = await fetch(analyzeUrl, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(body),
            });
            const result = await response.json();
            if (! response.ok) {
                throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Thao tác thất bại.');
            }
            window.dispatchEvent(new CustomEvent('ai-run-started', { detail: result }));
            this.selected = {};
            this.items = [];
            this.open = false;
        } catch (error) {
            this.error = error.message;
        } finally {
            this.submitting = false;
        }
    },

    submitSelected() {
        if (this.count === 0) return;
        this.send({ article_ids: Object.keys(this.selected).map(Number) });
    },

    submitAllPending() {
        if (! window.confirm('Phân tích TẤT CẢ bài chưa được phân tích? Số lượng lớn có thể làm mô hình chạy rất lâu.')) return;
        this.send({});
    },
});
