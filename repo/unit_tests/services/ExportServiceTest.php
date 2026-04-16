<?php

declare(strict_types=1);

namespace tests\services;

use app\model\User;
use app\service\ExportService;
use PHPUnit\Framework\TestCase;

class ExportServiceTest extends TestCase
{
    private ExportService $service;
    private array $exportedFiles = [];
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService();
        $this->testUser = $this->createTestUser();
    }

    protected function tearDown(): void
    {
        foreach ($this->exportedFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        User::where('username', 'unit-test-export-user')->delete();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // exportToPdf
    // ------------------------------------------------------------------

    public function testExportToPdfReturnsFilePath(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $path = $this->service->exportToPdf($data, 'unit_test_export', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $this->assertStringEndsWith('.pdf', $path);
        $this->assertFileExists($path);
    }

    public function testExportToPdfFileHasPdfMagicBytes(): void
    {
        $data = ['title' => 'Unit Test Report'];
        $path = $this->service->exportToPdf($data, 'unit_test_magic', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $header = file_get_contents($path, false, null, 0, 5);
        $this->assertStringStartsWith('%PDF-', $header);
    }

    public function testExportToPdfContainsDataValues(): void
    {
        $data = ['campus' => 'MainCampus', 'count' => '42'];
        $path = $this->service->exportToPdf($data, 'unit_test_content', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $content = file_get_contents($path);
        $this->assertStringContainsString('MainCampus', $content);
        $this->assertStringContainsString('42', $content);
    }

    // ------------------------------------------------------------------
    // exportToExcel
    // ------------------------------------------------------------------

    public function testExportToExcelReturnsFilePath(): void
    {
        $data = [['Column A', 'Column B'], ['row1a', 'row1b']];
        $path = $this->service->exportToExcel($data, 'unit_test_excel', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $this->assertStringEndsWith('.xlsx', $path);
        $this->assertFileExists($path);
    }

    public function testExportToExcelFileIsValidZip(): void
    {
        $data = [['Item', 'Count'], ['orders', '5']];
        $path = $this->service->exportToExcel($data, 'unit_test_zip', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $zip = new \ZipArchive();
        $result = $zip->open($path);
        $this->assertTrue($result === true, 'XLSX file must be a valid ZIP archive');
        $zip->close();
    }

    public function testExportToExcelContainsWorksheetFile(): void
    {
        $data = [['A', 'B']];
        $path = $this->service->exportToExcel($data, 'unit_test_sheet', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $zip = new \ZipArchive();
        $zip->open($path);
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        $this->assertNotFalse($sheetXml, 'XLSX must contain xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<worksheet', $sheetXml);
    }

    // ------------------------------------------------------------------
    // getWatermarkText
    // ------------------------------------------------------------------

    public function testGetWatermarkTextContainsUsername(): void
    {
        $text = $this->service->getWatermarkText($this->testUser->id);

        $this->assertStringContainsString('unit-test-export-user', $text);
        $this->assertStringContainsString('CampusOps', $text);
    }

    public function testGetWatermarkTextReturnsFallbackForUnknownUser(): void
    {
        $text = $this->service->getWatermarkText(999999);

        $this->assertEquals('CampusOps', $text);
    }

    public function testGetWatermarkTextContainsDateStamp(): void
    {
        $text = $this->service->getWatermarkText($this->testUser->id);

        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}/', $text);
    }

    // ------------------------------------------------------------------
    // exportToPng (only when GD extension is available)
    // ------------------------------------------------------------------

    public function testExportToPngReturnsFilePathWhenGdAvailable(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not available');
        }

        $data = ['label' => 'Unit Test PNG'];
        $path = $this->service->exportToPng($data, 'unit_test_png', $this->testUser->id);
        $this->exportedFiles[] = $path;

        $this->assertStringEndsWith('.png', $path);
        $this->assertFileExists($path);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createTestUser(): User
    {
        User::where('username', 'unit-test-export-user')->delete();
        $user = new User();
        $user->username = 'unit-test-export-user';
        $user->role = 'administrator';
        $user->status = 'active';
        $user->setPassword('TestPassword123');
        $user->save();
        return $user;
    }
}
