import Alpine from 'alpinejs';
import './confirm-modal.js';
import './admin/category-tag.js';
import './admin/ai-weekly.js';
import './admin/notifications.js';
window.Alpine = Alpine;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const bookmarkUrl = (template, postId) => template.replace('__POST__', String(postId));

window.homeFeed = (posts, authenticated, loginUrl, filters = {}, pagination = {}, bookmarkUrls = {}) => ({
    posts,
    authenticated,
    loginUrl,
    bookmarkUrls,
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
    bookmarkNotice: false,
    bookmarkNoticeMessage: 'Sign in to save articles to your bookmarks.',
    bookmarkNoticeShowsLogin: false,
    bookmarkingPostId: null,
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
        return this.posts.find((post) => post.id === postId)?.bookmarked === true;
    },

    bookmarkCount(post) {
        return post.bookmarks;
    },

    async toggleBookmark(post) {
        if (!this.authenticated) {
            this.bookmarkNoticeMessage = 'Sign in to save articles to your bookmarks.';
            this.bookmarkNoticeShowsLogin = true;
            this.bookmarkNotice = true;

            return;
        }

        if (this.bookmarkingPostId === post.id) {
            return;
        }

        const previousBookmarked = post.bookmarked;
        const previousCount = post.bookmarks;
        const nextBookmarked = !previousBookmarked;
        const url = bookmarkUrl(
            nextBookmarked ? this.bookmarkUrls.store : this.bookmarkUrls.destroy,
            post.id,
        );

        this.bookmarkingPostId = post.id;
        post.bookmarked = nextBookmarked;
        post.bookmarks += nextBookmarked ? 1 : -1;

        try {
            const response = await fetch(url, {
                method: nextBookmarked ? 'POST' : 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await response.json();

            if (!response.ok || typeof payload.bookmarks_count !== 'number') {
                throw new Error(`Bookmark request failed with status ${response.status}`);
            }

            post.bookmarked = payload.bookmarked;
            post.bookmarks = payload.bookmarks_count;
        } catch (error) {
            post.bookmarked = previousBookmarked;
            post.bookmarks = previousCount;
            this.bookmarkNoticeMessage = 'Unable to update bookmark. Please try again.';
            this.bookmarkNoticeShowsLogin = false;
            this.bookmarkNotice = true;
            console.error('Unable to update bookmark:', error);
        } finally {
            this.bookmarkingPostId = null;
        }
    },

    focusSearch() {
        this.$refs.search?.focus();
    },
});

window.bookmarkButton = (config) => ({
    postId: config.postId,
    bookmarked: config.bookmarked,
    count: config.count,
    storeUrl: config.storeUrl,
    destroyUrl: config.destroyUrl,
    loading: false,
    error: false,

    async toggle() {
        if (this.loading) {
            return;
        }

        const nextBookmarked = !this.bookmarked;
        const previousBookmarked = this.bookmarked;
        const previousCount = this.count;

        this.loading = true;
        this.error = false;
        this.bookmarked = nextBookmarked;
        this.count += nextBookmarked ? 1 : -1;

        try {
            const response = await fetch(
                bookmarkUrl(nextBookmarked ? this.storeUrl : this.destroyUrl, this.postId),
                {
                    method: nextBookmarked ? 'POST' : 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                },
            );
            const payload = await response.json();

            if (!response.ok || typeof payload.bookmarks_count !== 'number') {
                throw new Error(`Bookmark request failed with status ${response.status}`);
            }

            this.bookmarked = payload.bookmarked;
            this.count = payload.bookmarks_count;
            this.$dispatch('bookmark-toggled', {
                id: this.postId,
                bookmarked: this.bookmarked,
            });
        } catch (error) {
            this.bookmarked = previousBookmarked;
            this.count = previousCount;
            this.error = true;
            console.error('Unable to update bookmark:', error);
        } finally {
            this.loading = false;
        }
    },
});

window.articleComments = (options = {}) => ({
    indexUrl: options.indexUrl,
    storeUrl: options.storeUrl,
    repliesUrlTemplate: options.repliesUrlTemplate,
    updateUrlTemplate: options.updateUrlTemplate,
    deleteUrlTemplate: options.deleteUrlTemplate,
    authenticated: options.authenticated ?? false,
    currentUserId: options.currentUserId ?? null,
    loginUrl: options.loginUrl ?? '/login',
    comments: [],
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
    openMenu: null,
    editing: null,
    editDrafts: {},
    editErrors: {},
    editSubmitting: null,
    submissionKeys: {},
    deletedCommentIds: [],
    mutationVersion: 0,

    init() {
        this.loadComments();
    },

    normalizeComment(comment, depth = 0) {
        return {
            ...comment,
            id: Number(comment.id),
            parent_id: comment.parent_id === null || comment.parent_id === undefined
                ? null
                : Number(comment.parent_id),
            depth,
            expanded: false,
            childrenLoaded: false,
            childrenLoading: false,
            childrenError: '',
            nextRepliesUrl: null,
        };
    },

    summaryLabel() {
        if (this.loading) {
            return 'Loading conversation';
        }

        const rootCount = this.comments.filter((comment) => comment.depth === 0).length;

        if (rootCount === 0) {
            return 'No comments yet';
        }

        return `${rootCount}${this.nextCommentsUrl ? '+' : ''} threads`;
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

    findComment(commentId) {
        const normalizedId = Number(commentId);

        return this.comments.find((comment) => comment.id === normalizedId);
    },

    newSubmissionKey() {
        return window.crypto?.randomUUID?.()
            ?? `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    },

    isVisible(comment) {
        let parentId = comment.parent_id;
        const visited = new Set();

        while (parentId !== null) {
            if (visited.has(parentId)) {
                return false;
            }

            visited.add(parentId);
            const parent = this.findComment(parentId);

            if (!parent || !parent.expanded) {
                return false;
            }

            parentId = parent.parent_id;
        }

        return true;
    },

    subtreeEndIndex(comment) {
        const parentIndex = this.comments.findIndex((item) => item.id === comment.id);
        let index = parentIndex + 1;

        while (index < this.comments.length && this.comments[index].depth > comment.depth) {
            index += 1;
        }

        return index;
    },

    removeSubtree(comment) {
        const commentIndex = this.comments.findIndex((item) => item.id === comment.id);

        if (commentIndex === -1) {
            return false;
        }

        this.comments.splice(commentIndex, this.subtreeEndIndex(comment) - commentIndex);

        return true;
    },

    pruneEmptyDeletedAncestors(comment) {
        let current = comment;

        while (current?.status === 'DELETED' && current.replies_count === 0) {
            const parent = current.parent_id === null ? null : this.findComment(current.parent_id);

            this.removeSubtree(current);

            if (!parent) {
                return;
            }

            parent.replies_count = Math.max(0, parent.replies_count - 1);
            current = parent;
        }
    },

    insertDirectChild(parent, child) {
        const parentIndex = this.comments.findIndex((comment) => comment.id === parent.id);

        if (parentIndex === -1 || this.comments.some((comment) => comment.id === child.id)) {
            return;
        }

        this.comments.splice(parentIndex + 1, 0, child);
    },

    insertChildren(parent, children) {
        const existingIds = new Set(this.comments.map((comment) => comment.id));
        const newChildren = children.filter((comment) => !existingIds.has(comment.id));

        if (newChildren.length === 0) {
            return;
        }

        this.comments.splice(this.subtreeEndIndex(parent), 0, ...newChildren);
    },

    async loadComments(url = this.indexUrl) {
        this.loading = true;
        this.error = false;

        try {
            const payload = await this.request(url);
            this.comments = (payload.data ?? []).map((comment) => this.normalizeComment(comment));
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
            this.comments.push(...(payload.data ?? [])
                .filter((comment) => !this.deletedCommentIds.includes(Number(comment.id)))
                .map((comment) => this.normalizeComment(comment)));
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

    commentUrl(template, commentId) {
        return template.replace('__COMMENT__', commentId);
    },

    isOwnComment(comment) {
        return this.authenticated
            && comment.status !== 'DELETED'
            && this.currentUserId !== null
            && Number(comment.author?.id) === Number(this.currentUserId);
    },

    toggleMenu(commentId) {
        this.openMenu = this.openMenu === commentId ? null : commentId;
    },

    startEditing(comment) {
        if (!this.isOwnComment(comment)) {
            return;
        }

        this.openMenu = null;
        this.editing = comment.id;
        this.editDrafts[comment.id] = comment.content;
        this.editErrors[comment.id] = '';
    },

    cancelEditing() {
        this.editing = null;
        this.editSubmitting = null;
    },

    async saveEdit(comment) {
        const content = (this.editDrafts[comment.id] ?? '').trim();

        if (!content || this.editSubmitting !== null) {
            return;
        }

        this.editSubmitting = comment.id;
        this.editErrors[comment.id] = '';

        try {
            const payload = await this.request(this.commentUrl(this.updateUrlTemplate, comment.id), {
                method: 'PATCH',
                body: JSON.stringify({ content, version: comment.version }),
            });

            comment.content = payload.data?.content ?? content;
            comment.version = payload.data?.version ?? comment.version;
            comment.updated_at = payload.data?.updated_at ?? comment.updated_at;
            comment.edited_at = payload.data?.edited_at ?? new Date().toISOString();
            this.editing = null;
        } catch (error) {
            if (error.status === 422) {
                const messages = Object.values(error.payload?.errors ?? {}).flat();
                this.editErrors[comment.id] = messages[0] ?? error.message;
            } else if (error.status === 403) {
                this.editErrors[comment.id] = 'You can only edit your own comment.';
            } else if (error.status === 409) {
                this.editErrors[comment.id] = error.message;
            } else {
                this.editErrors[comment.id] = 'Unable to save your comment. Please try again.';
            }
        } finally {
            this.editSubmitting = null;
        }
    },

    async deleteComment(comment) {
        if (!this.isOwnComment(comment)) {
            return;
        }

        const confirmed = await window.showConfirmModal({
            title: 'Delete comment',
            message: 'This comment will be removed from the public conversation.',
            confirmText: 'Delete',
        });

        if (!confirmed) {
            return;
        }

        this.editSubmitting = comment.id;
        this.editErrors[comment.id] = '';

        try {
            await this.request(this.commentUrl(this.deleteUrlTemplate, comment.id), {
                method: 'DELETE',
            });

            this.mutationVersion += 1;
            if (!this.deletedCommentIds.includes(comment.id)) {
                this.deletedCommentIds.push(comment.id);
            }

            const commentIndex = this.comments.findIndex((item) => item.id === comment.id);
            const parent = comment.parent_id === null ? null : this.findComment(comment.parent_id);

            if (commentIndex === -1) {
                return;
            }

            if (comment.replies_count > 0) {
                comment.status = 'DELETED';
                comment.content = '[Comment deleted]';
                comment.author = {
                    id: null,
                    name: 'Deleted user',
                    avatar_url: null,
                    initial: 'D',
                };
                comment.edited_at = null;
                this.editing = null;
                this.openMenu = null;
                return;
            }

            this.removeSubtree(comment);

            if (parent) {
                parent.replies_count = Math.max(0, parent.replies_count - 1);
                this.pruneEmptyDeletedAncestors(parent);
            }

            this.editing = null;
            this.openMenu = null;
        } catch (error) {
            this.editErrors[comment.id] = error.status === 403
                ? 'You can only delete your own comment.'
                : 'Unable to delete your comment. Please try again.';
        } finally {
            this.editSubmitting = null;
        }
    },

    async toggleReplies(comment) {
        comment.expanded = !comment.expanded;

        if (comment.expanded && !comment.childrenLoaded && !comment.childrenLoading) {
            await this.loadReplies(comment);
        }
    },

    async loadReplies(comment, url = null) {
        comment.childrenLoading = true;
        comment.childrenError = '';
        const requestMutationVersion = this.mutationVersion;

        try {
            const payload = await this.request(url ?? this.repliesUrl(comment.id));
            if (requestMutationVersion !== this.mutationVersion) {
                return;
            }

            const children = (payload.data ?? [])
                .filter((child) => !this.deletedCommentIds.includes(Number(child.id)))
                .map((child) => this.normalizeComment(child, comment.depth + 1));
            this.insertChildren(comment, children);
            comment.childrenLoaded = true;
            comment.nextRepliesUrl = payload.links?.next ?? null;
        } catch (error) {
            comment.childrenError = 'Unable to load replies.';
            console.error('Unable to load replies:', error);
        } finally {
            comment.childrenLoading = false;
        }
    },

    startReply(comment) {
        if (!this.authenticated) {
            window.location.href = this.loginUrl;

            return;
        }

        this.replyingTo = comment.id;
        this.formErrors[comment.id] = '';
        this.successMessages[comment.id] = '';
        this.$nextTick(() => document.getElementById(`reply-${comment.id}`)?.focus());
    },

    cancelReply() {
        this.replyingTo = null;
    },

    async submitComment() {
        await this.submit(null, this.draft);
    },

    async submitReply(comment) {
        await this.submit(comment.id, this.replyDrafts[comment.id] ?? '');
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
        this.submissionKeys[key] ??= this.newSubmissionKey();

        try {
            const payload = await this.request(this.storeUrl, {
                method: 'POST',
                headers: {
                    'Idempotency-Key': this.submissionKeys[key],
                },
                body: JSON.stringify({ content: trimmedContent, parent_id: parentId }),
            });
            const comment = payload.data;
            delete this.submissionKeys[key];

            if (parentId === null) {
                this.comments.unshift(this.normalizeComment(comment));
                this.draft = '';
                return;
            }

            const parent = this.findComment(parentId);
            this.replyDrafts[parentId] = '';
            this.replyingTo = null;

            if (!parent) {
                return;
            }

            const child = this.normalizeComment(comment, parent.depth + 1);
            this.insertDirectChild(parent, child);
            parent.replies_count += 1;
            parent.expanded = true;

            if (!parent.childrenLoaded) {
                await this.loadReplies(parent);
            }
        } catch (error) {
            if (error.status === 422) {
                delete this.submissionKeys[key];
                const messages = Object.values(error.payload?.errors ?? {}).flat();
                this.formErrors[key] = messages[0] ?? error.message;
            } else if (error.status === 409) {
                delete this.submissionKeys[key];
                this.formErrors[key] = error.message;
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

window.savedBookmarks = (initialVisibleCount) => ({
    visibleCount: initialVisibleCount,

    handleBookmark(event) {
        if (!event.detail.bookmarked) {
            this.visibleCount = Math.max(0, this.visibleCount - 1);
        }
    },
});

window.savedBookmarkCard = (config) => ({
    ...window.bookmarkButton(config),
    removed: false,

    handleBookmark(event) {
        if (event.detail.id === this.postId && !event.detail.bookmarked) {
            this.removed = true;
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
