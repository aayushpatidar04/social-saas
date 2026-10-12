<?php

namespace App\Mail;

use App\Models\Organization;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class OrganizationInvitationMail extends Mailable
{
    public function __construct(public Organization $organization, public string $role, public string $url)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're invited to {$this->organization->name} on ".config('app.name'));
    }

    public function content(): Content
    {
        $org = e($this->organization->name);
        $url = e($this->url);

        return new Content(htmlString: "<p>You have been invited to join <strong>{$org}</strong> as <strong>{$this->role}</strong>.</p>"
            ."<p><a href=\"{$url}\">Accept the invitation and set your password</a></p>"
            .'<p>This link is valid for 7 days. If you did not expect this email you can ignore it.</p>');
    }
}
