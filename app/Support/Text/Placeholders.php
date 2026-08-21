<?php

namespace App\Support\Text;

use App\Enums\Placeholder;
use App\Models\Customer;

/**
 * Turns the placeholders in a quote text into the customer it is going to.
 *
 * Filled once, when a version is saved, not each time a page is rendered.
 * SPEC §3 snapshots the intro and footer onto the version so that a quote
 * someone has already read or signed cannot change wording afterwards, and a
 * placeholder that resolved late would be a hole straight through that: the
 * document hash M6 takes at signing would stop meaning anything.
 */
final class Placeholders
{
    /**
     * Three brackets, and only lower-case words inside. Anything else is left
     * alone rather than guessed at - "[[[" appearing in prose is far-fetched,
     * but it is not this class's business to decide it was meant as a token.
     */
    private const SYNTAX = '/\[\[\[([a-z_]+)\]\]\]/';

    /**
     * @param  string  $text  sanitised HTML from the quote text editor
     */
    public static function fill(string $text, Customer $customer): string
    {
        return (string) preg_replace_callback(
            self::SYNTAX,
            function (array $match) use ($customer): string {
                $placeholder = Placeholder::tryFrom($match[1]);

                // A misspelled token leaves nothing behind. The customer
                // seeing "[[[customer_frist_name]]]" in their quote is a worse
                // outcome than a missing word, and the editor lists every
                // spelling that works so this should be rare.
                if ($placeholder === null) {
                    return '';
                }

                // Escaped, because this lands in HTML. A customer called
                // "Tom & Jerry BV" would otherwise produce markup that is at
                // best wrong, and the values reach the PDF and the portal
                // alike.
                return e($placeholder->valueFor($customer));
            },
            $text,
        );
    }

    /**
     * Every placeholder there is, for the editor to offer. Built from the enum
     * so the list someone reads and the list that resolves cannot drift.
     *
     * @return array<int, array{token: string, label: string, example: string}>
     */
    public static function all(): array
    {
        return array_map(fn (Placeholder $placeholder): array => [
            'token' => $placeholder->token(),
            'label' => $placeholder->label(),
            'example' => $placeholder->example(),
        ], Placeholder::cases());
    }
}
