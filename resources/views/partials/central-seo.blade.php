<meta head-key="description" name="description" content="{{ $seo['description'] }}">
<meta head-key="robots" name="robots" content="{{ $seo['robots'] }}">
<link head-key="canonical" rel="canonical" href="{{ $seo['canonical'] }}">
<meta head-key="og:type" property="og:type" content="website">
<meta head-key="og:url" property="og:url" content="{{ $seo['canonical'] }}">
<meta head-key="og:title" property="og:title" content="{{ $seo['title'] }}">
<meta head-key="og:description" property="og:description" content="{{ $seo['description'] }}">
<meta head-key="og:site_name" property="og:site_name" content="{{ $seo['brand'] }}">
<meta head-key="og:locale" property="og:locale" content="{{ $seo['ogLocale'] }}">
@foreach ($seo['ogLocaleAlternates'] as $alternate)
<meta head-key="og:locale:alternate:{{ $alternate }}" property="og:locale:alternate" content="{{ $alternate }}">
@endforeach
<meta head-key="twitter:card" name="twitter:card" content="{{ $seo['ogImage'] ? 'summary_large_image' : 'summary' }}">
<meta head-key="twitter:title" name="twitter:title" content="{{ $seo['title'] }}">
<meta head-key="twitter:description" name="twitter:description" content="{{ $seo['description'] }}">
@if ($seo['ogImage'])
<meta head-key="og:image" property="og:image" content="{{ $seo['ogImage'] }}">
<meta head-key="twitter:image" name="twitter:image" content="{{ $seo['ogImage'] }}">
@endif
@if ($seo['jsonLd'])
<script type="application/ld+json">{!! $seo['jsonLd'] !!}</script>
@endif
