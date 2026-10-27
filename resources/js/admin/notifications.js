import Alpine from 'alpinejs';

Alpine.data('adminNotifications', () => ({
    items: [],
    unreadCount: 0,
    timer: null,
    loading: false,

    init() {
        this.refresh();
        this.timer = setInterval(() => {
            if (!document.hidden) {
                this.refresh();
            }
        }, 30000);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async refresh() {
        if (this.loading) {
            return;
        }
        this.loading = true;
        try {
            const response = await fetch(this.$el.dataset.notificationsUrl, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (response.ok) {
                const result = await response.json();
                this.items = result.data;
                this.unreadCount = result.unread_count;
            }
        } catch {
            // Keep the last notification list until the next poll succeeds.
        } finally {
            this.loading = false;
        }
    },
}));
