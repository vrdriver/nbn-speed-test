<?php
// CONFIGURATION
$dbHost="DATABASE_HOST";
$dbName="DATABASE_NAME";
$dbUser="DATABASE_USER";
$dbPass="DATABASE_PASS";

# This should be the same as the index.html file
# Create your own: https://www.uuidgenerator.net/version4
$API_KEY = "491cc49c-94ea-410a-8adf-1ac79027771f";

$RATE_LIMIT_SECONDS = 60;

// SECURITY: HTTPS only
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    http_response_code(403);
    die(json_encode(["error" => "HTTPS required"]));
}

// SECURITY: API key check
$headers = getallheaders();
if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== $API_KEY) {
    http_response_code(403);
    die(json_encode(["error" => "Invalid API Key"]));
}

// Connect to database (PDO with prepared statements)
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(["error" => "Database connection failed"]));
}

// Handle request based on method
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // SECURITY: Rate limiting check
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $pdo->prepare("SELECT timestamp FROM speedtest_results WHERE ip_address = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute([$ip]);
    $last = $stmt->fetchColumn();
    if ($last && (time() - strtotime($last) < $RATE_LIMIT_SECONDS)) {
        http_response_code(429);
        die(json_encode(["error" => "Rate limit exceeded"]));
    }

    // INPUT VALIDATION
    $download = filter_input(INPUT_POST, 'download', FILTER_VALIDATE_FLOAT);
    $upload = filter_input(INPUT_POST, 'upload', FILTER_VALIDATE_FLOAT);
    $ping = filter_input(INPUT_POST, 'ping', FILTER_VALIDATE_FLOAT);

    if ($download === false || $upload === false || $ping === false) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid input"]));
    }

    // Additional sanity checks
    if ($download < 0 || $download > 10000 ||
        $upload < 0 || $upload > 10000 ||
        $ping < 0 || $ping > 5000) {
        http_response_code(400);
        die(json_encode(["error" => "Values out of range"]));
    }

    // STORE DATA
    $stmt = $pdo->prepare("
        INSERT INTO speedtest_results (timestamp, download, upload, ping, ip_address)
        VALUES (NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([$download, $upload, $ping, $ip]);

    echo json_encode(["status" => "success", "message" => "Data stored"]);
} elseif ($method === 'GET') {
    // INPUT VALIDATION for GET request
    $start_date = filter_input(INPUT_GET, 'start_date', FILTER_SANITIZE_STRING);
    $end_date = filter_input(INPUT_GET, 'end_date', FILTER_SANITIZE_STRING);
    $granularity = filter_input(INPUT_GET, 'granularity', FILTER_SANITIZE_STRING);

    if (!$start_date || !$end_date || !DateTime::createFromFormat('Y-m-d', $start_date) || !DateTime::createFromFormat('Y-m-d', $end_date)) {
        http_response_code(400);
        die(json_encode(["error" => "Invalid or missing start_date/end_date"]));
    }

    // Validate granularity (only allow 'hour' for now, as per index.html)
    if ($granularity !== 'hour') {
        http_response_code(400);
        die(json_encode(["error" => "Invalid granularity"]));
    }

    // Ensure range is not too large
    $start_dt = new DateTime($start_date);
    $end_dt = new DateTime($end_date);
    $interval = $start_dt->diff($end_dt);
    if ($interval->days > 365) {
        http_response_code(400);
        die(json_encode(["error" => "Range too large (max 1 year)"]));
    }

    // Query to aggregate data by hour, adjusted to GMT+10
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(DATE_ADD(tested_at_utc, INTERVAL 10 HOUR), '%Y-%m-%d %H:00:00') AS bucket,
            AVG(download_mbps) AS down_avg,
            MIN(download_mbps) AS down_min,
            MAX(download_mbps) AS down_max,
            AVG(upload_mbps) AS up_avg,
            MIN(upload_mbps) AS up_min,
            MAX(upload_mbps) AS up_max,
            AVG(ping_ms) AS ping_avg
        FROM speedtest_results
        WHERE tested_at_utc >= ? AND tested_at_utc <= ?
        GROUP BY DATE_FORMAT(DATE_ADD(tested_at_utc, INTERVAL 10 HOUR), '%Y-%m-%d %H:00:00')
        ORDER BY bucket
    ");
    $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return JSON
    header('Content-Type: application/json');
    echo json_encode($data);
} else {
    http_response_code(405);
    die(json_encode(["error" => "Method not allowed"]));
}
?>