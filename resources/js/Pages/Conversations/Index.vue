<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    conversations: Object,
    filters: Object,
});

const statusFilter = ref(null);
const sectorFilter = ref(null);

const applyFilters = () => {
    router.get(route('conversations.index'), {
        status: statusFilter.value,
        sector_id: sectorFilter.value,
    }, {
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head title="Conversas" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Conversas
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <!-- Filtros -->
                        <div class="mb-4 flex gap-4">
                            <TextInput
                                v-model="statusFilter"
                                type="text"
                                placeholder="Status"
                                class="max-w-xs"
                            />
                            <PrimaryButton @click="applyFilters">
                                Filtrar
                            </PrimaryButton>
                        </div>

                        <!-- Tabela -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Número
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Setor
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Agente
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Última Mensagem
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            Ações
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr
                                        v-for="conv in conversations.data"
                                        :key="conv.id"
                                    >
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ conv.whatsapp_number }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ conv.sector || '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ conv.agent || '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800': conv.status === 'new',
                                                    'bg-blue-100 text-blue-800': conv.status === 'queued',
                                                    'bg-green-100 text-green-800': conv.status === 'in_progress',
                                                    'bg-gray-100 text-gray-800': conv.status === 'closed',
                                                }"
                                            >
                                                {{ conv.status }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ conv.last_message_at || '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <Link
                                                :href="route('conversations.show', conv.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Abrir
                                            </Link>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação -->
                        <div
                            v-if="conversations.links"
                            class="mt-4 flex justify-between"
                        >
                            <div>
                                <span class="text-sm text-gray-700">
                                    Mostrando {{ conversations.from }} a {{ conversations.to }} de
                                    {{ conversations.total }} resultados
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <Link
                                    v-for="link in conversations.links"
                                    :key="link.label"
                                    :href="link.url || '#'"
                                    :class="{
                                        'opacity-50 cursor-not-allowed': !link.url,
                                        'bg-indigo-600 text-white': link.active,
                                    }"
                                    class="px-3 py-2 text-sm border rounded"
                                    v-html="link.label"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

