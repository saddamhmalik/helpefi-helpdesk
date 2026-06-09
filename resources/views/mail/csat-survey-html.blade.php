<p>Your support request <strong>{{ $ticket->number }}</strong> has been resolved.</p>

<p>How satisfied were you with our support?</p>

<p>
    @foreach($rateUrls as $rating => $url)
        <a href="{{ $url }}" style="display:inline-block;margin-right:8px;text-decoration:none;font-size:20px;">{{ str_repeat('★', $rating) }}{{ str_repeat('☆', 5 - $rating) }}</a>
    @endforeach
</p>

<p><a href="{{ $surveyUrl }}">Leave detailed feedback</a></p>

<p style="color:#64748b;font-size:12px;margin-top:24px;">Thank you for helping us improve.</p>
