import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../resources/js/admin/ai-weekly.js', import.meta.url), 'utf8');
const activeRun = (id = 1, status = 'RUNNING') => ({ id, status, run_type: 'ANALYZE' });
const result = runs => ({ ok: true, json: async () => ({ current_runs: runs }) });

function createMonitor(fetch, initial = [activeRun()], listing = null) {
    const listeners = {};
    const timers = new Map();
    let timerId = 0;
    const window = {
        setTimeout(callback) { const id = ++timerId; timers.set(id, callback); return id; },
        clearTimeout(id) { timers.delete(id); },
        dispatchEvent() {},
    };
    runInNewContext(source, {
        window, document: { addEventListener: (name, callback) => { listeners[name] = callback; } },
        fetch, AbortController, URL,
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        FormData: class { constructor(form) { return Object.entries(form.fields); } },
    });
    return { monitor: window.aiPipeline(initial, '/admin/automation-runs', listing), window, timers, listeners };
}

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });
    return { promise, resolve };
}

test('polling releases the action only after the job reaches a terminal status', async () => {
    const { monitor } = createMonitor(async () => result([activeRun(1, 'SUCCESS')]));
    assert.equal(monitor.busy('ANALYZE'), true);
    assert.equal(monitor.busy('COLLECT'), false);

    await monitor.refresh();

    assert.equal(monitor.runs[0].status, 'SUCCESS');
    assert.equal(monitor.busy('ANALYZE'), false);
    monitor.setSubmitting({ type: 'ANALYZE', value: true });
    assert.equal(monitor.busy('ANALYZE'), true);
});

test('starting a run immediately tracks it and ignores an older polling response', async () => {
    const request = deferred();
    const { monitor } = createMonitor(() => request.promise, []);
    const polling = monitor.refresh();
    monitor.track({ state: activeRun(2, 'PENDING') });

    request.resolve(result([]));
    await polling;

    assert.equal(monitor.runs[0].id, 2);
    assert.equal(monitor.busy('ANALYZE'), true);
});

test('failed polling preserves progress and the busy action, then retries', async () => {
    let offline = true;
    const { monitor, timers } = createMonitor(async () => {
        if (offline) throw new Error('offline');
        return result([activeRun(1, 'PARTIAL')]);
    });

    await monitor.refresh();
    assert.equal(monitor.pollError, true);
    assert.equal(monitor.busy('ANALYZE'), true);
    assert.equal(timers.size, 1);
    offline = false;
    await monitor.refresh();
    assert.equal(monitor.pollError, false);
    assert.equal(monitor.busy('ANALYZE'), false);
});

test('polling does not overlap and stops after component destruction', async () => {
    const request = deferred();
    let calls = 0;
    let signal;
    const { monitor, timers } = createMonitor((url, options) => {
        calls++;
        signal = options.signal;
        return request.promise;
    });
    const polling = monitor.refresh();
    await monitor.refresh();
    assert.equal(calls, 1);

    monitor.destroy();
    request.resolve(result([activeRun(1, 'SUCCESS')]));
    await polling;
    await monitor.refresh();

    assert.equal(signal.aborted, true);
    assert.equal(timers.size, 0);
    assert.equal(monitor.runs[0].status, 'RUNNING');
    assert.equal(calls, 1);
});

test('detail polling reads the single run state', async () => {
    const { monitor } = createMonitor(async () => ({ ok: true, json: async () => ({ state: activeRun(1, 'FAILED') }) }));

    await monitor.refresh();

    assert.equal(monitor.runs.length, 1);
    assert.equal(monitor.runs[0].status, 'FAILED');
});

test('repeated submit events send only one request while the first is pending', async () => {
    const request = deferred();
    let calls = 0;
    const { listeners } = createMonitor(() => { calls++; return request.promise; });
    const button = {};
    const feedback = {};
    const form = {
        dataset: { runType: 'ANALYZE' }, fields: { _token: 'test' }, action: '/admin/automation-runs/analyze', method: 'post',
        querySelector: selector => selector === '[type="submit"]' ? button : selector === '[data-ai-feedback]' ? feedback : { value: 'test' },
    };
    const event = { target: { closest: () => form }, preventDefault() {} };
    const pending = listeners.submit(event);
    await listeners.submit(event);
    assert.equal(calls, 1);
    assert.equal(button.disabled, true);
    request.resolve({ ok: true, json: async () => ({ run_id: 1, reused: true }) });
    await pending;
    assert.match(feedback.textContent, /Job đã được chạy trước đó/);
    assert.equal(form.dataset.submitting, undefined);
});


test('polling updates progress on paginated records separately from global active jobs', async () => {
    const oldRun = activeRun(2, 'SUCCESS');
    oldRun.progress = { completed: 3, total: 3, percent: 100 };
    const listing = { records: [oldRun], total: 31, page: 2, filtered: true };
    const { monitor } = createMonitor(async () => ({ ok: true, json: async () => ({ current_runs: [activeRun(9)], listing }) }), [], { records: [], total: 0, page: 2, filtered: true });

    await monitor.refresh();

    assert.equal(monitor.listing.records[0].id, 2);
    assert.equal(monitor.listing.records[0].progress.percent, 100);
    assert.equal(monitor.listing.total, 31);
    assert.equal(monitor.runs[0].id, 9);
    assert.equal(monitor.busy('ANALYZE'), true);
});

test('starting a job inserts its record immediately on the unfiltered first page', () => {
    const request = deferred();
    const { monitor } = createMonitor(() => request.promise, [], { records: [], total: 0, page: 1, filtered: false });

    monitor.track({ state: activeRun(7, 'PENDING') });
    monitor.track({ state: activeRun(7, 'RUNNING') });

    assert.equal(monitor.listing.records.length, 1);
    assert.equal(monitor.listing.records[0].status, 'RUNNING');
    assert.equal(monitor.listing.total, 1);
    monitor.destroy();
    request.resolve(result([]));
});

test('starting a job preserves filtered and later-page records until the server refreshes them', () => {
    for (const settings of [{ page: 1, filtered: true }, { page: 2, filtered: false }]) {
        const request = deferred();
        const { monitor } = createMonitor(() => request.promise, [], { records: [activeRun(3, 'SUCCESS')], total: 31, ...settings });

        monitor.track({ state: activeRun(8, 'PENDING') });

        assert.equal(monitor.listing.records.length, 1);
        assert.equal(monitor.listing.records[0].id, 3);
        assert.equal(monitor.listing.total, 31);
        monitor.destroy();
        request.resolve(result([]));
    }
});


test('reusing an off-page job does not prepend it or inflate the total', () => {
    const request = deferred();
    const { monitor } = createMonitor(() => request.promise, [], { records: [activeRun(40, 'SUCCESS')], total: 40, page: 1, per_page: 30, last_page: 2, filtered: false });

    monitor.track({ reused: true, state: activeRun(1) });

    assert.equal(monitor.listing.records.length, 1);
    assert.equal(monitor.listing.records[0].id, 40);
    assert.equal(monitor.listing.total, 40);
    assert.equal(monitor.busy('ANALYZE'), true);
    monitor.destroy();
    request.resolve(result([]));
});

test('a new job creates the next page immediately and page links preserve filters', () => {
    const request = deferred();
    const { monitor } = createMonitor(() => request.promise, [], { records: [], total: 30, page: 1, per_page: 30, last_page: 1, filtered: false, page_url: 'https://apollo.test/admin/automation-runs?created_from=2026-10-01&page=1' });

    monitor.track({ reused: false, state: activeRun(31, 'PENDING') });

    assert.equal(monitor.listing.total, 31);
    assert.equal(monitor.listing.last_page, 2);
    const next = new URL(monitor.pageUrl(2));
    assert.equal(next.searchParams.get('page'), '2');
    assert.equal(next.searchParams.get('created_from'), '2026-10-01');
    monitor.destroy();
    request.resolve(result([]));
});

test('polling replaces pagination metadata when the history shrinks', async () => {
    const listing = { records: [], total: 30, page: 1, per_page: 30, last_page: 1, filtered: false, page_url: 'https://apollo.test/admin/automation-runs?page=1' };
    const { monitor } = createMonitor(async () => ({ ok: true, json: async () => ({ current_runs: [], listing }) }), [], { ...listing, total: 31, last_page: 2 });

    await monitor.refresh();

    assert.equal(monitor.listing.total, 30);
    assert.equal(monitor.listing.last_page, 1);
});
