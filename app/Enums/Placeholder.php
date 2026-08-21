<?php

namespace App\Enums;

use App\Models\Customer;

/**
 * What Stephan can drop into a quote text so it addresses the customer it is
 * actually going to.
 *
 * Not in the original spec. It exists because the alternative was a hardcoded
 * greeting, and any hardcoded greeting is an opinion about how Xolution talks
 * to its clients - "Beste klant" is cold, "Beste Daan" is a guess at how
 * formal he wants to be. Placeholders hand that decision back to whoever
 * writes the text.
 *
 * The triple-bracket syntax is deliberately unlike anything Markdown, Blade or
 * a WYSIWYG editor treats as special, so it survives being typed, pasted and
 * sanitised without needing to be escaped anywhere.
 */
enum Placeholder: string
{
    case CustomerSalutation = 'customer_salutation';
    case CustomerFirstName = 'customer_first_name';
    case CustomerLastName = 'customer_last_name';
    case CustomerFullName = 'customer_full_name';
    case CustomerCompanyName = 'customer_company_name';

    /**
     * The token as it is written in a text.
     */
    public function token(): string
    {
        return "[[[{$this->value}]]]";
    }

    /**
     * How it is named in the editor. English, like every screen the two of
     * them use, even though what it produces is Dutch.
     */
    public function label(): string
    {
        return match ($this) {
            self::CustomerSalutation => __('Salutation'),
            self::CustomerFirstName => __('First name'),
            self::CustomerLastName => __('Last name'),
            self::CustomerFullName => __('Full name'),
            self::CustomerCompanyName => __('Company'),
        };
    }

    /**
     * What it turns into, shown beside the label so nobody has to send a quote
     * to find out.
     */
    public function example(): string
    {
        return match ($this) {
            self::CustomerSalutation => __('heer'),
            self::CustomerFirstName => __('Daan'),
            self::CustomerLastName => __('Daansen'),
            self::CustomerFullName => __('Daan Daansen'),
            self::CustomerCompanyName => __('Daan Test BV'),
        };
    }

    /**
     * What it becomes for a given customer.
     *
     * A blank string rather than null throughout: a customer with no
     * salutation should leave a gap in the sentence, not the word "null". The
     * gap itself collapses, because everything this feeds is HTML and HTML
     * collapses runs of whitespace - "Geachte  Jansen" reads as "Geachte
     * Jansen" on the page and in the PDF alike.
     */
    public function valueFor(Customer $customer): string
    {
        return match ($this) {
            // Spelled out rather than written as a nullsafe chain with ??.
            // That form works only because ?? quietly suppresses reading a
            // property on null, which is too subtle to lean on for the one
            // value here that is genuinely allowed to be absent.
            self::CustomerSalutation => $customer->salutation === null
                ? ''
                : $customer->salutation->value,
            self::CustomerFirstName => $customer->first_name,
            self::CustomerLastName => $customer->last_name,
            self::CustomerFullName => $customer->contact_person,
            self::CustomerCompanyName => $customer->company_name,
        };
    }
}
