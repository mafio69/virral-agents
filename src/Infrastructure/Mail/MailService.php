<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;

final readonly class MailService
{
    public function __construct(private string $dsn)
    {
    }

    public function send(string $to, string $subject, string $body): void
    {
        $email = (new Email())
            ->from('noreply@virral-agents.local')
            ->to($to)
            ->subject($subject)
            ->html($body);

        // Konfiguracja poczty jest potrzebna dopiero przy wysyłaniu, nie podczas logowania.
        $mailer = new Mailer(Transport::fromDsn($this->dsn));
        $mailer->send($email);
    }
}
