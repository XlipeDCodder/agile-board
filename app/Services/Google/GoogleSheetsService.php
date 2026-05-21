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
}
