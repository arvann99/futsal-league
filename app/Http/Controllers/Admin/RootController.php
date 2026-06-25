<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RootController extends Controller
{
    /**
     * R22 — Daftar permintaan upgrade untuk ditinjau admin root.
     */
    public function requests(Request $request)
    {
        $status = $request->query('status'); // pending|approved|rejected|null(=semua)

        $requests = SubscriptionRequest::with(['user', 'reviewer'])
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true),
                fn ($q) => $q->where('status', $status))
            // pending dulu, lalu terbaru.
            ->orderByRaw("FIELD(status,'pending','approved','rejected')")
            ->latest()
            ->get();

        $pendingCount = SubscriptionRequest::where('status', 'pending')->count();

        return view('admin.root.requests', compact('requests', 'status', 'pendingCount'));
    }

    /**
     * R22 — Setujui pembayaran → naikkan paket user. Atomik (lock baris) agar
     * approval ganda tidak menaikkan plan berulang.
     */
    public function approve(SubscriptionRequest $subscriptionRequest)
    {
        $valid = ['pro', 'ultimate'];

        try {
            DB::transaction(function () use ($subscriptionRequest, $valid) {
                $req = SubscriptionRequest::where('id', $subscriptionRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($req->status !== 'pending') {
                    throw new \RuntimeException('ALREADY_REVIEWED');
                }
                // Validasi defensif: plan yang disetujui harus valid.
                if (! in_array($req->requested_plan, $valid, true)) {
                    throw new \RuntimeException('INVALID_PLAN');
                }

                // 'plan' tidak mass-assignable (hardening anti privilege-escalation) —
                // di-set eksplisit di sini, satu-satunya jalur kenaikan paket (setelah ACC root).
                $req->user->forceFill(['plan' => $req->requested_plan])->save();
                $req->update([
                    'status' => 'approved',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            $msg = $e->getMessage() === 'INVALID_PLAN'
                ? 'Paket yang diminta tidak valid.'
                : 'Permintaan ini sudah ditinjau sebelumnya.';
            return back()->with('error', $msg);
        }

        $name = $subscriptionRequest->fresh()->user?->name ?? 'Admin';
        return back()->with('success', "Pembayaran disetujui. Paket {$name} dinaikkan ke " . ucfirst($subscriptionRequest->fresh()->requested_plan) . '.');
    }

    /**
     * R22 — Tolak pembayaran (plan user tidak berubah). Bukti TF dihapus.
     */
    public function reject(Request $request, SubscriptionRequest $subscriptionRequest)
    {
        $validated = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        try {
            DB::transaction(function () use ($subscriptionRequest, $validated) {
                $req = SubscriptionRequest::where('id', $subscriptionRequest->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($req->status !== 'pending') {
                    throw new \RuntimeException('ALREADY_REVIEWED');
                }

                $req->update([
                    'status' => 'rejected',
                    'reviewed_by' => Auth::id(),
                    'reviewed_at' => now(),
                    'note' => $validated['note'] ?? null,
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Permintaan ini sudah ditinjau sebelumnya.');
        }

        // Hapus berkas bukti TF yang ditolak agar tidak menumpuk.
        if ($subscriptionRequest->payment_proof) {
            Storage::disk('local')->delete($subscriptionRequest->payment_proof);
        }

        return back()->with('success', 'Pembayaran ditolak.');
    }

    /**
     * R22 — Sajikan berkas bukti transfer (disk privat) hanya untuk root.
     */
    public function proof(SubscriptionRequest $subscriptionRequest)
    {
        abort_unless(Storage::disk('local')->exists($subscriptionRequest->payment_proof), 404);

        return Storage::disk('local')->response($subscriptionRequest->payment_proof);
    }
}
