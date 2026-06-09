<?php

namespace App\Http\Controllers;

use App\Domains\Identity\Actions\RegisterUser;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rules\Password;

class InvitationController extends Controller
{
    private function authorizeHr(): void
    {
        abort_unless(
            auth()->user()?->isSuperAdmin() || auth()->user()?->hasAnyRole(['admin', 'pflegefachkraft']),
            403,
        );
    }

    public function create(): View
    {
        $this->authorizeHr();

        $invitations = Invitation::with('invitedBy')
            ->latest()
            ->paginate(20);

        return view('hr.invitations.create', compact('invitations'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeHr();
        $data = $request->validate([
            'email' => ['required', 'email', 'unique:users,email', 'unique:invitations,email'],
            'role' => ['required', 'in:employee,nurse,admin'],
        ]);

        $invitation = Invitation::create([
            'email' => $data['email'],
            'role' => $data['role'],
            'invited_by' => $request->user()->getKey(),
            'token' => Str::uuid(),
            'expires_at' => now()->addHours(72),
        ]);

        Mail::to($invitation->email)->queue(new InvitationMail($invitation));

        return back()->with('success', 'Einladung wurde versendet.');
    }

    public function show(string $token): View
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->isExpired() || $invitation->isAccepted()) {
            return view('auth.invite-invalid', [
                'message' => 'Dieser Einladungslink ist nicht mehr gültig. Bitte wenden Sie sich an Ihre Personalabteilung.',
            ]);
        }

        return view('auth.invite', compact('invitation'));
    }

    public function accept(Request $request, string $token): View|RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        if (! $invitation || $invitation->isExpired() || $invitation->isAccepted()) {
            return view('auth.invite-invalid', [
                'message' => 'Dieser Einladungslink ist nicht mehr gültig. Bitte wenden Sie sich an Ihre Personalabteilung.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = app(RegisterUser::class)
            ->handle($data['name'], $invitation->email, $data['password'], null, $invitation->role);

        $invitation->update(['accepted_at' => now()]);

        Auth::login($user);

        return redirect()->route('overview');
    }
}
