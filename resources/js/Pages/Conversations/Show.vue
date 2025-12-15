<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    conversation: Object,
    messages: Array,
    transferLogs: Array,
    sectors: Array,
    agents: Array,
});

const messageForm = useForm({
    message: '',
});

const transferForm = useForm({
    sector_id: '',
    agent_id: null,
    note: '',
});

const showTransferModal = ref(false);
let pollInterval = null;

const sendMessage = () => {
    messageForm.post(route('conversations.sendMessage', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset('message');
            refreshMessages();
        },
    });
};

const assumeConversation = () => {
    router.post(route('conversations.assume', props.conversation.id), {}, {
        preserveScroll: true,
    });
};

const transferConversation = () => {
    transferForm.post(route('conversations.transfer', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            showTransferModal.value = false;
            transferForm.reset();
        },
    });
};

const closeConversation = () => {
    if (confirm('Deseja realmente encerrar esta conversa?')) {
        router.post(route('conversations.close', props.conversation.id), {}, {
            onSuccess: () => {
                router.visit(route('conversations.index'));
            },
        });
    }
};

const refreshMessages = () => {
    router.reload({ only: ['messages'], preserveScroll: true });
};

// Polling para atualização em tempo real
onMounted(() => {
    pollInterval = setInterval(() => {
        refreshMessages();
    }, 5000); // Atualiza a cada 5 segundos
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <Head :title="`Conversa - ${conversation.whatsapp_number}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Conversa - {{ conversation.whatsapp_number }}
                </h2>
                <div class="flex gap-2">
                    <PrimaryButton
                        v-if="!conversation.agent_id && conversation.status !== 'closed'"
                        @click="assumeConversation"
                    >
                        Assumir
                    </PrimaryButton>
                    <PrimaryButton
                        v-if="conversation.agent_id && conversation.status !== 'closed'"
                        @click="showTransferModal = true"
                    >
                        Transferir
                    </PrimaryButton>
                    <DangerButton
                        v-if="conversation.status !== 'closed'"
                        @click="closeConversation"
                    >
                        Encerrar
                    </DangerButton>
                    <Link :href="route('conversations.index')">
                        <PrimaryButton variant="secondary">
                            Voltar
                        </PrimaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Info Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-semibold mb-4">
                                    Informações
                                </h3>
                                <dl class="space-y-3">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">
                                            Status
                                        </dt>
                                        <dd>
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                :class="{
                                                    'bg-yellow-100 text-yellow-800': conversation.status === 'new',
                                                    'bg-blue-100 text-blue-800': conversation.status === 'queued',
                                                    'bg-green-100 text-green-800': conversation.status === 'in_progress',
                                                    'bg-gray-100 text-gray-800': conversation.status === 'closed',
                                                }"
                                            >
                                                {{ conversation.status }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">
                                            Setor
                                        </dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ conversation.sector || '-' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500">
                                            Agente
                                        </dt>
                                        <dd class="text-sm text-gray-900">
                                            {{ conversation.agent || '-' }}
                                        </dd>
                                    </div>
                                </dl>

                                <div
                                    v-if="transferLogs.length > 0"
                                    class="mt-6"
                                >
                                    <h4 class="text-sm font-semibold mb-2">
                                        Histórico de Transferências
                                    </h4>
                                    <div class="space-y-2 text-sm">
                                        <div
                                            v-for="log in transferLogs"
                                            :key="log.id"
                                            class="p-2 bg-gray-50 rounded"
                                        >
                                            <div>
                                                {{ log.from_sector || 'Inicial' }} → {{ log.to_sector }}
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                {{ log.created_at }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Chat -->
                    <div class="lg:col-span-2">
                        <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <!-- Messages -->
                            <div class="p-6 h-[500px] overflow-y-auto border-b">
                                <div class="space-y-4">
                                    <div
                                        v-for="message in messages"
                                        :key="message.id"
                                        class="flex"
                                        :class="{
                                            'justify-start': message.direction === 'client',
                                            'justify-end': message.direction === 'agent',
                                            'justify-center': message.direction === 'bot' || message.direction === 'system',
                                        }"
                                    >
                                        <div
                                            class="max-w-xs lg:max-w-md px-4 py-2 rounded-lg"
                                            :class="{
                                                'bg-gray-200': message.direction === 'client',
                                                'bg-indigo-600 text-white': message.direction === 'agent',
                                                'bg-gray-100 text-gray-600': message.direction === 'bot' || message.direction === 'system',
                                            }"
                                        >
                                            <div class="text-sm">
                                                {{ message.body }}
                                            </div>
                                            <div
                                                class="text-xs mt-1"
                                                :class="{
                                                    'text-gray-500': message.direction !== 'agent',
                                                    'text-indigo-200': message.direction === 'agent',
                                                }"
                                            >
                                                {{ message.sent_at }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Input -->
                            <div
                                v-if="conversation.status !== 'closed'"
                                class="p-4"
                            >
                                <form @submit.prevent="sendMessage">
                                    <div class="flex gap-2">
                                        <TextInput
                                            v-model="messageForm.message"
                                            type="text"
                                            placeholder="Digite sua mensagem..."
                                            class="flex-1"
                                            :disabled="messageForm.processing"
                                        />
                                        <PrimaryButton
                                            type="submit"
                                            :disabled="messageForm.processing || !messageForm.message"
                                        >
                                            Enviar
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Modal -->
        <Modal
            :show="showTransferModal"
            @close="showTransferModal = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">
                    Transferir Conversa
                </h2>
                <form @submit.prevent="transferConversation">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Setor
                            </label>
                            <select
                                v-model="transferForm.sector_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                required
                            >
                                <option value="">
                                    Selecione um setor
                                </option>
                                <option
                                    v-for="sector in sectors"
                                    :key="sector.id"
                                    :value="sector.id"
                                >
                                    {{ sector.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Observação (opcional)
                            </label>
                            <textarea
                                v-model="transferForm.note"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                rows="3"
                            />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <PrimaryButton
                            type="button"
                            variant="secondary"
                            @click="showTransferModal = false"
                        >
                            Cancelar
                        </PrimaryButton>
                        <PrimaryButton
                            type="submit"
                            :disabled="transferForm.processing"
                        >
                            Transferir
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>



