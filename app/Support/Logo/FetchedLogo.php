<?php

namespace App\Support\Logo;

/**
 * An image that has been pulled down and is ready to be stored or printed.
 */
final readonly class FetchedLogo
{
    /**
     * @param  int|null  $width  pixels, for a raster. An SVG has no pixel width,
     *                           which is the whole reason it scales.
     */
    public function __construct(
        public string $mime,
        public string $bytes,
        public ?int $width = null,
    ) {}

    public function isVector(): bool
    {
        return $this->mime === 'image/svg+xml';
    }

    /**
     * The form the quote template needs.
     *
     * Embedded rather than linked, for the same reason the uploaded logo was:
     * Gotenberg renders in its own container and an address pointing back at
     * this application is not necessarily one it can reach. It also keeps the
     * logo's bytes inside the rendered document, which is what M6 hashes at
     * signing - a linked image would leave the hash covering an address rather
     * than what the signer actually saw.
     */
    public function toDataUri(): string
    {
        return 'data:'.$this->mime.';base64,'.base64_encode($this->bytes);
    }
}
