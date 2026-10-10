# Deploying Jewelry Trader to cPanel

Written for a standard cPanel account with MySQL. Read section 1 before
touching anything — two of those checks decide whether the rest of this is
straightforward or painful.

Throughout, replace `USER` with the cPanel username and `example.com` with the
real domain.

---

## 1. Before you start — check these three things

**PHP 8.3 or newer.** cPanel → *MultiPHP Manager*. The application will not
run on 8.2 or below. Then cPanel → *Select PHP Version* → *Extensions*, and
confirm these are ticked:

```
bcmath  ctype  curl  dom  fileinfo  gd  intl  mbstring
openssl  pdo_mysql  tokenizer  xml  zip
```

`gd` is needed for image handling and `intl` for currency formatting; the rest
are Laravel's own requirements.

**Terminal access.** cPanel → *Terminal*. If it is present, everything below
is straightforward. If the host has disabled it, read section 9 first — you
can still deploy, but migrations and seeding need a different route and it is
worth knowing that before you upload anything.

**Whether you can set a document root.** Laravel serves from `public/`, not
from the application root. The clean way is to point the domain at
`/home/USER/jewelry-trader/public`, which also keeps `.env`, the database
credentials and all application code outside the web root. Section 3 covers
what to do if the host will not allow it.

---

## 2. Build the release locally

cPanel has no Node and often no Composer, so build on your own machine and
upload the result.

```bash
git clone <repo-url> jewelry-trader-release
cd jewelry-trader-release

composer install --no-dev --optimize-autoloader
npm ci
npm run build

rm -rf .git node_modules tests .github
```

Two notes. `--no-dev` matters: without it you ship development tooling onto a
public server. And run `composer install` on PHP 8.3 locally, so the
autoloader and any platform-specific packages match the server.

Then zip the folder — `jewelry-trader.zip` — including hidden files.

---

## 3. Upload and set the document root

Upload the zip through cPanel → *File Manager* to `/home/USER/`, and extract
it so the application lives at `/home/USER/jewelry-trader`.

**Preferred: point the domain at `public/`.**

- For the main domain: cPanel → *Domains* → edit the domain → set the document
  root to `jewelry-trader/public`.
- For a subdomain (e.g. `shop.example.com`): cPanel → *Domains* → *Create a
  New Domain*, and set the document root to `/home/USER/jewelry-trader/public`
  rather than accepting the default.

**If the host will not let you change the document root**, put the application
outside `public_html` and move only the public files into it:

```bash
mv /home/USER/jewelry-trader/public/* /home/USER/public_html/
mv /home/USER/jewelry-trader/public/.htaccess /home/USER/public_html/
```

Then edit `/home/USER/public_html/index.php` and change the two require paths
so they point up and across into the application:

```php
require __DIR__.'/../jewelry-trader/vendor/autoload.php';

$app = require_once __DIR__.'/../jewelry-trader/bootstrap/app.php';
```

This works, but it is the weaker arrangement: every future deployment has to
repeat the move. Prefer the document root if you can get it.

---

## 4. Database

cPanel → *MySQL Databases*:

1. Create a database — cPanel will name it `USER_jewelry`.
2. Create a user — `USER_jewelry` — with a long generated password. Save it
   somewhere safe; you will need it in the next step and nowhere else.
3. Add the user to the database with **ALL PRIVILEGES**.

---

## 5. Configure the environment

Create `/home/USER/jewelry-trader/.env` (File Manager → *+ File*, then *Edit*):

```ini
APP_NAME="Jewelry Trader"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=USER_jewelry
DB_USERNAME=USER_jewelry
DB_PASSWORD=the-password-you-just-made

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local

MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.example.com
MAILGUN_SECRET=your-mailgun-key
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Jewelry Trader"
```

`APP_DEBUG=false` is not optional. With it on, any error page shows database
credentials and file paths to whoever triggered it.

**`.env` holds infrastructure only.** Stripe keys, the AI service key and the
metals feed key are *not* here — they are entered in the admin panel, stored
encrypted, and can be rotated without a deployment. If you find yourself
adding one to this file, something has gone wrong.

Then generate the application key:

```bash
cd ~/jewelry-trader
php artisan key:generate
```

That key encrypts every stored secret. If it is ever regenerated on a live
system, every saved API key becomes unreadable — back it up with the database.

---

## 6. Permissions

```bash
cd ~/jewelry-trader
find storage bootstrap/cache -type d -exec chmod 755 {} \;
find storage bootstrap/cache -type f -exec chmod 644 {} \;
chmod 600 .env
```

Do not use 777. cPanel runs PHP as your own user, so 755 is sufficient, and
777 lets any other account on a shared server write to your application.

---

## 7. Install the database and the first account

```bash
cd ~/jewelry-trader

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

`db:seed` sets up roles, permissions, settings defaults, locations, the
payment gateway row, the metals feed providers, the field colour rules and the
step-up challenges. It deliberately does **not** create a login in production.

Load the appraiser's rate table — this one overwrites, so it is a deliberate
separate step:

```bash
php artisan db:seed --class=OpeningRateTableSeeder --force
```

Create the first Super Admin:

```bash
php artisan jt:create-admin
```

It asks for a name, an email and a password (minimum twelve characters, mixed
case, with a number). The password is prompted rather than passed as an
argument so it does not land in your shell history. Two-factor enrolment
happens on first sign-in, so have an authenticator app open.

Finally, cache for production:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Re-run those three after any future change to `.env` or to the code.

---

## 8. Cron — two entries

cPanel → *Cron Jobs*. Both run every minute (`* * * * *`). Use the
`ea-php83` binary explicitly; the default `php` on a cPanel box is often an
older version.

**The scheduler**, which runs the audit-log retention purge:

```
* * * * * cd /home/USER/jewelry-trader && /usr/local/bin/ea-php83 artisan schedule:run >> /dev/null 2>&1
```

**The queue**, which calculates commission after each sale:

```
* * * * * cd /home/USER/jewelry-trader && /usr/local/bin/ea-php83 artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/queue.log 2>&1
```

`--stop-when-empty` and `--max-time=55` matter on shared hosting: they let the
worker finish and exit rather than becoming a long-running process, which most
cPanel hosts kill or bill for. If commission figures stop appearing after a
sale, this cron is the first thing to check.

---

## 9. If Terminal is not available

Everything above except sections 7 and 8 works through File Manager. For the
database setup, add a **temporary** cron job set to run once, five minutes
from now:

```
*/5 * * * * cd /home/USER/jewelry-trader && /usr/local/bin/ea-php83 artisan migrate --force --no-interaction && /usr/local/bin/ea-php83 artisan db:seed --force --no-interaction >> /home/USER/deploy.log 2>&1
```

Wait, read `/home/USER/deploy.log`, then **delete that cron job**. Repeat the
same way for `OpeningRateTableSeeder`.

`jt:create-admin` is interactive and cannot run from cron. Run it with
options and it will still prompt for the password, so if you have no Terminal
at all, ask the host to enable SSH for one session — it is a two-minute job
and you only need it once.

---

## 10. After it is live

**HTTPS.** cPanel → *SSL/TLS Status* → run AutoSSL. The application sets
secure cookies, so it will not log anyone in over plain HTTP.

**Stripe.** In the Stripe dashboard, add a webhook endpoint at
`https://example.com/webhooks/stripe` subscribed to `payment_intent.succeeded`
and `charge.refunded`. Then, in the admin panel at *Settings → Payments*,
enter the publishable key, the secret key and the webhook signing secret. Keep
test mode on until you have run a test sale end to end.

**The pricing stack.** *Settings → Pricing* holds the craftsman's formula
percentages and the rate tables, already loaded from the appraiser's opening
table. *Pricing Control* shows which layers are live. The live metals feed and
the AI reading service are both off until a provider and key are entered.

**Check these before handing it over:**

- [ ] `https://example.com` loads the storefront
- [ ] `https://example.com/admin` redirects to sign-in, and the admin account
      works through two-factor enrolment
- [ ] Visiting `https://example.com/.env` returns 404, not a file
- [ ] An error page shows a plain message, not a stack trace
- [ ] A test sale completes at the register and the stock moves to sold
- [ ] Commission appears against that sale within a minute — proves the queue
      cron is running
- [ ] *Settings → Pricing Control* prices its worked example

---

## 11. Deploying an update

```bash
cd ~/jewelry-trader
php artisan down

# upload the new release, or: git pull && composer install --no-dev --optimize-autoloader
# upload the locally built public/build

php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache

php artisan up
```

Back up the database before any update that includes a migration. cPanel →
*Backup* → *Download a MySQL Database Backup* takes a few seconds and is the
difference between a bad afternoon and a bad week.

---

## Troubleshooting

**500 error, blank page.** Read `storage/logs/laravel.log`. If it is empty,
the problem is before Laravel starts: almost always permissions on `storage/`
or a missing `APP_KEY`.

**"The stream or file could not be opened".** `storage/` is not writable —
redo section 6.

**Styles missing, pages unstyled.** `public/build` was not uploaded. It is
produced by `npm run build` locally and is not in the repository.

**Routes 404 except the home page.** `.htaccess` did not survive the upload —
File Manager hides dotfiles by default. Enable *Show Hidden Files* and confirm
`.htaccess` is in the document root.

**Login succeeds then bounces back.** Sessions cannot be written. Check the
`sessions` table exists and the database credentials in `.env` are correct.

**Commission never appears.** The queue cron in section 8 is not running, or
is using the wrong PHP binary.

**A setting saves but nothing changes.** Run `php artisan config:cache` again;
a stale config cache is the usual cause after an `.env` edit.
