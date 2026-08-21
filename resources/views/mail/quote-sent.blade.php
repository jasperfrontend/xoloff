{{--
    The message a customer receives when a quote is sent (SPEC §7).

    Hand-written rather than built from Laravel's markdown mail components,
    which carry an English footer and a house style that is not Xolution's.
    Styles are inline because that is the only thing mail clients agree on -
    a stylesheet in the head is stripped by most of them.

    Dutch, like the PDF and for the same reason: this is read by the customer.
--}}
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offerte {{ $quote->id }}</title>
</head>
<body style="margin: 0; padding: 24px; background-color: #f5f8f9; font-family: Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #14181f;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width: 100%; max-width: 560px; margin: 0 auto; background-color: #ffffff; border-radius: 8px;">
    <tr>
        <td style="padding: 32px;">
            @if (filled($sender))
                <p style="margin: 0 0 24px; font-weight: bold;">{{ $sender }}</p>
            @endif

            <p style="margin: 0 0 16px;">Beste {{ $contact }},</p>

            <p style="margin: 0 0 16px;">
                Hierbij ontvangt u offerte {{ $quote->id }}. U kunt de offerte
                bekijken via onderstaande knop.
            </p>

            <p style="margin: 0 0 24px;">
                {{-- A real link, not a button image: images are blocked by
                     default in most clients and the link is the whole point
                     of this message. --}}
                <a href="{{ $link }}"
                   style="display: inline-block; padding: 12px 20px; background-color: #2e9238; border-radius: 6px; color: #ffffff; font-weight: bold; text-decoration: none;">
                    Bekijk de offerte
                </a>
            </p>

            @if ($validUntil !== null)
                <p style="margin: 0 0 16px;">
                    Deze offerte is geldig tot en met
                    <strong>{{ $validUntil->format('d-m-Y') }}</strong>.
                </p>
            @endif

            <p style="margin: 0 0 8px; color: #5b6472; font-size: 13px;">
                Werkt de knop niet? Kopieer dan deze link naar uw browser:
            </p>
            <p style="margin: 0; word-break: break-all; color: #5b6472; font-size: 13px;">
                {{ $link }}
            </p>
        </td>
    </tr>
</table>
</body>
</html>
