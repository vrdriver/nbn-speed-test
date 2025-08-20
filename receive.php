<?php
session_start();
$dbHost="DATABASE_HOST";
$dbName="DATABASE_NAME";
$dbUser="DATABASE_USER";
$dbPass="DATABASE_PASS";

# This should be the same as the active_nbn.py file
# Create your own: https://www.uuidgenerator.net/version4
$API_KEY="d7d140c6-8739-4aa0-b25a-207ef7ec8bbd";

// Rate limiting (5 req/sec per IP)
$ip = $_SERVER['REMOTE_ADDR']; $time = time();
if(!isset($_SESSION['req'])) $_SESSION['req']=[];
$_SESSION['req'] = array_filter($_SESSION['req'], fn($t)=>$t>$time-1);
if(count($_SESSION['req'])>5){ http_response_code(429); exit('Too Many Requests'); }
$_SESSION['req'][]=$time;

// Method & API key check
if($_SERVER['REQUEST_METHOD']!=='POST'){ http_response_code(405); exit('Method Not Allowed'); }
if(!isset($_SERVER['HTTP_X_API_KEY']) || $_SERVER['HTTP_X_API_KEY']!==$API_KEY){ http_response_code(403); exit('Forbidden'); }

// Read JSON
$data = json_decode(file_get_contents('php://input'), true);
if(!is_array($data)){ http_response_code(400); exit('Invalid JSON'); }

// Validate numeric fields
foreach(['download_mbps','upload_mbps','ping_ms'] as $field){
    if(!isset($data[$field]) || !is_numeric($data[$field]) || $data[$field]<0 || $data[$field]>1000){
        http_response_code(400); exit("Invalid $field");
    }
}

try{
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",$dbUser,$dbPass,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        INSERT INTO speedtest_results
        (tested_at_utc,ping_ms,jitter_ms,packet_loss_pct,download_mbps,upload_mbps,server_id,server_name,isp,iface)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['tested_at_utc'],
        $data['ping_ms'],
        $data['jitter_ms'] ?? null,
        $data['packet_loss_pct'] ?? null,
        $data['download_mbps'],
        $data['upload_mbps'],
        $data['server_id'] ?? null,
        $data['server_name'] ?? null,
        $data['isp'] ?? null,
        $data['iface'] ?? null
    ]);

    echo json_encode(['ok'=>true]);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['error'=>'DB error']);
}
