<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine if the user can view any conversations.
     */
    public function viewAny(User $user): bool
    {
        return true; // Todos os usuários autenticados podem ver conversas
    }

    /**
     * Determine if the user can view the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        // Admin pode ver todas
        if ($user->isAdmin()) {
            return true;
        }

        // Agente só pode ver conversas do seu setor ou que ele está atendendo
        if ($user->isAgent()) {
            if ($conversation->current_agent_id === $user->id) {
                return true;
            }

            if ($conversation->current_sector_id === $user->sector_id) {
                return true;
            }

            return false;
        }

        return false;
    }

    /**
     * Determine if the user can create conversations.
     */
    public function create(User $user): bool
    {
        return true; // Conversas são criadas via webhook
    }

    /**
     * Determine if the user can update the conversation.
     */
    public function update(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /**
     * Determine if the user can assume the conversation.
     */
    public function assume(User $user, Conversation $conversation): bool
    {
        // Admin pode assumir qualquer conversa
        if ($user->isAdmin()) {
            return true;
        }

        // Agente só pode assumir conversas do seu setor e que não estão sendo atendidas
        if ($user->isAgent()) {
            return $conversation->current_sector_id === $user->sector_id
                && ($conversation->current_agent_id === null || $conversation->current_agent_id === $user->id);
        }

        return false;
    }

    /**
     * Determine if the user can transfer the conversation.
     */
    public function transfer(User $user, Conversation $conversation): bool
    {
        // Admin pode transferir qualquer conversa
        if ($user->isAdmin()) {
            return true;
        }

        // Agente só pode transferir conversas que ele está atendendo
        if ($user->isAgent()) {
            return $conversation->current_agent_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can close the conversation.
     */
    public function close(User $user, Conversation $conversation): bool
    {
        // Admin pode fechar qualquer conversa
        if ($user->isAdmin()) {
            return true;
        }

        // Agente só pode fechar conversas que ele está atendendo
        if ($user->isAgent()) {
            return $conversation->current_agent_id === $user->id;
        }

        return false;
    }
}
