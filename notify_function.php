<?php
// notify_function.php
// Contém a função centralizada para enviar mensagens via CallMeBot

/**
 * Envia uma mensagem de WhatsApp usando a API CallMeBot.
 *
 * @param string $phone O número de telefone (com +código do país).
 * @param string $text A mensagem a ser enviada.
 * @param string $apikey A API key do CallMeBot.
 * @return array Retorna um array com ['ok', 'http_code', 'body', 'error'].
 */
function send_whatsapp_callmebot(string $phone, string $text, string $apikey): array {
    $url = "https://api.callmebot.com/whatsapp.php?phone=" . urlencode($phone)
         . "&text=" . urlencode($text)
         . "&apikey=" . urlencode($apikey);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // timeout curto
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Desabilita a verificação SSL
    $body = curl_exec($ch);
    $err = null;
    if ($body === false) $err = curl_error($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($http_code >= 200 && $http_code < 300 && $err === null);
    return ['ok' => $ok, 'http_code' => $http_code, 'body' => $body, 'error' => $err];
}