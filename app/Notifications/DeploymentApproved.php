<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada pro deployer + assignees do card quando um admin aprova um deploy
 * de staging. Significa "ok, libere pra produção".
 */
class DeploymentApproved extends Notification
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
        $url = url('/deploys');

        return (new MailMessage)
            ->subject("[B-Agile] Deploy aprovado: card #{$item->id} liberado para produção")
            ->greeting("Olá, {$notifiable->name}.")
            ->line("**{$approver?->name}** aprovou o deploy em homologação do card #{$item->id} \"{$item->title}\".")
            ->line('O card está liberado para subir para **produção**.')
            ->action('Ver detalhes', $url);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deployment_approved',
            'deployment_id' => $this->deployment->id,
            'item_id' => $this->deployment->item_id,
            'item_title' => $this->deployment->item?->title,
            'approver_id' => $this->deployment->approver_id,
            'approver_name' => $this->deployment->approver?->name,
            'url' => '/deploys',
            'message' => "{$this->deployment->approver?->name} aprovou o deploy do card #{$this->deployment->item_id}. Liberado para produção.",
        ];
    }
}
