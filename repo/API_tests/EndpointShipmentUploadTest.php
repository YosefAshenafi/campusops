<?php

declare(strict_types=1);

namespace tests\api;

use app\model\Order;
use app\model\Shipment;
use app\model\FileUpload;

/**
 * HTTP endpoint tests for:
 *
 * Shipments:
 *   GET  /api/v1/shipments
 *   GET  /api/v1/shipments/:id
 *   POST /api/v1/orders/:order_id/shipments
 *   GET  /api/v1/orders/:order_id/shipments
 *   POST /api/v1/shipments/:id/scan
 *   GET  /api/v1/shipments/:id/scan-history
 *   GET  /api/v1/shipments/:id/exceptions
 *   POST /api/v1/shipments/:id/exceptions
 *
 * File upload:
 *   GET    /api/v1/upload/:id
 *   DELETE /api/v1/upload/:id
 */
class EndpointShipmentUploadTest extends HttpTestCase
{
    private Order    $order;
    private Shipment $shipment;
    private FileUpload $fileRecord;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupUsersLike('http-ship-%');
        $this->cleanupUsersLike('http-test-admin%');
        $this->cleanupTestData();

        $this->order    = $this->createOrder();
        $this->shipment = $this->createShipment($this->order->id);
        $this->fileRecord = $this->createFileRecord();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        $this->cleanupUsersLike('http-ship-%');
        $this->cleanupUsersLike('http-test-admin%');
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // GET /api/v1/shipments
    // ------------------------------------------------------------------

    public function testListShipmentsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/shipments');
        $this->assertUnauthorized($res);
    }

    public function testListShipmentsReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->get('/api/v1/shipments');
        $this->assertForbidden($res);
    }

    public function testListShipmentsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/shipments');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/shipments/:id
    // ------------------------------------------------------------------

    public function testGetShipmentReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id);
        $this->assertUnauthorized($res);
    }

    public function testGetShipmentReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id);

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertEquals($this->shipment->id, $data['id'] ?? null);
    }

    public function testGetShipmentReturns404ForNonExistentId(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/shipments/999999');

        $this->assertNotFound($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/orders/:order_id/shipments
    // ------------------------------------------------------------------

    public function testListOrderShipmentsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/orders/' . $this->order->id . '/shipments');
        $this->assertUnauthorized($res);
    }

    public function testListOrderShipmentsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/orders/' . $this->order->id . '/shipments');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/orders/:order_id/shipments — requires shipments.create
    // ------------------------------------------------------------------

    public function testCreateShipmentReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/shipments', [
            'carrier' => 'UPS',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testCreateShipmentReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->post('/api/v1/orders/' . $this->order->id . '/shipments', [
            'carrier' => 'UPS',
        ]);
        $this->assertForbidden($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/shipments/:id/scan-history
    // ------------------------------------------------------------------

    public function testGetScanHistoryReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id . '/scan-history');
        $this->assertUnauthorized($res);
    }

    public function testGetScanHistoryReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id . '/scan-history');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/shipments/:id/exceptions
    // ------------------------------------------------------------------

    public function testGetExceptionsReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id . '/exceptions');
        $this->assertUnauthorized($res);
    }

    public function testGetExceptionsReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/shipments/' . $this->shipment->id . '/exceptions');

        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/shipments/:id/confirm-delivery — requires shipments.deliver
    // ------------------------------------------------------------------

    public function testConfirmDeliveryReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/confirm-delivery', []);
        $this->assertUnauthorized($res);
    }

    public function testConfirmDeliveryReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/confirm-delivery', []);
        $this->assertForbidden($res);
    }

    public function testConfirmDeliverySuccessForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/confirm-delivery', []);
        $this->assertStatus(200, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/shipments/:id/exceptions — requires shipments.exception
    // ------------------------------------------------------------------

    public function testReportExceptionReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/exceptions', [
            'description' => 'Package damaged',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testReportExceptionReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/exceptions', [
            'description' => 'Package damaged',
        ]);
        $this->assertForbidden($res);
    }

    public function testReportExceptionSuccessForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/exceptions', [
            'description' => 'Package arrived damaged',
        ]);
        $this->assertStatus(201, $res);
        $this->assertSuccess($res);
    }

    // ------------------------------------------------------------------
    // POST /api/v1/shipments/:id/scan — requires shipments.update
    // ------------------------------------------------------------------

    public function testScanShipmentReturns401WhenUnauthenticated(): void
    {
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/scan', [
            'scan_code' => 'ABC123',
        ]);
        $this->assertUnauthorized($res);
    }

    public function testScanShipmentReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->post('/api/v1/shipments/' . $this->shipment->id . '/scan', [
            'scan_code' => 'ABC123',
        ]);
        $this->assertForbidden($res);
    }

    // ------------------------------------------------------------------
    // GET /api/v1/upload/:id
    // ------------------------------------------------------------------

    public function testGetFileReturns401WhenUnauthenticated(): void
    {
        $res = $this->get('/api/v1/upload/' . $this->fileRecord->id);
        $this->assertUnauthorized($res);
    }

    public function testGetFileReturns403WhenNotOwnerOrAdmin(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->get('/api/v1/upload/' . $this->fileRecord->id);
        // regular user without ownership → 403
        $this->assertForbidden($res);
    }

    public function testGetFileReturns200ForAdmin(): void
    {
        $this->loginAsAdmin();
        $res = $this->get('/api/v1/upload/' . $this->fileRecord->id);

        $this->assertStatus(200, $res);
        $data = $res['body']['data'] ?? [];
        $this->assertEquals($this->fileRecord->id, $data['id'] ?? null);
    }

    // ------------------------------------------------------------------
    // DELETE /api/v1/upload/:id — requires uploads.delete
    // ------------------------------------------------------------------

    public function testDeleteFileReturns401WhenUnauthenticated(): void
    {
        $res = $this->delete('/api/v1/upload/' . $this->fileRecord->id);
        $this->assertUnauthorized($res);
    }

    public function testDeleteFileReturns403ForRegularUser(): void
    {
        $this->loginAsRole('regular_user', 'http-ship-regular');
        $res = $this->delete('/api/v1/upload/' . $this->fileRecord->id);
        $this->assertForbidden($res);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function createOrder(): Order
    {
        $order = new Order();
        $order->activity_id   = 1;
        $order->created_by    = 1;
        $order->team_lead_id  = 1;
        $order->state         = 'placed';
        $order->items         = json_encode([]);
        $order->amount        = 0.0;
        $order->ticket_number = 'http-ship-order-' . uniqid();
        $order->save();
        return $order;
    }

    private function createShipment(int $orderId): Shipment
    {
        $s = new Shipment();
        $s->order_id        = $orderId;
        $s->carrier         = 'UPS';
        $s->tracking_number = 'http-ship-track-' . uniqid();
        $s->status          = 'created';
        $s->save();
        return $s;
    }

    private function createFileRecord(): FileUpload
    {
        $adminUser = \app\model\User::where('username', 'http-test-admin')->find();
        $uploadedBy = $adminUser ? $adminUser->id : 1;

        $f = new FileUpload();
        $f->uploaded_by   = $uploadedBy;
        $f->filename      = 'http-ship-file-' . uniqid() . '.pdf';
        $f->original_name = 'test.pdf';
        $f->sha256        = hash('sha256', 'http-ship-test-' . uniqid());
        $f->file_path     = '/tmp/nonexistent.pdf';
        $f->size          = 1024;
        $f->category      = 'general';
        $f->save();
        return $f;
    }

    private function cleanupTestData(): void
    {
        Order::where('ticket_number', 'like', 'http-ship-order-%')->delete();
        Shipment::where('tracking_number', 'like', 'http-ship-track-%')->delete();
        FileUpload::where('filename', 'like', 'http-ship-file-%')->delete();
    }
}
