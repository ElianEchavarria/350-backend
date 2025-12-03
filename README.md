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
