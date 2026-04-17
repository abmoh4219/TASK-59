<?php

namespace App\Tests\ApiTests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * ApprovalApiTest — integration tests for /api/approvals endpoints.
 *
 * Requires a running MySQL test database with seed data.
 * Tests the full approve-step-1 flow: employee creates request,
 * supervisor sees it in the queue and approves it.
 */
class ApprovalApiTest extends WebTestCase
{
    private static ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $sharedClient = null;

    protected function setUp(): void
    {
        parent::setUp();
        self::$sharedClient = null;
        self::ensureKernelShutdown();
    }

    // -------------------------------------------------------------------------
    // Helper: login and return [client, csrfToken]
    // -------------------------------------------------------------------------

    /**
     * Compute the session-bound HMAC signature for a privileged write.
     * Mirrors the scheme used by the browser client interceptor and by
     * ApiSignatureAuthenticator::validate().
     *
     * @return array{signature: string, timestamp: string}
     */
    private function sign(string $method, string $path, string $body, string $key): array
    {
        $timestamp = (string) time();
        $bodyHash = hash('sha256', $body);
        $payload = $method . $path . $timestamp . $bodyHash;
        return [
            'signature' => hash_hmac('sha256', $payload, $key),
            'timestamp' => $timestamp,
        ];
    }

    private function loginAs(string $username, string $password): array
    {
        if (self::$sharedClient === null) {
            self::$sharedClient = static::createClient();
        }
        $client = self::$sharedClient;

        $client->request(
            'POST',
            '/api/auth/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['username' => $username, 'password' => $password])
        );

        $this->assertResponseStatusCodeSame(200, "Login failed for: $username");

        $data = json_decode($client->getResponse()->getContent(), true);
        $csrfToken = $data['csrfToken'] ?? '';

        return [$client, $csrfToken];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    /**
     * Full happy-path approval flow:
     *  1. Employee creates an exception request.
     *  2. Supervisor fetches their approval queue and finds the step.
     *  3. Supervisor approves step 1 with a comment.
     *  4. Response confirms success.
     */
    public function testSupervisorApproveStep1(): void
    {
        // Step 1 — Employee creates a request
        [$employeeClient, $employeeCsrf] = $this->loginAs('employee', 'Emp@2024!');

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $clientKey = 'approve-flow-' . uniqid('', true);

        $employeeClient->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $employeeCsrf,
            ],
            json_encode([
                'requestType' => 'PTO',
                'startDate'   => $today,
                'endDate'     => $today,
                'reason'      => 'Supervisor approval flow test',
                'clientKey'   => $clientKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Employee should be able to create a request');

        $requestData = json_decode($employeeClient->getResponse()->getContent(), true);
        $requestId = $requestData['id'];
        $this->assertNotNull($requestId, 'Created request must have an ID');
        $this->assertNotEmpty($requestData['steps'], 'Request must have at least one approval step');

        // Grab the step ID from the response (step 1)
        $stepId = $requestData['steps'][0]['id'];
        $this->assertNotNull($stepId, 'Step 1 must have an ID');

        // Step 2 — Supervisor fetches their queue
        [$supervisorClient, $supervisorCsrf] = $this->loginAs('supervisor', 'Super@2024!');

        $supervisorClient->request(
            'GET',
            '/api/approvals/queue',
            [],
            [],
            [
                'HTTP_X-CSRF-Token' => $supervisorCsrf,
            ]
        );

        $this->assertResponseStatusCodeSame(200);

        $queue = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertIsArray($queue, 'Queue response must be an array');

        // Verify the newly created request's step appears in the queue
        $stepIds = array_column($queue, 'stepId');
        $this->assertContains(
            $stepId,
            $stepIds,
            'The newly created approval step must appear in the supervisor queue',
        );

        // Step 3 — Supervisor approves step 1 (privileged write: requires signature)
        $approveBody = json_encode(['comment' => 'Approved during automated test']);
        $sig = $this->sign('POST', "/api/approvals/{$stepId}/approve", $approveBody, $supervisorCsrf);
        $supervisorClient->request(
            'POST',
            "/api/approvals/{$stepId}/approve",
            [],
            [],
            [
                'CONTENT_TYPE'         => 'application/json',
                'HTTP_X-CSRF-Token'    => $supervisorCsrf,
                'HTTP_X-Api-Signature' => $sig['signature'],
                'HTTP_X-Timestamp'     => $sig['timestamp'],
            ],
            $approveBody
        );

        $this->assertResponseStatusCodeSame(200, 'Supervisor should be able to approve step 1');

        $approveData = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $approveData);
        $this->assertStringContainsStringIgnoringCase(
            'approved',
            $approveData['message'],
            'Success message should say approved',
        );
    }

    /**
     * Supervisor fetching their approval queue must return HTTP 200 and an array.
     * The queue may be empty if no pending requests exist, but the shape must be correct.
     */
    public function testApprovalQueueReturnsData(): void
    {
        [$supervisorClient, $supervisorCsrf] = $this->loginAs('supervisor', 'Super@2024!');

        $supervisorClient->request(
            'GET',
            '/api/approvals/queue',
            [],
            [],
            ['HTTP_X-CSRF-Token' => $supervisorCsrf]
        );

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertIsArray($data, 'Approval queue must return a JSON array');

        // If items exist, each must have the required fields
        foreach ($data as $item) {
            $this->assertArrayHasKey('stepId', $item, 'Queue item must have stepId');
            $this->assertArrayHasKey('requestType', $item, 'Queue item must have requestType');
            $this->assertArrayHasKey('employeeName', $item, 'Queue item must have employeeName');
            $this->assertArrayHasKey('slaDeadline', $item, 'Queue item must have slaDeadline');
            $this->assertArrayHasKey('remainingMinutes', $item, 'Queue item must have remainingMinutes');
            $this->assertArrayHasKey('isOverdue', $item, 'Queue item must have isOverdue flag');
        }
    }

    /**
     * The /api/health endpoint must return HTTP 200 and indicate the API is alive.
     * This test is deliberately lightweight — it verifies routing and container boot.
     */
    public function testHealthEndpoint(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $this->assertResponseStatusCodeSame(200);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertNotNull($data, 'Health endpoint must return valid JSON');
    }

    /**
     * Supervisor rejects step 1 of an employee's exception request.
     * POST /api/approvals/{stepId}/reject must return 200 and mark the request REJECTED.
     */
    public function testSupervisorRejectStep1(): void
    {
        [$employeeClient, $employeeCsrf] = $this->loginAs('employee', 'Emp@2024!');

        $today     = (new \DateTimeImmutable())->format('Y-m-d');
        $clientKey = 'reject-flow-' . uniqid('', true);

        $employeeClient->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $employeeCsrf,
            ],
            json_encode([
                'requestType' => 'CORRECTION',
                'startDate'   => $today,
                'endDate'     => $today,
                'reason'      => 'Reject flow test',
                'clientKey'   => $clientKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201, 'Employee must be able to create a request');
        $requestData = json_decode($employeeClient->getResponse()->getContent(), true);
        $stepId      = $requestData['steps'][0]['id'];

        [$supervisorClient, $supervisorCsrf] = $this->loginAs('supervisor', 'Super@2024!');

        $rejectBody = json_encode(['comment' => 'Rejected during automated test']);
        $sig = $this->sign('POST', "/api/approvals/{$stepId}/reject", $rejectBody, $supervisorCsrf);

        $supervisorClient->request(
            'POST',
            "/api/approvals/{$stepId}/reject",
            [],
            [],
            [
                'CONTENT_TYPE'         => 'application/json',
                'HTTP_X-CSRF-Token'    => $supervisorCsrf,
                'HTTP_X-Api-Signature' => $sig['signature'],
                'HTTP_X-Timestamp'     => $sig['timestamp'],
            ],
            $rejectBody
        );

        $this->assertResponseStatusCodeSame(200, 'Supervisor should be able to reject step 1');

        $rejectData = json_decode($supervisorClient->getResponse()->getContent(), true);
        $this->assertArrayHasKey('message', $rejectData);
        $this->assertStringContainsStringIgnoringCase('rejected', $rejectData['message']);
    }

    /**
     * POST /api/approvals/{stepId}/reassign — admin reassigns a pending step.
     */
    public function testAdminReassignsApprovalStep(): void
    {
        [$employeeClient, $employeeCsrf] = $this->loginAs('employee', 'Emp@2024!');

        $today     = (new \DateTimeImmutable())->format('Y-m-d');
        $clientKey = 'reassign-flow-' . uniqid('', true);

        $employeeClient->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $employeeCsrf,
            ],
            json_encode([
                'requestType' => 'CORRECTION',
                'startDate'   => $today,
                'endDate'     => $today,
                'reason'      => 'Reassign step test',
                'clientKey'   => $clientKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $requestData = json_decode($employeeClient->getResponse()->getContent(), true);
        $stepId      = $requestData['steps'][0]['id'];

        // Admin reassigns step 1 to HR Admin
        [$adminClient, $adminCsrf] = $this->loginAs('admin', 'Admin@WFOps2024!');

        // Find HR admin ID
        $adminClient->request('GET', '/api/admin/users', [], [], ['HTTP_X-CSRF-TOKEN' => $adminCsrf]);
        $users   = json_decode($adminClient->getResponse()->getContent(), true);
        $hrAdmin = array_values(array_filter($users, fn($u) => $u['username'] === 'hradmin'))[0];
        $hrId    = $hrAdmin['id'];

        $reassignBody = json_encode(['newApproverId' => $hrId, 'reason' => 'Admin reassignment test']);
        $sig = $this->sign('POST', "/api/approvals/{$stepId}/reassign", $reassignBody, $adminCsrf);

        $adminClient->request(
            'POST',
            "/api/approvals/{$stepId}/reassign",
            [],
            [],
            [
                'CONTENT_TYPE'         => 'application/json',
                'HTTP_X-CSRF-Token'    => $adminCsrf,
                'HTTP_X-Api-Signature' => $sig['signature'],
                'HTTP_X-Timestamp'     => $sig['timestamp'],
            ],
            $reassignBody
        );

        $status = $adminClient->getResponse()->getStatusCode();
        // Accept 200 (success) or 400 (invalid target role for this step)
        $this->assertContains($status, [200, 400], "Reassign must return 200 or 400, got $status");
    }

    /** Unauthenticated request to GET /api/approvals/queue must return 401. */
    public function testApprovalQueueUnauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/approvals/queue');
        $this->assertContains($client->getResponse()->getStatusCode(), [401, 403]);
    }

    /** Employee (non-approver) trying to approve a step they do not own must fail. */
    public function testEmployeeCannotApproveStep(): void
    {
        // Create a request as employee (step 1 belongs to supervisor)
        [$employeeClient, $employeeCsrf] = $this->loginAs('employee', 'Emp@2024!');
        $today     = (new \DateTimeImmutable())->format('Y-m-d');
        $clientKey = 'emp-approve-' . uniqid('', true);

        $employeeClient->request(
            'POST',
            '/api/requests',
            [],
            [],
            [
                'CONTENT_TYPE'      => 'application/json',
                'HTTP_X-CSRF-Token' => $employeeCsrf,
            ],
            json_encode([
                'requestType' => 'CORRECTION',
                'startDate'   => $today,
                'endDate'     => $today,
                'reason'      => 'Employee tries to approve own request',
                'clientKey'   => $clientKey,
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $stepId = json_decode($employeeClient->getResponse()->getContent(), true)['steps'][0]['id'];

        // Employee tries to approve the step (they are the requester, NOT the approver)
        $approveBody = json_encode(['comment' => 'Self-approve attempt']);
        $sig = $this->sign('POST', "/api/approvals/{$stepId}/approve", $approveBody, $employeeCsrf);

        $employeeClient->request(
            'POST',
            "/api/approvals/{$stepId}/approve",
            [],
            [],
            [
                'CONTENT_TYPE'         => 'application/json',
                'HTTP_X-CSRF-Token'    => $employeeCsrf,
                'HTTP_X-Api-Signature' => $sig['signature'],
                'HTTP_X-Timestamp'     => $sig['timestamp'],
            ],
            $approveBody
        );

        $this->assertContains(
            $employeeClient->getResponse()->getStatusCode(),
            [400, 403],
            'Employee must not be able to approve a step they do not own'
        );
    }
}
