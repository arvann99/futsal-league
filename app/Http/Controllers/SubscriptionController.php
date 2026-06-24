<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /**
     * R22 — Halaman paket: tampilkan plan aktif, kuota terpakai, dan form upgrade.
     */
    public function showPlans()
    {
        $user = Auth::user();

        $usage = [
            'tournaments' => $user->tournaments()->count(),
            'tournament_limit' => $user->tournamentLimit(),
            'team_limit' => $user->teamLimit(),
        ];

        $pendingRequest = $user->subscriptionRequests()
            ->where('status', 'pending')
            ->latest()
            ->first();

        $plans = User::PLAN_LIMITS;
        $prices = SubscriptionRequest::PRICES;

        return view('admin.subscription.plans', compact('user', 'usage', 'pendingRequest', 'plans', 'prices'));
    }

    /**
     * R22 — Admin mengirim permintaan upgrade + bukti transfer.
     */
    public function requestUpgrade(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'requested_plan' => 'required|in:pro,ultimate',
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], [
            'payment_proof.required' => 'Bukti transfer wajib diunggah.',
            'payment_proof.image' => 'Bukti transfer harus berupa gambar.',
            'payment_proof.max' => 'Ukuran bukti transfer maksimal 4 MB.',
        ]);

        $requestedPlan = $validated['requested_plan'];

        // Tolak bila plan diminta sama / lebih rendah dari plan aktif.
        $rank = ['free' => 0, 'pro' => 1, 'ultimate' => 2];
        if (($rank[$requestedPlan] ?? 0) <= ($rank[$user->plan] ?? 0)) {
            return back()->with('error', 'Paket yang dipilih harus lebih tinggi dari paket Anda saat ini.');
        }

        // Bukti transfer = dokumen finansial sensitif → simpan di disk PRIVAT
        // (storage/app/private), bukan public. Diakses hanya via route root.
        $path = $request->file('payment_proof')->store('payment-proofs', 'local');

        // Cegah double-pending secara atomik (lock baris pending milik user).
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($user, $requestedPlan, $path) {
                $pending = SubscriptionRequest::where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->exists();

                if ($pending) {
                    throw new \RuntimeException('PENDING_EXISTS');
                }

                SubscriptionRequest::create([
                    'user_id' => $user->id,
                    'requested_plan' => $requestedPlan,
                    'payment_proof' => $path,
                    'amount' => SubscriptionRequest::PRICES[$requestedPlan] ?? null,
                    'status' => 'pending',
                ]);
            });
        } catch (\RuntimeException $e) {
            // Hapus file yg sudah terlanjur diunggah saat ditolak double-pending.
            \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            return back()->with('error', 'Anda masih punya permintaan upgrade yang menunggu persetujuan. Tunggu ditinjau admin root.');
        }

        return back()->with('success', 'Bukti transfer berhasil dikirim. Permintaan upgrade Anda menunggu persetujuan admin root.');
    }
}
