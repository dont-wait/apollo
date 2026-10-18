import Alpine from 'alpinejs';
import './admin/category-tag.js';
window.Alpine = Alpine;

window.homeFeed = (posts, authenticated, loginUrl, filters = {}, pagination = {}) => ({
    posts,
    authenticated,
    loginUrl,
    search: filters.q ?? '',
    activeCategory: filters.category ?? '',
    sort: filters.sort ?? (filters.q ? 'relevance' : 'latest'),
    pagination,
    loading: false,
    loadError: false,
    requestId: 0,
    requestController: null,
    pendingUrl: null,
    committedUrl: null,
    sortMenuOpen: false,
    sortOptions: [
        { value: 'relevance', label: 'Most relevant' },
        { value: 'latest', label: 'Latest' },
        { value: 'popular', label: 'Most bookmarked' },
        { value: 'reading', label: 'Longest reads' },
    ],
    view: 'list',
    bookmarked: [],
    bookmarkNotice: false,
    newsletterSubmitted: false,

    visiblePosts() {
        return this.posts;
    },

    sortLabel() {
        return this.sortOptions.find((option) => option.value === this.sort)?.label ?? 'Latest';
    },

    setSort(value) {
        this.sort = value;
        this.sortMenuOpen = false;
        this.updateQuery('sort', value, { scroll: false });
    },

    setCategory(category) {
        this.activeCategory = category;
        this.updateQuery('category', category, { scroll: false });
    },

    applyFilters() {
        this.updateQuery('q', this.search.trim(), { scroll: false });
    },

    resetFilters() {
        const url = new URL(window.location.href);

        ['category', 'q', 'sort', 'page', 'cursor', 'search_page'].forEach((key) => url.searchParams.delete(key));
        this.search = '';
        this.activeCategory = '';
        this.sort = 'latest';
        this.load(url, { scroll: false });
    },

    goToPage(pageUrl) {
        if (!pageUrl || this.loading) {
            return;
        }
        this.load(new URL(pageUrl, window.location.href));
    },

    updateQuery(key, value, { scroll = false } = {}) {
        const url = new URL(this.pendingUrl ?? window.location.href);

        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }

        url.searchParams.delete('page');
        url.searchParams.delete('cursor');
        url.searchParams.delete('search_page');
        this.load(url, { scroll });
    },

    async load(url, { push = true, scroll = true } = {}) {
        if (this.loading && this.pendingUrl === url.href) {
            return;
        }

        this.requestController?.abort();
        const controller = new AbortController();
        const requestId = ++this.requestId;
        this.requestController = controller;
        this.pendingUrl = url.href;
        this.loading = true;
        this.loadError = false;
        const timeout = window.setTimeout(() => controller.abort(), 15000);

        try {
            const response = await fetch(url, {
                signal: controller.signal,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const payload = await response.json();
            if (requestId !== this.requestId) {
                return;
            }
            if (controller.signal.aborted) {
                throw new Error('Request timed out');
            }
            this.posts = payload.data;
            this.pagination = payload.pagination;
            this.committedUrl = url.href;
            this.syncFilters(url);

            const nextUrl = `${url.pathname}${url.search}`;

            if (push && window.location.href !== url.href) {
                window.history.pushState({}, '', nextUrl);
            }

            if (scroll) {
                document.getElementById('articles')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            if (requestId !== this.requestId) {
                return;
            }
            this.loadError = true;
            const previousUrl = new URL(this.committedUrl ?? window.location.href);
            this.syncFilters(previousUrl);
            if (!push) {
                window.history.replaceState({}, '', previousUrl);
            }
            console.error('Unable to load articles:', error);
        } finally {
            window.clearTimeout(timeout);
            if (requestId === this.requestId) {
                this.loading = false;
                this.pendingUrl = null;
                this.requestController = null;
            }
        }
    },

    syncFilters(url) {
        this.search = url.searchParams.get('q') ?? '';
        this.activeCategory = url.searchParams.get('category') ?? '';
        this.sort = url.searchParams.get('sort') ?? (this.search ? 'relevance' : 'latest');
    },

    init() {
        this.committedUrl = window.location.href;
        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);

            this.syncFilters(url);
            this.load(url, { push: false, scroll: false });
        });
    },

    isBookmarked(postId) {
        return this.bookmarked.includes(postId);
    },

    bookmarkCount(post) {
        return post.bookmarks + (this.isBookmarked(post.id) ? 1 : 0);
    },

    toggleBookmark(postId) {
        if (!this.authenticated) {
            this.bookmarkNotice = true;

            return;
        }

        this.bookmarked = this.isBookmarked(postId)
            ? this.bookmarked.filter((id) => id !== postId)
            : [...this.bookmarked, postId];
    },

    focusSearch() {
        this.$refs.search?.focus();
    },
});

window.articleComments = (options = {}) => ({
    indexUrl: options.indexUrl,
    storeUrl: options.storeUrl,
    repliesUrlTemplate: options.repliesUrlTemplate,
    authenticated: options.authenticated ?? false,
    loginUrl: options.loginUrl ?? '/login',
    comments: [],
    replies: {},
    expandedReplies: {},
    replyLoading: {},
    replyErrors: {},
    replyNextUrls: {},
    replyDrafts: {},
    formErrors: {},
    successMessages: {},
    draft: '',
    nextCommentsUrl: null,
    loadMoreError: '',
    loading: true,
    loadingMore: false,
    error: false,
    submitting: null,
    replyingTo: null,

    init() {
        this.loadComments();
    },

    summaryLabel() {
        if (this.loading) {
            return 'Loading conversation';
        }

        if (this.comments.length === 0) {
            return 'No comments yet';
        }

        return `${this.comments.length}${this.nextCommentsUrl ? '+' : ''} threads`;
    },

    csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    },

    async request(url, options = {}) {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.csrfToken(),
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers ?? {}),
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            const error = new Error(payload.message ?? `Request failed with status ${response.status}`);
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload;
    },

    async loadComments(url = this.indexUrl) {
        this.loading = true;
        this.error = false;

        try {
            const payload = await this.request(url);
            this.comments = payload.data ?? [];
            this.nextCommentsUrl = payload.links?.next ?? null;
        } catch (error) {
            this.error = true;
            console.error('Unable to load comments:', error);
        } finally {
            this.loading = false;
        }
    },

    async loadMoreComments() {
        if (!this.nextCommentsUrl || this.loadingMore) {
            return;
        }

        this.loadingMore = true;
        this.loadMoreError = '';

        try {
            const payload = await this.request(this.nextCommentsUrl);
            this.comments = [...this.comments, ...(payload.data ?? [])];
            this.nextCommentsUrl = payload.links?.next ?? null;
        } catch (error) {
            this.loadMoreError = 'Unable to load more comments. Please try again.';
            console.error('Unable to load more comments:', error);
        } finally {
            this.loadingMore = false;
        }
    },

    repliesUrl(commentId) {
        return this.repliesUrlTemplate.replace('__COMMENT__', commentId);
    },

    async toggleReplies(commentId) {
        this.expandedReplies[commentId] = !this.expandedReplies[commentId];

        if (this.expandedReplies[commentId] && this.replies[commentId] === undefined) {
            await this.loadReplies(commentId);
        }
    },

    async loadReplies(commentId, url = this.repliesUrl(commentId)) {
        this.replyLoading[commentId] = true;
        this.replyErrors[commentId] = '';

        try {
            const payload = await this.request(url);
            const existingReplies = this.replies[commentId] ?? [];
            const existingIds = new Set(existingReplies.map((reply) => reply.id));
            const newReplies = (payload.data ?? []).filter((reply) => !existingIds.has(reply.id));
            this.replies[commentId] = [...existingReplies, ...newReplies];
            this.replyNextUrls[commentId] = payload.links?.next ?? null;
        } catch (error) {
            this.replyErrors[commentId] = 'Unable to load replies.';
            console.error('Unable to load replies:', error);
        } finally {
            this.replyLoading[commentId] = false;
        }
    },

    startReply(commentId) {
        if (!this.authenticated) {
            window.location.href = this.loginUrl;

            return;
        }

        this.replyingTo = commentId;
        this.formErrors[commentId] = '';
        this.$nextTick(() => document.getElementById(`reply-${commentId}`)?.focus());
    },

    cancelReply() {
        this.replyingTo = null;
    },

    async submitComment() {
        await this.submit(null, this.draft);
    },

    async submitReply(commentId) {
        await this.submit(commentId, this.replyDrafts[commentId] ?? '');
    },

    async submit(parentId, content) {
        const key = parentId ?? 'root';
        const trimmedContent = content.trim();

        if (!trimmedContent || this.submitting !== null) {
            return;
        }

        this.submitting = key;
        this.formErrors[key] = '';
        this.successMessages[key] = '';

        try {
            const payload = await this.request(this.storeUrl, {
                method: 'POST',
                body: JSON.stringify({ content: trimmedContent, parent_id: parentId }),
            });
            const comment = payload.data;

            if (parentId === null) {
                this.draft = '';
                if (comment.status === 'VISIBLE') {
                    this.comments = [comment, ...this.comments];
                } else {
                    this.successMessages.root = 'Your comment is awaiting moderation.';
                }
            } else {
                this.replyDrafts[parentId] = '';
                this.expandedReplies[parentId] = true;
                if (comment.status === 'VISIBLE') {
                    if (this.replies[parentId] === undefined) {
                        this.replies[parentId] = [comment];
                        await this.loadReplies(parentId);
                    } else {
                        this.replies[parentId] = [
                            comment,
                            ...this.replies[parentId].filter((reply) => reply.id !== comment.id),
                        ];
                    }
                    const root = this.comments.find((item) => item.id === parentId);
                    if (root) {
                        root.replies_count += 1;
                    }
                } else {
                    this.successMessages[parentId] = 'Your reply is awaiting moderation.';
                }
                this.replyingTo = null;
            }
        } catch (error) {
            if (error.status === 422) {
                const messages = Object.values(error.payload?.errors ?? {}).flat();
                this.formErrors[key] = messages[0] ?? error.message;
            } else if (error.status === 401) {
                this.authenticated = false;
                this.formErrors[key] = 'Your session has expired. Please sign in again.';
            } else if (error.status === 429) {
                this.formErrors[key] = 'You are posting too quickly. Please try again in a moment.';
            } else {
                this.formErrors[key] = 'Unable to post your comment. Please try again.';
            }
        } finally {
            this.submitting = null;
        }
    },
});

window.adminTrafficChart = () => ({
    width: 600,
    height: 200,
    maxValue: 90,
    readers: [24, 35, 43, 39, 51, 66, 74, 69, 84],
    subscribers: [9, 17, 22, 20, 29, 41, 48, 44, 58],
    readerTrend: [108, 112, 119, 117, 126, 131, 137, 142],

    x(index, series, width = this.width) {
        return (index / (series.length - 1)) * width;
    },

    y(value, height = this.height, maxValue = this.maxValue, minValue = 0) {
        return height - ((value - minValue) / (maxValue - minValue)) * height;
    },

    line(series, width = this.width, height = this.height, maxValue = this.maxValue, minValue = 0) {
        return series.map((value, index) => `${index === 0 ? 'M' : 'L'} ${this.x(index, series, width)} ${this.y(value, height, maxValue, minValue)}`).join(' ');
    },

    area(series, width = this.width, height = this.height, maxValue = this.maxValue, minValue = 0) {
        return `${this.line(series, width, height, maxValue, minValue)} L ${width} ${height} L 0 ${height} Z`;
    },
});

window.editorialSignalChart = () => ({
    width: 240,
    height: 100,
    maxValue: 100,
    editorialSignals: [18, 61, 35, 52, 76, 65, 88],
    verifiedSignals: [12, 19, 25, 31, 44, 51, 72],

    x(index, series) {
        return 12 + (index / (series.length - 1)) * (this.width - 24);
    },

    y(value) {
        return this.height - 12 - (value / this.maxValue) * (this.height - 24);
    },

    line(series) {
        return series.map((value, index) => `${index === 0 ? 'M' : 'L'} ${this.x(index, series)} ${this.y(value)}`).join(' ');
    },

    area(series) {
        return `${this.line(series)} L ${this.width - 12} ${this.height - 12} L 12 ${this.height - 12} Z`;
    },
});

Alpine.start();
