<?php

namespace App\Mail;

use App\Models\AppSettings;
use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The message that carries a quote's magic link to the customer (SPEC §7).
 *
 * Dutch throughout and hardcoded rather than translated, exactly like the PDF
 * template: the application runs in English for the two people who use it, and
 * this is the one thing they send that a customer reads.
 *
 * Not queued. There is no guarantee a worker is running, and a quote that
 * silently never left would be worse than one that fails loudly while someone
 * is still looking at the screen.
 */
class QuoteSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Quote $quote,
        public readonly AppSettings $settings,
    ) {}

    public function envelope(): Envelope
    {
        $sender = $this->settings->company_name;

        return new Envelope(
            // The address is Xolution's, from the mail configuration. The name
            // beside it is whatever the settings screen says, so the inbox
            // shows the company rather than the tool that produced this.
            from: new Address(
                (string) config('mail.from.address'),
                $sender ?: (string) config('mail.from.name'),
            ),
            subject: $sender === null
                ? "Offerte {$this->quote->id}"
                : "Offerte {$this->quote->id} van {$sender}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quote-sent',
            // A plain-text alternative as well as the HTML. This message goes
            // through antispamcloud on its way out (SPEC §7), and a message
            // with no text part scores worse for it.
            text: 'mail.quote-sent-text',
            with: [
                'link' => route('portal.quote', $this->quote->magic_link_token),
                'sender' => $this->settings->company_name,
                'contact' => $this->quote->customer->contact_person,
                'validUntil' => $this->quote->valid_until,
            ],
        );
    }

    /**
     * No PDF attached, on purpose.
     *
     * SPEC §7 tracks reading by portal visit. A customer who has the document
     * in the message has no reason to follow the link, which would leave every
     * quote looking unread. It would also put Gotenberg in the path of sending
     * a quote at all, so an idle container would stop the send rather than
     * only the download.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
