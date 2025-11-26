## Webhook Manager Bot – Developer Guide

Maintained by **Makoos**  
Channel: [@IM_MAKOOS](https://t.me/IM_MAKOOS)  
Telegram ID / Support: `1007009569` / [@barsam_dev](https://t.me/barsam_dev)

---

### 1. High-Level Architecture

| Layer | Responsibility | Key Classes |
| --- | --- | --- |
| Entry Point | Bootstrap config, wire services, handle webhook payload, report fatal errors | `bot.php` |
| Clients | Strongly-typed interface for Telegram Bot API + retries/telemetry | `App\Clients\TelegramClient` |
| Repositories | Persistent storage backed by flat files (users, verified list, wizard context, steps) | `App\Repositories\UserRepository` |
| Services | Domain workflows (menu handling, wizard, admin actions, queue orchestration) | `App\Services\BotService`, `BroadcastQueue` |
| Support | Cross-cutting helpers (logging, validation) | `App\Logging\Logger`, `App\Support\Validator` |

All dependencies are resolved manually in `bot.php` to keep deployment simple (no Composer autoloader required).

---

### 2. Directory Layout

```
├── bot.php                 # Webhook endpoint / bootstrap
├── config.php              # Static configuration (token, admins, channels, flags)
├── src/
│   ├── Clients/TelegramClient.php
│   ├── Logging/Logger.php
│   ├── Repositories/UserRepository.php
│   ├── Services/BotService.php
│   ├── Services/BroadcastQueue.php
│   └── Support/Validator.php
├── data/                   # Runtime data (auto-created)
│   ├── users.txt
│   ├── verified.txt
│   ├── logs/bot.log
│   ├── queue/
│   ├── context/
│   └── steps/
├── tests/                  # PHPUnit tests + bootstrap
└── README.md / DEVELOPERS.md
```

Keep `data/` writable and **never** commit it to VCS.

---

### 3. Configuration Tips (`config.php`)

- `API_KEY` / `$token`: bot token from BotFather.
- `ADMINS`, `ADMIN_ROLES`: numeric Telegram IDs mapped to roles (`super_admin`, `admin`).
- `CHANNELS`: ordered list; the first entry is used for membership enforcement.
- `SUPPORT_CONTACTS`: Telegram usernames displayed in the UI.
- `VERIFY_SSL`: set `true` when your hosting environment has proper CA bundles.
- Additional directories (`LOG_DIR`, `QUEUE_DIR`, etc.) are created automatically.

For production, consider sourcing values from environment variables before falling back to literals.

---

### 4. Runtime Flow

1. Telegram hits `bot.php` (HTTPS POST).
2. Payload decoded → background queue jobs processed (`BroadcastQueue::processAllPending`).
3. `BotService::handle()` dispatches to `handleMessage` or `handleCallback`.
4. User journeys:
   - `/start`: clean keyboard, enforce channel membership, show menu.
   - Inline buttons: run webhook wizard (token + HTTPS URL) or admin features.
   - Broadcast/Forward: stored as jobs on disk; each webhook tick drains a batch.
5. Fatal errors are logged with a unique `error_id` and forwarded to the first admin via Telegram DM.

---

### 5. Broadcast Queue

- Jobs stored as JSON files under `data/queue/`.
- Each process cycle sends up to `$batchSize` (default 25) messages to avoid timeouts.
- Once finished, the JSON file is removed and a summary is logged.
- You can trigger faster draining with a cron job that runs `php bot.php` using mocked payloads or by writing a small CLI wrapper that calls `BroadcastQueue::processAllPending()`.

---

### 6. Testing

1. Install PHPUnit (globally or via Composer).
2. Run tests:
   ```bash
   phpunit            # global
   # or
   ./vendor/bin/phpunit
   ```
3. `tests/ValidatorTest.php` currently covers URL/token validation. Extend with integration tests by mocking Telegram responses (e.g., using `StreamWrapper` or a custom HTTP client interface).

---

### 7. Extensibility Ideas

- **Storage backend:** swap `UserRepository` with SQLite/Redis implementation while keeping the interface consistent.
- **Job dispatcher:** replace file queue with Redis, RabbitMQ, or Laravel queues for higher throughput.
- **Localization:** wrap all user-facing strings into a translator helper and load locales from JSON.
- **Security:** enable `VERIFY_SSL`, add rate limiting, and sign incoming requests using Telegram `X-Telegram-Bot-Api-Secret-Token`.
- **Metrics:** export queue depth and error counts to Prometheus or any logging platform.

---

### 8. Support & Contact

- Primary owner: **Makoos**
- Telegram Channel: `@IM_MAKOOS`
- Direct contact: `@barsam_dev` (ID: `1007009569`)

Please mention the **error ID** shown in bot responses when reporting issues—it maps directly to log entries in `data/logs/bot.log`.

