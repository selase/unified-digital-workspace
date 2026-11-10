<x-mail::message>
# Hello {{ $name }}

Use this link to {{ $purpose }} at {{ $organisation }}. It works once and expires in {{ $minutes }} minutes.

<x-mail::button :url="$url">Sign in</x-mail::button>

If you did not ask for this, you can ignore this email; nobody can sign in without it.
</x-mail::message>
