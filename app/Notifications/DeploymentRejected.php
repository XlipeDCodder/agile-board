<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada pro deployer quando um admin rejeita um deploy de staging.
 * Inclui o motivo da rejeição.
 */
class DeploymentRejected extends Notification
{
    use Queueable;

    public function __construct(public Deployment $deployment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->deployment->item;
        $approver = $this->deployment->approver;
        $reason = $this->deployment->rejection_reason ?: '(sem motivo informado)';
        $url = url('/deploys');

        return (new MailMessage)
            ->subject("[B-Agile] Deploy rejeitado: card #{$item->id}")
            ->greeting("Olá, {$notifiable->name}.")
            ->line("**{$approver?->name}** rejeitou o deploy em homologação do card #{$item->id} \"{$item->title}\".")
            ->line("Motivo: _{$reason}_")
            ->action('Ver detalhes', $url)
            ->line('Você pode solicitar um novo deploy após resolver os pontos levantados.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deployment_rejected',
            'deployment_id' => $this->deployment->id,
            'item_id' => $this->deployment->item_id,
            'item_title' => $this->deployment->item?->title,
            'approver_id' => $this->deployment->approver_id,
            'approver_name' => $this->deployment->approver?->name,
            'rejection_reason' => $this->deployment->rejection_reason,
            'url' => '/deploys',
            'message' => "{$this->deployment->approver?->name} rejeitou o deploy do card #{$this->deployment->item_id}.",
        ];
    }
}
