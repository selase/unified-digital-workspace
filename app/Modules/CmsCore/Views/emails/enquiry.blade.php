<x-mail::message>
# New {{ $type->label() }} enquiry

Someone used the **{{ $type->label() }}** form on {{ $siteName }}.

<x-mail::panel>
**Name:** {{ $visitorName }}

**Email:** {{ $visitorEmail }}

@if($phone)
**Phone:** {{ $phone }}

@endif
@if($organisation)
**Organisation:** {{ $organisation }}

@endif
</x-mail::panel>

**Message**

{{ $enquiryMessage }}

Reply to this email to respond to {{ $visitorName }} directly.
</x-mail::message>
