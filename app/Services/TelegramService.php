<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $botToken;
    private ?string $chatId;

    public function __construct()
    {
        $this->botToken = config('services.telegram.bot_token', '');
        $this->chatId = config('services.telegram.chat_id');
    }

    /**
     * Envoie un message Telegram
     *
     * @param string $message Le message à envoyer
     * @return bool True si l'envoi a réussi, false sinon
     */
    public function sendMessage(string $message): bool
    {
        // Si le token ou le chat_id ne sont pas configurés, on retourne false silencieusement
        if (empty($this->botToken) || empty($this->chatId)) {
            return false;
        }

        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";

            // Convertir le chat_id en entier (Telegram attend un nombre)
            $chatId = is_numeric($this->chatId) ? (int) $this->chatId : $this->chatId;

            $response = Http::timeout(5)->post($url, [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML'
            ]);

            // Vérifier si la requête a réussi
            if ($response->successful() && $response->json('ok') === true) {
                return true;
            }

            // Erreur silencieusement ignorée - on retourne false sans lever d'exception
            return false;
        } catch (\Exception $e) {
            // On ne log pas l'erreur pour rester silencieux comme demandé
            return false;
        }
    }
}

