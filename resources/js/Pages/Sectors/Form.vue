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
    sector: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: props.sector?.name ?? '',
    slug: props.sector?.slug ?? '',
    menu_code: props.sector?.menu_code ?? '',
    active: props.sector?.active ?? true,
});

const submit = () => {
    if (props.sector?.id) {
        form.put(route('sectors.update', props.sector.id));
    } else {
        form.post(route('sectors.store'));
    }
};
</script>

<template>
    <Head :title="sector ? 'Editar Setor' : 'Novo Setor'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ sector ? 'Editar Setor' : 'Novo Setor' }}
                </h2>
                <Link :href="route('sectors.index')">
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
                                <TextInput
                                    id="name"
                                    v-model="form.name"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.name" />
                            </div>

                            <div>
                                <InputLabel for="slug" value="Slug" />
                                <TextInput
                                    id="slug"
                                    v-model="form.slug"
                                    class="mt-1 block w-full"
                                    placeholder="ex: financeiro"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.slug" />
                            </div>

                            <div>
                                <InputLabel for="menu_code" value="Código do menu (ex: 1, 2, 3...)" />
                                <TextInput
                                    id="menu_code"
                                    v-model="form.menu_code"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError class="mt-2" :message="form.errors.menu_code" />
                            </div>

                            <div class="flex items-center gap-2">
                                <Checkbox name="active" v-model:checked="form.active" />
                                <span class="text-sm text-gray-700">Ativo</span>
                            </div>

                            <div class="flex justify-end gap-2">
                                <SecondaryButton type="button" @click="$inertia.visit(route('sectors.index'))">
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


