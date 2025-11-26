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

class BroadcastQueue
{
    public function __construct(
        private readonly string $queueDir,
        private readonly TelegramClient $telegram,
        private readonly UserRepository $users,
        private readonly Logger $logger,
        private readonly int $batchSize = 25
    ) {
        $this->ensureDirectory($queueDir);
    }

    public function enqueueText(string $text, ?array $targets = null): string
    {
        $jobId = $this->generateJobId();
        $this->writeJob($jobId, [
            'type' => 'text',
            'payload' => ['text' => $text],
            'targets' => $targets ?? $this->users->getAllUsers(),
            'status' => 'pending',
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'created_at' => time(),
        ]);

        return $jobId;
    }

    public function enqueueForward(int|string $fromChatId, int|string $messageId, ?array $targets = null): string
    {
        $jobId = $this->generateJobId();
        $this->writeJob($jobId, [
            'type' => 'forward',
            'payload' => [
                'from_chat_id' => $fromChatId,
                'message_id' => $messageId,
            ],
            'targets' => $targets ?? $this->users->getAllUsers(),
            'status' => 'pending',
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'created_at' => time(),
        ]);

        return $jobId;
    }

    public function process(string $jobId): array
    {
        $job = $this->readJob($jobId);
        if ($job === null) {
            return ['error' => 'JOB_NOT_FOUND'];
        }

        $targets = $job['targets'] ?? [];
        $processed = $job['processed'] ?? 0;
        $success = $job['success'] ?? 0;
        $failed = $job['failed'] ?? 0;
        $slice = array_slice($targets, $processed, $this->batchSize);

        foreach ($slice as $userId) {
            $result = null;
            if ($job['type'] === 'text') {
                $result = $this->telegram->sendMessage((int)$userId, $job['payload']['text'] ?? '');
            } elseif ($job['type'] === 'forward') {
                $payload = $job['payload'];
                $result = $this->telegram->forwardMessage(
                    (int)$userId,
                    $payload['from_chat_id'],
                    $payload['message_id']
                );
            }

            if ($result !== null && ($result['ok'] ?? false)) {
                $success++;
            } else {
                $failed++;
            }

            $processed++;
        }

        $job['processed'] = $processed;
        $job['success'] = $success;
        $job['failed'] = $failed;
        $job['status'] = $processed >= count($targets) ? 'done' : 'pending';
        $this->writeJob($jobId, $job);

        $summary = [
            'job_id' => $jobId,
            'status' => $job['status'],
            'processed' => $processed,
            'success' => $success,
            'failed' => $failed,
            'remaining' => max(0, count($targets) - $processed),
        ];

        if ($job['status'] === 'done') {
            $this->logger->info('Broadcast finished', [
                'job_id' => $jobId,
                'success' => $success,
                'failed' => $failed,
            ]);
            @unlink($this->jobPath($jobId));
        }

        return $summary;
    }

    public function processAllPending(): array
    {
        $results = [];
        $files = glob($this->queueDir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $jobId = basename($file, '.json');
            $results[] = $this->process($jobId);
        }

        return $results;
    }

    private function generateJobId(): string
    {
        return uniqid('job_', true);
    }

    private function jobPath(string $jobId): string
    {
        return $this->queueDir . DIRECTORY_SEPARATOR . $jobId . '.json';
    }

    private function writeJob(string $jobId, array $data): void
    {
        $path = $this->jobPath($jobId);
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($path, $encoded, LOCK_EX);
    }

    private function readJob(string $jobId): ?array
    {
        $path = $this->jobPath($jobId);
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        return json_decode($content, true);
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

