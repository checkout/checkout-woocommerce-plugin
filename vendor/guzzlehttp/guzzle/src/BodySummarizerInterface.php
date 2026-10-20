<?php

namespace CheckoutComWC\Vendor\GuzzleHttp;

use CheckoutComWC\Vendor\Psr\Http\Message\MessageInterface;

interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
