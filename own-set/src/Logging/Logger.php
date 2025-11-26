<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

namespace App\Logging;

use RuntimeException;

class Logger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
        $this->ensureDirectory(dirname($logFile));
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): string
    {
        $errorId = $context['error_id'] ?? $this->generateErrorId();
        $context['error_id'] = $errorId;
        $this->write('ERROR', $message, $context);
        return $errorId;
    }

    private function write(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context === [] ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $line = sprintf('[%s] [%s] %s%s%s', $timestamp, $level, $message, $contextStr, PHP_EOL);
        $result = file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        if ($result === false) {
            throw new RuntimeException('Failed to write log file: ' . $this->logFile);
        }
    }

    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create log directory: ' . $dir);
        }
    }

    private function generateErrorId(): string
    {
        return bin2hex(random_bytes(6));
    }
}

