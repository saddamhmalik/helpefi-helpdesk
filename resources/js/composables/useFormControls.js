export const formInputClass =
    'block w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm transition placeholder:text-slate-400 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-500/10 disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500';

const selectChevron = encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="%2394a3b8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>');

export const formSelectClass = `${formInputClass} appearance-none bg-[url('data:image/svg+xml,${selectChevron}')] bg-[length:1rem] bg-[right_0.75rem_center] bg-no-repeat pr-10`;
export const formTextareaClass = `${formInputClass} min-h-[6rem] resize-y`;

export const formMaxWidthClass = (size = 'lg') => ({
    sm: 'max-w-lg',
    md: 'max-w-2xl',
    lg: 'max-w-3xl',
    xl: 'max-w-4xl',
}[size] ?? 'max-w-3xl');
