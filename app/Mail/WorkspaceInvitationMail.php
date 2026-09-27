<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use LogicException;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Captured now: only the model that issued the token knows it, and a
     * queued mail would reload the invitation from the database.
     */
    public readonly string $acceptUrl;

    public function __construct(public Invitation $invitation)
    {
        $this->acceptUrl = $invitation->acceptUrl()
            ?? throw new LogicException('An invitation email needs a freshly issued token.');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('invitation.mail.subject', ['workspace' => $this->invitation->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workspace-invitation',
            with: [
                'workspace' => $this->invitation->tenant->name,
                'inviter' => $this->invitation->inviter?->name,
                'acceptUrl' => $this->acceptUrl,
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
