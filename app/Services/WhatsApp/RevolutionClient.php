<?php

namespace App\Services\WhatsApp;

use App\Services\WhatsApp\Exceptions\RevolutionApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevolutionClient
{
    private string $baseUrl;
    private string $token;
    /**
     * Identificador da instância na Evolution API.
     * Na prática, para Evolution API v2.x, isso costuma ser o "instance name" (ex: "teste-local"),
     * e NÃO o UUID (instanceId).
     */
    private string $instanceId;

    public function __construct()
    {
        $this->baseUrl = config('revolution.api.base_url');
        $this->token = config('revolution.api.token');
        $this->instanceId = config('revolution.api.instance_id');

        if (empty($this->baseUrl) || empty($this->token) || empty($this->instanceId)) {
            throw RevolutionApiException::missingConfiguration();
        }
    }

    /**
     * Envia uma mensagem de texto para um número WhatsApp
     */
    public function sendTextMessage(string $number, string $text): void
    {
        try {
            $response = $this->makeRequest('POST', "/message/sendText/{$this->instanceId}", [
                'number' => $this->formatNumber($number),
                'text' => $text,
            ]);

            if (!$response->successful()) {
                throw RevolutionApiException::apiError('Failed to send text message', $response);
            }

            Log::info('Text message sent successfully', [
                'number' => $number,
                'response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending text message', [
                'number' => $number,
                'error' => $e->getMessage(),
            ]);

            throw RevolutionApiException::sendFailed($e);
        }
    }

    /**
     * Envia uma mensagem com mídia
     */
    public function sendMediaMessage(string $number, string $mediaUrl, string $caption = '', string $mediaType = 'image'): void
    {
        try {
            // Evolution API espera "mediatype" (minúsculo)
            $response = $this->makeRequest('POST', "/message/sendMedia/{$this->instanceId}", [
                'number' => $this->formatNumber($number),
                'media' => $mediaUrl,
                'caption' => $caption,
                'mediatype' => $mediaType,
            ]);

            if (!$response->successful()) {
                throw RevolutionApiException::apiError('Failed to send media message', $response);
            }

            Log::info('Media message sent successfully', [
                'number' => $number,
                'media_type' => $mediaType,
                'response' => $response->json(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending media message', [
                'number' => $number,
                'error' => $e->getMessage(),
            ]);

            throw RevolutionApiException::sendFailed($e);
        }
    }

    /**
     * Marca mensagem como lida
     */
    public function markAsRead(string $messageId): void
    {
        try {
            // Endpoint pode variar por versão/instalação. Mantemos como best-effort.
            $response = $this->makeRequest('POST', "/chat/markMessageAsRead/{$this->instanceId}", [
                'messageId' => $messageId,
            ]);

            if (!$response->successful()) {
                Log::warning('Failed to mark message as read', [
                    'message_id' => $messageId,
                    'response' => $response->json(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error marking message as read', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Faz requisição HTTP para a API
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        return Http::withHeaders([
            'apikey' => $this->token,
            'Content-Type' => 'application/json',
        ])->{strtolower($method)}($url, $data);
    }

    /**
     * Formata número para o formato esperado pela API
     */
    private function formatNumber(string $number): string
    {
        // Para grupos, a Evolution aceita o JID completo (ex: 1203...@g.us)
        if (str_contains($number, '@g.us')) {
            return $number;
        }

        // Se vier um JID de usuário, mantemos só os dígitos (Evolution espera E.164 sem sufixo)
        if (str_contains($number, '@s.whatsapp.net')) {
            $number = str_replace('@s.whatsapp.net', '', $number);
        }

        // Remove caracteres não numéricos
        $number = preg_replace('/[^0-9]/', '', $number);

        // Se começar com 0, remove
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }

        // Se não começar com código do país, adiciona 55 (Brasil)
        if (!str_starts_with($number, '55')) {
            $number = '55' . $number;
        }

        // Evolution API espera apenas o número em E.164 (somente dígitos), sem "@s.whatsapp.net"
        return $number;
    }
}

