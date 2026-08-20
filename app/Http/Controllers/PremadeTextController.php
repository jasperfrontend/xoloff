<?php

namespace App\Http\Controllers;

use App\Enums\PremadeTextKey;
use App\Http\Requests\PremadeTextRequest;
use App\Models\PremadeText;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The intro and footer every quote carries (SPEC §3). There are exactly two,
 * fixed by key, so this edits rather than creating or deleting: no index, no
 * create, no destroy.
 */
class PremadeTextController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('premade-texts/Edit', [
            'texts' => [
                PremadeTextKey::Intro->value => PremadeText::contentFor(PremadeTextKey::Intro),
                PremadeTextKey::Footer->value => PremadeText::contentFor(PremadeTextKey::Footer),
            ],
        ]);
    }

    /**
     * Editing these never rewrites history: quote versions carry their own
     * snapshot of both texts, taken when the version was saved, so a quote
     * already sent keeps the wording it was sent with (SPEC §3).
     */
    public function update(PremadeTextRequest $request): RedirectResponse
    {
        foreach ($request->validated() as $key => $content) {
            PremadeText::updateOrCreate(
                ['key' => $key],
                ['content' => (string) $content],
            );
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Quote texts saved.')]);

        return to_route('premade-texts.edit');
    }
}
