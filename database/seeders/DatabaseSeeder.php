<?php

namespace Database\Seeders;

use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Mapeamento de slugs para nomes amigáveis dos setores
     */
    private function getSectorNameMap(): array
    {
        return [
            'recepcao' => 'Recepção',
            'atendimento' => 'Atendimento',
            'protocolo' => 'Protocolo',
            'empresa_facil' => 'Empresa Fácil',
            'alvara_funcionamento' => 'Alvará de Funcionamento',
            'divida_ativa' => 'Dívida Ativa',
            'cobranca' => 'Cobrança',
            'ti' => 'TI',
            'juridico' => 'Jurídico',
            'itbi' => 'ITBI',
            'cadastro_imobiliario' => 'Cadastro Imobiliário',
            'itr' => 'ITR',
            'fiscalizacao' => 'Fiscalização',
            'coordenacao' => 'Coordenação',
            'copa' => 'Copa',
        ];
    }

    /**
     * Converte slug em nome amigável
     */
    private function getSectorName(string $slug): string
    {
        $map = $this->getSectorNameMap();
        
        if (isset($map[$slug])) {
            return $map[$slug];
        }

        // Se não estiver no mapa, capitaliza e substitui underscores
        return ucwords(str_replace('_', ' ', $slug));
    }

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->createSectors();
        $this->createUsers();
    }

    /**
     * Cria todos os setores necessários baseado nas configurações do bot
     */
    private function createSectors(): void
    {
        $sectorsToCreate = [];

        // 1. Setor de Recepção (definido em config/bot.reception_sector)
        $receptionConfig = config('bot.reception_sector', []);
        if (!empty($receptionConfig)) {
            $sectorsToCreate[] = [
                'slug' => $receptionConfig['slug'] ?? 'recepcao',
                'name' => $receptionConfig['name'] ?? 'Recepção',
                'menu_code' => $receptionConfig['menu_code'] ?? '99',
                'active' => $receptionConfig['active'] ?? true,
            ];
        }

        // 2. Setores de roteamento por keywords (config/bot.keyword_routes)
        $keywordRoutes = config('bot.keyword_routes', []);
        $menuCode = 1;
        foreach ($keywordRoutes as $slug => $keywords) {
            if (is_string($slug) && $slug !== '') {
                // Verifica se já não foi adicionado (ex: recepção)
                $exists = false;
                foreach ($sectorsToCreate as $sector) {
                    if ($sector['slug'] === $slug) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $sectorsToCreate[] = [
                        'slug' => $slug,
                        'name' => $this->getSectorName($slug),
                        'menu_code' => (string) $menuCode,
                        'active' => true,
                    ];
                    $menuCode++;
                }
            }
        }

        // 3. Setores referenciados em clarification_flows (podem não estar em keyword_routes)
        $clarificationFlows = config('bot.clarification_flows', []);
        foreach ($clarificationFlows as $flow) {
            if (isset($flow['options']) && is_array($flow['options'])) {
                foreach ($flow['options'] as $option) {
                    $sectorSlug = $option['sector_slug'] ?? null;
                    if ($sectorSlug && is_string($sectorSlug) && $sectorSlug !== '') {
                        // Verifica se já não foi adicionado
                        $exists = false;
                        foreach ($sectorsToCreate as $sector) {
                            if ($sector['slug'] === $sectorSlug) {
                                $exists = true;
                                break;
                            }
                        }

                        if (!$exists) {
                            $sectorsToCreate[] = [
                                'slug' => $sectorSlug,
                                'name' => $this->getSectorName($sectorSlug),
                                'menu_code' => (string) $menuCode,
                                'active' => true,
                            ];
                            $menuCode++;
                        }
                    }
                }
            }
        }

        // Cria todos os setores usando firstOrCreate para evitar duplicatas
        foreach ($sectorsToCreate as $sectorData) {
            Sector::firstOrCreate(
                ['slug' => $sectorData['slug']],
                [
                    'name' => $sectorData['name'],
                    'menu_code' => $sectorData['menu_code'],
                    'active' => $sectorData['active'],
                ]
            );
        }
    }

    /**
     * Cria usuários iniciais (admin e agente)
     */
    private function createUsers(): void
    {
        // Busca ou cria setor de atendimento para vincular o agente
        $atendimentoSector = Sector::where('slug', 'atendimento')->first();
        
        // Se não existir, cria um setor de atendimento básico
        if (!$atendimentoSector) {
            $atendimentoSector = Sector::create([
                'name' => 'Atendimento',
                'slug' => 'atendimento',
                'menu_code' => '1',
                'active' => true,
            ]);
        }

        // Cria usuário admin
        User::firstOrCreate(
            ['email' => 'admin@exemplo.com'],
            [
                'name' => 'Administrador',
                'email' => 'admin@exemplo.com',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'active' => true,
            ]
        );

        // Cria usuário agente vinculado ao setor de atendimento
        User::firstOrCreate(
            ['email' => 'agent@exemplo.com'],
            [
                'name' => 'Atendente',
                'email' => 'agent@exemplo.com',
                'password' => Hash::make('123456'),
                'role' => 'agent',
                'sector_id' => $atendimentoSector->id,
                'active' => true,
            ]
        );
    }
}
