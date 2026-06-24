<?php
require_once __DIR__ . '/sms_parse.php';

$result = null;
$error = null;

function sendSmsWithClass($apiId, $apiKey, $senderId, $receiverTel, $message) {
    hdev_sms::api_id($apiId);
    hdev_sms::api_key($apiKey);
    return hdev_sms::send($senderId, $receiverTel, $message);
}

function sendSmsWithCurl($apiId, $apiKey, $senderId, $receiverTel, $message) {
    $data = [
        'sender_id' => $senderId,
        'ref' => 'sms',
        'message' => $message,
        'tel' => $receiverTel
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://sms-api.hdev.rw/v1/api/' . $apiId . '/' . $apiKey,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $data
    ]);

    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($curlError) {
        return ['success' => false, 'error' => $curlError];
    }

    $decoded = json_decode($response, true);
    return $decoded !== null ? $decoded : ['raw_response' => $response];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apiId = trim($_POST['api_id'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $senderId = trim($_POST['sender_id'] ?? '');
    $receiverTel = trim($_POST['receiver_tel'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $method = trim($_POST['method'] ?? 'class');

    if ($apiId === '' || $apiKey === '' || $senderId === '' || $receiverTel === '' || $message === '') {
        $error = 'All fields are required.';
    } else {
        try {
            if ($method === 'curl') {
                $result = sendSmsWithCurl($apiId, $apiKey, $senderId, $receiverTel, $message);
            } else {
                $result = sendSmsWithClass($apiId, $apiKey, $senderId, $receiverTel, $message);
            }
        } catch (Throwable $e) {
            $error = 'SMS sending failed: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Test - STRGS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">SMS Test Tool</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">API ID</label>
                                    <input type="text" class="form-control" name="api_id" value="<?php echo htmlspecialchars($_POST['api_id'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">API Key</label>
                                    <input type="text" class="form-control" name="api_key" value="<?php echo htmlspecialchars($_POST['api_key'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sender ID</label>
                                    <input type="text" class="form-control" name="sender_id" value="<?php echo htmlspecialchars($_POST['sender_id'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Receiver Tel</label>
                                    <input type="text" class="form-control" name="receiver_tel" placeholder="+2507XXXXXXXX" value="<?php echo htmlspecialchars($_POST['receiver_tel'] ?? ''); ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message</label>
                                    <textarea class="form-control" name="message" rows="3" required><?php echo htmlspecialchars($_POST['message'] ?? 'Registration successful. Welcome to STRGS.'); ?></textarea>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Method</label>
                                    <select class="form-select" name="method">
                                        <option value="class" <?php echo (($_POST['method'] ?? 'class') === 'class') ? 'selected' : ''; ?>>hdev_sms class</option>
                                        <option value="curl" <?php echo (($_POST['method'] ?? '') === 'curl') ? 'selected' : ''; ?>>Raw cURL</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary">Send Test SMS</button>
                            </div>
                        </form>

                        <?php if ($result !== null): ?>
                            <hr>
                            <h6>SMS API Response</h6>
                            <pre class="bg-dark text-light p-3 rounded"><?php echo htmlspecialchars(print_r($result, true)); ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

