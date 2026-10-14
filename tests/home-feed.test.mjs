import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../resources/js/app.js', import.meta.url), 'utf8');
const component = source.slice(source.indexOf('window.homeFeed ='), source.indexOf('window.adminTrafficChart ='));

function createFeed(fetch) {
    const history = [];
    const timers = new Map();
    const window = {
        location: { href: 'https://apollo.test/' },
        history: {
            pushState(_, __, url) {
                history.push(url);
                window.location.href = new URL(url, window.location.href).href;
            },
            replaceState(_, __, url) {
                window.location.href = new URL(url, window.location.href).href;
            },
        },
        setTimeout(callback) {
            const id = timers.size + 1;
            timers.set(id, callback);
            return id;
        },
        clearTimeout(id) { timers.delete(id); },
        addEventListener() {},
    };
    runInNewContext(component, { window, fetch, URL, AbortController, console: { error() {} }, document: { getElementById() {} } });
    const feed = window.homeFeed([{ id: 'original' }], false, '/login', {}, { current_page: 1, last_page: 1 });
    feed.init();
    return { feed, window, history, timers };
}

function deferred() {
    let resolve;
    const promise = new Promise((done) => { resolve = done; });
    return { promise, resolve };
}

function result(id) {
    return { ok: true, json: async () => ({ data: [{ id }], pagination: { total: 1, current_page: 1, last_page: 1 } }) };
}

test('only the latest response updates results and history, preserving pending filters', async () => {
    const requests = [];
    const { feed, history } = createFeed((url, options) => {
        const request = deferred();
        requests.push({ ...request, url, options });
        return request.promise;
    });
    feed.setCategory('systems');
    feed.setSort('popular');
    assert.equal(requests[1].url.searchParams.get('category'), 'systems');
    assert.equal(requests[0].options.signal.aborted, true);
    requests[1].resolve(result('latest'));
    await new Promise(setImmediate);
    requests[0].resolve(result('stale'));
    await new Promise(setImmediate);
    assert.equal(feed.posts[0].id, 'latest');
    assert.equal(history.length, 1);
    assert.equal(feed.loading, false);
});

test('failure rolls back selected filters and leaves existing results intact', async () => {
    const { feed, history } = createFeed(async () => { throw new Error('offline'); });
    feed.setCategory('systems');
    await new Promise(setImmediate);
    assert.equal(feed.activeCategory, '');
    assert.equal(feed.posts[0].id, 'original');
    assert.equal(feed.loadError, true);
    assert.equal(feed.loading, false);
    assert.equal(history.length, 0);
});

test('timeout clears loading and allows retry', async () => {
    const { feed, timers } = createFeed((_, { signal }) => new Promise((resolve, reject) => {
        signal.addEventListener('abort', () => reject(new Error('timeout')));
    }));
    const request = feed.load(new URL('https://apollo.test/?category=systems'));
    [...timers.values()][0]();
    await request;
    assert.equal(feed.loading, false);
    assert.equal(feed.loadError, true);
});

test('pagination includes gaps and unique positions for both ellipses', () => {
    const { feed } = createFeed(async () => result('unused'));
    feed.pagination = { current_page: 5, last_page: 10 };
    assert.deepEqual(Array.from(feed.paginationPages()), [1, '...', 4, 5, 6, '...', 10]);
});
