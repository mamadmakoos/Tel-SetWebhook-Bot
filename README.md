```
╔═════════════════════════════════════════╗
║  ██████╗ ██╗   ██╗███████╗██████╗       ║
║  ██╔══██╗██║   ██║██╔════╝██╔══██╗      ║
║  ██████╔╝██║   ██║█████╗  ██████╔╝      ║
║  ██╔═══╝ ██║   ██║██╔══╝  ██╔══██╗      ║
║  ██║     ╚██████╔╝███████╗██║  ██║      ║
║  ╚═╝      ╚═════╝ ╚══════╝╚═╝  ╚═╝      ║
║⚡ Cyberpunk Telegram Webhook Manager ⚡║
╚═════════════════════════════════════════╝
```

# ⚡ Cyberpunk Telegram Webhook Manager Bot 🌌


## 🔧 Installation 🚀

1. **Configure `config.php`** 📝

   * Set `$token` to the BotFather token 🔑
   * Set `$admin` (and optionally `$adminRoles`) to your numeric Telegram ID 🧑‍💻
   * Update `$channel`, `$channelList`, `$support`, `$supportContacts` with your public data 🌐
   * Flip `VERIFY_SSL` to `true` when your server has valid CA bundles 🔒
   * Optionally set `$secretToken` for Telegram’s secret-token header 🔑
   * Enable `$enableIpWhitelist` to restrict requests to official Telegram IP ranges 🌐

2. **Deploy files** 📂

   * Upload `bot.php`, `config.php`, and `src/` to HTTPS server 🌐
   * Ensure `data/` (and subfolders) are writable 💾

3. **Set the webhook** 🌐

   * `https://api.telegram.org/bot<token>/setWebhook?url=https://your-domain/bot.php`
   * Or tap “Set Webhook” inside bot 🖱️

4. **Verify** ✅

   * Send `/start` as admin 🚀
   * Run webhook wizard 🔄
   * Optional: run tests via PHPUnit 🧪

## 🌟 Key Features ✨

* 🏗 **Modular architecture:** `TelegramClient`, `UserRepository`, `BroadcastQueue`, `BotService`
* 📣 **Broadcast queue:** Chunked mass messages to avoid Telegram timeouts ⏱️
* 🧙 **Webhook wizard:** Token + HTTPS URL management securely 🔒
* 📜 **Structured logging:** Critical events in `data/logs/bot.log` with error IDs 🕵️
* 👥 **Multi-admin/multi-channel:** `$adminRoles` + `$channelList` 🛡️
* 🔎 **Validation & safety:** IDs, tokens, URLs validated and HTML-escaped 🌐
* 🛡 **Security guardrails:** Secret token + IP whitelisting enforced 🚨

## 🧩 Project Layout 📁

```
├── bot.php                  # Webhook entry point ⚡
├── config.php               # Static configuration 📝
├── src/
│   ├── Clients/TelegramClient.php 💻
│   ├── Logging/Logger.php 📜
│   ├── Repositories/UserRepository.php 🗄️
│   └── Services/
│       ├── BotService.php 🧩
│       └── BroadcastQueue.php 📡
└── data/
    ├── users.txt 👥
    ├── verified.txt ✅
    ├── logs/bot.log 📜
    ├── queue/ 📦
    ├── context/ 🔄
    └── steps/ 🛠️
```

## 🧪 Quick Testing Checklist 🕹️

* Send `/start` and confirm membership enforcement ✅
* Tap “Set Webhook” 🔗
* Use `/panel`: try “Stats”, “Broadcast”, “Forward” buttons 📊
* On errors: note `error_id` ⚠️ and check `data/logs/bot.log`
* Run PHPUnit (v10+) 🧪

```
./vendor/bin/phpunit
```

or

```
phpunit
```

## 🔐 Security Hardening Tips 🛡️

* **Secret token:** `$secretToken` header enforced 🔑
* **IP whitelist:** Drop requests outside Telegram IPv4 ranges 🌐
* **Logging:** Rejected scenarios logged with offending IP 📜
