<?php

namespace Tests\Unit;

use App\Support\Html\RichText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * This content ends up rendered as HTML in three places: the builder, the
 * Gotenberg PDF (a real Chromium, which runs scripts while rendering) and the
 * client portal in M5. What survives this class is what all three receive.
 */
class RichTextTest extends TestCase
{
    public function test_it_keeps_the_markup_the_editor_produces()
    {
        $html = '<p>Beste <strong>klant</strong>, <em>hierbij</em> onze <u>offerte</u>.</p>'
            .'<h2>Voorwaarden</h2><ul><li>Een</li><li>Twee</li></ul>'
            .'<ol><li>Eerst</li></ol><blockquote><p>Let op</p></blockquote>'
            .'<h3>Klein</h3><p>Regel<br>Regel</p><p><s>Vervallen</s></p>';

        $this->assertSame($html, RichText::sanitize($html));
    }

    public function test_it_discards_a_script_rather_than_leaving_its_source_as_text()
    {
        $this->assertSame(
            '<p>Hallo</p>',
            RichText::sanitize('<p>Hallo</p><script>alert(1)</script>'),
        );
    }

    /**
     * Pasting from Word or a website is the realistic way foreign markup gets
     * in, and it arrives wrapped in layers of divs and spans. The wrapper goes,
     * what a person actually typed stays.
     */
    public function test_it_unwraps_unknown_elements_but_keeps_their_text()
    {
        $this->assertSame(
            '<p>Van elders <strong>geplakt</strong></p>',
            RichText::sanitize('<div><p><span style="font-family: Calibri">Van elders <strong>geplakt</strong></span></p></div>'),
        );
    }

    public function test_it_strips_every_attribute_except_a_safe_link()
    {
        $this->assertSame(
            '<p>Tekst</p>',
            RichText::sanitize('<p class="mso-normal" style="color:red" onclick="steal()" data-x="1">Tekst</p>'),
        );
    }

    public function test_it_keeps_a_safe_link_and_defends_the_target_window()
    {
        $this->assertSame(
            '<p><a href="https://xolution.nl" rel="noopener noreferrer">Xolution</a></p>',
            RichText::sanitize('<p><a href="https://xolution.nl" target="_blank" onclick="x()">Xolution</a></p>'),
        );
    }

    /**
     * The link text is deliberately kept when the address is refused: dropping
     * the whole element would silently delete a sentence.
     *
     * @param  non-empty-string  $href
     */
    #[DataProvider('unsafeLinks')]
    public function test_it_refuses_an_address_that_could_execute(string $href)
    {
        $sanitized = RichText::sanitize('<p><a href="'.$href.'">Klik</a></p>');

        $this->assertStringNotContainsString('href', $sanitized);
        $this->assertStringContainsString('Klik', $sanitized);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeLinks(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript in capitals' => ['JavaScript:alert(1)'],
            // A browser strips these before resolving the address, so a scheme
            // check that does not strip them first is checking the wrong string.
            'javascript split by a tab' => ["java\tscript:alert(1)"],
            'javascript split by a newline' => ["java\nscript:alert(1)"],
            'javascript with leading spaces' => ['   javascript:alert(1)'],
            'data url' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            // Borrows whichever scheme the page was served over, so it is an
            // absolute address to somewhere else rather than a relative one.
            'protocol relative' => ['//evil.example/x'],
        ];
    }

    /**
     * @param  non-empty-string  $href
     */
    #[DataProvider('safeLinks')]
    public function test_it_accepts_an_address_that_cannot_execute(string $href)
    {
        $this->assertStringContainsString(
            'href="'.$href.'"',
            RichText::sanitize('<p><a href="'.$href.'">Klik</a></p>'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeLinks(): array
    {
        return [
            'https' => ['https://xolution.nl/voorwaarden'],
            'http' => ['http://xolution.nl'],
            'mailto' => ['mailto:stephan@xolution.nl'],
            'relative path' => ['/voorwaarden.pdf'],
        ];
    }

    /**
     * An editor that has been cleared still submits one empty paragraph. The
     * footer carries the legal disclaimer and may not be blank, so that stray
     * tag must not pass for content.
     */
    public function test_an_emptied_editor_counts_as_empty()
    {
        $this->assertSame('', RichText::sanitize('<p></p>'));
        $this->assertSame('', RichText::sanitize(''));
        $this->assertSame('', RichText::sanitize('   '));
    }

    public function test_it_leaves_accented_characters_alone()
    {
        $this->assertSame(
            '<p>Prijzen zijn één keer per jaar indexeerbaar. Größe: 5 m².</p>',
            RichText::sanitize('<p>Prijzen zijn één keer per jaar indexeerbaar. Größe: 5 m².</p>'),
        );
    }

    public function test_it_does_not_mistake_text_for_markup()
    {
        $this->assertSame(
            '<p>Levering &lt; 5 dagen &amp; altijd op maat</p>',
            RichText::sanitize('<p>Levering &lt; 5 dagen &amp; altijd op maat</p>'),
        );
    }

    /**
     * Running the result back through must not change it again. If it did, what
     * is stored would differ from what is shown, and the difference would grow
     * with every save.
     */
    public function test_it_is_stable_when_applied_twice()
    {
        $once = RichText::sanitize('<div><p>Hallo <a href="javascript:x" onclick="y">daar</a></p><script>z</script></div>');

        $this->assertSame($once, RichText::sanitize($once));
    }
}
