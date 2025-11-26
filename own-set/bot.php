<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

require_once __DIR__ . '/config.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/src/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

use App\Clients\TelegramClient;
use App\Logging\Logger;
use App\Repositories\UserRepository;
use App\Services\BotService;
use App\Services\BroadcastQueue;

$logger = new Logger(LOG_FILE);
$telegram = new TelegramClient(API_KEY, $logger, VERIFY_SSL);
$userRepository = new UserRepository(USERS_FILE, VERIFIED_FILE, STEPS_DIR, CONTEXT_DIR, $logger);
$queue = new BroadcastQueue(QUEUE_DIR, $telegram, $userRepository, $logger);
$bot = new BotService($telegram, $userRepository, $queue, $logger);

$payload = file_get_contents('php://input');
if ($payload === false || $payload === '') {
    http_response_code(400);
    $logger->warning('Empty payload received from Telegram');
    exit;
}

$update = json_decode($payload, true);
if (!is_array($update)) {
    http_response_code(400);
    $logger->warning('Invalid JSON payload', ['raw' => substr($payload, 0, 200)]);
    exit;
}

$queue->processAllPending();

try {
    $bot->handle($update);
    http_response_code(200);
} catch (Throwable $exception) {
    $errorId = $logger->error('Unhandled bot exception', [
        'message' => $exception->getMessage(),
        'trace' => $exception->getTraceAsString(),
    ]);
    $adminChat = ADMINS[0] ?? null;
    if ($adminChat !== null) {
        try {
            $alert = htmlspecialchars(
                "⚠️ خطای بحرانی در ربات\nشناسه خطا: {$errorId}\n{$exception->getMessage()}",
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
            $telegram->sendMessage($adminChat, $alert);
        } catch (Throwable $notifyError) {
            $logger->warning('Failed to notify admin about error', [
                'error_id' => $errorId,
                'notify_error' => $notifyError->getMessage(),
            ]);
        }
    }
    http_response_code(500);
    echo "error_id={$errorId}";
}
