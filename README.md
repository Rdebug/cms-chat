# Sistema de Atendimento via WhatsApp

Sistema completo de atendimento via WhatsApp usando Laravel 12 (compatível com Laravel 11), Inertia.js, Vue 3 e Revolution API.

## 🚀 Tecnologias

- **Backend**: Laravel 12, PHP 8.2+
- **Frontend**: Inertia.js + Vue 3 + TailwindCSS
- **Autenticação**: Laravel Breeze (Inertia/Vue)
- **API WhatsApp**: Revolution API

## 📋 Requisitos

- PHP 8.2 ou superior
- Composer
- Node.js e npm
- MySQL/MariaDB
- Credenciais da Revolution API

## ⚙️ Instalação

### 1. Clone o repositório e instale as dependências

```bash
composer install
npm install
```

### 2. Configure o ambiente

Copie o arquivo `.env.example` para `.env` e configure:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure as variáveis de ambiente

Adicione no arquivo `.env`:

```env
# Revolution API
REVOLUTION_API_BASE_URL=https://sua-api-revolution.com
REVOLUTION_API_TOKEN=seu-token-aqui
REVOLUTION_API_INSTANCE_ID=sua-instance-id
REVOLUTION_API_WEBHOOK_SECRET=seu-webhook-secret

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=chat_gestao_fazendaria
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha
```

### 4. Execute as migrations

```bash
php artisan migrate
```

### 5. Compile os assets

```bash
npm run build
# ou para desenvolvimento:
npm run dev
```

## 🎯 Configuração da Revolution API

1. Configure o webhook na sua instância da Revolution API para apontar para:
   ```
   https://seu-dominio.com/webhook/whatsapp
   ```

2. A estrutura do payload esperada pela API deve ser normalizada no `WhatsAppWebhookController`. Ajuste o método `normalizePayload()` conforme a documentação real da Revolution API.

## 📁 Estrutura do Projeto

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── ConversationController.php
│   │   ├── DashboardController.php
│   │   ├── SectorController.php
│   │   ├── UserController.php
│   │   └── Webhook/
│   │       └── WhatsAppWebhookController.php
│   ├── Requests/
│   └── Policies/
├── Models/
│   ├── Conversation.php
│   ├── Message.php
│   ├── Sector.php
│   ├── TransferLog.php
│   └── User.php
└── Services/
    ├── ConversationService.php
    ├── BotRoutingService.php
    └── WhatsApp/
        ├── RevolutionClient.php
        └── Exceptions/
            └── RevolutionApiException.php

resources/js/
├── Pages/
│   ├── Conversations/
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── Sectors/
│   ├── Users/
│   └── Dashboard.vue
└── Layouts/
    └── AuthenticatedLayout.vue
```

## 🔑 Primeiro Acesso

1. Crie um usuário admin manualmente:

```bash
php artisan tinker
```

```php
User::create([
    'name' => 'Admin',
    'email' => 'admin@exemplo.com',
    'password' => Hash::make('senha123'),
    'role' => 'admin',
    'active' => true,
]);
```

2. Acesse `/login` e faça login

3. Crie os setores através do painel em `/sectors`

4. Configure o webhook na Revolution API

## 📝 Funcionalidades Principais

### ✅ Implementado

- ✅ Autenticação (Breeze)
- ✅ Modelagem completa do banco de dados
- ✅ Webhook para receber mensagens
- ✅ Serviço de comunicação com Revolution API
- ✅ Serviços de negócio (ConversationService, BotRoutingService)
- ✅ Policies de autorização
- ✅ Dashboard com estatísticas
- ✅ Lista de conversas
- ✅ Detalhe da conversa com chat
- ✅ Envio de mensagens
- ✅ Assumir conversa
- ✅ Transferir conversa
- ✅ Encerrar conversa
- ✅ Polling para atualização em tempo real (5 segundos)

### 🔨 Pendente (Componentes Vue)

Os seguintes componentes Vue precisam ser criados/implementados:

1. **Sectors/Index.vue** - Lista de setores
2. **Sectors/Form.vue** - Formulário de criar/editar setor
3. **Users/Index.vue** - Lista de usuários
4. **Users/Form.vue** - Formulário de criar/editar usuário

Siga o padrão dos componentes existentes em `Conversations/Index.vue` e `Conversations/Show.vue`.

## 🔄 Fluxo de Atendimento

1. Cliente envia mensagem via WhatsApp
2. Webhook recebe mensagem e cria/atualiza conversa
3. Se conversa não tem setor, bot envia menu inicial
4. Cliente seleciona setor digitando o código
5. Bot confirma e status muda para `queued`
6. Agente assume conversa (status → `in_progress`)
7. Agente e cliente trocam mensagens
8. Se necessário, agente transfere para outro setor
9. Agente encerra conversa (status → `closed`)

## 🧪 Testes

Execute os testes com:

```bash
php artisan test
```

Alguns testes básicos foram criados como estrutura. Implemente os testes conforme necessário.

## 🔒 Segurança

- Middleware de autenticação em todas as rotas do painel
- Policies para autorização (admin/agent)
- Validação de webhook (quando configurado)
- CSRF protection habilitado

## 📚 Comandos Úteis

```bash
# Rodar migrations
php artisan migrate

# Criar seeder (se necessário)
php artisan make:seeder SectorSeeder

# Limpar cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Compilar assets em produção
npm run build
```

## 🐛 Troubleshooting

### Webhook não está recebendo mensagens

1. Verifique se o webhook está configurado corretamente na Revolution API
2. Verifique os logs em `storage/logs/laravel.log`
3. Teste o endpoint manualmente:
   ```bash
   curl -X POST https://seu-dominio.com/webhook/whatsapp \
     -H "Content-Type: application/json" \
     -d '{"event": {...}}'
   ```

### Mensagens não aparecem no chat

1. Verifique se o polling está funcionando (verifique o console do navegador)
2. Verifique os logs do servidor
3. Verifique se a estrutura do payload do webhook está correta

## 📄 Licença

Este projeto é de código aberto e está disponível sob a licença MIT.

## 🤝 Contribuindo

Contribuições são bem-vindas! Por favor, abra uma issue ou pull request.
