<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MessageService
{
    public function sendWelcomeSms(User $user, string $message): bool
    {
        $from = env('TWILIO_FROM');
        $sid = env('TWILIO_ACCOUNT_SID');
        $token = env('TWILIO_AUTH_TOKEN');

        $to = preg_replace('/[^+0-9]/', '', $user->country_code . $user->phone);

        if ($sid && $token && $from) {
            $response = Http::withBasicAuth($sid, $token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'From' => $from,
                    'To'   => $to,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('Échec de l\'envoi SMS Twilio.', [
                'user_id' => $user->id,
                'response' => $response->body(),
                'status' => $response->status(),
            ]);

            return false;
        }

        Log::info('Envoi du message de bienvenue en mode journalisation.', [
            'user_id' => $user->id,
            'phone'   => $to,
            'message' => $message,
        ]);

        return true;
    }
}
