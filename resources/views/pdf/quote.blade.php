{{--
    The quote as Xolution's customers receive it.

    One hardcoded template rather than a settings screen, which is the decision
    SPEC §6 records rather than defers: Gotenberg drives a real Chromium, so the
    branded output was always going to be HTML and CSS underneath, and for a
    single-tenant two-person tool a CSS edit and a redeploy is a perfectly
    reasonable way to change the branding.

    Dutch throughout, because this is the one screen a customer reads and the
    intro and footer are written in Dutch. The accent colour and the sans-serif
    stack are the two things worth changing if the branding moves.
--}}
@php
    use App\Support\Formatting\Dutch;
@endphp
    <!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Offerte {{ $quote->id }}</title>
    <style>
        :root {
            --ink: #14181f;
            --muted: #5b6472;
            --line: #e3e7ee;
            --accent: #0f6f7a;
            --tint: #f5f8f9;
        }

        /* No @page margin rule here on purpose: Chromium's print API owns
           the margins and ignores it, so declaring them twice would mean one
           of the two was always a lie. They are sent to Gotenberg instead,
           from QuotePdfController::MARGINS. */

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            /* Deliberately a stack of faces a container is likely to actually
               have. A font that is not installed silently becomes something
               else, and a quote is not the place to discover that. */
            font-family: 'Helvetica Neue', Helvetica, Arial, 'DejaVu Sans', sans-serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: var(--ink);
        }

        h1 {
            margin: 0;
            font-size: 22pt;
            font-weight: 600;
            letter-spacing: -0.01em;
        }

        h2 {
            margin: 0 0 6pt;
            font-size: 11pt;
            font-weight: 600;
        }

        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16pt;
            border-bottom: 2pt solid var(--accent);
            padding-bottom: 10pt;
        }

        .logo {
            max-height: 60pt;
            max-width: 180pt;
        }

        .reference {
            margin-top: 6pt;
            color: var(--muted);
            font-size: 9.5pt;
        }

        .reference span + span::before {
            content: '·';
            margin: 0 5pt;
        }

        .addressee {
            margin: 16pt 0 0;
        }

        .addressee .company {
            font-weight: 600;
        }

        .addressee .lines {
            color: var(--muted);
            white-space: pre-line;
        }

        .intro {
            margin: 16pt 0 0;
        }

        table.lines {
            width: 100%;
            margin-top: 18pt;
            border-collapse: collapse;
        }

        table.lines th {
            border-bottom: 1pt solid var(--line);
            padding: 6pt 6pt 6pt 0;
            text-align: left;
            font-size: 8.5pt;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
        }

        table.lines td {
            border-bottom: 1pt solid var(--line);
            padding: 8pt 6pt 8pt 0;
            vertical-align: top;
        }

        table.lines th.figure,
        table.lines td.figure {
            padding-right: 0;
            padding-left: 6pt;
            text-align: right;
            white-space: nowrap;
        }

        /* A line and the specs that belong to it must not be split across a
           page break: half a specification reads as a different offer. */
        table.lines tr {
            page-break-inside: avoid;
        }

        .description {
            font-weight: 600;
        }

        .specs {
            margin: 3pt 0 0;
            padding: 0;
            list-style: none;
            color: var(--muted);
            font-size: 9pt;
        }

        .specs li {
            display: inline-block;
            margin-right: 10pt;
        }

        .specs .key::after {
            content: ':';
            margin-right: 3pt;
        }

        .discount {
            color: var(--muted);
            font-size: 9pt;
        }

        .totals {
            margin-top: 14pt;
            /* Kept on the right and narrow, so the eye lands on the total
               rather than sweeping the width of the page to find it. */
            margin-left: auto;
            width: 62%;
            border-collapse: collapse;
            page-break-inside: avoid;
        }

        .totals td {
            padding: 4pt 0;
        }

        .totals td.figure {
            text-align: right;
            white-space: nowrap;
        }

        .totals tr.grand td {
            border-top: 1.5pt solid var(--ink);
            padding-top: 7pt;
            font-size: 12pt;
            font-weight: 600;
        }

        .totals .label {
            color: var(--muted);
        }

        .totals .grand .label {
            color: var(--ink);
        }

        .footer-text {
            margin-top: 22pt;
            border-top: 1pt solid var(--line);
            padding-top: 10pt;
            color: var(--muted);
            font-size: 8.5pt;
            page-break-inside: avoid;
        }

        .rich p {
            margin: 0 0 6pt;
        }

        .rich p:last-child {
            margin-bottom: 0;
        }

        .rich h2,
        .rich h3 {
            margin: 10pt 0 4pt;
            font-size: 11pt;
        }

        .rich ul,
        .rich ol {
            margin: 6pt 0;
            padding-left: 16pt;
        }

        .rich blockquote {
            margin: 6pt 0;
            border-left: 2pt solid var(--line);
            padding-left: 8pt;
        }

        .rich a {
            color: var(--accent);
        }

        .empty {
            margin-top: 18pt;
            border: 1pt dashed var(--line);
            border-radius: 4pt;
            padding: 16pt;
            text-align: center;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="header">
    <div>
        <h1>Offerte</h1>
        <div class="reference">
            <span>Nummer {{ $quote->id }}</span>
            <span>Versie {{ $version->version_number }}</span>
            <span>{{ $version->updated_at?->timezone('Europe/Amsterdam')->format('d-m-Y') }}</span>
        </div>
    </div>

    @if ($logo !== null)
        <img class="logo" src="{{ $logo }}" alt="{{ config('app.name') }}">
    @endif
</div>

<div class="addressee">
    <h2>Aan</h2>
    <div class="company">{{ $quote->customer->company_name }}</div>
    <div class="lines">{{ $quote->customer->contact_person }}
{{ $quote->customer->billing_address }}
{{ $quote->customer->country }}</div>
</div>

@if (filled($version->intro_text_snapshot))
    {{-- Written in the quote text editor and allowlisted down to a handful of
         tags by App\Support\Html\RichText before it was ever stored, so it is
         printed as the markup it is rather than escaped. --}}
    <div class="intro rich">{!! $version->intro_text_snapshot !!}</div>
@endif

@if ($totals->lines === [])
    <p class="empty">Deze offerte bevat nog geen regels.</p>
@else
    <table class="lines">
        <thead>
        <tr>
            <th>Omschrijving</th>
            <th class="figure">Aantal</th>
            <th class="figure">Stukprijs</th>
            <th class="figure">BTW</th>
            <th class="figure">Bedrag</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($totals->lines as $line)
            @php $lineItem = $lineItems[$line->lineItemId] ?? null; @endphp
            <tr>
                <td>
                    <div class="description">{{ $line->name }}</div>

                    @if ($lineItem?->specs)
                        <ul class="specs">
                            @foreach ($lineItem->specs as $key => $value)
                                <li><span class="key">{{ $key }}</span>{{ $value }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($line->lineDiscount !== '0.00')
                        <div class="discount">Korting {{ Dutch::money($line->lineDiscount) }}</div>
                    @endif
                </td>
                <td class="figure">{{ Dutch::amount($line->quantity) }}</td>
                <td class="figure">{{ Dutch::money($line->unitPriceExVat) }}</td>
                <td class="figure">{{ Dutch::percentage($line->taxClassPercentage) }}</td>
                <td class="figure">{{ Dutch::money($line->net) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($totals->quoteDiscount !== '0.00')
            <tr>
                <td class="label">Subtotaal voor korting</td>
                <td class="figure">{{ Dutch::money($totals->subtotalBeforeQuoteDiscount) }}</td>
            </tr>
            <tr>
                <td class="label">Korting op de offerte</td>
                <td class="figure">- {{ Dutch::money($totals->quoteDiscount) }}</td>
            </tr>
        @endif

        <tr>
            <td class="label">Subtotaal excl. btw</td>
            <td class="figure">{{ Dutch::money($totals->subtotal) }}</td>
        </tr>

        {{-- One line per tax class, because a quote may mix rates and a single
             "btw" figure would hide which rate applied to what. --}}
        @foreach ($totals->taxClassTotals as $taxClassTotal)
            <tr>
                <td class="label">
                    Btw {{ Dutch::percentage($taxClassTotal->percentage) }}
                    over {{ Dutch::money($taxClassTotal->net) }}
                </td>
                <td class="figure">{{ Dutch::money($taxClassTotal->vat) }}</td>
            </tr>
        @endforeach

        <tr class="grand">
            <td class="label">Totaal</td>
            <td class="figure">{{ Dutch::money($totals->total) }}</td>
        </tr>
    </table>
@endif

@if (filled($version->footer_text_snapshot))
    {{-- The mandatory legal text. Snapshotted with the version, so a quote
         already sent keeps the terms it was sent under (SPEC §3). --}}
    <div class="footer-text rich">{!! $version->footer_text_snapshot !!}</div>
@endif
</body>
</html>
