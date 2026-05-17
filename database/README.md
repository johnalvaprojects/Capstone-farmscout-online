# Database setup (FarmScout Online)

## For GitHub / new clones

This repo does **not** include a full database dump with real user data or SMTP secrets.

### Fresh install (recommended)

1. Create database `farmscout_online` in HeidiSQL or phpMyAdmin.
2. Run scripts **in this order**:
   1. `schema.sql` — core tables
   2. `install_missing_dashboard_tables.sql` — reservations, messaging, related tables
   3. `reservation_multi_product_and_public_refs.sql` — multi-item reservations + public IDs (`RSV-`, `CHAT-`)
   4. Optional: `create_admin_logs_table.sql` if you use admin activity logs
3. Register users through the app, or use your own seed data (do not commit real passwords).

### Your local full export

If you have `farmscout_online.sql` from HeidiSQL, keep it **only on your machine**. It is listed in `.gitignore` because it often contains:

- Gmail SMTP passwords in `app_config`
- Real email addresses and password hashes
- Local file paths

**Never push that file to GitHub.**

### Legacy migrations

Older one-off scripts live in `archive/legacy-migrations/`. They were used while developing; you do not need them if you follow the install order above.

### Re-export for backups (local only)

HeidiSQL → select `farmscout_online` → **Tools → Export database as SQL** → save outside the repo or in a gitignored folder.
