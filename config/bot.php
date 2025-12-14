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
    // Protocolo (protocolos digitais)
        'protocolo' => [
            'protocolo','protocolar','protocolei','protocolado','processo','abertura de processo',
            'requerimento','petição','anexo','anexar documento','documentação','documento',
            'assinatura','assinar','digital','protocolo digital','numero do protocolo','andamento'
        ],

        // Atendimento (NFA avulsa, taxas de embarque rodoviária)
        'atendimento' => [
            'atendimento','senhas','senha','painel','guichê','guiche','fila',
            'nota fiscal avulsa','nfa','nfa avulsa','nf avulsa','emitir nota','emissão de nota',
            'taxa de embarque','rodoviária','rodoviaria','embarque','empresa da rodoviaria','cadastrada na rodoviaria'
        ],

        // Empresa Fácil (abertura de empresas / fluxo inicial de CNPJ)
        'empresa_facil' => [
            'empresa facil','empresafacil','abre empresa','abrir empresa','abertura de empresa',
            'cnpj','abrir cnpj','novo cnpj','mei','microempreendedor','microempresa','me',
            'lt' ,'ltda','alteração contratual','viabilidade','inscrição municipal','inscricao municipal',
            'cadastro de empresa','regularizar empresa','ativar empresa'
        ],

        // Alvará de Funcionamento
        'alvara_funcionamento' => [
            'alvará','alvara','alvará de funcionamento','alvara de funcionamento','licença','licenca',
            'renovar alvará','renovacao de alvara','emissão de guia de alvará','guia do alvará',
            'licenciamento','taxa de licenciamento','vigilância sanitária','vigilancia sanitaria',
            'bombeiros','corpo de bombeiros','habite-se','vistoria'
        ],

        // Dívida Ativa (parcelamentos, IPTU, gestão dívida)
        'divida_ativa' => [
            'dívida ativa','divida ativa','divida','dívida','debito','débito','pendência','pendencia',
            'parcelamento','parcelar','refis','negociar debito','segunda via','2 via','boleto','guia','dam',
            'iptu','itbi atrasado','iss atrasado','taxa atrasada','juros','multa de mora'
        ],

        // Cobrança (negociação, acordos, descontos, protesto)
        'cobranca' => [
            'cobrança','cobranca','acordo','acordar','desconto','negociação','negociacao',
            'protesto','cartório de protesto','cartorio de protesto','serasa','restrição','restricao',
            'notificação de cobrança','notificacao de cobranca','cobrança extrajudicial'
        ],

        // TI (infra e sistemas)
        'ti' => [
            'ti','tecnologia','sistema','portal','site','acesso','login','senha do sistema','recuperar senha',
            'erro','bug','instabilidade','fora do ar','nao carrega','não carrega','travando',
            'internet','rede','computador','impressora','whatsapp','integracao','api'
        ],

        // Jurídico (ITBI, imunidades, isenções, apoio)
        'juridico' => [
            'jurídico','juridico','parecer','processo','recurso','defesa','impugnação','impugnacao',
            'lei','decreto','decisão','decisao','sentença','sentenca','mandado','liminar',
            'imunidade','isenção','isencao','não incidência','nao incidencia','responsabilidade','procuradoria'
        ],

        // ITBI (compras e vendas / cartório)
        'itbi' => [
            'itbi','transmissão','transmissao','compra e venda','comprar imóvel','vender imóvel',
            'escritura','registro','cartório','cartorio','guia de itbi','calcular itbi','valor venal',
            'declaração de itbi','declaracao itbi','transferência de imóvel','transferencia de imovel'
        ],

        // Cadastro Imobiliário (imóveis, desmembramento/remembramento, titularidade IPTU, cálculo IPTU)
        'cadastro_imobiliario' => [
            'cadastro imobiliário','cadastro imobiliario','imóvel','imovel','inscrição imobiliária','inscricao imobiliaria',
            'iptu','calcular iptu','revisão de iptu','revisao iptu','metragem','área','area','endereço','endereco',
            'desmembramento','remembramento','unificar terreno','desmembrar','remembrar',
            'alteração de titularidade','alteracao de titularidade','mudar nome do iptu','trocar titular do iptu',
            'atualizar cadastro do imóvel','atualizar cadastro do imovel'
        ],

        // ITR (imposto rural)
        'itr' => [
            'itr','imposto territorial rural','imposto rural','rural','propriedade rural','fazenda','sítio','sitio',
            'ccir','incra','nirf','cafir','itr atrasado','declaração itr','declaracao itr','vtn'
        ],

        // Fiscalização (ISS)
        'fiscalizacao' => [
            'fiscalização','fiscalizacao','fiscal','vistoria fiscal','auto de infração','auto de infracao',
            'multa','autuação','autuacao','infração','infracao','notificação','notificacao',
            'iss','issqn','nota de serviço','nota de servico','tomador','prestador','alíquota','aliquota',
            'sonegação','sonegacao','denúncia','denuncia'
        ],

        // Coordenação (decisões)
        'coordenacao' => [
            'coordenação','coordenacao','coordenador','secretaria adjunta','secretário adjunto','secretario adjunto',
            'reclamação','reclamacao','elogio','ouvidoria','sugestão','sugestao','prioridade',
            'caso urgente','urgente','resolver','escalonar','falar com responsável','falar com responsavel'
        ],

        // Copa (limpeza) — geralmente não deveria receber triagem externa, mas deixo se quiser filtrar internamente
        'copa' => [
            'limpeza','copa','faxina','higienização','higienizacao','material de limpeza','zeladoria'
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Fluxos de desambiguação
    |--------------------------------------------------------------------------
    |
    | Quando uma palavra pode se referir a múltiplos setores, o bot faz
    | uma pergunta específica e aguarda resposta numérica para encaminhar.
    |
    | Estrutura:
    |   'palavra_ambigua' => [
    |       'question' => 'Pergunta a ser feita',
    |       'options' => [
    |           ['sector_slug' => 'slug_do_setor', 'label' => 'Rótulo mostrado'],
    |           ...
    |       ],
    |   ],
    |
    */
    'clarification_flows' => [
        'guia' => [
            'question' => 'Qual guia/tributo você precisa emitir ou regularizar?',
            'options' => [
                ['sector_slug' => 'cadastro_imobiliario', 'label' => 'IPTU (cálculo, revisão, cadastro do imóvel)'],
                ['sector_slug' => 'divida_ativa', 'label' => 'IPTU em atraso / Dívida ativa / Parcelamento'],
                ['sector_slug' => 'itbi', 'label' => 'ITBI (compra e venda de imóvel)'],
                ['sector_slug' => 'alvara_funcionamento', 'label' => 'Alvará / Licenciamento'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'ISS / Multa de fiscalização'],
            ],
        ],

        'boleto' => [
            'question' => 'Esse boleto é sobre o quê?',
            'options' => [
                ['sector_slug' => 'divida_ativa', 'label' => 'Débito/parcelamento (dívida ativa)'],
                ['sector_slug' => 'cadastro_imobiliario', 'label' => 'IPTU (cálculo/revisão/2ª via por cadastro)'],
                ['sector_slug' => 'alvara_funcionamento', 'label' => 'Taxa/guia de alvará'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'Multa/ISS de fiscalização'],
            ],
        ],

        'iptu' => [
            'question' => 'Seu assunto de IPTU é qual?',
            'options' => [
                ['sector_slug' => 'cadastro_imobiliario', 'label' => 'Cálculo/revisão, metragem, dados do imóvel, titularidade'],
                ['sector_slug' => 'divida_ativa', 'label' => 'IPTU em atraso, negociação, parcelamento, dívida ativa'],
                ['sector_slug' => 'cobranca', 'label' => 'Cobrança/protesto/negociação (se já foi notificado)'],
            ],
        ],

        'transferencia' => [
            'question' => 'A transferência é de quê?',
            'options' => [
                ['sector_slug' => 'itbi', 'label' => 'Transferência de imóvel (compra e venda/cartório/ITBI)'],
                ['sector_slug' => 'cadastro_imobiliario', 'label' => 'Transferência/alteração de titularidade do IPTU (cadastro)'],
                ['sector_slug' => 'juridico', 'label' => 'Imunidade/isenção ou dúvida jurídica sobre transferência'],
            ],
        ],

        'cadastro' => [
            'question' => 'Cadastro de quê?',
            'options' => [
                ['sector_slug' => 'cadastro_imobiliario', 'label' => 'Imóvel / IPTU / inscrição imobiliária'],
                ['sector_slug' => 'empresa_facil', 'label' => 'Empresa / CNPJ / inscrição municipal'],
                ['sector_slug' => 'atendimento', 'label' => 'Cadastro ligado à rodoviária/taxa de embarque'],
            ],
        ],

        'cnpj' => [
            'question' => 'Seu assunto sobre CNPJ é qual?',
            'options' => [
                ['sector_slug' => 'empresa_facil', 'label' => 'Abrir/alterar empresa (Empresa Fácil)'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'ISS/nota de serviço/fiscalização da empresa'],
                ['sector_slug' => 'divida_ativa', 'label' => 'Débitos/parcelamento ligados à empresa'],
            ],
        ],

        'alvara' => [
            'question' => 'Sobre o alvará, o que você precisa?',
            'options' => [
                ['sector_slug' => 'alvara_funcionamento', 'label' => 'Emitir/renovar alvará e guias'],
                ['sector_slug' => 'empresa_facil', 'label' => 'Abertura de empresa (passo anterior ao alvará)'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'Exigência/multa/pendência de fiscalização relacionada'],
            ],
        ],

        'nota' => [
            'question' => 'Quando você diz “nota”, está falando de qual tipo?',
            'options' => [
                ['sector_slug' => 'atendimento', 'label' => 'Nota Fiscal Avulsa (NFA)'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'Nota de serviço / ISS / fiscalização'],
                ['sector_slug' => 'divida_ativa', 'label' => 'Débitos/guia relacionados a nota/pagamento'],
            ],
        ],

        'pagamento' => [
            'question' => 'Esse pagamento é referente a quê?',
            'options' => [
                ['sector_slug' => 'divida_ativa', 'label' => 'Pagar/parcelar tributos em atraso (dívida ativa)'],
                ['sector_slug' => 'alvara_funcionamento', 'label' => 'Taxa/licenciamento/alvará'],
                ['sector_slug' => 'fiscalizacao', 'label' => 'Multa/ISS de fiscalização'],
                ['sector_slug' => 'cobranca', 'label' => 'Cobrança/protesto/negociação'],
            ],
        ],

        'protocolo' => [
            'question' => 'Você precisa protocolar um pedido/documento ou tratar de tributo específico?',
            'options' => [
                ['sector_slug' => 'protocolo', 'label' => 'Protocolar documento/pedido e acompanhar andamento'],
                ['sector_slug' => 'divida_ativa', 'label' => 'Dívida/parcelamento/IPTU'],
                ['sector_slug' => 'itbi', 'label' => 'ITBI/compra e venda de imóvel'],
                ['sector_slug' => 'alvara_funcionamento', 'label' => 'Alvará/licenciamento'],
            ],
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | Log de payload
    |--------------------------------------------------------------------------
    | Por padrão, o webhook não deve logar o payload inteiro (muito grande).
    */
    'log_full_webhook_payload' => (bool) env('BOT_LOG_FULL_WEBHOOK_PAYLOAD', false),
];