<?php

namespace App\Enums;

/**
 * The two texts a quote carries. Fixed by SPEC §3 rather than user-defined:
 * every quote has exactly one intro and one footer, and the PDF template
 * places each of them somewhere specific.
 */
enum PremadeTextKey: string
{
    case Intro = 'intro';
    case Footer = 'footer';
}
