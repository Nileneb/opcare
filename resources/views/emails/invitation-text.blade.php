Sie wurden von {{ $invitedBy }} zu OPCare eingeladen.

E-Mail: {{ $invitation->email }}
Rolle: {{ ucfirst($invitation->role) }}
Gültig bis: {{ $expiresAt }}

Bitte verwenden Sie diesen Link, um Ihr Konto zu erstellen:

{{ $inviteUrl }}

Der Link ist 72 Stunden gültig.
