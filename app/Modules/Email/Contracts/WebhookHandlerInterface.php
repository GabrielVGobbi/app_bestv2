<?php

declare(strict_types=1);

namespace App\Modules\Email\Contracts;

use Illuminate\Http\Request;

interface WebhookHandlerInterface
{
    /**
     * Verify the request signature from the provider.
     * Throw an exception or return false if the signature is invalid.
     */
    public function verifySignature(Request $request): bool;

    /**
     * Process the webhook payload and update the EmailLog accordingly.
     */
    public function handle(Request $request): void;
}
