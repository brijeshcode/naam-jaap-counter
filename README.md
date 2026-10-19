# 🙏 Naam Jaap Counter

A simple app to count your mantra jaap on a digital 108-bead mala.

## How to use

1. **Register** with a username and password, or **Login** if you already have an account.
2. **Tap the big circle** each time you chant — the bead ring fills up and the count rises. Every 108 completes one **mala**.
3. Chanted on a physical mala? Enter the number in **Add physical counter** and tap **Add count** to include it.
4. **Today** and **Total** cards show your jaap and malas at a glance.
5. Tap **History** (bottom bar) to see your progress by **week, month, or year**. Use ‹ › to move between periods.

## Handy options (top bar)

- **EN / हि** — switch between English and Hindi.
- **1,234 / 1.2K** — switch between full numbers and short form (K, Lakh/Crore or Million/Billion).
- **🌙 / ☀️** — switch between light and dark mode.
- **↻** — sync your latest counts. **⇥** — log out.

## Good to know

- Works **offline** — your counts are saved on your device and sync when you're back online.
- Your account stays signed in on **one device** at a time.
- **Reset today's count** clears only today's jaap (it can't be undone).

## Run locally (dev)

Requires PHP 8+ and MySQL/MariaDB (XAMPP provides both).

```bash
# 1. Start MySQL (XAMPP Control Panel → Start MySQL)

# 2. One-time DB setup
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS naam_jaap CHARACTER SET utf8mb4"
C:\xampp\mysql\bin\mysql -u root naam_jaap < schema.sql

# 3. Start the app (from the project root)
php -S localhost:8000
```

Then open **http://localhost:8000/index.php**.

- DB credentials come from `.env` (defaults: host `127.0.0.1`, db `naam_jaap`, user `root`, no password — matches XAMPP).
- `localhost` is a secure context, so the PWA **install button** shows in Chrome/Edge.

Happy chanting! 🧡
