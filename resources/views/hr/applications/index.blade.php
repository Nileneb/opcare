<x-layouts.app title="Bewerbungen">
    <section class="page-heading">
        <h1>Bewerbungen</h1>
        <p>Offene Bewerbungen und Statusübersicht.</p>
    </section>

    <div class="panel">
        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; align-items:center; margin-bottom:1rem;">
            <span>Status:</span>
            @foreach (['' => 'Alle', 'new' => 'Neu', 'reviewing' => 'In Prüfung', 'rejected' => 'Abgelehnt', 'hired' => 'Eingestellt'] as $key => $label)
                <a href="{{ route('hr.applications.index', array_filter(['status' => $key])) }}" class="btn btn-ghost btn-sm {{ $status === $key ? 'is-active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>

        @if ($applications->isEmpty())
            <p>Es sind noch keine Bewerbungen eingegangen.</p>
        @else
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>E-Mail</th>
                        <th>Stelle</th>
                        <th>Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($applications as $application)
                        <tr>
                            <td>{{ $application->full_name }}</td>
                            <td>{{ $application->email }}</td>
                            <td>{{ $application->position_applied }}</td>
                            <td>
                                @php
                                    $colors = ['new' => 'blue', 'reviewing' => 'yellow', 'rejected' => 'red', 'hired' => 'green'];
                                @endphp
                                <span class="badge badge-{{ $colors[$application->status] ?? 'gray' }}">{{ ucfirst($application->status) }}</span>
                            </td>
                            <td><a href="{{ route('hr.applications.show', $application) }}">Ansehen</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $applications->links() }}
        @endif
    </div>
</x-layouts.app>
