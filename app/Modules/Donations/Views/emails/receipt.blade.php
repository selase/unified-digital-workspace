@php use App\Modules\Finance\Support\Money; @endphp
<x-mail::message>
# Thank you, {{ $donation->donor->name }}

{{ $senderName }} gratefully acknowledges your gift of **{{ Money::format($donation->amount, $donation->currency) }}** received on {{ $donation->date->format('j F Y') }}@if($donation->campaign), for **{{ $donation->campaign->name }}**@endif.

Your receipt {{ $donation->receipt_number }} is attached.

If you have any questions, reply to this email.
</x-mail::message>
