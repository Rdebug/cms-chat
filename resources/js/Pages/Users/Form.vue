<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Checkbox from '@/Components/Checkbox.vue';

const props = defineProps({
    user: {
        type: Object,
        default: null,
    },
    sectors: {
        type: Array,
        default: () => [],
    },
});

const isEdit = !!props.user?.id;

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    role: props.user?.role ?? 'agent',
    sector_id: props.user?.sector_id ?? '',
    active: props.user?.active ?? true,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    if (isEdit) {
        form.put(route('users.update', props.user.id), {
            onFinish: () => {
                form.reset('password', 'password_confirmation');
            },
        });
    } else {
        form.post(route('users.store'), {
            onFinish: () => {
                form.reset('password', 'password_confirmation');
            },
        });
    }
};
</script>

<template>
    <Head :title="isEdit ? 'Editar Usuário' : 'Novo Usuário'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ isEdit ? 'Editar Usuário' : 'Novo Usuário' }}
                </h2>
                <Link :href="route('users.index')">
                    <SecondaryButton>Voltar</SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div>
                                <InputLabel for="name" value="Nome" />
                                <TextInput id="name" v-model="form.name" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="email" value="Email" />
                                <TextInput id="email" type="email" v-model="form.email" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <InputLabel for="role" value="Perfil" />
                                <select
                                    id="role"
                                    v-model="form.role"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="admin">admin</option>
                                    <option value="agent">agent</option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.role" />
                            </div>

                            <div v-if="form.role === 'agent'">
                                <InputLabel for="sector_id" value="Setor (agente)" />
                                <select
                                    id="sector_id"
                                    v-model="form.sector_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Selecione</option>
                                    <option v-for="s in sectors" :key="s.id" :value="s.id">
                                        {{ s.name }}
                                    </option>
                                </select>
                                <InputError class="mt-2" :message="form.errors.sector_id" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <InputLabel for="password" :value="isEdit ? 'Nova senha (opcional)' : 'Senha'" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        v-model="form.password"
                                        class="mt-1 block w-full"
                                        :required="!isEdit"
                                        autocomplete="new-password"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <div>
                                    <InputLabel for="password_confirmation" value="Confirmar senha" />
                                    <TextInput
                                        id="password_confirmation"
                                        type="password"
                                        v-model="form.password_confirmation"
                                        class="mt-1 block w-full"
                                        :required="!isEdit"
                                        autocomplete="new-password"
                                    />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Checkbox name="active" v-model:checked="form.active" />
                                <span class="text-sm text-gray-700">Ativo</span>
                            </div>

                            <div class="flex justify-end gap-2">
                                <SecondaryButton type="button" @click="$inertia.visit(route('users.index'))">
                                    Cancelar
                                </SecondaryButton>
                                <PrimaryButton :disabled="form.processing">
                                    Salvar
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


