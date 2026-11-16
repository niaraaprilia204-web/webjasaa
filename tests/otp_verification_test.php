<?php

/**
 * OTP business-logic tests.
 *
 * IMPORTANT: The fake sender below never contacts WhatsApp. These tests verify
 * hashing, expiry, single-use, attempt limits, rate limits, account linking, and
 * failure handling. Real WhatsApp delivery requires a Fonnte token and a
 * connected Fonnte device.
 *
 * Run:
 *   php tests/otp_verification_test.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../app/Config/Config.php';
require_once __DIR__ . '/../app/Core/Database.php';
require_once __DIR__ . '/../app/Models/BaseModel.php';
require_once __DIR__ . '/../app/Models/OtpVerification.php';
require_once __DIR__ . '/../app/Services/FonnteService.php';
require_once __DIR__ . '/../app/Services/WhatsAppService.php';
require_once __DIR__ . '/../app/Services/OtpService.php';

class FakeWhatsAppSender
{
    public $shouldFail = false;
    public $calls = 0;
    public $lastCode = null;
    public $lastPhone = null;

    public function isConfigured()
    {
        return true;
    }

    public function sendOtp($to, $code)
    {
        $this->calls++;
        $this->lastPhone = $to;
        $this->lastCode = $code;

        if ($this->shouldFail) {
            return ['success' => false, 'error_code' => 'provider_error'];
        }

        return ['success' => true, 'message_id' => 'wamid.test-only'];
    }
}

$passed = 0;
$failed = 0;

function check($name, $condition, $detail = '')
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  [PASS] {$name}\n";
        return;
    }

    $failed++;
    echo "  [FAIL] {$name}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
}

$testPhones = [
    '628990000001',
    '628990000002',
    '628990000003',
    '628990000004',
    '628990000005',
    '628990000006',
];

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$phoneList = "'" . implode("','", $testPhones) . "'";
$pdo->exec("DELETE FROM `otp_verifications` WHERE `phone` IN ({$phoneList})");
$pdo->exec("DELETE FROM `users` WHERE `email` LIKE 'otp.test.%@example.test'");

function latestRow(PDO $pdo, $phone)
{
    $stmt = $pdo->prepare("SELECT * FROM `otp_verifications` WHERE `phone` = :phone ORDER BY `id` DESC LIMIT 1");
    $stmt->execute([':phone' => $phone]);
    return $stmt->fetch() ?: null;
}

function wrongCodeFor($code)
{
    return $code === '000000' ? '111111' : '000000';
}

echo "=== OTP Verification Tests (no real WhatsApp messages) ===\n\n";

echo "[1] Phone validation\n";
check('0812-3456-7890 -> 6281234567890', OtpService::normalizePhone('0812-3456-7890') === '6281234567890');
check('+62 812 3456 7890 -> 6281234567890', OtpService::normalizePhone('+62 812 3456 7890') === '6281234567890');
check('8123456789 -> 628123456789', OtpService::normalizePhone('8123456789') === '628123456789');
check('021-555-1234 rejected', OtpService::normalizePhone('021-555-1234') === null);
check('letters rejected', OtpService::normalizePhone('0812-ABC-7890') === null);
check('too short rejected', OtpService::normalizePhone('0812') === null);
check('empty rejected', OtpService::normalizePhone('') === null);

echo "\n[2] Fonnte configuration and payload\n";
$unconfigured = new FonnteService('');
$unconfiguredResult = $unconfigured->sendOtp('6281234567890', '123456');
check(
    'missing token returns not_configured (no fake success)',
    $unconfiguredResult['success'] === false && $unconfiguredResult['error_code'] === 'not_configured'
);

$fonnte = new FonnteService('dummy-token');
$fonntePayload = $fonnte->buildPayload('6281234567890', '123456');
check('official Fonnte endpoint is used', $fonnte->getEndpoint() === 'https://api.fonnte.com/send');
check('Fonnte target is the normalized phone', ($fonntePayload['target'] ?? '') === '6281234567890');
check('Fonnte message contains the OTP', strpos($fonntePayload['message'] ?? '', '123456') !== false);
check('Fonnte message states the 5-minute expiry', strpos($fonntePayload['message'] ?? '', '5 menit') !== false);
check('Fonnte country code is 62', ($fonntePayload['countryCode'] ?? '') === '62');
check('Fonnte connectOnly is true', ($fonntePayload['connectOnly'] ?? false) === true);
check('token is not placed in the payload', strpos(json_encode($fonntePayload), 'dummy-token') === false);

$headerInjection = new FonnteService("dummy-token\r\nX-Injected: 1");
check(
    'token containing CR/LF is rejected',
    $headerInjection->isConfigured() === false
        && $headerInjection->sendOtp('6281234567890', '123456')['error_code'] === 'not_configured'
);

echo "\n[3] Fonnte response handling\n";
$okResponse = '{"detail":"success! message in queue","id":["80367170"],"process":"pending","requestid":2937124,"status":true,"target":["6281234567890"]}';
$okParsed = $fonnte->interpretResponse(200, $okResponse);
check('status true + id is accepted', $okParsed['success'] === true && $okParsed['message_id'] === '80367170');
check('accepted response exposes no token', strpos(json_encode($okParsed), 'dummy-token') === false);

$rejectedResponse = '{"reason":"token invalid","status":false,"requestid":2937124}';
$rejectedParsed = $fonnte->interpretResponse(200, $rejectedResponse);
check('status false is a failure', $rejectedParsed['success'] === false && $rejectedParsed['error_code'] === 'provider_error');

$uppercaseRejected = '{"reason":"target invalid","Status":false,"requestid":1}';
check('uppercase Status false is a failure', $fonnte->interpretResponse(200, $uppercaseRejected)['success'] === false);

$noIdResponse = '{"detail":"success! message in queue","status":true}';
check('status true without id is not success', $fonnte->interpretResponse(200, $noIdResponse)['success'] === false);

check('invalid JSON is not success', $fonnte->interpretResponse(200, 'not-json')['success'] === false);
check('201 with status true is accepted', $fonnte->interpretResponse(201, $okResponse)['success'] === true);
check('non-2xx response is not success', $fonnte->interpretResponse(500, $okResponse)['success'] === false);

echo "\n[4] Meta fallback payload behavior (optional provider)\n";
$metaUnconfigured = new WhatsAppService('', '', 'v26.0');
$metaUnconfiguredResult = $metaUnconfigured->sendOtp('6281234567890', '123456');
check(
    'missing Meta credentials returns not_configured',
    $metaUnconfiguredResult['success'] === false && $metaUnconfiguredResult['error_code'] === 'not_configured'
);

$templateSender = new WhatsAppService('dummy-token', '123456789', 'v26.0', 'otp_template', 'id', true);
$templatePayload = $templateSender->buildPayload('6281234567890', '123456');
check('official Graph endpoint is used', $templateSender->getEndpoint() === 'https://graph.facebook.com/v26.0/123456789/messages');
check('template message type', ($templatePayload['type'] ?? '') === 'template');
check('template name and language', ($templatePayload['template']['name'] ?? '') === 'otp_template' && ($templatePayload['template']['language']['code'] ?? '') === 'id');
check('template body parameter contains OTP', ($templatePayload['template']['components'][0]['parameters'][0]['text'] ?? '') === '123456');
check('template button parameter contains OTP', ($templatePayload['template']['components'][1]['parameters'][0]['text'] ?? '') === '123456');
check('Meta token is not placed in payload', strpos(json_encode($templatePayload), 'dummy-token') === false);

$textSender = new WhatsAppService('dummy-token', '123456789', 'v26.0', '', 'id', false);
$textPayload = $textSender->buildPayload('6281234567890', '654321');
check('Meta text fallback type', ($textPayload['type'] ?? '') === 'text');
check('Meta text fallback contains OTP', strpos($textPayload['text']['body'] ?? '', '654321') !== false);

echo "\n[5] Successful request stores a hash, not the OTP\n";
$fake = new FakeWhatsAppSender();
$service = new OtpService($fake);
$phone = $testPhones[0];
$request = $service->requestOtp('0899-000-0001', null, '');
check('request succeeds with fake provider', !empty($request['success']), $request['message'] ?? '');
check('fake provider was called once', $fake->calls === 1);
check('provider receives normalized phone', $fake->lastPhone === $phone);
check('OTP is six digits', is_string($fake->lastCode) && preg_match('/^\d{6}$/', $fake->lastCode) === 1);
check('success response does not contain OTP', strpos(json_encode($request), (string) $fake->lastCode) === false);

$row = latestRow($pdo, $phone);
check('DB row created', !empty($row));
check('stored value is not the plaintext OTP', !empty($row) && $row['otp_hash'] !== $fake->lastCode);
check('stored value verifies as bcrypt hash', !empty($row) && password_verify($fake->lastCode, $row['otp_hash']));
check('expiry is 5 minutes or less', !empty($row)
    && strtotime($row['expires_at']) > time() + 240
    && strtotime($row['expires_at']) <= time() + 310);

echo "\n[6] Wrong code, correct code, single use\n";
$wrong = wrongCodeFor($fake->lastCode);
$wrongResult = $service->verifyOtp($phone, $wrong, null);
check('wrong code rejected', empty($wrongResult['success']) && $wrongResult['error_code'] === 'invalid_code');
check('remaining attempts reported', ($wrongResult['data']['remaining_attempts'] ?? 0) === 4);

$goodResult = $service->verifyOtp($phone, $fake->lastCode, null);
check('correct code accepted', !empty($goodResult['success']), $goodResult['message'] ?? '');
check('guest verification is not linked', ($goodResult['data']['linked'] ?? true) === false);
check('success response masks the phone', strpos($goodResult['message'], $phone) === false);

$reuseResult = $service->verifyOtp($phone, $fake->lastCode, null);
check('same code cannot be reused', empty($reuseResult['success']));
$row = latestRow($pdo, $phone);
check('consumed_at is set', !empty($row['consumed_at']));

echo "\n[7] Expired code\n";
$fakeExpired = new FakeWhatsAppSender();
$expiredService = new OtpService($fakeExpired);
$phone = $testPhones[3];
$expiredRequest = $expiredService->requestOtp($phone, null, '');
check('expired-test request succeeds', !empty($expiredRequest['success']));
$pdo->prepare("UPDATE `otp_verifications` SET `expires_at` = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE `phone` = :phone")
    ->execute([':phone' => $phone]);
$expiredResult = $expiredService->verifyOtp($phone, $fakeExpired->lastCode, null);
check('expired code rejected', empty($expiredResult['success']));
check('expired response does not reveal the code', strpos(json_encode($expiredResult), (string) $fakeExpired->lastCode) === false);

echo "\n[8] Verification attempt limit (brute force protection)\n";
$fakeAttempts = new FakeWhatsAppSender();
$attemptService = new OtpService($fakeAttempts);
$phone = $testPhones[4];
$attemptService->requestOtp($phone, null, '');
$wrong = wrongCodeFor($fakeAttempts->lastCode);
$lastResult = null;
for ($i = 0; $i < OTP_MAX_VERIFY_ATTEMPTS; $i++) {
    $lastResult = $attemptService->verifyOtp($phone, $wrong, null);
}
check('fifth wrong attempt locks the code', empty($lastResult['success']) && $lastResult['error_code'] === 'too_many_attempts');
$afterLock = $attemptService->verifyOtp($phone, $fakeAttempts->lastCode, null);
check('correct code also rejected after lock', empty($afterLock['success']));

echo "\n[9] Request rate limit (3 per 15 minutes)\n";
$fakeRate = new FakeWhatsAppSender();
$rateService = new OtpService($fakeRate);
$phone = $testPhones[1];
$rateResults = [];
for ($i = 0; $i < 4; $i++) {
    $rateResults[] = $rateService->requestOtp($phone, null, '');
}
check('first three requests allowed', !empty($rateResults[0]['success']) && !empty($rateResults[1]['success']) && !empty($rateResults[2]['success']));
check('fourth request blocked', empty($rateResults[3]['success']) && $rateResults[3]['error_code'] === 'rate_limited');
check('retry_after is positive', (int) ($rateResults[3]['data']['retry_after'] ?? 0) > 0);

echo "\n[10] Provider failure never reports success\n";
$fakeFail = new FakeWhatsAppSender();
$fakeFail->shouldFail = true;
$failService = new OtpService($fakeFail);
$phone = $testPhones[2];
$failedRequest = $failService->requestOtp($phone, null, '');
check('API failure returns send_failed', empty($failedRequest['success']) && $failedRequest['error_code'] === 'send_failed');
check('failure response does not claim delivery', stripos($failedRequest['message'], 'telah dikirim') === false);
$failedRow = latestRow($pdo, $phone);
check('failed-delivery OTP is invalidated', !empty($failedRow['consumed_at']));
check('failed-delivery code cannot be verified', empty($failService->verifyOtp($phone, $fakeFail->lastCode, null)['success']));

echo "\n[11] Account linking\n";
$email = 'otp.test.' . bin2hex(random_bytes(4)) . '@example.test';
$stmt = $pdo->prepare("INSERT INTO `users` (`role`, `name`, `email`, `password`, `phone`, `is_active`) VALUES ('customer', 'OTP Test User', :email, :password, NULL, 1)");
$stmt->execute([':email' => $email, ':password' => password_hash('test-only', PASSWORD_BCRYPT)]);
$userId = (int) $pdo->lastInsertId();

$fakeLink = new FakeWhatsAppSender();
$linkService = new OtpService($fakeLink);
$phone = $testPhones[5];
$linkRequest = $linkService->requestOtp($phone, $userId, '');
check('request with logged-in user succeeds', !empty($linkRequest['success']));
$linkResult = $linkService->verifyOtp($phone, $fakeLink->lastCode, $userId);
check('verification links the account', !empty($linkResult['success']) && ($linkResult['data']['linked'] ?? false) === true);

$userStmt = $pdo->prepare("SELECT `phone`, `phone_verified_at` FROM `users` WHERE `id` = :id");
$userStmt->execute([':id' => $userId]);
$userRow = $userStmt->fetch();
check('users.phone saved as normalized number', ($userRow['phone'] ?? '') === $phone);
check('users.phone_verified_at set', !empty($userRow['phone_verified_at']));

echo "\n[12] Unconfigured service does not create a pending OTP\n";
$countBefore = (int) $pdo->query("SELECT COUNT(*) FROM `otp_verifications` WHERE `phone` = '628990000006'")->fetchColumn();
$unconfiguredService = new OtpService(new FonnteService(''));
$unconfiguredRequest = $unconfiguredService->requestOtp('628990000006', null, '');
$countAfter = (int) $pdo->query("SELECT COUNT(*) FROM `otp_verifications` WHERE `phone` = '628990000006'")->fetchColumn();
check('unconfigured request returns not_configured', empty($unconfiguredRequest['success']) && $unconfiguredRequest['error_code'] === 'not_configured');
check('no OTP row created while unconfigured', $countBefore === $countAfter);

// Cleanup all test data.
$pdo->exec("DELETE FROM `otp_verifications` WHERE `phone` IN ({$phoneList})");
$pdo->exec("DELETE FROM `users` WHERE `email` LIKE 'otp.test.%@example.test'");

echo "\n=== Result: {$passed} passed, {$failed} failed ===\n";
exit($failed === 0 ? 0 : 1);
