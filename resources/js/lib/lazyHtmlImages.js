export function lazyLoadHtmlImages(html) {
    if (!html) {
        return '';
    }

    return html.replace(/<img\b([^>]*)>/gi, (match, attributes) => {
        if (/\bloading\s*=/.test(attributes)) {
            return match;
        }

        return `<img${attributes} loading="lazy" decoding="async">`;
    });
}
