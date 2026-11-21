<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mail;

/**
 * A mail message that can be stored locally (see LocalMailServer)
 * and/or handed to the MailManager for delivery.
 */
class LocalMail
{
    /** Database id, null until the mail has been saved. */
    public ?int $id = null;

    public string $subject = '';

    public string $body = '';

    public string $to = '';

    public string $reply_to = '';

    /** @var string[] */
    public array $cc = [];

    /** @var string[] */
    public array $bcc = [];

    /**
     * Attachments, in the same shape MailManager::send() accepts:
     * a path string, or ['path' => ..., 'name' => ..., 'type' => ...].
     * Only the references are stored, never the file contents.
     *
     * @var array<int, string|array<string, string>>
     */
    public array $attachments = [];

    public bool $is_html = false;

    /** Set by LocalMailServer when saved / loaded (Y-m-d H:i:s). */
    public ?string $created_at = null;

    /**
     * Send this mail through the given MailManager.
     *
     * The local-copy step is skipped, otherwise a mail sent from the
     * local store would be saved a second time.
     */
    public function send(MailManager $mailManager): bool
    {
        $options = [
            'html' => $this->is_html,
            'skip_local_copy' => true,
        ];

        if ($this->cc !== []) {
            $options['cc'] = $this->cc;
        }
        if ($this->bcc !== []) {
            $options['bcc'] = $this->bcc;
        }
        if ($this->reply_to !== '') {
            $options['reply_to'] = $this->reply_to;
        }
        if ($this->attachments !== []) {
            $options['attachments'] = $this->attachments;
        }

        return $mailManager->send($this->to, $this->subject, $this->body, $options);
    }

    /**
     * Build a LocalMail from the arguments/options MailManager::send() receives.
     */
    public static function fromSendArguments(string $to, string $subject, string $body, array $options = []): self
    {
        $mail = new self();
        $mail->to = $to;
        $mail->subject = $subject;
        $mail->body = $body;
        $mail->is_html = (bool) ($options['html'] ?? false);
        $mail->cc = array_values((array) ($options['cc'] ?? []));
        $mail->bcc = array_values((array) ($options['bcc'] ?? []));
        $mail->reply_to = (string) ($options['reply_to'] ?? '');
        $mail->attachments = array_values((array) ($options['attachments'] ?? []));

        return $mail;
    }

    public static function fromArray(array $data): self
    {
        $mail = new self();
        $mail->id = isset($data['id']) ? (int) $data['id'] : null;
        $mail->subject = (string) ($data['subject'] ?? '');
        $mail->body = (string) ($data['body'] ?? '');
        $mail->to = (string) ($data['to'] ?? '');
        $mail->reply_to = (string) ($data['reply_to'] ?? '');
        $mail->cc = array_values((array) ($data['cc'] ?? []));
        $mail->bcc = array_values((array) ($data['bcc'] ?? []));
        $mail->attachments = array_values((array) ($data['attachments'] ?? []));
        $mail->is_html = (bool) ($data['is_html'] ?? false);
        $mail->created_at = isset($data['created_at']) ? (string) $data['created_at'] : null;

        return $mail;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'to' => $this->to,
            'reply_to' => $this->reply_to,
            'cc' => $this->cc,
            'bcc' => $this->bcc,
            'attachments' => $this->attachments,
            'is_html' => $this->is_html,
            'created_at' => $this->created_at,
        ];
    }
}
