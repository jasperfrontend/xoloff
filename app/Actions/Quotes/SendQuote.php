<?php

namespace App\Actions\Quotes;

use App\Enums\AuditAction;
use App\Enums\QuoteStatus;
use App\Mail\QuoteSent;
use App\Models\AppSettings;
use App\Models\Quote;
use App\Support\Audit\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues a quote to its customer (SPEC §7): a magic link, a date it stops
 * being valid, a status saying it is out in the world, and an email carrying
 * the link.
 *
 * Issuing and delivering are deliberately not all-or-nothing. Once the link
 * exists and the status has moved, the quote has been sent - a mail server
 * that refused the message is a delivery problem, and rolling the send back
 * would leave a quote that says draft while its link is live.
 */
final class SendQuote
{
    /**
     * Long enough that guessing is not a strategy. This is the customer's
     * whole credential for the quote - there is no second factor and no
     * account behind it - so it is sized like a password rather than like an
     * identifier.
     */
    private const TOKEN_LENGTH = 64;

    /**
     * @param  int|null  $validityDays  how long it stays valid, or null to follow the application default
     * @return bool whether the email reached the mail server
     */
    public function handle(Quote $quote, ?int $validityDays = null): bool
    {
        // Kept across a re-send rather than rotated. Both links lead to the
        // same place - the portal always shows the current version - so
        // rotating would only break the link in an email the customer already
        // has, for no gain.
        $quote->magic_link_token ??= Str::random(self::TOKEN_LENGTH);

        // Null means "follow the application default", so a window that
        // matches the default is stored as no override at all. Storing 30
        // where the default is 30 would silently detach this quote from a
        // later change to it.
        $quote->validity_days_override = $validityDays === AppSettings::current()->default_validity_days
            ? null
            : $validityDays;

        $quote->sent_at = CarbonImmutable::now();
        $quote->valid_until = CarbonImmutable::today()->addDays($quote->validityDays());

        // Back to sent even from opened. A re-send is a new offer to look at,
        // and whether the customer has opened this one is exactly what the
        // status is for.
        $quote->transitionTo(QuoteStatus::Sent, [
            'valid_until' => $quote->valid_until->toDateString(),
            'validity_days' => $quote->validityDays(),
        ]);

        return $this->deliver($quote);
    }

    /**
     * Emails the link, and records the attempt either way.
     *
     * SPEC §2 asks for every send to be logged, and a failure is the case that
     * most needs it: whoever pressed Send has to know the customer does not
     * have the message, and later has to be able to see when that happened.
     * The exception is caught rather than left to bubble, because the quote
     * really was sent - what failed is only the carrying of it.
     */
    private function deliver(Quote $quote): bool
    {
        $quote->loadMissing('customer');

        try {
            Mail::to($quote->customer->email)
                ->send(new QuoteSent($quote, AppSettings::current()));
        } catch (Throwable $exception) {
            // The message text goes to the application log rather than the
            // audit log: it is a mail server's wording, occasionally with a
            // host or a credential in it, and the audit log is browsed.
            Log::error('Sending quote '.$quote->getKey().' failed.', ['exception' => $exception]);

            $this->record($quote, delivered: false);

            return false;
        }

        $this->record($quote, delivered: true);

        return true;
    }

    private function record(Quote $quote, bool $delivered): void
    {
        AuditLog::record($quote, AuditAction::NotificationSent, [
            ...$quote->auditContext(),
            'attributes' => [
                'channel' => 'email',
                'recipient' => $quote->customer->email,
                'delivered' => $delivered,
            ],
        ]);
    }
}
