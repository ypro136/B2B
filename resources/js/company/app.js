const markReady = () => {
    document.documentElement.classList.add('js-ready');
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', markReady);
} else {
    markReady();
}
