# FarmScout Online

[![PHP](https://img.shields.io/badge/PHP-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Vite](https://img.shields.io/badge/Vite-646CFF?style=flat&logo=vite&logoColor=white)](https://vitejs.dev/)
[![React](https://img.shields.io/badge/React-61DAFB?style=flat&logo=react&logoColor=black)](https://react.dev/)

Web app for **Baloan Public Market**: product discovery, price comparison, multi-product reservations, farmer and admin dashboards, and reservation-linked chat.

**Stack:** PHP 7.4+ (PDO), MySQL, Vite + React SPA (`spa-src/` → `app/`), Apache/XAMPP-friendly layout.

**My role:** Full-stack development — Vite/React SPA, PHP REST-style APIs, MySQL schema and migrations (reservations, chat, dashboards), shared scripts for the reservation flow, and deployment packaging for production hosting.

> Portfolio / academic project — suitable for OJT, capstone, or resume demos. Configure your own `.env` and database; do not commit secrets.

## Screenshots

### Products page
![Products browse](docs/screenshots/products.png)

### Reserve product (modal)
![Reservation flow](docs/screenshots/reserve.png)

### Login
![Login](docs/screenshots/login.png)

---

## Highlights (for recruiters)

- Consumer SPA for browsing markets, products, and price trends
- Reservation flow with optional **multi-product cart** and public reference IDs (`RSV-YYYY-NNNN`, `CHAT-YYYY-NNNN`)
- Farmer and **DTI/admin** tooling (dashboards, reference prices, logs)
- Real-time-style **chat** tied to reservations
- Google Maps integration (optional API key via `.env`)
- Structured PHP APIs under `api/` + shared `includes/enhanced_functions.php`

---

## Requirements

- PHP **7.4+** with extensions: **pdo_mysql**, **json**, **mbstring**, **openssl** (as needed by your host)
- MySQL **5.7+** / MariaDB **10.3+**
- **Node.js 18+** and npm (only to build the SPA from `spa-src/`)

---

## Local setup (XAMPP-style)

1. **Clone** this repo into your web root, e.g. `htdocs/farmscout_online`.

2. **Environment**
   - Copy `config/env.sample` to **`.env`** in the project root (same folder as `index.php`).
   - Edit `.env`: set `DB_*`, `APP_URL` (match your folder URL, e.g. `http://localhost/farmscout_online`), and optional email / Google Maps / OAuth keys.

3. **Database** — see [`database/README.md`](database/README.md)
   - Create database `farmscout_online`.
   - Run `database/schema.sql`, then `install_missing_dashboard_tables.sql`, then `reservation_multi_product_and_public_refs.sql`.
   - Do **not** commit `database/farmscout_online.sql` (local exports may contain SMTP passwords and real user data).

4. **Build the SPA** (writes into `app/` — `index.html`, hashed JS/CSS under `app/assets/`)

   ```bash
   cd spa-src
   npm install
   npm run build
   ```

   `postbuild` runs a small prune so old hashed bundles under `app/assets/` are not left behind.

5. **Open the app**  
   Point the browser at your app URL, e.g. `http://localhost/farmscout_online/app/` for the SPA home, or `index.php` / `login.php` for classic PHP entry points depending on how you host it.

---

## Project layout (short)

| Path | Role |
|------|------|
| `api/` | JSON APIs (reservations, chat, products, auth helpers, …) |
| `app/` | **Production SPA output** (do not edit by hand; rebuild from `spa-src`) |
| `spa-src/` | Vite + source for the SPA (`npm run build`) |
| `app/js/`, `app/css/` | Shared scripts/styles (e.g. reservation modal) loaded by pages |
| `includes/` | PHP includes (`enhanced_functions.php`, security, headers, chat shell) |
| `config/` | `database.php`, `env.sample`, email/maps config |
| `database/` | SQL migrations and exports |

---

## Deploy notes

- Deploy **PHP**, `api/`, `includes/`, `config/` (without committing real secrets), **`app/`** after `npm run build`, plus `app/js` and `app/css` if you use them on the server.
- Run the same **MySQL migrations** on the server DB as on local (including `reservation_multi_product_and_public_refs.sql` if you use multi-item reservations).
- Ensure `storage/` (and any cache dirs your config uses) are **writable** by the web server if applicable.

---

## Quick start

After cloning into your web root (see [Local setup](#local-setup-xampp-style) for detail):

1. Copy `config/env.sample` to **`.env`** in the project root and set `DB_*`, `APP_URL`, and any optional keys (email, maps, OAuth).
2. Create the database and run SQL in order — see [**database/README.md**](database/README.md) (`schema.sql`, then `install_missing_dashboard_tables.sql`, then `reservation_multi_product_and_public_refs.sql` as needed).
3. From `spa-src/`: `npm install` then `npm run build` (output goes to `app/`).
4. Open `http://localhost/<your-folder>/app/` for the SPA, or `login.php` / `index.php` for classic entry points.

Before every commit, check `git status`: do not track `.env`, `database/farmscout_online.sql`, or `node_modules`.

---

## GitHub profile (for employers)

On the repository home page, use the **About** section (gear icon) to add a short description and topics (e.g. `php`, `mysql`, `vite`, `react`, `capstone`, `portfolio`). On your GitHub profile, use **Customize your pins** to feature this repository.

---

## License

Licensed under the [MIT License](LICENSE). Use and adapt for **OJT / capstone / portfolio** as allowed by your school or employer.
