<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiRoutingService
{
    /**
     * @param array<int, array{slug:string,name:string,menu_code?:string|null}> $sectors
     * @return array{sector_slug?:string,confidence?:float,clarifying_question?:string}|null
     */
    public function classifySector(string $text, array $sectors): ?array
    {
        if (!(bool) config('bot.ai_routing_enabled', false)) {
            return null;
        }

        $apiKey = (string) (config('bot.ai_routing_api_key') ?? '');
        if ($apiKey === '') {
            Log::warning('AI routing enabled but AI_ROUTING_API_KEY is empty');
            return null;
        }

        $model = (string) (config('bot.ai_routing_model') ?? 'gpt-4o-mini');
        $minConfidence = (float) config('bot.ai_routing_min_confidence', 0.7);

        $system = <<<SYS
Você é um classificador de atendimento. Sua tarefa é escolher o setor correto para uma mensagem.
Responda SOMENTE em JSON válido com as chaves:
- sector_slug (string ou null)
- confidence (number 0..1)
- clarifying_question (string ou null)

Se estiver em dúvida, retorne sector_slug=null e forneça clarifying_question.
SYS;

        $sectorsJson = json_encode($sectors, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $user = "Mensagem do cliente:\n{$text}\n\nSetores disponíveis (JSON):\n{$sectorsJson}";

        try {
            $resp = Http::timeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                    'temperature' => 0.0,
                    'response_format' => ['type' => 'json_object'],
                ]);

            if (!$resp->successful()) {
                Log::warning('AI routing request failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ]);
                return null;
            }

            $content = $resp->json('choices.0.message.content');
            if (!is_string($content) || $content === '') {
                return null;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                return null;
            }

            $sectorSlug = isset($data['sector_slug']) && is_string($data['sector_slug']) ? $data['sector_slug'] : null;
            $confidence = isset($data['confidence']) ? (float) $data['confidence'] : null;
            $clarifying = isset($data['clarifying_question']) && is_string($data['clarifying_question'])
                ? $data['clarifying_question']
                : null;

            if ($sectorSlug !== null) {
                $validSlugs = array_map(fn ($s) => (string) ($s['slug'] ?? ''), $sectors);
                if (!in_array($sectorSlug, $validSlugs, true)) {
                    return null;
                }
            }

            if ($confidence !== null && ($confidence < 0.0 || $confidence > 1.0)) {
                $confidence = null;
            }

            // Se confiança é baixa, preferimos clarificação/menú
            if ($sectorSlug !== null && $confidence !== null && $confidence < $minConfidence) {
                return [
                    'sector_slug' => null,
                    'confidence' => $confidence,
                    'clarifying_question' => $clarifying,
                ];
            }

            return [
                'sector_slug' => $sectorSlug,
                'confidence' => $confidence,
                'clarifying_question' => $clarifying,
            ];

        } catch (\Throwable $e) {
            Log::error('AI routing exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}


