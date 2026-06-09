<p>{{ $agent->name }} sent a message regarding ticket <strong>{{ $ticket->number }}</strong>:</p>

{!! $replyBodyHtml !!}

<p style="color:#64748b;font-size:12px;margin-top:24px;">
    Reply to this email to continue the side conversation.
    Keep <strong>[{{ $ticket->number }} Side #{{ $conversation->id }}]</strong> in the subject line.
</p>
