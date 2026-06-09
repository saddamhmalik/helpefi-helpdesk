{{ $agent->name }} sent a message regarding ticket {{ $ticket->number }}:

{{ $replyBody }}

---
Reply to this email to continue the side conversation. Do not remove [{{ $ticket->number }} Side #{{ $conversation->id }}] from the subject line.
