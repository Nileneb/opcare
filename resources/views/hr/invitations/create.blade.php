<x-layouts.app title="Einladungen verwalten">
    <section class="page-heading">
        <h1>Einladungen versenden</h1>
        <p>Hier können Sie neue Einladungen erzeugen und den Status offener Einladungen einsehen.</p>
    </section>

    @if (session('success'))
        <div class="panel" style="border-color:var(--green-400); color:var(--green-900); background:var(--green-50);">
            {{ session('success') }}
        </div>
    @endif

    <div class="panel">
        <form method="POST" action="{{ route('hr.invitations.store') }}">
            @csrf
            <div class="field">
                <label for="email">E-Mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required />
                @error('email') <span class="err">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label for="role">Rolle</label>
                <select id="role" name="role" required>
                    <option value="employee" @selected(old('role') === 'employee')>Mitarbeiter</option>
                    <option value="nurse" @selected(old('role') === 'nurse')>Pflegekraft</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
                @error('role') <span class="err">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary btn-block">Einladung senden</button>
        </form>
    </div>

    <div class="panel">
        <h2>Offene Einladungen</h2>

        @if ($invitations->isEmpty())
            <p>Es liegen derzeit keine Einladungen vor.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>E-Mail</th>
                        <th>Rolle</th>
                        <th>Eingeladen von</th>
                        <th>Gültig bis</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invitations as $invitation)
                        <tr>
                            <td>{{ $invitation->email }}</td>
                            <td>{{ ucfirst($invitation->role) }}</td>
                            <td>{{ $invitation->invitedBy?->name ?? '–' }}</td>
                            <td>{{ $invitation->expires_at->format('d.m.Y H:i') }}</td>
                            <td>{{ $invitation->isAccepted() ? 'Angenommen' : ($invitation->isExpired() ? 'Abgelaufen' : 'Ausstehend') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $invitations->links() }}
        @endif
    </div>
</x-layouts.app>
