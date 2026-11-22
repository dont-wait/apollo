(function () {
    var theme = 'dark';

    try {
        theme = localStorage.getItem('apollo-theme') === 'light' ? 'light' : 'dark';
    } catch (e) {}

    document.documentElement.setAttribute('data-theme', theme);
})();
