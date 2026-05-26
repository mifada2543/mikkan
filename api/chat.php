<?php
// Perpanjang max execution time untuk request LLM yang panjang (kode, penjelasan detail)
// Default PHP = 30 detik, kita set 200 detik (lebih dari timeout cURL 180 detik)
set_time_limit(200);

require_once __DIR__ . '/_proxy.php';

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
proxy_local_llm_request('/chat', 'POST', $payload);