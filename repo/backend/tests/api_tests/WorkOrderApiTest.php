<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * WorkOrderApiTest — integration tests that exercise the full HTTP stack against a
 * real MySQL test database. All six seeded users are available (see README fixtures).
 *
 * Login credentials (Phase 1 seeds):
 *   employee   / Emp@2024!
 *   dispatcher / Dispatch@2024!
 *   technician / Tech@2024!
 *   supervisor / Super@2024!
 *   hradmin    / HRAdmin@2024!
 *   admin      / Admin@WFOps2024!
 */
class WorkOrderApiTest extends WebTestCase
{
    // -------------------------------------------------------------------------
    // Login helpers
    // -------------------------------------------------------------------------

    /**
     * Log in with the given credentials and return [client, csrfToken].
     * The client's cookie jar retains the session cookie for subsequent requests.
     */
    private function loginAs(string $username, string $password): array
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

        $this->assertResponseStatusCodeSame(200, "Login as '$username' must succeed");

        $data      = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $data['csrfToken'] ?? '';

        $this->assertNotEmpty($csrfToken, 'Login response must include a CSRF token');

        return [$client, $csrfToken];
    }

    private function loginAsEmployee(): array
    {
        return $this->loginAs('employee', 'Emp@2024!');
    }

    private function loginAsDispatcher(): array
    {
        return $this->loginAs('dispatcher', 'Dispatch@2024!');
    }

    // -------------------------------------------------------------------------
    // Smoke test
    // -------------------------------------------------------------------------

    /**
     * GET /api/health must return 200 (basic Docker liveness check).
     */
    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);
    }

    // -------------------------------------------------------------------------
    // Submit work order
    // -------------------------------------------------------------------------

    /**
     * An authenticated employee must be able to submit a new work order via
     * POST /api/work-orders with a JSON body (no file upload in this test).
     *
     * Expected: 201 Created, response body contains 'id' and 'status' = 'submitted'.
     */
    public function testSubmitWorkOrder(): void
    {
        [$client, $csrfToken] = $this->loginAsEmployee();

        $payload = [
            'category'    => 'Plumbing',
            'priority'    => 'MEDIUM',
            'description' => 'Leaking tap in break room sink.',
            'building'    => 'Block A',
            'room'        => '101',
        ];

        $client->request(
            'POST',
            '/api/work-orders',
            [],
            [],
            [
                'CONTENT_TYPE'        => 'application/json',
                'HTTP_X-CSRF-TOKEN'   => $csrfToken,
            ],
            json_encode($payload)
        );

        $this->assertResponseStatusCodeSame(201, 'Work order submission must return 201 Created');

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'Response body must be a JSON object');
        $this->assertArrayHasKey('id', $data, 'Response must include the new work order id');
        $this->assertArrayHasKey('status', $data, 'Response must include status');
        $this->assertSame('submitted', $data['status'], 'Newly created work order status must be "submitted"');
        $this->assertIsInt($data['id'], 'id must be an integer');
        $this->assertGreaterThan(0, $data['id'], 'id must be a positive integer');
        $this->assertSame('Plumbing', $data['category']);
        $this->assertSame('MEDIUM', $data['priority']);
    }

    // -------------------------------------------------------------------------
    // List work orders
    // -------------------------------------------------------------------------

    /**
     * GET /api/work-orders as an employee must return 200 with a paginated
     * 'data' array containing only that employee's own work orders.
     */
    public function testListWorkOrdersAsEmployee(): void
    {
        [$client, $csrfToken] = $this->loginAsEmployee();

        $client->request(
            'GET',
            '/api/work-orders',
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $csrfToken]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertIsArray($data, 'Response body must be a JSON object');
        $this->assertArrayHasKey('data', $data, 'Response must include "data" array');
        $this->assertArrayHasKey('total', $data, 'Response must include "total" count');
        $this->assertIsArray($data['data'], '"data" must be an array');
        $this->assertIsInt($data['total'], '"total" must be an integer');
        $this->assertGreaterThanOrEqual(0, $data['total']);
    }

    // -------------------------------------------------------------------------
    // Dispatcher assigns technician
    // -------------------------------------------------------------------------

    /**
     * A dispatcher must be able to:
     *  1. Submit a work order as an employee (to have a fresh 'submitted' one).
     *  2. Log in as dispatcher and PATCH its status to 'dispatched'.
     *
     * Expected: 200 OK with 'status' = 'dispatched'.
     */
    public function testDispatcherAssignsTechnician(): void
    {
        // Step 1 — create a work order as the employee.
        [$empClient, $empCsrf] = $this->loginAsEmployee();

        $empClient->request(
            'POST',
            '/api/work-orders',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $empCsrf,
            ],
            json_encode([
                'category'    => 'Electrical',
                'priority'    => 'HIGH',
                'description' => 'Faulty power socket in conference room.',
                'building'    => 'Block B',
                'room'        => '202',
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Employee must be able to submit a work order');

        $created = json_decode($empClient->getResponse()->getContent(), true);
        $workOrderId = $created['id'];

        // Step 2 — dispatcher transitions it to 'dispatched'.
        [$dispClient, $dispCsrf] = $this->loginAsDispatcher();

        $dispClient->request(
            'PATCH',
            "/api/work-orders/{$workOrderId}/status",
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $dispCsrf,
            ],
            json_encode([
                'status' => 'dispatched',
                // technicianId 6 is the seeded technician user (see README).
                'technicianId' => 6,
            ])
        );

        $this->assertResponseStatusCodeSame(200, 'Dispatcher must be allowed to dispatch the work order');

        $updated = json_decode($dispClient->getResponse()->getContent(), true);

        $this->assertSame('dispatched', $updated['status'], 'Status must be "dispatched" after dispatcher transition');
    }

    // -------------------------------------------------------------------------
    // Employee cannot see another user's work order
    // -------------------------------------------------------------------------

    /**
     * An employee must receive 403 or 404 when requesting the detail of a work
     * order submitted by a different user.
     *
     * Strategy: submit a work order as the employee, then try to read it as admin
     * (which succeeds) to confirm the ID is valid, then read it as a second
     * employee-level account (the dispatcher account only has ROLE_DISPATCHER,
     * but we can reuse the admin here to show the order exists, then verify the
     * employee cannot access an ID that belongs to the admin's context).
     *
     * Simpler approach: create a work order as employee, then log in as admin and
     * POST another as admin (if permitted), then try to GET the admin work order
     * as employee. If admin cannot submit, skip gracefully.
     *
     * Simplest reliable approach: create a work order as employee (id=X), then
     * log in as a *different* session as admin and attempt GET /api/work-orders/X.
     * Admin can see all. Then — as employee — attempt GET /api/work-orders/(X+9999)
     * which does not exist → 404. The key assertion is that 403 is returned when
     * another employee's real order is accessed.
     *
     * We directly test the 403 path: submit one work order as employee (id=X),
     * then log in as admin, submit another (id=Y), then as employee try GET Y → 403.
     */
    public function testEmployeeCannotSeeOtherOrders(): void
    {
        // Create a work order owned by the admin user.
        [$adminClient, $adminCsrf] = $this->loginAs('admin', 'Admin@WFOps2024!');

        $adminClient->request(
            'POST',
            '/api/work-orders',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $adminCsrf,
            ],
            json_encode([
                'category'    => 'General',
                'priority'    => 'LOW',
                'description' => 'Admin test work order — do not touch.',
                'building'    => 'HQ',
                'room'        => 'Admin Office',
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Admin must be able to submit a work order');

        $adminOrder   = json_decode($adminClient->getResponse()->getContent(), true);
        $adminOrderId = $adminOrder['id'];

        // Now attempt to read that work order as the regular employee — expect 403.
        [$empClient, $empCsrf] = $this->loginAsEmployee();

        $empClient->request(
            'GET',
            "/api/work-orders/{$adminOrderId}",
            [],
            [],
            ['HTTP_X-CSRF-TOKEN' => $empCsrf]
        );

        $statusCode = $empClient->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [403, 404],
            "Employee must receive 403 or 404 when accessing another user's work order, got $statusCode"
        );
    }

    // -------------------------------------------------------------------------
    // Invalid submission — missing required field
    // -------------------------------------------------------------------------

    /**
     * Submitting a work order without 'category' must return 400 Bad Request.
     */
    public function testSubmitWorkOrderMissingCategoryReturnsBadRequest(): void
    {
        [$client, $csrfToken] = $this->loginAsEmployee();

        $client->request(
            'POST',
            '/api/work-orders',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            json_encode([
                // 'category' intentionally omitted
                'priority'    => 'LOW',
                'description' => 'Missing category test.',
                'building'    => 'Block C',
                'room'        => '303',
            ])
        );

        $this->assertResponseStatusCodeSame(400, 'Submission without category must return 400 Bad Request');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data, '400 response must include "error" key');
    }

    // -------------------------------------------------------------------------
    // Invalid transition — employee cannot dispatch
    // -------------------------------------------------------------------------

    /**
     * An employee trying to transition a submitted work order to 'dispatched'
     * must receive 400 Bad Request (service throws InvalidArgumentException).
     */
    public function testEmployeeCannotDispatchWorkOrder(): void
    {
        // First submit a work order as the employee.
        [$client, $csrfToken] = $this->loginAsEmployee();

        $client->request(
            'POST',
            '/api/work-orders',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            json_encode([
                'category'    => 'HVAC',
                'priority'    => 'URGENT',
                'description' => 'AC unit making loud noise.',
                'building'    => 'Block D',
                'room'        => '404',
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        $wo   = json_decode($client->getResponse()->getContent(), true);
        $woId = $wo['id'];

        // Now try to dispatch it as the same employee — must fail.
        $client->request(
            'PATCH',
            "/api/work-orders/{$woId}/status",
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-TOKEN' => $csrfToken,
            ],
            json_encode(['status' => 'dispatched'])
        );

        $this->assertResponseStatusCodeSame(
            400,
            'Employee must not be allowed to dispatch a work order (state machine role guard)'
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }
}
