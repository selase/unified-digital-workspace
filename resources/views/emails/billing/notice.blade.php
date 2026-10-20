<x-mail::message>
# {{ $messageRecord->kind === 'payment_receipt' ? 'Payment received. Thank you.' : ($messageRecord->kind === 'cycle_upcoming' ? 'Your next month of work' : ($messageRecord->kind === 'invoice_overdue' ? 'Your invoice is overdue' : 'Your workspace invoice')) }}

Hello {{ $details['tenant_name'] }},

@if($messageRecord->kind === 'cycle_upcoming')
Your next billing month begins **{{ \Carbon\Carbon::parse($details['cycle_start'])->format('j F Y') }}** on **{{ $details['plan'] }}**.

Your fixed monthly subscriptions and purchased website editor seats are **{{ $details['currency'] }} {{ number_format($details['fixed_amount'], 2) }}**.
@if($details['setup_amount'] > 0)
A separate one-time setup charge of **{{ $details['currency'] }} {{ number_format($details['setup_amount'], 2) }}** also applies to this first month.
@endif

This is an advance notice, **not an invoice or a request to pay this estimate**. Monthly active access, eligible email or dedicated database usage and applicable taxes are finalised after the month closes. Your billing contact will receive a reviewed invoice with a secure payment link once it is issued.

GHS invoices are paid through **Paystack**. Open the payment link in your invoice email, review the amount, and choose an available payment method on Paystack's secure checkout. We do not automatically debit your card. Keep this billing email up to date in Billing Settings.
@elseif($messageRecord->kind === 'payment_receipt')
We have verified payment of **{{ $details['currency'] }} {{ number_format($details['amount'], 2) }}** for invoice **{{ $details['number'] }}**.

Your receipt is attached as a PDF. Keep it with the invoice for your records.
<x-mail::button :url="$paymentUrl">View paid invoice and receipt</x-mail::button>
@else
Invoice **{{ $details['number'] }}** covers {{ \Carbon\Carbon::parse($details['period_start'])->format('j M Y') }}–{{ \Carbon\Carbon::parse($details['period_end'])->format('j M Y') }}.

**Amount due: {{ $details['currency'] }} {{ number_format($details['amount'], 2) }}**

**Due date: {{ $details['due_date'] ? \Carbon\Carbon::parse($details['due_date'])->format('j F Y') : 'See invoice' }}**

@if($details['currency'] === 'GHS')
Review the invoice and pay through **Paystack** using the secure link below. Payment methods are shown by Paystack at checkout. We never ask you to email card details or transfer money to a personal account.
@else
Review the invoice and use the secure checkout available in its currency.
@endif
<x-mail::button :url="$paymentUrl">Review invoice and pay</x-mail::button>

The invoice PDF is attached. Once payment is verified, we will record it against this invoice and send a receipt. If you have already paid, use the link to check the latest status before trying again.
@endif

@if($paymentUrl)
This private billing link expires after 30 days. Treat it like a financial document and share it only with the person responsible for payment. A later reminder includes a fresh link.
@endif

Thanks,<br>
Unified Digital Workspace
</x-mail::message>
