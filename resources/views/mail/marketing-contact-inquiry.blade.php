New contact inquiry for {{ $appName }}

Topic: {{ $topicLabel }}
Name: {{ $name }}
Email: {{ $email }}
@if ($company)
Company: {{ $company }}
@endif

Message:
{{ $inquiryMessage }}

---
Reply directly to {{ $email }} to respond.
