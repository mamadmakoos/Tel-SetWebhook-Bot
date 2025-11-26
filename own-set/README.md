## Telegram Webhook Manager Bot

### Installation

1. **Configure `config.php`**
   - Set `$token` to the BotFather token.
   - Set `$admin` (and optionally `$adminRoles`) to your numeric Telegram ID.
   - Update `$channel`, `$channelList`, `$support`, and `$supportContacts` with your public data.
   - Flip `VERIFY_SSL` to `true` once your hosting environment has valid CA bundles.

2. **Deploy files**
   - Upload `bot.php`, `config.php`, and the entire `src/` directory to an HTTPS-enabled server.
   - Ensure the `data/` directory (and subfolders) are writable by PHP; they are auto-created on first run.

3. **Set the webhook**
   - Call `https://api.telegram.org/bot<token>/setWebhook?url=https://your-domain/bot.php`
     or tap the “Set Webhook” button inside the bot once it is live.

4. **Verify**
   - Send `/start` from the admin account and confirm the inline keyboard appears.
   - Run the webhook wizard (Set/Reset/Delete) and ensure Telegram returns `ok: true`.
   - Optional: execute tests via `./vendor/bin/phpunit` (or `phpunit` if installed globally).

### Key Features

- **Modular architecture:** `TelegramClient`, `UserRepository`, `BroadcastQueue`, and `BotService` encapsulate responsibilities; `bot.php` is only the entry point.
- **Broadcast queue:** Mass messages and forwards are chunked into batches to avoid Telegram timeouts.
- **Webhook wizard:** Every user can submit a token + HTTPS URL to Set/Reset/Delete their webhook securely.
- **Structured logging:** All critical events land in `data/logs/bot.log` with unique error IDs for quick triage.
- **Multi-admin / multi-channel:** Role-aware configuration lists (`$adminRoles`, `$channelList`) make it easy to extend governance.
- **Validation & safety:** All IDs, tokens, and URLs are validated; outbound responses are HTML-escaped.

### Project Layout

```
├── bot.php                  # Webhook entry point
├── config.php               # Static configuration
├── src/
│   ├── Clients/TelegramClient.php
│   ├── Logging/Logger.php
│   ├── Repositories/UserRepository.php
│   └── Services/
│       ├── BotService.php
│       └── BroadcastQueue.php
└── data/
    ├── users.txt
    ├── verified.txt
    ├── logs/bot.log
    ├── queue/
    ├── context/
    └── steps/
```

### Quick Testing Checklist

- Send `/start` and confirm membership enforcement works.
- Tap “Set Webhook” to complete the wizard and inspect Telegram’s response.
- From `/panel`, try “Stats”, “Broadcast”, and “Forward” buttons.
- On errors, note the `error_id` shown in chat and inspect `data/logs/bot.log`.
- Run PHPUnit (requires v10+):
  ```
  ./vendor/bin/phpunit
  ```
  or
  ```
  phpunit
  ```

