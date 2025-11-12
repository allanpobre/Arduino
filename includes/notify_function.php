<?php
// notify_function.php
// Contém a função centralizada para enviar mensagens (APENAS TELEGRAM)

// INCLUI O NOVO ARQUIVO DE CONFIGURAÇÃO
require_once __DIR__ . '/config.php';

/**
 * Envia uma mensagem de Telegram usando a API oficial.
 *
 * @param string $chat_id O ID do chat para onde enviar.
 * @param string $text A mensagem a ser enviada.
 * @return array Retorna um array com ['ok', 'http_code', 'body', 'error'].
 */
// A FUNÇÃO FOI SIMPLIFICADA (NÃO PRECISA MAIS DO $bot_token)
function send_telegram_bot(string $chat_id, string $text): array {
    
    // Verifica se o token foi carregado do config.php
    if (!defined('TELEGRAM_BOT_TOKEN') || TELEGRAM_BOT_TOKEN === '') {
        return ['ok' => false, 'http_code' => 500, 'body' => null, 'error' => 'TELEGRAM_BOT_TOKEN não definido em includes/config.php'];
    }

    // USA A CONSTANTE GLOBAL
    $url = "https://api.telegram.org/bot" . TELEGRAM_BOT_TOKEN . "/sendMessage";
    
    $post_data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $body = curl_exec($ch);
    $err = null;
    if ($body === false) $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($http_code >= 200 && $http_code < 300 && $err === null);
    return ['ok' => $ok, 'http_code' => $http_code, 'body' => $body, 'error' => $err];
}