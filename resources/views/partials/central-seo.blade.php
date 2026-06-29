@if ($googleVerification = config('marketing_seo.analytics.google_site_verification'))
<meta head-key="google-site-verification" name="google-site-verification" content="{{ $googleVerification }}">
@endif
@if ($bingVerification = config('marketing_seo.analytics.bing_site_verification'))
<meta head-key="msvalidate.01" name="msvalidate.01" content="{{ $bingVerification }}">
@endif
@if ($gaId = config('marketing_seo.analytics.google_analytics_id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ $gaId }}', { anonymize_ip: true });
</script>
@endif
@if ($ahrefsKey = config('marketing_seo.analytics.ahrefs_analytics_key'))
<script>
    var ahrefs_analytics_script = document.createElement('script');
    ahrefs_analytics_script.async = true;
    ahrefs_analytics_script.src = 'https://analytics.ahrefs.com/analytics.js';
    ahrefs_analytics_script.setAttribute('data-key', @json($ahrefsKey));
    document.getElementsByTagName('head')[0].appendChild(ahrefs_analytics_script);
</script>
@endif
