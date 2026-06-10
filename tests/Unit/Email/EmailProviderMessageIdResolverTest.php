<?php

namespace Tests\Unit\Email;

use App\Modules\Email\Services\EmailProviderMessageIdResolver;
use Illuminate\Mail\SentMessage as IlluminateSentMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Email;

class EmailProviderMessageIdResolverTest extends TestCase
{
    public function test_it_resolves_resend_provider_message_id_from_transport_header(): void
    {
        $email = (new Email)
            ->from('noreply@example.com')
            ->to('user@example.com')
            ->subject('Test')
            ->text('Body');

        $symfonySentMessage = new SentMessage($email, Envelope::create($email));
        $email->getHeaders()->addTextHeader('X-Resend-Email-ID', '  resend-email-id  ');

        $messageId = (new EmailProviderMessageIdResolver)->resolve(
            new IlluminateSentMessage($symfonySentMessage),
            'resend',
        );

        $this->assertSame('resend-email-id', $messageId);
    }

    public function test_it_ignores_non_provider_message_ids_for_log_and_array_mailers(): void
    {
        $email = (new Email)
            ->from('noreply@example.com')
            ->to('user@example.com')
            ->subject('Test')
            ->text('Body');

        $symfonySentMessage = new SentMessage($email, Envelope::create($email));
        $sentMessage = new IlluminateSentMessage($symfonySentMessage);
        $resolver = new EmailProviderMessageIdResolver;

        $this->assertNull($resolver->resolve($sentMessage, 'log'));
        $this->assertNull($resolver->resolve($sentMessage, 'array'));
    }
}
