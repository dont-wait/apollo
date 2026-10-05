import Alpine from 'alpinejs';

window.Alpine = Alpine;

window.homeFeed = (posts, authenticated, loginUrl) => ({
    posts,
    authenticated,
    loginUrl,
    search: '',
    activeCategory: 'All Posts',
    sort: 'latest',
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

    matchesPost(post) {
        const query = this.search.trim().toLowerCase();
        const matchesCategory = this.activeCategory === 'All Posts' || post.category === this.activeCategory;
        const searchableText = `${post.title} ${post.excerpt} ${post.category} ${post.tags.join(' ')}`.toLowerCase();

        return matchesCategory && (!query || searchableText.includes(query));
    },

    visiblePosts() {
        const visiblePosts = this.posts.filter((post) => this.matchesPost(post));

        if (this.sort === 'popular') {
            return visiblePosts.sort((a, b) => b.bookmarks - a.bookmarks);
        }

        if (this.sort === 'reading') {
            return visiblePosts.sort((a, b) => b.readMinutes - a.readMinutes);
        }

        return visiblePosts.sort((a, b) => b.publishedOrder - a.publishedOrder);
    },

    sortLabel() {
        return this.sortOptions.find((option) => option.value === this.sort)?.label ?? 'Latest';
    },

    setSort(value) {
        this.sort = value;
        this.sortMenuOpen = false;
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
