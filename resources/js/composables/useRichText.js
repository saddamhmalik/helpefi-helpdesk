export const stripHtml = (value) => (value ?? '').replace(/<[^>]+>/g, '').trim();

export const isEmptyRichText = (value) => stripHtml(value) === '';
