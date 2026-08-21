{{--
    The plain-text half of the same message. Not a nicety: this mail leaves
    through antispamcloud (SPEC §7), and a message with no text part scores
    worse for it.
--}}
@if (filled($sender)){{ $sender }}

@endif
Beste {{ $contact }},

Hierbij ontvangt u offerte {{ $quote->id }}. U kunt de offerte bekijken via deze link:

{{ $link }}
@if ($validUntil !== null)

Deze offerte is geldig tot en met {{ $validUntil->format('d-m-Y') }}.
@endif
