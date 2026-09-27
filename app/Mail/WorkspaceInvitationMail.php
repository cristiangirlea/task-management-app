<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WorkspaceInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

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
                'acceptUrl' => $this->invitation->acceptUrl(),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
