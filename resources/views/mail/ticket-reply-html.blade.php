<!DOCTYPE html>
<html>
<body style="font-family: sans-serif; color: #1e293b; line-height: 1.5;">
    <p><strong>{{ $agent->name }}</strong> replied to your request <strong>[{{ $ticket->number }}]</strong>:</p>
    <div>{!! $replyBodyHtml !!}</div>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;">
    <p style="font-size: 13px; color: #64748b;">
        To reply, respond to this email or include [{{ $ticket->number }}] in the subject line.
    </p>
</body>
</html>
