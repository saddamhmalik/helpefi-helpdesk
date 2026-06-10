<p>We received your request [{{ $ticket->number }}]:</p>

{!! $replyBodyHtml !!}

<hr style="border:0;border-top:1px solid #e2e8f0;margin:24px 0;">

<p><strong>Your message:</strong></p>

<div style="border-left:3px solid #cbd5e1;padding-left:12px;color:#475569;">
{!! $originalMessageBodyHtml !!}
</div>

<p>---<br>
To reply, respond to this email or include [{{ $ticket->number }}] in the subject line.</p>
