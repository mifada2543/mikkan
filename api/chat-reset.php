<?php
require_once __DIR__ . '/_proxy.php';

$payload = json_decode(file_get_contents('php://input'), true) ?? [];
proxy_local_llm_request('/chat/reset', 'POST', $payload);
