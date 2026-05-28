<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada pros ADMINS (exceto o próprio deployer) quando um dev solicita
 * um deploy em homologação. Pede ação: aprovar/rejeitar na página /deploys.
 */
class DeploymentRequested extends Notification
{
    use Queueable;

    public function __construct(public Deployment $deployment) {}

    public function via(object $notifiable): array
    {
        // 'broadcast' faz o sino piscar em tempo real via Reverb.
        return ['database', 'mail', 'broadcast'];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        // O front decide ícone/cor pelo 'type'. O payload aqui é o que
        // chega no Echo listener do NotificationBell.
        return new BroadcastMessage($this->toArray($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $item = $this->deployment->item;
        $deployer = $this->deployment->deployer;
        $url = url('/deploys');

        return (new MailMessage)
            ->subject("[B-Agile] Deploy em homologação aguardando aprovação: #{$item->id}")
            ->greeting("Olá, {$notifiable->name}.")
            ->line("**{$deployer->name}** solicitou um deploy em **homologação** do card #{$item->id} \"{$item->title}\".")
            ->lineIf((bool) $this->deployment->notes, "Notas do deploy:\n\n_{$this->deployment->notes}_")
            ->action('Revisar e aprovar', $url)
            ->line('Após sua aprovação, o dev poderá promover o card para produção.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'deployment_requested',
            'deployment_id' => $this->deployment->id,
            'item_id' => $this->deployment->item_id,
            'item_title' => $this->deployment->item?->title,
            'deployer_id' => $this->deployment->deployer_id,
            'deployer_name' => $this->deployment->deployer?->name,
            'notes' => $this->deployment->notes,
            'url' => '/deploys',
            'message' => "{$this->deployment->deployer?->name} solicitou deploy em homologação do card #{$this->deployment->item_id}.",
        ];
    }
}
