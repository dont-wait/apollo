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

Alpine.start();
