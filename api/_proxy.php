<?php

if (!function_exists('proxy_local_llm_request')) {
    function proxy_local_llm_request(string $path, string $method = 'GET', ?array $payload = null): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $targetUrl = 'http://127.0.0.1:5000' . $path;
        $ch = curl_init($targetUrl);

        if ($ch === false) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'response' => 'Gagal menginisialisasi proxy request.'
            ]);
            exit;
        }

        $headers = [
            'Accept: application/json',
        ];

        // Timeout berbeda per endpoint
        // /chat bisa sangat lama untuk jawaban panjang (kode, penjelasan detail)
        $isChat   = ($path === '/chat');
        $timeout  = $isChat ? 180 : 30;   // chat: 3 menit | health/reset: 30 detik

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        if ($payload !== null) {
            $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        $curlError    = curl_error($ch);
        $statusCode   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($responseBody === false || $curlError) {
            http_response_code(503);
            echo json_encode([
                'status'   => 'error',
                'response' => 'Backend AI lokal tidak dapat dihubungi. Pastikan server Python sudah berjalan.'
            ]);
            exit;
        }

        if ($statusCode > 0) {
            http_response_code($statusCode);
        }

        echo $responseBody;
        exit;
    }
}