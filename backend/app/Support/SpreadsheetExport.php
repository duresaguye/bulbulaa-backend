<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds .xlsx downloads from a ReportDocument with OpenSpout, which Filament already installs.
 */
class SpreadsheetExport
{
    /**
     * @param  array<string, mixed>  $document  from ReportDocument::forReport()
     */
    public static function report(array $document): StreamedResponse
    {
        return self::stream($document['filename'], function (Writer $writer) use ($document): void {
            $bold = (new Style)->setFontBold();
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(self::sheetName(__('Report')));
            $sheet->setColumnWidth(48, 1);
            $sheet->setColumnWidth(18, 2, 3, 4);
            $sheet->setColumnWidth(48, 5);

            $writer->addRow(Row::fromValues([$document['title']], (new Style)->setFontBold()->setFontSize(14)));
            $writer->addRow(Row::fromValues([$document['subtitle']]));
            $writer->addRow(Row::fromValues([ReportDocument::printedOn()]));
            $writer->addRow(Row::fromValues([]));

            foreach ($document['meta'] as $item) {
                $writer->addRow(Row::fromValues([$item['label'], $item['value']]));
            }

            if ($document['results'] !== null) {
                $results = $document['results'];
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([$results['heading']], $bold));
                $writer->addRow(Row::fromValues([__('Target'), __('Measurement unit'), $results['target_label'], $results['actual_label'], __('Achievement').' (%)', __('Note (optional)')], $bold));

                foreach ($results['rows'] as $row) {
                    $writer->addRow(Row::fromValues([
                        $row['title'],
                        $row['unit'],
                        self::number($row['target']),
                        self::number($row['actual']),
                        $row['achievement'],
                        (string) ($row['note'] ?? ''),
                    ]));
                }
            }

            $writer->addRow(Row::fromValues([]));

            foreach ($document['narrative'] as $item) {
                $writer->addRow(Row::fromValues([$item['label']], $bold));
                $writer->addRow(Row::fromValues([filled($item['value']) ? $item['value'] : __('Not provided')]));
            }

            $writer->addNewSheetAndMakeItCurrent()->setName(self::sheetName(__('Review history')));
            $writer->getCurrentSheet()->setColumnWidth(22, 1, 2);
            $writer->getCurrentSheet()->setColumnWidth(30, 3);
            $writer->getCurrentSheet()->setColumnWidth(60, 4);
            $writer->addRow(Row::fromValues([__('Status'), __('By'), __('When'), __('Comment for the office (optional)')], $bold));

            foreach ($document['reviews'] as $review) {
                $writer->addRow(Row::fromValues([$review['action'], $review['person'], $review['when'], (string) ($review['comment'] ?? '')]));
            }
        });
    }

    /**
     * @param  array<string, mixed>  $document  from ReportDocument::forOfficePerformance()
     */
    public static function officePerformance(array $document): StreamedResponse
    {
        return self::stream($document['filename'], function (Writer $writer) use ($document): void {
            $bold = (new Style)->setFontBold();
            $sheet = $writer->getCurrentSheet();
            $sheet->setName(self::sheetName(__('Offices')));
            $sheet->setColumnWidth(36, 1);
            $sheet->setColumnWidth(14, 2, 3, 4, 5);
            $sheet->setColumnWidth(22, 6, 7);
            $sheet->setColumnWidth(12, 8);

            $writer->addRow(Row::fromValues([$document['title']], (new Style)->setFontBold()->setFontSize(14)));
            $writer->addRow(Row::fromValues([$document['subtitle']]));
            $writer->addRow(Row::fromValues([ReportDocument::printedOn()]));
            $writer->addRow(Row::fromValues([]));
            $writer->addRow(Row::fromValues([
                __('Office'), __('On track'), __('At risk'), __('Behind plan'), __('Not started'),
                $document['week_label'], $document['month_label'], __('Overdue'),
            ], $bold));

            foreach ($document['rows'] as $row) {
                $writer->addRow(Row::fromValues([
                    str_repeat('  ', $row['depth']).$row['office'],
                    $row['on_track'], $row['at_risk'], $row['behind'], $row['not_started'],
                    $row['week'], $row['month'], $row['overdue'],
                ]));
            }

            $writer->addNewSheetAndMakeItCurrent()->setName(self::sheetName(__('Targets')));
            $targets = $writer->getCurrentSheet();
            $targets->setColumnWidth(30, 1);
            $targets->setColumnWidth(44, 2);
            $targets->setColumnWidth(16, 3, 4, 5, 6, 7);
            $targets->setColumnWidth(16, 8);
            $writer->addRow(Row::fromValues([
                __('Office'), __('Target'), __('Measurement unit'), __('Annual target'), __('Expected by now'), __('Reported'), __('Achievement').' (%)', __('Pace'),
            ], $bold));

            foreach ($document['rows'] as $row) {
                foreach ($row['targets'] as $target) {
                    $writer->addRow(Row::fromValues([
                        $row['office'], $target['title'], $target['unit'],
                        self::number($target['target']), self::number($target['expected']), self::number($target['actual']),
                        $target['achievement'], $target['pace'],
                    ]));
                }
            }
        });
    }

    /**
     * @param  callable(Writer): void  $fill
     */
    private static function stream(string $filename, callable $fill): StreamedResponse
    {
        return response()->streamDownload(function () use ($fill): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $fill($writer);
            $writer->close();
        }, $filename.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Turns a displayed figure such as "1,200" back into a number so Excel can total and chart it; "—" becomes empty.
     */
    private static function number(string $value): float|string|null
    {
        if ($value === '—' || $value === '') {
            return null;
        }

        $plain = str_replace(',', '', $value);

        return is_numeric($plain) ? (float) $plain : $value;
    }

    /**
     * Excel sheet names are limited to 31 characters and some symbols.
     */
    private static function sheetName(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }
}
