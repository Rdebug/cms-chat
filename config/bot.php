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
        'financeiro' => ['boleto', 'pagamento', 'pix', 'nota fiscal', 'nf', 'reembolso', 'cobrança', 'cobranca'],
        'cadastro' => ['cadastro', 'senha', 'acesso', 'login', 'e-mail', 'email', 'atualizar dados'],
        'tributos' => ['iptu', 'iss', 'tributo', 'guia', 'darf', 'taxa', 'imposto'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log de payload
    |--------------------------------------------------------------------------
    | Por padrão, o webhook não deve logar o payload inteiro (muito grande).
    */
    'log_full_webhook_payload' => (bool) env('BOT_LOG_FULL_WEBHOOK_PAYLOAD', false),
];


