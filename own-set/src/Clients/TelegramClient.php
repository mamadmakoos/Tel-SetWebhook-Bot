<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

namespace App\Clients;

use App\Logging\Logger;

class TelegramClient
{
    private string $token;
    private Logger $logger;
    private bool $verifySsl;

    public function __construct(string $token, Logger $logger, bool $verifySsl = true)
    {
        $this->token = $token;
        $this->logger = $logger;
        $this->verifySsl = $verifySsl;
    }

    public function request(string $method, array $payload = [], ?string $tokenOverride = null): ?array
    {
        $token = $tokenOverride ?? $this->token;
        $url = "https://api.telegram.org/bot{$token}/{$method}";
        $ch = curl_init($url);

        if ($ch === false) {
            $this->logger->error('Failed to init curl', ['method' => $method]);
            return null;
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT => 'WebhookManagerBot/2.0',
        ];

        if (!$this->verifySsl) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if ($payload !== []) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $options);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $this->logger->error('Curl exec failed', ['method' => $method, 'error' => $error]);
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Invalid JSON response', [
                'method' => $method,
                'http_code' => $httpCode,
                'body' => substr($response, 0, 200),
                'json_error' => json_last_error_msg(),
            ]);
            return null;
        }

        if (($decoded['ok'] ?? false) !== true) {
            $this->logger->warning('Telegram returned error', [
                'method' => $method,
                'http_code' => $httpCode,
                'response' => $decoded,
            ]);
        }

        return $decoded;
    }

    public function sendMessage(int|string $chatId, string $text, array $replyMarkup = [], ?string $tokenOverride = null): ?array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== []) {
            $markup = $this->normalizeMarkup($replyMarkup);
            $payload['reply_markup'] = json_encode($markup, JSON_UNESCAPED_UNICODE);
        }

        return $this->request('sendMessage', $payload, $tokenOverride);
    }

    public function editMessage(int|string $chatId, int|string $messageId, string $text, array $replyMarkup = [], ?string $tokenOverride = null): ?array
    {
        $payload = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup !== []) {
            $payload['reply_markup'] = json_encode($this->normalizeMarkup($replyMarkup), JSON_UNESCAPED_UNICODE);
        }

        return $this->request('editMessageText', $payload, $tokenOverride);
    }

    public function answerCallback(string $callbackId, string $text = '', bool $showAlert = false, ?string $tokenOverride = null): ?array
    {
        return $this->request('answerCallbackQuery', [
            'callback_query_id' => $callbackId,
            'text' => $text,
            'show_alert' => $showAlert,
        ], $tokenOverride);
    }

    public function setWebhook(string $url, ?string $tokenOverride = null): ?array
    {
        return $this->request('setWebhook', ['url' => $url], $tokenOverride);
    }

    public function deleteWebhook(?string $tokenOverride = null): ?array
    {
        return $this->request('deleteWebhook', [], $tokenOverride);
    }

    public function resetWebhook(string $url, ?string $tokenOverride = null): ?array
    {
        $this->deleteWebhook($tokenOverride);
        usleep(250000);
        return $this->setWebhook($url, $tokenOverride);
    }

    public function getWebhookInfo(?string $tokenOverride = null): ?array
    {
        return $this->request('getWebhookInfo', [], $tokenOverride);
    }

    public function getChatMember(string $channel, int|string $userId, ?string $tokenOverride = null): ?array
    {
        return $this->request('getChatMember', [
            'chat_id' => $channel,
            'user_id' => $userId,
        ], $tokenOverride);
    }

    public function forwardMessage(int|string $toChat, int|string $fromChat, int|string $messageId, ?string $tokenOverride = null): ?array
    {
        return $this->request('forwardMessage', [
            'chat_id' => $toChat,
            'from_chat_id' => $fromChat,
            'message_id' => $messageId,
        ], $tokenOverride);
    }

    private function normalizeMarkup(array $markup): array
    {
        if (isset($markup['inline_keyboard']) || isset($markup['keyboard']) || isset($markup['remove_keyboard'])) {
            return $markup;
        }

        if (array_is_list($markup)) {
            return ['inline_keyboard' => $markup];
        }

        return $markup;
    }
}

