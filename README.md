## Environment

Set these environment variables on your hosting platform (Render) or in a local `.env` file for development. The backend loads `.env` automatically.

### Email Configuration
 
- `ADMIN_EMAIL`: recipient email for order notifications.
- `SENDGRID_API_KEY`: if set, emails send via SendGrid HTTPS API (recommended on Render).
- `SENDGRID_FROM`: the verified SendGrid sender email (Single Sender or domain-authenticated). Must match SendGrid Sender Identity.
- `SMTP_HOST`: SMTP server host (fallback path).
- `SMTP_PORT`: SMTP server port (e.g., 587).
- `SMTP_SECURE`: `tls` or `ssl`.
- `SMTP_USER`: SMTP username.
- `SMTP_PASS`: SMTP password.
- `SMTP_FROM`: From email address (defaults to `SMTP_USER`).
- `SMTP_FROM_NAME`: From display name (e.g., `Shop`).
- `SMTP_TIMEOUT`: Optional timeout in seconds (default 8-10).

Notes:
- Render often blocks outbound SMTP; using `SENDGRID_API_KEY` avoids that by using HTTPS.
- When `SENDGRID_API_KEY` is present, checkout emails use SendGrid and fall back to SMTP only if the API call fails.
 - SendGrid requires a verified sender identity. Set `SENDGRID_FROM` to a verified sender (or authenticate your domain), otherwise the API returns 403.

### Quick Email Test

Use `test_email.php` to verify email configuration without placing an order:

Request (POST):

```
POST /test_email.php
Content-Type: application/x-www-form-urlencoded

to=your@email.com&subject=Test&text=Hello&html=<p>Hello</p>
```

Response JSON includes `email_sent`, and `sendgrid_error` or `smtp_error` if delivery fails.
# PHP Backend

This folder contains a simple PHP backend that provides:

- User registration and login (session-based, SQLite)
- Products API (inventory with stock)
- Session-based shopping cart endpoints
- Checkout that updates inventory, records an order, and attempts to email the admin

Quick start (local testing)

1. Make sure PHP 7.4+ is installed locally.
2. From this repo root run the built-in PHP server for the backend:

```powershell
cd backend
php -S localhost:8000
```

3. Initialize the DB (first run):

Open in browser or curl: `http://localhost:8000/init_db.php` — this will create `database.sqlite` and seed products from `products.json`.

4. Register / Login / Use API:

- `POST /register.php` with `username` and `password` (form or x-www-form-urlencoded)
- `POST /login.php` with `username` and `password`
- `GET /api_products.php` returns current products and stock
- `POST /add_to_cart.php` with `product_id` and `qty` to add to session cart
- `POST /checkout.php` with `name` to place order (must be logged in)

Email delivery

- The backend will try PHP's `mail()` and requires an `ADMIN_EMAIL` environment variable (or a file `admin_email.txt` containing an email address in the `backend/` folder) to send order emails.
- If mail() is not available (common on Vercel), orders are still saved in `orders.log` and `orders` table.

Deploying with Vercel

Vercel does not run PHP natively out-of-the-box. For a simple configuration you have two options:

1. Deploy frontend to Vercel and backend to a PHP host (Render, Fly, or a VPS).
   - This is the easiest and recommended approach.
2. Use a community PHP builder for Vercel (e.g. `vercel-php`). This requires extra configuration on Vercel and may not support `mail()`.

Environment variables on hosting

- Set `ADMIN_EMAIL` to the email address where you want order notifications to be sent.

Security notes

- Passwords are hashed with `password_hash`.
- This is a minimal demo; add CSRF protection, input validation, HTTPS, and stronger mail delivery for production.
