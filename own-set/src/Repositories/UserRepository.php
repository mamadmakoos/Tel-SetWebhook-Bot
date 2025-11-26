<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

namespace App\Repositories;

use App\Logging\Logger;

class UserRepository
{
    public function __construct(
        private readonly string $usersFile,
        private readonly string $verifiedFile,
        private readonly string $stepsDir,
        private readonly string $contextDir,
        private readonly Logger $logger
    ) {
        $this->ensureFile($this->usersFile);
        $this->ensureFile($this->verifiedFile);
        $this->ensureDirectory($this->stepsDir);
        $this->ensureDirectory($this->contextDir);
    }

    public function addUser(int|string $userId): void
    {
        $userId = (string)$userId;
        $users = $this->getAllUsers();
        if (in_array($userId, $users, true)) {
            return;
        }

        $this->writeLine($this->usersFile, $userId);
    }

    /**
     * @return string[]
     */
    public function getAllUsers(): array
    {
        return $this->readList($this->usersFile);
    }

    public function countUsers(): int
    {
        return count($this->getAllUsers());
    }

    public function countVerified(): int
    {
        return count($this->readList($this->verifiedFile));
    }

    public function isVerified(int|string $userId): bool
    {
        return in_array((string)$userId, $this->readList($this->verifiedFile), true);
    }

    public function verify(int|string $userId): void
    {
        $userId = (string)$userId;
        if ($this->isVerified($userId)) {
            return;
        }
        $this->writeLine($this->verifiedFile, $userId);
    }

    public function setStep(int|string $chatId, ?string $step): void
    {
        $path = $this->stepsDir . DIRECTORY_SEPARATOR . "step_{$chatId}.txt";
        if ($step === null) {
            if (file_exists($path)) {
                unlink($path);
            }
            return;
        }

        file_put_contents($path, $step, LOCK_EX);
    }

    public function getStep(int|string $chatId): ?string
    {
        $path = $this->stepsDir . DIRECTORY_SEPARATOR . "step_{$chatId}.txt";
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        return $content === false ? null : trim($content);
    }

    public function saveContext(int|string $chatId, array $context): void
    {
        $path = $this->contextDir . DIRECTORY_SEPARATOR . "ctx_{$chatId}.json";
        $encoded = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($path, $encoded, LOCK_EX);
    }

    public function getContext(int|string $chatId): array
    {
        $path = $this->contextDir . DIRECTORY_SEPARATOR . "ctx_{$chatId}.json";
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $this->logger->warning('Failed to decode context', [
                'chat_id' => $chatId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function clearContext(int|string $chatId): void
    {
        $path = $this->contextDir . DIRECTORY_SEPARATOR . "ctx_{$chatId}.json";
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function readList(string $file): array
    {
        if (!file_exists($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false || trim($content) === '') {
            return [];
        }

        return array_filter(
            array_map('trim', explode(PHP_EOL, $content)),
            static fn ($value) => $value !== ''
        );
    }

    private function writeLine(string $file, string $line): void
    {
        $result = file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            $this->logger->error('Failed to append file', ['file' => $file]);
        }
    }

    private function ensureFile(string $file): void
    {
        $dir = dirname($file);
        $this->ensureDirectory($dir);
        if (!file_exists($file)) {
            file_put_contents($file, '');
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->logger->error('Failed to create directory', ['dir' => $dir]);
        }
    }
}

