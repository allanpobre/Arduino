<?php
// notify_function.php
// Contém a função centralizada para enviar mensagens (APENAS TELEGRAM)

/**
 * Envia uma mensagem de Telegram usando a API oficial.
 *
 * @param string $bot_token O token do seu bot (do BotFather).
 * @param string $chat_id O ID do chat para onde enviar.
 * @param string $text A mensagem a ser enviada (suporta Markdown Básico).
 * @return array Retorna um array com ['ok', 'http_code', 'body', 'error'].
 */
function send_telegram_bot(string $bot_token, string $chat_id, string $text): array {
    
    $url = "https://api.telegram.org/bot" . $bot_token . "/sendMessage";
    
    $post_data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'Markdown' // Permite usar *negrito* ou _itálico_
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true); // Definindo como POST
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data)); // Enviando como JSON
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
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