<?php

namespace App\Support;

use DateTimeInterface;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use ZipArchive;

/**
 * Small, dependency-free XLSX writer for simple tabular exports.
 * Sheet definitions contain: name, title, subtitle, columns, and a rows closure.
 */
class XlsxWorkbook
{
    public function write(array $sheets, string $filePath): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekspor Excel membutuhkan ekstensi PHP ZIP.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('File Excel tidak dapat dibuat.');
        }

        $temporarySheets = [];

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($sheets)));
            $zip->addFromString('_rels/.rels', $this->rootRelationships());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships(count($sheets)));
            $zip->addFromString('xl/styles.xml', $this->stylesXml());

            foreach (array_values($sheets) as $index => $sheet) {
                $temporaryPath = tempnam(sys_get_temp_dir(), 'kasir-xlsx-');
                if ($temporaryPath === false) {
                    throw new RuntimeException('File sementara Excel tidak dapat dibuat.');
                }
                $temporarySheets[] = $temporaryPath;

                $this->writeWorksheet($temporaryPath, $sheet);
                if (!$zip->addFile($temporaryPath, 'xl/worksheets/sheet' . ($index + 1) . '.xml')) {
                    throw new RuntimeException('Sheet Excel tidak dapat dimasukkan ke workbook.');
                }

            }

            if (!$zip->close()) {
                throw new RuntimeException('Workbook Excel gagal disimpan.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            throw $exception;
        } finally {
            foreach ($temporarySheets as $temporaryPath) {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }
    }

    private function writeWorksheet(string $path, array $sheet): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Sheet Excel tidak dapat ditulis.');
        }

        $columns = $sheet['columns'];
        $lastColumn = $this->columnName(count($columns));
        $rowNumber = 0;
        $dataRows = 0;

        try {
            $this->writeTo($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            $this->writeTo($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">');
            $this->writeTo($handle, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
            $this->writeTo($handle, '<sheetFormatPr defaultRowHeight="20"/><cols>');

            foreach ($columns as $columnIndex => $column) {
                $columnName = $this->columnName($columnIndex + 1);
                $width = max(10, min(60, (float) ($column['width'] ?? 18)));
                $this->writeTo($handle, '<col min="' . ($columnIndex + 1) . '" max="' . ($columnIndex + 1) . '" width="' . $width . '" customWidth="1"/>');
            }
            $this->writeTo($handle, '</cols><sheetData>');

            $rowNumber++;
            $this->writeTo($handle, '<row r="1" ht="32" customHeight="1">' . $this->inlineCell('A1', $sheet['title'], 1) . '</row>');

            $rowNumber++;
            $this->writeTo($handle, '<row r="2" ht="24" customHeight="1">' . $this->inlineCell('A2', $sheet['subtitle'], 2) . '</row>');
            $this->writeTo($handle, '<row r="3"/>');

            $rowNumber++;
            $headerCells = '';
            foreach ($columns as $columnIndex => $column) {
                $headerCells .= $this->inlineCell($this->columnName($columnIndex + 1) . '4', $column['header'], 3);
            }
            $this->writeTo($handle, '<row r="4" ht="26" customHeight="1">' . $headerCells . '</row>');

            $dataRows = 0;
            foreach (($sheet['rows'])() as $values) {
                $dataRows++;
                $rowNumber = $dataRows + 4;
                $cells = '';
                foreach ($columns as $columnIndex => $column) {
                    $cell = $values[$columnIndex] ?? null;
                    $cellType = $column['type'] ?? 'text';
                    if (is_array($cell) && array_key_exists('value', $cell)) {
                        $cellType = $cell['type'] ?? $cellType;
                        $cell = $cell['value'];
                    }
                    $cells .= $this->dataCell($this->columnName($columnIndex + 1) . $rowNumber, $cell, $cellType, $dataRows % 2 === 0);
                }
                $this->writeTo($handle, '<row r="' . $rowNumber . '">' . $cells . '</row>');
            }

            $this->writeTo($handle, '</sheetData>');
            $this->writeTo($handle, '<autoFilter ref="A4:' . $lastColumn . max(4, $rowNumber) . '"/>');
            $this->writeTo($handle, '<mergeCells count="2"><mergeCell ref="A1:' . $lastColumn . '1"/><mergeCell ref="A2:' . $lastColumn . '2"/></mergeCells>');
            $this->writeTo($handle, '<pageMargins left="0.35" right="0.35" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>');
            $this->writeTo($handle, '</worksheet>');
        } finally {
            fclose($handle);
        }

        return $dataRows;
    }

    private function dataCell(string $reference, mixed $value, string $type, bool $alternate): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $reference . '"/>';
        }

        $style = match ($type) {
            'date' => $alternate ? 11 : 7,
            'currency' => $alternate ? 10 : 6,
            'number' => $alternate ? 9 : 5,
            default => $alternate ? 8 : 4,
        };

        if ($type === 'date') {
            $serial = $this->excelDate($value);
            return '<c r="' . $reference . '" s="' . $style . '" t="n"><v>' . $serial . '</v></c>';
        }

        if (in_array($type, ['number', 'currency'], true)) {
            $number = is_numeric($value) ? (string) $value : '0';
            return '<c r="' . $reference . '" s="' . $style . '" t="n"><v>' . $number . '</v></c>';
        }

        return $this->inlineCell($reference, (string) $value, $style);
    }

    private function inlineCell(string $reference, string $value, int $style): string
    {
        return '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . $this->xmlEscape($value) . '</t></is></c>';
    }

    private function excelDate(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            $dateString = $value->format('Y-m-d H:i:s');
        } else {
            $dateString = (string) $value;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $dateString, new DateTimeZone('UTC'));
        if (!$date) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($dateString, 0, 10), new DateTimeZone('UTC'));
        }
        if (!$date) {
            return '0';
        }

        $excelEpoch = new DateTimeImmutable('1899-12-30 00:00:00', new DateTimeZone('UTC'));
        $seconds = $date->getTimestamp() - $excelEpoch->getTimestamp();
        return number_format($seconds / 86400, 8, '.', '');
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder) . $name;
            $number = intdiv($number - 1, 26);
        }
        return $name;
    }

    private function xmlEscape(string $value): string
    {
        $value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function writeTo($handle, string $contents): void
    {
        if (fwrite($handle, $contents) !== strlen($contents)) {
            throw new RuntimeException('Penulisan file Excel gagal.');
        }
    }

    private function contentTypes(int $sheetCount): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' . $overrides . '</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbookXml(array $sheets): string
    {
        $sheetXml = '';
        foreach (array_values($sheets) as $index => $sheet) {
            $sheetXml .= '<sheet name="' . $this->xmlEscape($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="22000" windowHeight="12000"/></bookViews><sheets>' . $sheetXml . '</sheets><calcPr calcId="191029"/></workbook>';
    }

    private function workbookRelationships(int $sheetCount): string
    {
        $relationships = '';
        for ($i = 1; $i <= $sheetCount; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $relationships . '</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="dd/mm/yyyy hh:mm"/><numFmt numFmtId="165" formatCode="&quot;Rp&quot; #,##0"/></numFmts>'
            . '<fonts count="4"><font><sz val="11"/><name val="Aptos"/><family val="2"/></font><font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/><family val="2"/></font><font><sz val="10"/><color rgb="FF435A74"/><name val="Aptos"/><family val="2"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/><family val="2"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF526D8A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF2F5F8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFE1E8F0"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="12">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center" indent="1"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="3" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="3" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            . '<xf numFmtId="165" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" horizontal="right"/></xf>'
            . '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
