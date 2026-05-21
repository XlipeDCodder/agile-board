<?php

namespace App\Services\Google;

use App\Models\User;
use Google\Service\Sheets;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest;
use Google\Service\Sheets\GridProperties;
use Google\Service\Sheets\Request as SheetsRequest;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsService
{
    public function __construct(private GoogleClientFactory $clientFactory) {}

    /**
     * Cria uma Spreadsheet no Drive do user com headers + rows.
     *
     * @param  array<int,string>  $headers
     * @param  array<int,array<int,string>>  $rows
     * @return array{file_id: string, file_url: string, title: string}
     */
    public function createSheet(User $user, string $title, array $headers, array $rows): array
    {
        $client = $this->clientFactory->forUser($user);
        $sheets = new Sheets($client);

        $spreadsheet = $sheets->spreadsheets->create(new Spreadsheet([
            'properties' => new SpreadsheetProperties(['title' => $title]),
        ]));
        $sid = $spreadsheet->getSpreadsheetId();
        $sheetId = $spreadsheet->getSheets()[0]->getProperties()->getSheetId();

        // Popula valores: linha 1 = headers, demais = rows.
        $values = array_merge([$headers], $rows);
        $sheets->spreadsheets_values->update(
            $sid,
            'A1',
            new ValueRange(['values' => $values]),
            ['valueInputOption' => 'RAW'],
        );

        // Formata header (bold + freeze).
        $sheets->spreadsheets->batchUpdate($sid, new BatchUpdateSpreadsheetRequest([
            'requests' => [
                new SheetsRequest([
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1,
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'textFormat' => ['bold' => true],
                                'backgroundColor' => [
                                    'red' => 0.93, 'green' => 0.93, 'blue' => 0.93,
                                ],
                            ],
                        ],
                        'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
                    ],
                ]),
                new SheetsRequest([
                    'updateSheetProperties' => [
                        'properties' => [
                            'sheetId' => $sheetId,
                            'gridProperties' => new GridProperties(['frozenRowCount' => 1]),
                        ],
                        'fields' => 'gridProperties.frozenRowCount',
                    ],
                ]),
            ],
        ]));

        return [
            'file_id' => $sid,
            'file_url' => "https://docs.google.com/spreadsheets/d/{$sid}/edit",
            'title' => $title,
        ];
    }

    /**
     * Anexa linhas ao final da primeira aba da planilha. O Sheets API encontra
     * a primeira linha vazia automaticamente.
     *
     * @param  array<int,array<int,string>>  $rows
     * @return array{file_id: string, file_url: string, title: string, rows_appended: int}
     */
    public function appendRows(User $user, string $fileId, array $rows): array
    {
        $client = $this->clientFactory->forUser($user);
        $sheets = new Sheets($client);

        $sheets->spreadsheets_values->append(
            $fileId,
            'A1',
            new ValueRange(['values' => $rows]),
            ['valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS'],
        );

        $spreadsheet = $sheets->spreadsheets->get($fileId);

        return [
            'file_id' => $fileId,
            'file_url' => "https://docs.google.com/spreadsheets/d/{$fileId}/edit",
            'title' => $spreadsheet->getProperties()->getTitle(),
            'rows_appended' => count($rows),
        ];
    }

    /**
     * Atualiza um range específico (ex: "A1:C5", "Sheet2!B3") com novos valores.
     * Sobrescreve o conteúdo existente no range.
     *
     * @param  array<int,array<int,string>>  $values
     * @return array{file_id: string, file_url: string, title: string, range: string, updated_cells: int}
     */
    public function updateRange(User $user, string $fileId, string $range, array $values): array
    {
        $client = $this->clientFactory->forUser($user);
        $sheets = new Sheets($client);

        $response = $sheets->spreadsheets_values->update(
            $fileId,
            $range,
            new ValueRange(['values' => $values]),
            ['valueInputOption' => 'USER_ENTERED'],
        );

        $spreadsheet = $sheets->spreadsheets->get($fileId);

        return [
            'file_id' => $fileId,
            'file_url' => "https://docs.google.com/spreadsheets/d/{$fileId}/edit",
            'title' => $spreadsheet->getProperties()->getTitle(),
            'range' => $range,
            'updated_cells' => (int) $response->getUpdatedCells(),
        ];
    }
}
