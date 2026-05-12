<?php
/**
 * TTS Proxy - Routes text-to-speech requests to Flask backend
 * Converts LLM responses to speech using Kokoro TTS
 */

header('Cache-Control: public, max-age=3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $json_data = file_get_contents('php://input');
    $data      = json_decode($json_data, true);

    if (!isset($data['text']) || empty(trim($data['text']))) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['error' => 'Text kosong']);
        exit;
    }

    $text    = $data['text'];
    $user_id = $data['user_id'] ?? 'unknown';

    $flask_url = 'http://127.0.0.1:5000/tts';

    $ch = curl_init($flask_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'text'    => $text,
        'user_id' => $user_id,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: audio/wav',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);         // TTS bisa lambat untuk teks panjang
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

    $response     = curl_exec($ch);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curl_error   = curl_error($ch);
    curl_close($ch);

    if ($curl_error || $response === false) {
        header('Content-Type: application/json');
        http_response_code(503);
        echo json_encode(['error' => 'TTS backend tidak dapat dihubungi.']);
        exit;
    }

    if ($http_code === 200 && $content_type && strpos($content_type, 'audio') !== false) {
        header('Content-Type: ' . $content_type);
        header('Content-Length: ' . strlen($response));
        header('Content-Disposition: attachment; filename="response.wav"');
        echo $response;
        exit;
    }

    header('Content-Type: application/json');
    http_response_code($http_code ?: 503);
    echo json_encode([
        'error'     => 'TTS backend error',
        'http_code' => $http_code,
    ]);
    exit;

} catch (Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>