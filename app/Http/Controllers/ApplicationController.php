<?php

namespace App\Http\Controllers;

use App\Mail\InvitationMail;
use App\Models\Application;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function create(): View
    {
        return view('apply.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position_applied' => ['required', 'string', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $cvPath = null;
        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('applications/cv', config('opcare.media_disk'));
        }

        Application::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'position_applied' => $data['position_applied'],
            'cover_letter' => $data['cover_letter'] ?? null,
            'cv_path' => $cvPath,
            'status' => 'new',
        ]);

        return redirect()->route('apply.thanks');
    }

    public function index(Request $request): View
    {
        $status = $request->query('status');

        $applications = Application::when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('hr.applications.index', compact('applications', 'status'));
    }

    public function show(Application $application): View
    {
        return view('hr.applications.show', compact('application'));
    }

    public function updateStatus(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,reviewing,rejected,hired'],
        ]);

        $application->update($data);

        if ($data['status'] === 'hired') {
            $invitation = Invitation::create([
                'email' => $application->email,
                'role' => 'employee',
                'invited_by' => Auth::id(),
                'token' => Str::uuid(),
                'expires_at' => now()->addHours(72),
            ]);

            Mail::to($invitation->email)->queue(new InvitationMail($invitation));
        }

        return back()->with('success', 'Status aktualisiert.');
    }
}
