<?php

namespace App\Notifications;

use App\Models\Deployment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Enviada pros assignees do card quando um deploy em produção é registrado.
 * Pra deploy URGENTE (que pulou homologação), também vai pra admins (com
 * texto destacando que foi urgente).
 */
class ProductionDeployCompleted extends Notification
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
        $deployer = $this->deployment->deployer;
        $url = url('/deploys');
        $urgentTag = $this->deployment->is_urgent ? ' (URGENTE — pulou homologação)' : '';

        $mail = (new MailMessage)
            ->subject("[B-Agile] Card #{$item->id} foi para produção{$urgentTag}")
            ->greeting("Olá, {$notifiable->name}.")
            ->line("**{$deployer?->name}** registrou um deploy em **produção** do card #{$item->id} \"{$item->title}\".");

        if ($this->deployment->is_urgent) {
            $mail->line('⚠️ Este foi um deploy URGENTE, que pulou a etapa de homologação.');
        }

        return $mail
            ->lineIf((bool) $this->deployment->notes, "Notas do deploy:\n\n_{$this->deployment->notes}_")
            ->action('Ver detalhes', $url);
    }

    public function toArray(object $notifiable): array
    {
        $urgentTag = $this->deployment->is_urgent ? ' (urgente)' : '';
        return [
            'type' => 'production_deploy_completed',
            'deployment_id' => $this->deployment->id,
            'item_id' => $this->deployment->item_id,
            'item_title' => $this->deployment->item?->title,
            'deployer_id' => $this->deployment->deployer_id,
            'deployer_name' => $this->deployment->deployer?->name,
            'is_urgent' => $this->deployment->is_urgent,
            'url' => '/deploys',
            'message' => "{$this->deployment->deployer?->name} registrou deploy em produção do card #{$this->deployment->item_id}{$urgentTag}.",
        ];
    }
}
