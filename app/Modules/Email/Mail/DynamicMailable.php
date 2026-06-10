<?php

declare(strict_types=1);

namespace App\Modules\Email\Mail;

use App\Modules\Email\Exceptions\TemplateNotFoundException;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DynamicMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $variables
     */
    public function __construct(
        private readonly string $emailTemplate,
        private readonly array $variables = [],
        private readonly ?string $replyToAddress = null,
    ) {}

    public function build(): self
    {
        if (! view()->exists($this->emailTemplate)) {
            throw TemplateNotFoundException::forTemplate($this->emailTemplate);
        }

        $mailable = $this->view($this->emailTemplate, $this->variables);

        if ($this->replyToAddress) {
            $mailable->replyTo($this->replyToAddress);
        }

        return $mailable;
    }
}
