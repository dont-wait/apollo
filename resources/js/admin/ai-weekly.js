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
                } else if (this.listing.page === 1 && !this.listing.filtered) {
                    this.listing.records = [result.state, ...this.listing.records].slice(0, 30);
                    this.listing.total++;
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

    typeLabel(type) {
        return { COLLECT: 'Thu thập nguồn', ANALYZE: 'Phân tích bài viết', GENERATE_REVIEW: 'Tạo review' }[type] ?? type;
    },

    statusLabel(status) {
        return { PENDING: 'Đang chờ', RUNNING: 'Đang chạy', SUCCESS: 'Hoàn tất', PARTIAL: 'Có lỗi một phần', FAILED: 'Thất bại' }[status] ?? status;
    },
});
