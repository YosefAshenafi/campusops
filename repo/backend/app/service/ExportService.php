<?php

namespace app\service;

use app\model\User;

class ExportService
{
    public function exportToPng(array $data, string $filename, int $userId): string
    {
        $user = User::find($userId);
        $username = $user ? $user->username : 'Unknown';
        $timestamp = date('Y-m-d H:i:s');

        $width = 800;
        $height = 600;
        $image = imagecreatetruecolor($width, $height);

        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocate($image, 200, 200, 200);
        
        imagefill($image, 0, 0, $white);
        imagestring($image, 5, 10, 10, "CampusOps Export", $gray);
        imagestring($image, 3, 10, $height - 40, "Exported by: {$username}", $gray);
        imagestring($image, 3, 10, $height - 25, "Timestamp: {$timestamp}", $gray);

        $row = 0;
        foreach ($data as $key => $value) {
            imagestring($image, 4, 50, 50 + ($row * 20), "{$key}: {$value}", $gray);
            $row++;
        }

        $filepath = runtime_path() . '/exports/' . $filename . '_' . $userId . '_' . time() . '.png';
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        imagepng($image, $filepath);
        imagedestroy($image);

        return $filepath;
    }

    public function exportToPdf(array $data, string $filename, int $userId): string
    {
        $user = User::find($userId);
        $username = $user ? $user->username : 'Unknown';
        $timestamp = date('Y-m-d H:i:s');

        $filepath = runtime_path() . '/exports/' . $filename . '_' . $userId . '_' . time() . '.pdf';
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        // Build PDF binary manually (minimal valid PDF with table data)
        $watermark = "CampusOps - {$username} - {$timestamp}";
        $lines = [];
        $lines[] = 'CampusOps Export Report';
        $lines[] = '';
        foreach ($data as $key => $value) {
            $lines[] = "{$key}: {$value}";
        }
        $lines[] = '';
        $lines[] = "Exported by: {$username}";
        $lines[] = "Timestamp: {$timestamp}";

        $textContent = implode("\n", $lines);

        // Build minimal PDF 1.4 structure
        $pdf = "%PDF-1.4\n";
        $objects = [];

        // Object 1: Catalog
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        // Object 2: Pages
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        // Object 4: Font
        $objects[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        // Build content stream with text
        $stream = "BT\n/F1 10 Tf\n";
        $y = 750;
        foreach ($lines as $line) {
            $escapedLine = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $line);
            $stream .= "50 {$y} Td\n({$escapedLine}) Tj\n0 0 Td\n";
            $y -= 14;
            if ($y < 50) break;
        }
        // Watermark
        $stream .= "0.9 0.9 0.9 rg\n/F1 36 Tf\n200 400 Td\n({$watermark}) Tj\n";
        $stream .= "ET\n";

        $streamLen = strlen($stream);
        $objects[5] = "5 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}endstream\nendobj\n";

        // Object 3: Page
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 5 0 R /Resources << /Font << /F1 4 0 R >> >> >>\nendobj\n";

        // Write objects
        $offsets = [];
        foreach ([1, 2, 3, 4, 5] as $num) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $objects[$num];
        }

        // Cross-reference table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ([1, 2, 3, 4, 5] as $num) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        file_put_contents($filepath, $pdf);

        return $filepath;
    }

    public function exportToExcel(array $data, string $filename, int $userId): string
    {
        $user = User::find($userId);
        $username = $user ? $user->username : 'Unknown';
        $timestamp = date('Y-m-d H:i:s');

        $filepath = runtime_path() . '/exports/' . $filename . '_' . $userId . '_' . time() . '.xlsx';
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $zip = new \ZipArchive();
        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create XLSX file');
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // xl/worksheets/sheet1.xml — prepend metadata rows, then data, then footer
        $metaRows = [
            ['CampusOps Export'],
            ['Generated by: ' . $username],
            ['Timestamp: ' . $timestamp],
            [],
        ];
        $allRows = array_merge($metaRows, $data);

        $sheetData = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($allRows as $rowIdx => $row) {
            $rowNum = $rowIdx + 1;
            $sheetData .= '<row r="' . $rowNum . '">';
            if (!empty($row)) {
                foreach (array_values($row) as $colIdx => $cell) {
                    $colLetter = $colIdx < 26 ? chr(65 + $colIdx) : 'A' . chr(65 + $colIdx - 26);
                    $cellRef = $colLetter . $rowNum;
                    $cellVal = htmlspecialchars((string) $cell, ENT_XML1, 'UTF-8');
                    $sheetData .= '<c r="' . $cellRef . '" t="inlineStr"><is><t>' . $cellVal . '</t></is></c>';
                }
            }
            $sheetData .= '</row>';
        }

        // Footer row
        $footerRow = count($allRows) + 2;
        $footerVal = htmlspecialchars("Exported from CampusOps | {$username} | {$timestamp}", ENT_XML1, 'UTF-8');
        $sheetData .= '<row r="' . $footerRow . '"><c r="A' . $footerRow . '" t="inlineStr"><is><t>' . $footerVal . '</t></is></c></row>';

        $sheetData .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetData);

        $zip->close();

        return $filepath;
    }

    public function getWatermarkText(int $userId): string
    {
        $user = User::find($userId);
        if (!$user) {
            return 'CampusOps';
        }
        return "CampusOps - {$user->username} - " . date('Y-m-d H:i:s');
    }
}