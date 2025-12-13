<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bot de Triagem
    |--------------------------------------------------------------------------
    |
    | Configurações simples e previsíveis para triagem sem IA:
    | - comandos
    | - roteamento por palavras-chave (slug do setor -> lista de termos)
    | - cooldown para evitar spam de menu
    |
    */

    'menu_cooldown_minutes' => (int) env('BOT_MENU_COOLDOWN_MINUTES', 5),

    // Auto-encerramento por inatividade (0 desliga)
    'auto_close_minutes' => (int) env('BOT_AUTO_CLOSE_MINUTES', 0),
    'auto_close_send_message' => (bool) env('BOT_AUTO_CLOSE_SEND_MESSAGE', false),

    // IA para roteamento (opcional; desligado por padrão)
    'ai_routing_enabled' => (bool) env('AI_ROUTING_ENABLED', false),
    'ai_routing_min_confidence' => (float) env('AI_ROUTING_MIN_CONFIDENCE', 0.7),
    'ai_routing_provider' => env('AI_ROUTING_PROVIDER', 'openai'),
    'ai_routing_api_key' => env('AI_ROUTING_API_KEY'),
    'ai_routing_model' => env('AI_ROUTING_MODEL', 'gpt-4o-mini'),

    'menu_commands' => [
        'menu',
        'voltar',
        'início',
        'inicio',
        '0',
    ],

    'human_handoff_commands' => [
        'humano',
        'atendente',
        'pessoa',
        'suporte',
    ],

    // Setor "default" para pedidos de atendente humano (criado automaticamente se não existir)
    'reception_sector' => [
        'name' => env('BOT_RECEPTION_SECTOR_NAME', 'Recepção'),
        'slug' => env('BOT_RECEPTION_SECTOR_SLUG', 'recepcao'),
        'menu_code' => env('BOT_RECEPTION_SECTOR_MENU_CODE', '99'),
        'active' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Roteamento por palavras-chave
    |--------------------------------------------------------------------------
    |
    | Mapeia slug do setor -> termos. Se a mensagem contiver um dos termos,
    | o bot atribui o setor automaticamente.
    |
    */
    'keyword_routes' => [
        // IMPORTANTE: Os slugs devem corresponder exatamente aos slugs dos setores no banco de dados
        // Use: php artisan tinker -> App\Models\Sector::all(['slug', 'name'])
        
        // Dívida Ativa: para questões de boletos, pagamentos, cobranças
        'divida_ativa' => ['boleto', 'pagamento', 'pix', 'nota fiscal', 'nf', 'reembolso', 'cobrança', 'cobranca', 'divida', 'dívida', 'debito', 'débito', 'parcela'],
        
        // Fiscalização: para questões de fiscalização, autuações, multas
        'fiscalizacao' => ['fiscalização', 'fiscalizacao', 'multa', 'autuação', 'autuacao', 'infração', 'infracao', 'notificação', 'notificacao'],
        
        // Jurídico: para questões legais, processos, recursos
        'juridico' => ['jurídico', 'juridico', 'processo', 'recurso', 'advogado', 'legal', 'lei', 'decisão', 'decisao', 'sentença', 'sentenca'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log de payload
    |--------------------------------------------------------------------------
    | Por padrão, o webhook não deve logar o payload inteiro (muito grande).
    */
    'log_full_webhook_payload' => (bool) env('BOT_LOG_FULL_WEBHOOK_PAYLOAD', false),
];


