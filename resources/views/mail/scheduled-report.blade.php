Your scheduled helpdesk report "{{ $report->name }}" is attached.

Report type: {{ $typeLabel ?? $report->type }}
Frequency: {{ $schedule->frequency }}
@if($schedule->frequency === 'weekly')
Day: {{ ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][$schedule->weekday] ?? $schedule->weekday }}
@endif
Format: {{ strtoupper($schedule->format) }}

Open reports: {{ config('app.url') }}/reports

Thanks,
{{ config('app.name') }}
