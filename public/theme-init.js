(function () {
    var LIGHT = ['light', 'catppuccin-latte', 'tokyonight-day', 'kanagawa-lotus', 'gruvbox-light'];
    var DARK = ['dark', 'catppuccin', 'tokyonight', 'kanagawa', 'gruvbox'];

    window.apolloTheme = {
        isLight: function (theme) { return LIGHT.indexOf(theme) !== -1; },
        apply: function (theme, persist) {
            if (LIGHT.indexOf(theme) === -1 && DARK.indexOf(theme) === -1) { theme = 'dark'; }

            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.setAttribute('data-mode', LIGHT.indexOf(theme) !== -1 ? 'light' : 'dark');

            if (persist) {
                try { localStorage.setItem('apollo-theme', theme); } catch (e) {}
            }

            return theme;
        },
    };

    var theme = 'dark';

    try { theme = localStorage.getItem('apollo-theme') || 'dark'; } catch (e) {}

    window.apolloTheme.apply(theme, false);
})();
