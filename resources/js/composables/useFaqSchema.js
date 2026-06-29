const stripHtml = (value) => String(value ?? '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();

export function normalizeFaqItems(items = []) {
    if (!Array.isArray(items)) {
        return [];
    }

    return items
        .map((item) => ({
            question: String(item?.q ?? item?.question ?? '').trim(),
            answer: String(item?.a ?? item?.answer ?? '').trim(),
        }))
        .filter((item) => item.question && item.answer);
}

export function buildFaqSchema(items = []) {
    const normalized = normalizeFaqItems(items);

    if (!normalized.length) {
        return null;
    }

    return {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: normalized.map((item) => ({
            '@type': 'Question',
            name: item.question,
            acceptedAnswer: {
                '@type': 'Answer',
                text: stripHtml(item.answer),
            },
        })),
    };
}
