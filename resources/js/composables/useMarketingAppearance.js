export function isMarketingPage(component) {
    return component === 'Central/Home'
        || component === 'Central/Login'
        || component === 'Central/Register';
}

export function applyMarketingAppearance() {
    document.documentElement.classList.remove('dark');

    const meta = document.querySelector('meta[name="theme-color"]');

    if (meta) {
        meta.setAttribute('content', '#0066CC');
    }
}
