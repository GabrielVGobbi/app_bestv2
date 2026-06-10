<?php

declare(strict_types=1);

namespace App\Modules\Email\Services;

use Illuminate\Mail\SentMessage;

class EmailProviderMessageIdResolver
{
    public function resolve(?SentMessage $sentMessage, ?string $mailer): ?string
    {
        if ($sentMessage === null || $mailer === null) {
            return null;
        }

        $mailer = strtolower($mailer);
        $symfonyMessage = $sentMessage->getSymfonySentMessage();

        if ($mailer === 'resend') {
            $resendId = $this->resendMessageId($symfonyMessage->getOriginalMessage());

            return $resendId !== '' ? $resendId : null;
        }

        if (in_array($mailer, ['ses', 'ses-v2', 'smtp', 'sendmail'], true)) {
            return $symfonyMessage->getMessageId();
        }

        return null;
    }

    private function resendMessageId(object $message): string
    {
        if (! method_exists($message, 'getHeaders')) {
            return '';
        }

        $headers = $message->getHeaders();

        if (! $headers->has('X-Resend-Email-ID')) {
            return '';
        }

        return trim($headers->get('X-Resend-Email-ID')->getBodyAsString());
    }
}
