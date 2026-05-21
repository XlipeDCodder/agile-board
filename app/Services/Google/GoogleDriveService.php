<?php

namespace App\Services\Google;

use App\Models\User;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

class GoogleDriveService
{
    public function __construct(private GoogleClientFactory $clientFactory) {}

    /**
     * Move o arquivo pra lixeira do Drive do usuário. Reversível: o gestor
     * pode restaurar manualmente em drive.google.com/drive/trash por 30 dias.
     * Evitamos delete permanente — ação irreversível disparada por LLM é risco
     * grande demais pra um MVP.
     *
     * @return array{file_id: string, title: string, trashed: bool}
     */
    public function trashFile(User $user, string $fileId): array
    {
        $client = $this->clientFactory->forUser($user);
        $drive = new Drive($client);

        $file = $drive->files->get($fileId, ['fields' => 'id,name,trashed']);
        $title = $file->getName();

        $drive->files->update(
            $fileId,
            new DriveFile(['trashed' => true]),
            ['fields' => 'id,trashed'],
        );

        return [
            'file_id' => $fileId,
            'title' => $title,
            'trashed' => true,
        ];
    }
}
