<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invitation $invitation)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Einladung zu OPCare');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invitation',
            text: 'emails.invitation-text',
            with: [
                'inviteUrl' => route('invitations.show', $this->invitation->token),
                'expiresAt' => $this->invitation->expires_at->format('d.m.Y H:i'),
                'invitedBy' => $this->invitation->invitedBy->name ?? 'Ihre Personalabteilung',
            ]
        );
    }
}
