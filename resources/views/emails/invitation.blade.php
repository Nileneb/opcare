<div style="font-family:system-ui, sans-serif; color:#111; line-height:1.5; max-width:560px; margin:auto; padding:24px;">
    <h1 style="font-size:1.4rem; margin-bottom:0.5rem;">Du wurdest zu OPCare eingeladen</h1>
    <p style="margin:0 0 1rem;">{{ $invitedBy }} hat dich eingeladen, ein Benutzerkonto für OPCare zu erstellen.</p>

    <p style="margin:0.5rem 0;"><strong>E-Mail:</strong> {{ $invitation->email }}</p>
    <p style="margin:0.5rem 0;"><strong>Rolle:</strong> {{ ucfirst($invitation->role) }}</p>
    <p style="margin:0.5rem 0 1.5rem;"><strong>Gültig bis:</strong> {{ $expiresAt }}</p>

    <div style="text-align:center; margin:24px 0;">
        <a href="{{ $inviteUrl }}" style="display:inline-block; padding:14px 22px; background:#1f2937; color:#fff; text-decoration:none; border-radius:8px;">Einladung annehmen</a>
    </div>

    <p style="margin:0 0 0.75rem;">Sollte der Button nicht funktionieren, nutze folgenden Link:</p>
    <p style="word-break:break-all; margin:0 0 1.5rem;"><a href="{{ $inviteUrl }}">{{ $inviteUrl }}</a></p>

    <p style="margin:0; color:#6b7280;">Hinweis: Der Link ist 72 Stunden gültig.</p>
</div>
