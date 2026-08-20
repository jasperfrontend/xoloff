<?php

namespace Database\Seeders;

use App\Enums\PremadeTextKey;
use App\Models\PremadeText;
use Illuminate\Database\Seeder;

/**
 * Both texts exist from the first migration onward, so a quote saved before
 * anyone visits the editor still gets a real snapshot rather than an empty one.
 *
 * These are starting points to be rewritten in the editor, not fixed copy.
 * Idempotent, and deliberately does not overwrite: re-seeding a live database
 * must not throw away wording Stephan has since edited.
 */
class PremadeTextSeeder extends Seeder
{
    public function run(): void
    {
        $texts = [
            PremadeTextKey::Intro->value => '<p>Beste klant,</p><p>Hierbij ontvangt u onze offerte. '
                .'Heeft u vragen of wilt u iets aangepast zien, laat het ons gerust weten.</p>',

            PremadeTextKey::Footer->value => '<p>Op al onze offertes en overeenkomsten zijn onze algemene '
                .'voorwaarden van toepassing. Deze zijn op aanvraag beschikbaar en worden op verzoek kosteloos '
                .'toegezonden.</p>',
        ];

        foreach ($texts as $key => $content) {
            PremadeText::firstOrCreate(['key' => $key], ['content' => $content]);
        }
    }
}
