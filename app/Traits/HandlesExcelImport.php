<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait HandlesExcelImport
{
    /**
     * Parse an uploaded .xlsx, .xls, or .csv file into an array of associative rows.
     * Header labels are normalised to snake_case lowercase.
     */
    protected function parseUploadedFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return $ext === 'csv'
            ? $this->parseCsv($file->getRealPath())
            : $this->parseXlsx($file->getRealPath());
    }

    private function normaliseHeader(string $header): string
    {
        return strtolower(trim(str_replace([' ', '-'], '_', $header)));
    }

    private function parseCsv(string $path): array
    {
        $rows   = [];
        $handle = fopen($path, 'r');

        $rawHeaders = fgetcsv($handle);
        if (! $rawHeaders) {
            fclose($handle);

            return [];
        }

        $headers = array_map(fn ($h) => $this->normaliseHeader((string) $h), $rawHeaders);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), null);
            }
            $row    = array_combine($headers, array_slice($data, 0, count($headers)));
            $rows[] = array_map(fn ($v) => ($v === '' ? null : $v), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function parseXlsx(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];
        $headers     = null;

        foreach ($sheet->getRowIterator() as $row) {
            $cells        = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $cells[] = $cell->getFormattedValue();
            }

            // Trim trailing empty cells
            while (! empty($cells) && ($cells[array_key_last($cells)] === null || $cells[array_key_last($cells)] === '')) {
                array_pop($cells);
            }

            if (empty($cells)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($h) => $this->normaliseHeader((string) $h), $cells);
                continue;
            }

            // Skip fully empty data rows
            if (empty(array_filter($cells, fn ($c) => $c !== null && $c !== ''))) {
                continue;
            }

            if (count($cells) < count($headers)) {
                $cells = array_pad($cells, count($headers), null);
            }

            $row    = array_combine($headers, array_slice($cells, 0, count($headers)));
            $rows[] = array_map(fn ($v) => ($v === '' ? null : $v), $row);
        }

        return $rows;
    }

    /**
     * Build a styled spreadsheet for use as a downloadable template.
     *
     * The first sheet ("Data") contains only the header row and sample rows —
     * no note text — so that the parser never accidentally reads instructions
     * as data rows. If a $note is provided it is placed on a separate read-only
     * "Instructions" sheet.
     *
     * @param  string[]      $headers    Column header labels (e.g. "Contact Person")
     * @param  array[]       $sampleRows 0-indexed column arrays of sample values
     * @param  string|null   $note       Optional guidance written to the Instructions sheet
     */
    protected function createTemplateSpreadsheet(array $headers, array $sampleRows = [], ?string $note = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();

        // ── Sheet 1: Data ─────────────────────────────────────────────────────
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data');

        // Header row
        foreach ($headers as $colIdx => $label) {
            $col = Coordinate::stringFromColumnIndex($colIdx + 1);
            $sheet->setCellValue("{$col}1", $label);
        }

        $lastCol     = Coordinate::stringFromColumnIndex(count($headers));
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A1F36']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(24);

        // Sample rows
        foreach ($sampleRows as $rowIdx => $rowData) {
            $excelRow = $rowIdx + 2;
            foreach ($rowData as $colIdx => $value) {
                $col = Coordinate::stringFromColumnIndex($colIdx + 1);
                $sheet->setCellValue("{$col}{$excelRow}", $value);
            }
            if ($rowIdx % 2 === 0) {
                $sheet->getStyle("A{$excelRow}:{$lastCol}{$excelRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F8FF']],
                ]);
            }
        }

        // Auto-size columns
        foreach (range(1, count($headers)) as $colIdx) {
            $col = Coordinate::stringFromColumnIndex($colIdx);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ── Sheet 2: Instructions (only if a note is supplied) ────────────────
        if ($note) {
            $infoSheet = $spreadsheet->createSheet();
            $infoSheet->setTitle('Instructions');

            $infoSheet->setCellValue('A1', 'Import Instructions');
            $infoSheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => '1A1F36']],
            ]);

            $infoSheet->setCellValue('A3', $note);
            $infoSheet->getStyle('A3')->applyFromArray([
                'font'      => ['size' => 10, 'color' => ['rgb' => '444444']],
                'alignment' => ['wrapText' => true],
            ]);
            $infoSheet->getColumnDimension('A')->setWidth(90);
            $infoSheet->getRowDimension(3)->setRowHeight(60);

            $infoSheet->setCellValue('A5', 'Fill in the "Data" sheet tab (do not rename it) and upload the file.');
            $infoSheet->getStyle('A5')->applyFromArray([
                'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '888888']],
            ]);
        }

        // Ensure the Data sheet is active when the file opens
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Stream an Xlsx spreadsheet as a file download response.
     */
    protected function streamXlsxDownload(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
