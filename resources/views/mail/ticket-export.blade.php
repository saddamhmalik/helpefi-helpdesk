Ticket export: [{{ $ticket->number }}] {{ $ticket->subject }}

The ticket has been exported as a PDF attachment@if($includeConversation) including the full conversation@else without the conversation@endif.

---
{{ config('app.name') }}
