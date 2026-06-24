<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    public function index()
    {
        // R21 — scoping: admin hanya melihat tim buatannya.
        $teams = Team::where('created_by', Auth::id())->latest()->get();
        return view('admin.teams.index', compact('teams'));
    }

    public function create()
    {
        return view('admin.teams.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $slugBase = Str::slug($validated['name']);
        $validated['slug'] = $slugBase ? $this->generateUniqueSlug($slugBase) : null;
        $validated['manager_token'] = $this->generateUniqueManagerToken($validated['name']);
        $validated['verification_status'] = 'pending';
        $validated['created_by'] = Auth::id(); // R21 — scoping tim per admin

        Team::create($validated);

        return redirect()->route('teams.index')
            ->with('success', 'Tim berhasil ditambahkan.');
    }

    // accessManager removed — management moved to tournament participants page

    public function edit(Team $team)
    {
        return view('admin.teams.edit', compact('team'));
    }

    public function update(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $slugBase = Str::slug($validated['name']);
        if ($slugBase && $slugBase !== $team->slug) {
            $validated['slug'] = $this->generateUniqueSlug($slugBase, $team->id);
        } else {
            $validated['slug'] = $team->slug;
        }

        $team->update($validated);

        return redirect()->route('teams.index')
            ->with('success', 'Data tim berhasil diperbarui.');
    }

    public function resetToken(\Illuminate\Http\Request $request, Team $team)
    {
        $team->update(['manager_token' => $this->generateUniqueManagerToken($team->name)]);

        // Prefer returning to the referring page (participants page),
        // otherwise fall back to the access manager listing.
        $referer = $request->headers->get('referer');
        if ($referer) {
            // If referer matches /tournaments/{id}/participants, redirect to named route
            $path = parse_url($referer, PHP_URL_PATH) ?: '';
            if (preg_match('#/tournaments/(\d+)/participants#', $path, $m)) {
                return redirect()->route('tournaments.participants.index', $m[1])
                    ->with('success', 'Manager token berhasil direset.');
            }

            return redirect()->to($referer)->with('success', 'Manager token berhasil direset.');
        }

        return redirect()->route('teams.index')
            ->with('success', 'Manager token berhasil direset.');
    }

    private function generateUniqueSlug(string $base, int $exceptId = null): string
    {
        $slug = $base;
        $count = 1;

        while (Team::where('slug', $slug)
            ->when($exceptId, fn($query) => $query->where('id', '!=', $exceptId))
            ->exists()) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    private function generateUniqueManagerToken(?string $name = null): string
    {
        $base = $name ?? 'TEAM';
        $prefix = strtoupper(Str::slug($base, '')) ?: 'TEAM';

        do {
            $token = $prefix . '-' . random_int(1000, 9999);
        } while (Team::where('manager_token', $token)->exists());

        return $token;
    }

    public function destroy(Team $team)
    {
        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', 'Tim berhasil dihapus.');
    }
}
