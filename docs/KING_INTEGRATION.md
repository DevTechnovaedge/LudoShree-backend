# King WebSocket (Daddy King) Integration

Cross-platform Ludo table sync with the King network (`wss://kingws.daddyking.live/ws`).
Tables created on LudoShree appear on other platforms, and tables created on other
platforms (e.g. Daddy King) appear inside the LudoShree app as normal challenges.

**Money rule:** each platform handles ONLY its own users' wallets. Remote players are
shown through proxy ("ghost") user accounts that never hold or receive real money here.

---

## 1. Architecture (why API actions stay fast)

```
 Flutter app / Admin panel
        |
        v  (normal HTTP - only inserts a DB row, microseconds)
 +--------------+      +--------------+
 |  Laravel API | ---> |  king_outbox |  <-- message queue in MySQL
 +--------------+      +--------------+
                              |
                              v  (single long-running process)
                     +------------------+       one WebSocket
                     | php artisan      | <==========================> King server
                     | king:listen      |   login / ping / tables
                     +------------------+
                              |
                              v
                 game_challenges + king_tables + wallets
                 (updates broadcast to the app via the existing
                  Pusher `challenge.changed` events automatically)
```

- HTTP requests **never** open a WebSocket or wait on the network. All pushes to
  King go through the `king_outbox` table, pumped by the daemon every 250ms.
- The **only** action that waits is accepting a King-synced table: the join must be
  confirmed by the King server first (usually < 1 second) so that two players on
  different platforms can never take the same table. The wallet is debited only
  after the King server confirms.

## 2. One-time setup

### a) Database (run `php artisan migrate` on the server)

Migrations live in `database/migrations/2026_08_08_00000*_*king*.php` (5 files):

- `game_challenges` + columns: `game_source`, `king_table_id`, `king_sync_status`
- `users` + columns: `is_king_player`, `king_player_id`
- New tables: `king_tables` (network mirror), `king_outbox` (message queue),
  `king_event_logs` (audit + inconsistency log)

The migrations are guarded with `hasColumn` / `hasTable` checks, so they are safe
to run even if you already applied `database/sql/king_integration.sql` manually
(the raw SQL file is kept only as a reference).

### b) Composer dependency (on the server)

```bash
composer require ratchet/pawl:^0.4.3
```

(`ratchet/pawl` is already added to composer.json; the command updates composer.lock.)

### c) .env

```env
KING_WS_ENABLED=true
KING_WS_URL=wss://kingws.daddyking.live/ws
KING_WS_LOBBY=LUDO_KING_LOBBY
KING_WS_API_KEY=<api key from Daddy King>
KING_WS_API_SECRET=<api secret from Daddy King>
KING_GAME_TYPE_ID=1
```

Then `php artisan config:clear`.

### d) Run the daemon under supervisor

`/etc/supervisor/conf.d/king-listen.conf`:

```ini
[program:king-listen]
command=php /path/to/LudoShree-backend/artisan king:listen
directory=/path/to/LudoShree-backend
autostart=true
autorestart=true
startretries=999
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/king-listen.log
stopwaitsecs=10
```

```bash
sudo bash /var/www/html/ludo-shree/scripts/setup-king-supervisor.sh
```

Or copy `scripts/supervisor/king-listen.conf` manually to `/etc/supervisor/conf.d/`
then `supervisorctl reread && supervisorctl update && supervisorctl start king-listen`.

IMPORTANT: run exactly ONE instance of `king:listen`.

### e) GitHub Actions deploy (already wired)

Pushes to `main` run `.github/workflows/deploy.yml`, which executes
`scripts/deploy.sh`. After each deploy the script:

- runs `php artisan migrate --force` (King tables/columns)
- rebuilds config cache (picks up `.env` King keys)
- **`supervisorctl restart king-listen`** when the program is registered

One-time on the server (before the first successful daemon restart):

```bash
sudo bash /var/www/html/ludo-shree/scripts/setup-king-supervisor.sh
```

Ensure `.env` on the server has `KING_WS_ENABLED=true` and Daddy King credentials
before expecting the daemon to connect.

## 3. Flow summary

| Local action | What happens on the King network |
|---|---|
| User creates a classic table | `KingCreateTableRequest` queued; on success the challenge stores `king_table_id` (DK-x-y) |
| User cancels a waiting synced table | `KingTableDeleteRequest` |
| User accepts a synced table | `KingAcceptRequest` confirmed FIRST, then wallet debit + opponent set |
| Challenger sets room code | `KingUpdateCodeRequest` |
| User submits Win / Loss / Cancel | `ResultUpdateRequest` (`result`: Win / Loss / Cancel, optional image + video URLs) |
| User cancels a running synced game | `ResultUpdateRequest` with `Cancel` for the acting user (waiting tables use delete instead) |
| Admin resolves result / cancels / suspends | `ResultUpdateRequest` / `KingTableDeleteRequest` |

| Remote event (from King) | What happens locally |
|---|---|
| New table on another platform | Ghost user + waiting challenge created (shows in app lobby, name suffix `[DK]`) |
| Remote player joins OUR table | Ghost opponent attached, status Running (no local debit for the ghost) |
| Remote creator sets room code | Room code stored, app notified |
| **`ResultUpdateRequest` push** | Dedicated listener in `king:listen` → `handleResultUpdateRequest()` applies Win/Loss/Cancel + remote image/video URLs |
| Remote result Win | Ghost side marked winner; local user can still submit; both-claim-win = Dispute (admin) |
| Remote result Loss | Ghost side marked loser; local winner paid `paid_amount` (idempotent, one time only) |
| Remote result Cancel | Local stakes refunded when mutual / not started |
| Table deleted / taken elsewhere | Local waiting challenge auto-closed |

## 4. Consistency guarantees

- All remote-event handlers are **idempotent** (the same event can arrive twice via
  server push + list polling without double crediting).
- Ghost users are hard-blocked from: stake refunds, winner payouts, penalties paid
  locally (guards in `GameChallengeStakeRefundService`, `GameChallengeWinnerPayoutService`,
  loser flow in `ApiController`).
- Winner payout uses a one-time check on the wallet ledger (`Winner amount Ref:` /
  `Winner Ref:` credit) before crediting.
- Any inconsistency (conflicting accepts, rejected result updates, missing local
  challenge for a network table) is written to `king_event_logs` with level
  `warning`/`error` and shown in **Admin -> King Sync (DK) -> Issues** so you are
  informed before taking the next step.
- If the daemon is offline: local tables continue working locally (accept falls back
  to the normal local flow, logged as a warning); remote-owned tables are not
  joinable until the connection is back. On reconnect the daemon reconciles the
  full table list and pushes everything still pending in the outbox.

## 5. Admin panel

`Admin -> King Sync (DK)` (super-admin only):

- Connection status (online / offline / paused), client id
- **Network Tables** - live mirror of all King tables with links to challenges
- **Outbox** - every message with status; retry button for failed ones
- **Issues / Logs** - warnings & errors (data inconsistencies show here)
- **Pause / Resume** button - stops pushing/pulling without killing the daemon

## 6. Notes / decisions to confirm with the business

1. **Cross-platform payout amount:** when our user wins a cross-platform game they
   receive the normal `paid_amount` (2 x stake - commission) even though only one
   stake was collected locally (the remote loser's stake stays on their platform).
   This follows the "wallet handled freely at the accepting/owning platform" rule;
   platform-to-platform settlement is outside this system.
2. Remote players appear as users with a `[DK]` name suffix, `is_king_player=1` and
   a `0`-prefixed synthetic mobile. They will show up in admin user lists and
   leaderboards; filter with `WHERE is_king_player = 0` if needed.
3. Only game type 1 (Ludo Classic) is synced. Ulta Ludo stays local.
4. Remote tables with an amount outside the platform min/max game amount are not
   imported (logged as info).
5. LK Game API auto-settlement is bypassed for cross-platform games (results flow
   through the King network instead).
