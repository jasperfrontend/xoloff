<?php

namespace App\Enums;

/**
 * Where a quote stands (SPEC §3).
 *
 * The five the spec names and no more. "Countered" was dropped rather than
 * deferred (SPEC §11), replaced by the deny reason that arrives in M5.
 *
 * Stored as a string so the database stays readable, and ordered here the way
 * a quote moves: a draft is sent, a sent quote is opened, an opened one is
 * approved or denied.
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Opened = 'opened';
    case Approved = 'approved';
    case Denied = 'denied';

    /**
     * How the status reads on screen. Kept here rather than in the pages, so
     * the wording cannot drift between the list and the quote it lists.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Opened => __('Opened'),
            self::Approved => __('Approved'),
            self::Denied => __('Denied'),
        };
    }
}
