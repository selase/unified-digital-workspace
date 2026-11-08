@php use App\Modules\Finance\Support\Money; $isQuote = $document->type === 'quote'; @endphp
<x-mail::message>
# {{ $isQuote ? 'Quote' : 'Invoice' }} {{ $document->number }}

Hello {{ $document->contact->name }},

{{ $senderName }} has sent you {{ $isQuote ? 'a quote' : 'an invoice' }} for **{{ Money::format($document->total, $document->currency) }}**@if(! $isQuote && $document->due_date), due on **{{ $document->due_date->format('j F Y') }}**@endif.

<x-mail::button :url="$viewUrl">{{ $isQuote ? 'View quote' : 'View invoice and pay' }}</x-mail::button>

If you have any questions, reply to this email.
</x-mail::message>
