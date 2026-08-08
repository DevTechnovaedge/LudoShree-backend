<?php

namespace App\Http\Controllers;

class PaymentController extends Controller
{
    public function withdrawal()
    {
        // Call HAODA Payout API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.haodapayments.key'),
        ])->post(config('services.haodapayments.base_uri') . '/payout/initiate', [
            'user_id' => $user->id,
            'amount' => $amount,
            'bank_account' => $bankAccount,
        ]);

        if ($response->successful()) {
            return response()->json([
                'message' => 'Payout initiated successfully',
                'data' => $response->json(),
            ], 200);
        } else {
            return response()->json([
                'message' => 'Payout initiation failed',
                'error' => $response->json(),
            ], 400);
        }
    }
}
