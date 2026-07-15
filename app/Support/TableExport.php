<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single download path for admin table exports (products, orders, returns,
 * customers, inventory). Give it a format, a filename stem, the ordered column
 * keys (also the header row) and the rows (each an assoc array); it streams
 * CSV / XLSX / JSON. Every admin export MUST go through here — never hand-roll a
 * second exporter (same rule as Media / Setting).
 *
 * CSV carries a UTF-8 BOM so Excel reads Arabic; XLSX goes through openspout via
 * a temp file (a zip needs a seekable target) then downloads and self-deletes.
 */
class TableExport
{
    /**
     * @param  list<string>  $columns
     * @param  iterable<array<string, mixed>>  $rows
     */
    public static function download(string $format, string $filenameBase, array $columns, iterable $rows): Response
    {
        $format = in_array($format, ['csv', 'xlsx', 'json'], true) ? $format : 'csv';
        $filename = $filenameBase.'-'.now()->format('Y-m-d');

        $project = static fn (array $row): array => array_map(static fn (string $c) => $row[$c] ?? null, $columns);

        return match ($format) {
            'xlsx' => self::xlsx("{$filename}.xlsx", $columns, $rows, $project),
            'json' => self::json("{$filename}.json", $columns, $rows, $project),
            default => self::csv("{$filename}.csv", $columns, $rows, $project),
        };
    }

    private static function csv(string $filename, array $columns, iterable $rows, callable $project): Response
    {
        return response()->streamDownload(function () use ($rows, $columns, $project) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads Arabic correctly
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($v) => $v ?? '', $project($row)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function json(string $filename, array $columns, iterable $rows, callable $project): Response
    {
        $data = [];
        foreach ($rows as $row) {
            $data[] = array_combine($columns, $project($row));
        }

        return response()->streamDownload(
            static fn () => print (json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    private static function xlsx(string $filename, array $columns, iterable $rows, callable $project): Response
    {
        $temp = tempnam(sys_get_temp_dir(), 'retab_export_');
        $writer = new Writer();
        $writer->openToFile($temp);
        $writer->addRow(Row::fromValues($columns));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(static fn ($v) => $v ?? '', $project($row))));
        }
        $writer->close();

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
