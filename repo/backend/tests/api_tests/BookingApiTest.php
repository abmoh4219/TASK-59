<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * BookingApiTest — integration tests for the Booking and Resource endpoints.
 *
 * Each test uses a real MySQL database (test profile) and a fresh HTTP client
 * so session / cookie state does not bleed between tests.
 *
 * Fixtures (from Phase 1 seed) guarantee at least 3 bookable resources exist.
 */
class BookingApiTest extends WebTestCase
{
    /**
     * Clear leftover bookings from prior runs so the fixed-time-slot tests below
     * never collide with stale data in the persistent test database.
     */
    public static function setUpBeforeClass(): void
    {
        static::createClient();
        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $em->createQuery('DELETE FROM App\Entity\BookingAllocation a')->execute();
        $em->createQuery('DELETE FROM App\Entity\Booking b')->execute();
        static::ensureKernelShutdown();
    }

    // -------------------------------------------------------------------------
    // Login helper
    // -------------------------------------------------------------------------

    /**
     * Authenticate as the given user and return [client, csrfToken].
     * The client's cookie jar retains the session cookie for subsequent requests.
     *
     * @return array{0: KernelBrowser, 1: string}
     */
    private function login(string $username, string $password): array
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => $password])
        );

        $this->assertResponseStatusCodeSame(200, "Login for '$username' must return 200");

        $data      = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $data['csrfToken'] ?? '';

        $this->assertNotEmpty($csrfToken, 'Login response must include a non-empty csrfToken');

        return [$client, $csrfToken];
    }

    // -------------------------------------------------------------------------
    // Test: GET /api/health — smoke test, no auth required
    // -------------------------------------------------------------------------

    /**
     * The health endpoint must return HTTP 200 without authentication.
     */
    public function testHealthCheck(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);
    }

    // -------------------------------------------------------------------------
    // Test: GET /api/resources — authenticated employee sees resource list
    // -------------------------------------------------------------------------

    /**
     * An authenticated employee must receive a 200 response containing a JSON
     * array. Every item in the array must have at minimum the keys 'id', 'name',
     * and 'type'. The seed data guarantees at least one resource exists.
     */
    public function testListResources(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        $client->request(
            'GET',
            '/api/resources',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'GET /api/resources must return a JSON array');
        $this->assertNotEmpty($data, 'Resource list must contain at least one resource (check seed data)');

        $first = $data[0];
        $this->assertArrayHasKey('id',   $first, 'Each resource must have an "id" field');
        $this->assertArrayHasKey('name', $first, 'Each resource must have a "name" field');
        $this->assertArrayHasKey('type', $first, 'Each resource must have a "type" field');
        $this->assertIsInt($first['id'], '"id" field must be an integer');
        $this->assertNotEmpty($first['name'], '"name" field must not be empty');
    }

    // -------------------------------------------------------------------------
    // Test: GET /api/resources — unauthenticated request is rejected
    // -------------------------------------------------------------------------

    /**
     * Without a valid session, /api/resources must return 401 (or 403).
     */
    public function testListResourcesUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/resources');

        $statusCode = $client->getResponse()->getStatusCode();

        $this->assertContains(
            $statusCode,
            [401, 403],
            'Unauthenticated request to /api/resources must be rejected with 401 or 403'
        );
    }

    // -------------------------------------------------------------------------
    // Test: POST /api/bookings — create a new booking
    // -------------------------------------------------------------------------

    /**
     * An employee must be able to POST a new booking with a valid future date
     * range. The response must be 201 and include an 'id' field.
     */
    public function testCreateBookingSuccess(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        // Fetch the resource list first to get a real resource ID.
        $client->request(
            'GET',
            '/api/resources',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );
        $resources  = json_decode($client->getResponse()->getContent(), true);
        $resourceId = $resources[0]['id'];

        $uniqueKey = 'test-create-' . uniqid('', true);

        $client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_X-CSRF-TOKEN'  => $csrfToken,
            ],
            json_encode([
                'resourceId'    => $resourceId,
                'startDatetime' => '2035-08-01 09:00:00',
                'endDatetime'   => '2035-08-01 11:00:00',
                'purpose'       => 'API test booking',
                'clientKey'     => $uniqueKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Creating a valid booking must return 201');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'Create booking response must be a JSON object');
        $this->assertArrayHasKey('id', $data, 'Response must include an "id" field');
        $this->assertIsInt($data['id'], '"id" field must be an integer');
        $this->assertGreaterThan(0, $data['id'], '"id" must be a positive integer');
        $this->assertArrayHasKey('status', $data, 'Response must include a "status" field');
        $this->assertSame('active', $data['status'], 'Newly created booking must have status "active"');
    }

    // -------------------------------------------------------------------------
    // Test: POST /api/bookings (twice) — idempotent create
    // -------------------------------------------------------------------------

    /**
     * Posting the same clientKey twice must return the same booking ID on both
     * requests, proving the idempotency guard is active.
     */
    public function testIdempotentBookingReturnsSameId(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        // Fetch a valid resource ID.
        $client->request(
            'GET',
            '/api/resources',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );
        $resources  = json_decode($client->getResponse()->getContent(), true);
        $resourceId = $resources[0]['id'];

        // Use a fixed key so the second request triggers the idempotency path.
        $idempotentKey = 'idempotent-test-' . uniqid('', true);

        $payload = json_encode([
            'resourceId'    => $resourceId,
            'startDatetime' => '2036-07-10 13:00:00',
            'endDatetime'   => '2036-07-10 15:00:00',
            'purpose'       => 'Idempotency test',
            'clientKey'     => $idempotentKey,
        ]);

        $headers = [
            'CONTENT_TYPE'      => 'application/json',
            'HTTP_X-CSRF-TOKEN' => $csrfToken,
        ];

        // First POST — creates the booking.
        $client->request('POST', '/api/bookings', [], [], $headers, $payload);
        $this->assertResponseStatusCodeSame(201, 'First booking request must return 201');
        $firstData = json_decode($client->getResponse()->getContent(), true);
        $firstId   = $firstData['id'];

        // Second POST with the same clientKey — must return the existing booking.
        $client->request('POST', '/api/bookings', [], [], $headers, $payload);
        // The service returns the existing booking; controller returns 201 again.
        $secondData = json_decode($client->getResponse()->getContent(), true);
        $secondId   = $secondData['id'];

        $this->assertSame(
            $firstId,
            $secondId,
            'Both requests with the same clientKey must return the same booking ID (idempotency)'
        );
    }

    // -------------------------------------------------------------------------
    // Test: DELETE /api/bookings/{id} — cancel own booking
    // -------------------------------------------------------------------------

    /**
     * After cancelling a booking the status must change to 'cancelled'.
     * Steps: create → cancel → fetch detail and assert status.
     */
    public function testCancelOwnBooking(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        // Fetch a valid resource ID.
        $client->request(
            'GET',
            '/api/resources',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );
        $resources  = json_decode($client->getResponse()->getContent(), true);
        $resourceId = $resources[0]['id'];

        // Step 1: create booking.
        $client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            json_encode([
                'resourceId'    => $resourceId,
                'startDatetime' => '2037-09-05 08:00:00',
                'endDatetime'   => '2037-09-05 10:00:00',
                'purpose'       => 'Cancel test',
                'clientKey'     => 'cancel-test-' . uniqid('', true),
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Booking creation before cancel test must succeed');
        $createData = json_decode($client->getResponse()->getContent(), true);
        $bookingId  = $createData['id'];

        // Step 2: cancel the booking.
        $client->request(
            'DELETE',
            "/api/bookings/$bookingId",
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200, 'DELETE /api/bookings/{id} must return 200 for own booking');

        $cancelData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $cancelData, 'Cancel response must include a "message" field');

        // Step 3: fetch the booking detail and confirm status is 'cancelled'.
        $client->request(
            'GET',
            "/api/bookings/$bookingId",
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200, 'GET /api/bookings/{id} must return 200 after cancellation');

        $detailData = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('status', $detailData, 'Booking detail must include a "status" field');
        $this->assertSame(
            'cancelled',
            $detailData['status'],
            'Booking status must be "cancelled" after DELETE request'
        );
    }

    // -------------------------------------------------------------------------
    // Test: POST /api/bookings — missing required fields returns 400
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // Test: GET /api/resources/{id}/availability — check resource availability
    // -------------------------------------------------------------------------

    /**
     * GET /api/resources/{id}/availability?date=YYYY-MM-DD must return an
     * 'available' flag and a 'bookedSlots' array.
     */
    public function testGetResourceAvailability(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        $client->request('GET', '/api/resources', [], [], ['HTTP_X-CSRF-TOKEN' => $csrfToken]);
        $resources  = json_decode($client->getResponse()->getContent(), true);
        $resourceId = $resources[0]['id'];
        $date       = date('Y-m-d', strtotime('+60 days'));

        $client->request(
            'GET',
            "/api/resources/{$resourceId}/availability?date={$date}",
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('available', $data, 'Availability response must include "available"');
        $this->assertArrayHasKey('bookedSlots', $data, 'Availability response must include "bookedSlots"');
        $this->assertIsBool($data['available'], '"available" must be boolean');
        $this->assertIsArray($data['bookedSlots'], '"bookedSlots" must be an array');
    }

    // -------------------------------------------------------------------------
    // Test: GET /api/bookings — list own bookings
    // -------------------------------------------------------------------------

    /**
     * GET /api/bookings must return a list of bookings for the authenticated user.
     */
    public function testListOwnBookings(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        $client->request(
            'GET',
            '/api/bookings',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data, 'GET /api/bookings must return a JSON array');

        // Each booking (if any) must have expected fields
        foreach ($data as $booking) {
            $this->assertArrayHasKey('id', $booking, 'Each booking must have "id"');
            $this->assertArrayHasKey('status', $booking, 'Each booking must have "status"');
        }
    }

    /** Unauthenticated request to GET /api/bookings must be rejected. */
    public function testListBookingsUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/bookings');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    /** POST /api/bookings unauthenticated must return 401. */
    public function testCreateBookingUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/bookings', [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['resourceId' => 1, 'startDatetime' => '2035-01-01 09:00', 'endDatetime' => '2035-01-01 10:00', 'purpose' => 'test'])
        );
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    /**
     * Submitting a booking payload with no purpose must return 400 Bad Request.
     */
    public function testCreateBookingMissingPurposeReturns400(): void
    {
        [$client, $csrfToken] = $this->login('employee', 'Emp@2024!');

        $client->request(
            'POST',
            '/api/bookings',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            json_encode([
                'resourceId'    => 1,
                'startDatetime' => '2035-10-01 09:00:00',
                'endDatetime'   => '2035-10-01 11:00:00',
                // 'purpose' deliberately omitted
            ])
        );

        $this->assertResponseStatusCodeSame(400, 'Missing "purpose" field must return 400');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data, 'Error response must include an "error" field');
    }
}
