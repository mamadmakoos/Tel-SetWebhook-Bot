<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

namespace App\Services;

use App\Clients\TelegramClient;
use App\Logging\Logger;
use App\Repositories\UserRepository;
use App\Support\Validator;

class BotService
{
    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly UserRepository $users,
        private readonly BroadcastQueue $queue,
        private readonly Logger $logger
    ) {
    }

    public function handle(array $update): void
    {
        if (isset($update['message'])) {
            $this->handleMessage($update['message']);
            return;
        }

        if (isset($update['callback_query'])) {
            $this->handleCallback($update['callback_query']);
            return;
        }

        $this->logger->warning('Unsupported update payload', ['update' => $update]);
    }

    private function handleMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $userId = $message['from']['id'] ?? null;
        $text = trim($message['text'] ?? '');

        if ($chatId === null || $userId === null) {
            $this->logger->warning('Missing identifiers in message', ['message' => $message]);
            return;
        }

        $this->users->addUser($userId);
        $step = $this->users->getStep($chatId);

        if ($text === '/start') {
            $this->handleStart($chatId, $userId);
            return;
        }

        if ($text === '/panel' && $this->isAdmin($userId)) {
            $this->showAdminPanel($chatId);
            return;
        }

        if ($step !== null) {
            if ($this->handleWebhookWizard($chatId, $userId, $text, $step)) {
                return;
            }

            $this->handleAdminSteps($chatId, $userId, $text, $message);
            return;
        }
    }

    private function handleCallback(array $callback): void
    {
        $chatId = $callback['message']['chat']['id'] ?? null;
        $userId = $callback['from']['id'] ?? null;
        $data = $callback['data'] ?? '';
        $messageId = $callback['message']['message_id'] ?? null;
        $callbackId = $callback['id'] ?? null;

        if ($chatId === null || $userId === null || $callbackId === null) {
            $this->logger->warning('Invalid callback payload', ['callback' => $callback]);
            return;
        }

        if ($data === 'check_join') {
            $this->handleJoinCheck($chatId, $messageId, $callbackId, $userId);
            return;
        }

        if ($data === 'main_menu') {
            $this->sendMainMenu($chatId, $messageId, $callbackId, $userId);
            return;
        }

        if ($this->isAdmin($chatId)) {
            if ($this->handleAdminCallback($chatId, $messageId, $callbackId, $data)) {
                return;
            }
        }

        $this->handleUserCallback($chatId, $messageId, $callbackId, $data, $userId);
    }

    private function handleStart(int|string $chatId, int|string $userId): void
    {
        $this->telegram->sendMessage($chatId, "⌛", ['remove_keyboard' => true]);
        if ($this->isChannelMember($userId)) {
            $this->users->verify($userId);
            $text = $this->escape("🤖 به ربات مدیریت وب‌هوک خوش آمدید!\n\n🔧 لطفاً یک گزینه را انتخاب کنید:");
            $this->telegram->sendMessage($chatId, $text, $this->mainUserKeyboard());
        } else {
            $text = $this->escape("👋 برای استفاده از ربات، ابتدا در کانال عضو شوید و سپس روی «✅ بررسی عضویت» بزنید.");
            $this->telegram->sendMessage($chatId, $text, $this->joinKeyboard());
        }
    }

    private function showAdminPanel(int|string $chatId): void
    {
        $response = $this->telegram->sendMessage($chatId, '🛠️ پنل مدیریت ربات', $this->adminKeyboard());
        if ($response !== null && isset($response['result']['message_id'])) {
            file_put_contents(ADMIN_MSGID_FILE, (string)$response['result']['message_id']);
        }
    }

    private function handleAdminSteps(int|string $chatId, int|string $userId, string $text, array $message): void
    {
        if (!$this->isAdmin($userId)) {
            return;
        }

        $step = $this->users->getStep($chatId);
        if ($step === 'broadcast') {
            $jobId = $this->queue->enqueueText($text);
            $summary = $this->queue->process($jobId);
            $this->telegram->sendMessage($chatId, $this->formatBroadcastSummary($summary));
            $this->users->setStep($chatId, null);
        } elseif ($step === 'forward' && isset($message['message_id'])) {
            $jobId = $this->queue->enqueueForward($chatId, $message['message_id']);
            $summary = $this->queue->process($jobId);
            $this->telegram->sendMessage($chatId, $this->formatBroadcastSummary($summary));
            $this->users->setStep($chatId, null);
        }
    }

    private function handleJoinCheck(int|string $chatId, int|string $messageId, string $callbackId, int|string $userId): void
    {
        if ($this->isChannelMember($userId)) {
            $this->users->verify($userId);
            $text = $this->escape("✅ عضویت شما تأیید شد!\n\n🤖 به ربات مدیریت وب‌هوک خوش آمدید!");
            $this->telegram->editMessage($chatId, $messageId, $text, $this->mainUserKeyboard());
            $this->telegram->answerCallback($callbackId, "عضویت شما تأیید شد!");
            return;
        }

        $text = $this->escape("❌ هنوز عضو کانال نشدید!\n\nابتدا عضو شوید سپس دوباره تلاش کنید.");
        $this->telegram->editMessage($chatId, $messageId, $text, $this->joinKeyboard());
        $this->telegram->answerCallback($callbackId, "عضویت تایید نشد", true);
    }

    private function sendMainMenu(int|string $chatId, int|string $messageId, string $callbackId, int|string $userId): void
    {
        if ($this->users->isVerified($userId)) {
            $text = $this->escape("🤖 لطفاً یک گزینه را انتخاب کنید:");
            $this->telegram->editMessage($chatId, $messageId, $text, $this->mainUserKeyboard());
        } else {
            $text = $this->escape("❌ ابتدا در کانال عضو شوید.");
            $this->telegram->editMessage($chatId, $messageId, $text, $this->joinKeyboard());
        }
        $this->telegram->answerCallback($callbackId);
    }

    private function handleAdminCallback(int|string $chatId, int|string $messageId, string $callbackId, string $data): bool
    {
        $adminMsgId = $this->getAdminMessageId();
        if ($adminMsgId === null || $adminMsgId !== (int)$messageId) {
            return false;
        }

        if ($data === 'stats') {
            $totalUsers = $this->users->countUsers();
            $totalVerified = $this->users->countVerified();
            $growth = $totalUsers === 0 ? 0 : round(($totalVerified / $totalUsers) * 100, 1);
            $errorCount = $this->recentErrorCount();
            $text = $this->escape(sprintf(
                "📊 آمار ربات:\n👥 کاربران: %d\n✅ تأیید شده: %d\n📈 رشد: %s%%\n🚨 خطاهای اخیر: %d\n🕐 %s",
                $totalUsers,
                $totalVerified,
                $growth,
                $errorCount,
                date('H:i:s')
            ));
            $this->telegram->editMessage($chatId, $messageId, $text, $this->adminKeyboard());
            $this->telegram->answerCallback($callbackId);
            return true;
        }

        if ($data === 'broadcast') {
            $this->users->setStep($chatId, 'broadcast');
            $this->telegram->sendMessage($chatId, '📝 متن پیام همگانی را ارسال کنید.');
            $this->telegram->answerCallback($callbackId);
            return true;
        }

        if ($data === 'forward') {
            $this->users->setStep($chatId, 'forward');
            $this->telegram->sendMessage($chatId, '📎 پیام مورد نظر برای فوروارد را ارسال کنید.');
            $this->telegram->answerCallback($callbackId);
            return true;
        }

        if ($data === 'close_panel') {
            $this->telegram->request('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
            $this->telegram->answerCallback($callbackId, 'پنل بسته شد');
            return true;
        }

        if ($data === 'user_panel') {
            $text = '🔧 پنل کاربری مدیریت وب‌هوک';
            $this->telegram->editMessage($chatId, $messageId, $text, $this->mainUserKeyboard());
            $this->telegram->answerCallback($callbackId);
            return true;
        }

        return false;
    }

    private function handleUserCallback(int|string $chatId, int|string $messageId, string $callbackId, string $data, int|string $userId): void
    {
        if (!in_array($data, ['set_webhook', 'reset_webhook', 'delete_webhook', 'support', 'webhook_status'], true)) {
            return;
        }

        if ($data === 'support') {
            $this->telegram->sendMessage($chatId, $this->supportText(), $this->backKeyboard());
            $this->telegram->answerCallback($callbackId);
            return;
        }

        if ($data === 'webhook_status') {
            $info = $this->telegram->getWebhookInfo();
            $text = $this->escape(json_encode($info['result'] ?? $info ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->telegram->sendMessage($chatId, "ℹ️ وضعیت وب‌هوک:\n<code>{$text}</code>", $this->backKeyboard());
            $this->telegram->answerCallback($callbackId);
            return;
        }

        $operation = match ($data) {
            'set_webhook' => 'set',
            'reset_webhook' => 'reset',
            default => 'delete',
        };

        $this->users->setStep($chatId, "webhook:{$operation}:token");
        $this->users->saveContext($chatId, ['operation' => $operation, 'user_id' => $userId]);
        $this->telegram->sendMessage($chatId, "🛡️ لطفاً توکن ربات خود را ارسال کنید:");
        $this->telegram->answerCallback($callbackId);
    }

    private function handleWebhookWizard(int|string $chatId, int|string $userId, string $text, string $step): bool
    {
        if (!str_starts_with($step, 'webhook:')) {
            return false;
        }

        $context = $this->users->getContext($chatId);
        $operation = $context['operation'] ?? 'set';

        if (str_ends_with($step, ':token')) {
            if (!Validator::isValidToken($text)) {
                $this->telegram->sendMessage($chatId, "❌ توکن معتبر نیست. لطفاً دوباره ارسال کنید.");
                return true;
            }

            $context['token'] = $text;
            $this->users->saveContext($chatId, $context);

            if ($operation === 'delete') {
                $this->executeWebhookOperation($chatId, $context, null);
                return true;
            }

            $this->users->setStep($chatId, "webhook:{$operation}:url");
            $this->telegram->sendMessage($chatId, "🔗 لطفاً آدرس HTTPS وب‌هوک را ارسال کنید:");
            return true;
        }

        if (str_ends_with($step, ':url')) {
            if (!Validator::isValidHttpsUrl($text)) {
                $this->telegram->sendMessage($chatId, "❌ آدرس معتبر نیست. باید با https شروع شود.");
                return true;
            }

            $context['url'] = $text;
            $this->users->saveContext($chatId, $context);
            $this->executeWebhookOperation($chatId, $context, $text);
            return true;
        }

        return false;
    }

    private function executeWebhookOperation(int|string $chatId, array $context, ?string $url): void
    {
        $operation = $context['operation'] ?? 'set';
        $token = $context['token'] ?? API_KEY;
        $token = trim($token);

        $result = match ($operation) {
            'set' => $this->telegram->setWebhook($url ?? DEFAULT_WEBHOOK_URL, $token),
            'reset' => $this->telegram->resetWebhook($url ?? DEFAULT_WEBHOOK_URL, $token),
            'delete' => $this->telegram->deleteWebhook($token),
            default => null,
        };

        $ok = $result !== null && ($result['ok'] ?? false) === true;
        $status = $ok
            ? '✅ عملیات انجام شد.'
            : ('❌ خطا: ' . ($result['description'] ?? 'خطای ارتباط با تلگرام'));
        $this->telegram->sendMessage($chatId, $status, $this->backKeyboard());

        $this->users->setStep($chatId, null);
        $this->users->clearContext($chatId);
    }

    private function handleAdminStepsNoWizard(): void
    {
        // reserved for future extension
    }

    private function mainUserKeyboard(): array
    {
        return [
            [['text' => '🔗 ست وب‌هوک', 'callback_data' => 'set_webhook']],
            [
                ['text' => '💠 وضعیت وب‌هوک', 'callback_data' => 'webhook_status'],
                ['text' => '♻️ ریست وب‌هوک', 'callback_data' => 'reset_webhook'],
            ],
            [
                ['text' => '❌ حذف وب‌هوک', 'callback_data' => 'delete_webhook'],
                ['text' => '💬 پشتیبانی', 'callback_data' => 'support'],
            ],
            [
                ['text' => '🔄 بازگشت', 'callback_data' => 'main_menu'],
            ],
        ];
    }

    private function joinKeyboard(): array
    {
        $channel = $this->primaryChannel();
        return [
            [['text' => "📢 عضویت در کانال", 'url' => "https://t.me/{$channel}"]],
            [['text' => "✅ بررسی عضویت", 'callback_data' => "check_join"]],
        ];
    }

    private function adminKeyboard(): array
    {
        return [
            [['text' => '📊 آمار ربات', 'callback_data' => 'stats']],
            [
                ['text' => '📢 ارسال همگانی', 'callback_data' => 'broadcast'],
                ['text' => '🔄 فروارد همگانی', 'callback_data' => 'forward'],
            ],
            [
                ['text' => '👤 پنل کاربری', 'callback_data' => 'user_panel'],
                ['text' => '❌ بستن پنل', 'callback_data' => 'close_panel'],
            ],
        ];
    }

    private function backKeyboard(): array
    {
        return [
            [['text' => '🔙 بازگشت', 'callback_data' => 'main_menu']],
        ];
    }

    private function supportText(): string
    {
        $lines = array_map(
            static fn (string $contact) => "👨‍💻 $contact",
            SUPPORT_CONTACTS
        );
        $body = implode("\n", $lines);
        return $this->escape("💬 پشتیبانی ربات:\n\n{$body}\n\nبرای دریافت راهنمایی با یکی از موارد بالا در ارتباط باشید.");
    }

    private function formatBroadcastSummary(array $summary): string
    {
        if (isset($summary['error'])) {
            return $this->escape("❌ خطا در صف ارسال: {$summary['error']}");
        }

        return $this->escape(sprintf(
            "📣 وضعیت صف:\n📌 شناسه: %s\n✅ موفق: %d\n❌ ناموفق: %d\n⏳ باقی‌مانده: %d\nوضعیت: %s",
            $summary['job_id'] ?? 'نامشخص',
            $summary['success'] ?? 0,
            $summary['failed'] ?? 0,
            $summary['remaining'] ?? 0,
            $summary['status'] ?? 'نامشخص'
        ));
    }

    private function getAdminMessageId(): ?int
    {
        if (!file_exists(ADMIN_MSGID_FILE)) {
            return null;
        }

        $content = file_get_contents(ADMIN_MSGID_FILE);
        return $content === false ? null : (int)trim($content);
    }

    private function isAdmin(int|string $userId): bool
    {
        return in_array((int)$userId, ADMINS, true);
    }

    private function isChannelMember(int|string $userId): bool
    {
        $channel = '@' . $this->primaryChannel();
        $res = $this->telegram->getChatMember($channel, $userId);
        if ($res === null || ($res['ok'] ?? false) !== true) {
            return false;
        }

        $status = $res['result']['status'] ?? '';
        return in_array($status, ['member', 'administrator', 'creator'], true);
    }

    private function primaryChannel(): string
    {
        return CHANNELS[0] ?? 'IM_MAKOOS';
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function recentErrorCount(int $sample = 200): int
    {
        if (!file_exists(LOG_FILE)) {
            return 0;
        }

        $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return 0;
        }

        $lines = array_slice($lines, -$sample);
        return count(array_filter($lines, static fn (string $line): bool => str_contains($line, '[ERROR]')));
    }

}

