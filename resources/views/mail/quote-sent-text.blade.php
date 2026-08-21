{{--
    The plain-text half of the same message. Not a nicety: this mail leaves
    through antispamcloud (SPEC §7), and a message with no text part scores
    worse for it.

    Values are printed raw rather than through {{ }}. This half is text/plain,
    where HTML escaping has nothing to escape for and everything to spoil: a
    customer called "Anna O'Brien" would be greeted as "Anna O&#039;Brien",
    and a link with a query string would arrive with &amp; in it. The HTML half
    escapes exactly as it should, which is the difference this file exists to
    respect.
--}}
@if (filled($sender)){!! $sender !!}

@endif
Beste {!! $contact !!},

Hierbij ontvangt u offerte {!! $quote->id !!}. U kunt de offerte bekijken via deze link:

{!! $link !!}
@if ($validUntil !== null)

Deze offerte is geldig tot en met {!! $validUntil->format('d-m-Y') !!}.
@endif
