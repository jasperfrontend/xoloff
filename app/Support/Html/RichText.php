<?php

namespace App\Support\Html;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;

/**
 * Narrows editor output down to the small set of markup the quote template can
 * render.
 *
 * The two people who use xoloff are trusted, so this is not primarily an
 * anti-attacker measure. It is there because this content is rendered as HTML
 * in three places - the builder, the Gotenberg PDF (a real Chromium, which does
 * execute scripts while rendering) and later the client portal - and pasting
 * from Word or a website otherwise carries a payload of foreign styling and
 * scripts straight into all three.
 */
final class RichText
{
    /**
     * What the editor can produce and the quote template knows how to style.
     * Anything else is unwrapped, keeping its text.
     *
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'a',
        'h2', 'h3', 'blockquote',
    ];

    /**
     * Elements whose text is not content, so they go rather than get unwrapped.
     *
     * @var list<string>
     */
    private const DISCARDED_TAGS = ['script', 'style', 'head', 'iframe', 'object', 'embed', 'template'];

    /**
     * The only attribute that survives, and only on a link.
     */
    private const ALLOWED_LINK_SCHEMES = ['http', 'https', 'mailto'];

    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $body = $document->body;

        if ($body === null) {
            return '';
        }

        self::clean($body);

        $cleaned = '';

        foreach ($body->childNodes as $child) {
            $cleaned .= $document->saveHtml($child);
        }

        // An editor that has been cleared still submits a single empty
        // paragraph, which is not content and should not count as a filled-in
        // footer.
        return trim($cleaned) === '<p></p>' ? '' : trim($cleaned);
    }

    /**
     * Walks a snapshot of the child list, because unwrapping and removing
     * mutate the live NodeList underneath the loop.
     */
    private static function clean(Node $node): void
    {
        /** @var list<Node> $children */
        $children = iterator_to_array($node->childNodes);

        foreach ($children as $child) {
            if (! $child instanceof Element) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DISCARDED_TAGS, true)) {
                $child->remove();

                continue;
            }

            self::clean($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($child);

                continue;
            }

            self::stripAttributes($child, $tag);
        }
    }

    /**
     * Replaces an element with its own children, so unknown wrappers lose their
     * markup without losing what a person actually typed inside them.
     */
    private static function unwrap(Element $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            $element->parentNode?->insertBefore($child, $element);
        }

        $element->remove();
    }

    private static function stripAttributes(Element $element, string $tag): void
    {
        $href = $tag === 'a' ? $element->getAttribute('href') : null;

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->name);
        }

        if ($href !== null && self::isSafeLink($href)) {
            $element->setAttribute('href', $href);

            // Quote links point at the outside world, and the portal in M5 is a
            // page a customer opens - neither should hand the target a window
            // reference.
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    /**
     * Only schemes that cannot execute, or no scheme at all.
     */
    private static function isSafeLink(string $href): bool
    {
        // Whitespace and control characters are how a scheme gets smuggled past
        // a naive check: a browser strips them before resolving the URL, so this
        // has to strip them before inspecting it.
        $normalized = strtolower((string) preg_replace('/[\s\x00-\x1F\x7F]/', '', $href));

        if ($normalized === '') {
            return false;
        }

        foreach (self::ALLOWED_LINK_SCHEMES as $scheme) {
            if (str_starts_with($normalized, $scheme.':')) {
                return true;
            }
        }

        // A relative link carries no scheme. A protocol-relative "//host" does
        // carry one, it just borrows ours, so it counts as absolute and is
        // refused along with every other unlisted scheme.
        return ! str_starts_with($normalized, '//')
            && preg_match('/^[a-z][a-z0-9+.-]*:/', $normalized) !== 1;
    }
}
