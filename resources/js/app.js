import Alpine from 'alpinejs';
import './admin/category-tag.js';
window.Alpine = Alpine;

window.homeFeed = (posts, authenticated, loginUrl, filters = {}, pagination = {}) => ({
    posts,
    authenticated,
    loginUrl,
    search: filters.q ?? '',
    activeCategory: filters.category ?? 'All Posts',
    sort: filters.sort ?? 'latest',
    pagination,
    loading: false,
    loadError: false,
    sortMenuOpen: false,
    sortOptions: [
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

    paginationPages() {
        const lastPage = this.pagination.last_page ?? 1;
        const currentPage = this.pagination.current_page ?? 1;

        if (lastPage <= 7) {
            return Array.from({ length: lastPage }, (_, index) => index + 1);
        }

        const pages = [1];

        if (currentPage > 4) {
            pages.push('...');
        }

        for (let page = Math.max(2, currentPage - 1); page <= Math.min(lastPage - 1, currentPage + 1); page += 1) {
            pages.push(page);
        }

        if (currentPage < lastPage - 3) {
            pages.push('...');
        }

        pages.push(lastPage);

        return pages;
    },

    sortLabel() {
        return this.sortOptions.find((option) => option.value === this.sort)?.label ?? 'Latest';
    },

    setSort(value) {
        this.sort = value;
        this.sortMenuOpen = false;
        this.updateQuery('sort', value === 'latest' ? '' : value, { scroll: false });
    },

    setCategory(category) {
        this.activeCategory = category;
        this.updateQuery('category', category === 'All Posts' ? '' : category, { scroll: false });
    },

    applyFilters() {
        this.updateQuery('q', this.search.trim(), { scroll: false });
    },

    resetFilters() {
        const url = new URL(window.location.href);

        ['category', 'q', 'sort', 'page'].forEach((key) => url.searchParams.delete(key));
        this.search = '';
        this.activeCategory = 'All Posts';
        this.sort = 'latest';
        this.load(url);
    },

    goToPage(page) {
        if (page === '...' || page === this.pagination.current_page) {
            return;
        }

        const url = new URL(window.location.href);
        url.searchParams.set('page', page);
        this.load(url);
    },

    updateQuery(key, value, { scroll = false } = {}) {
        const url = new URL(window.location.href);

        if (value) {
            url.searchParams.set(key, value);
        } else {
            url.searchParams.delete(key);
        }

        url.searchParams.delete('page');
        this.load(url, { scroll });
    },

    async load(url, { push = true, scroll = true } = {}) {
        this.loading = true;
        this.loadError = false;

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            const payload = await response.json();
            this.posts = payload.data;
            this.pagination = payload.pagination;

            const nextUrl = `${url.pathname}${url.search}`;

            if (push) {
                window.history.pushState({}, '', nextUrl);
            }

            if (scroll) {
                document.getElementById('articles')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        } catch (error) {
            this.loadError = true;
            console.error('Unable to load articles:', error);
        } finally {
            this.loading = false;
        }
    },

    init() {
        window.addEventListener('popstate', () => {
            const url = new URL(window.location.href);

            this.search = url.searchParams.get('q') ?? '';
            this.activeCategory = url.searchParams.get('category') ?? 'All Posts';
            this.sort = url.searchParams.get('sort') ?? 'latest';
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
