(function () {
    try {
        var saved = localStorage.getItem('nutritale-theme');
        var theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
    } catch (e) {
        // localStorage unavailable (private mode etc) - fall back to light.
    }
})();
