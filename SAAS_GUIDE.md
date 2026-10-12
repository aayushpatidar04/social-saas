# Multi-tenant (SaaS) edition

This version turns the project into a platform you can give to many organizations.
Integration credentials live in the **database** (encrypted), not in `.env`, and there is a
**super admin** area to create and manage organizations.

## How it fits together

| Who | Where | Can do |
|---|---|---|
| **Super admin** (platform owner, belongs to no organization) | `/admin` | Create / edit / suspend / archive organizations, set plan + limits, manage the platform-wide apps (Admin → Integrations), invite an organization's admins, "View as" a user for support |
| **Organization admin** | Settings → Team / Organization / Integrations | Invite and manage their team, connect accounts, (optionally) register their *own* apps |
| **Manager** | Accounts | Connect / disconnect accounts |
| **Team member / viewer** | App | Work with comments; read-only on accounts |

**Credentials.** One row per provider, either *platform-wide* (managed by you) or *owned by one
organization*. An organization uses its own app only if you ticked **"May use its own apps"** for it;
otherwise it uses yours. Providers: Meta (Facebook + Instagram), YouTube, X, LinkedIn, OpenAI, Ollama
(Ollama is infrastructure and is platform-only).

**Isolation.** Every tenant table carries `organization_id`, and a global scope limits all queries of a
signed-in user to their organization even if a controller forgets to filter. A given Facebook Page /
Instagram account / channel can belong to **one organization only**.

---

## Connecting Meta accounts

### Facebook (many Pages, many Facebook logins)
1. *Accounts → + Facebook* → Facebook login.
2. A **picker** lists every Page the user manages, and the Instagram account linked to each.
   Tick what to connect (nothing is connected automatically any more).
3. Repeat with another Facebook login to bring in more Pages. Each Page/Instagram account is a separate row.

The token is exchanged for a **long-lived** user token first, so the Page tokens do not expire
(previously they were short-lived and stopped working after about an hour).

### Instagram directly (no Facebook Page needed)
*Accounts → + Instagram* uses **Instagram API with Instagram Login**. The user logs in with the
Instagram Business/Creator account itself. The token lasts 60 days and is **refreshed automatically**
by the daily `meta:refresh-tokens` job; if it ever lapses the account shows **Re-authorize**.
Personal Instagram accounts are rejected with an explanation.

### Meta app dashboard settings
Use the exact URLs shown in *Admin → Integrations → Meta* (they are generated from `APP_URL`).

| Setting | Value |
|---|---|
| Facebook Login → Valid OAuth Redirect URIs | `{APP_URL}/auth/facebook/callback` |
| Instagram → API setup with Instagram login → Business login settings → Redirect URL | `{APP_URL}/auth/instagram/callback` |
| Webhooks → callback URL (both the *Page* and *Instagram* objects) | `{APP_URL}/webhooks/meta/{key}` — shown after you first save the Meta credentials |
| Webhooks → verify token | the "Webhook verify token" field (auto-generated if left blank) |
| Webhook fields | Page → `feed`; Instagram → `comments` |

* **Instagram App ID / secret are different from the Facebook App ID / secret.** They are on the
  Instagram → API setup with Instagram login screen. Fill each pair in separately; you may fill only
  one pair (e.g. Instagram only).
* While the Meta app is in *development mode* only users with a role on the app can connect.
  Going live needs Meta App Review for the permissions in use.
* Webhooks are signed by Meta; the platform **rejects** unsigned or wrongly signed events.
* An organization with its own Meta app gets its own webhook URL/key (shown in its Integrations page).

---

## Upgrading an existing installation

> **Back up the database first.** The migrations change tables and encrypt stored tokens.

```bash
# 0. PHP 8.4+ is required (composer.lock needs it)
git pull / upload the new code
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan config:clear
php artisan migrate --force          # tenant columns, encrypted tokens, new tables
php artisan saas:import-env          # copies Facebook/YouTube/X/LinkedIn/OpenAI/Ollama values from .env into the DB
php artisan saas:create-superadmin you@yourcompany.com   # prints a generated password
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Then:
1. Sign in at `/login` → you land on `/admin`.
2. *Admin → Integrations*: press **Test** on Meta (and the others), fill in the **Instagram** pair if you
   want direct Instagram login, and register the URLs above in the Meta dashboard.
3. Your existing data keeps working: the existing organization becomes the first organization, existing
   Instagram accounts stay linked to their Page, tokens are encrypted in place.
   Give that organization's admin(s) a plan/limits under *Organizations*.
4. Once everything works, **delete the integration values from `.env`** and rotate any secret that was
   ever shared (the uploaded project contained a live `.env` and `.git`).
5. Keep the queue worker and the scheduler (`schedule:run` every minute) running — token refresh and sync
   depend on it (see `DEPLOYMENT_GUIDE.md`).

Behaviour changes to be aware of
* Public **registration is closed**; people join by invitation (super admin → organization admin → team).
* The organization **plan can no longer be changed by the organization** itself.
* Facebook sync no longer silently creates Instagram accounts; they are connected explicitly.
* `db:seed` creates demo users only in `local`/`testing` environments.
* Archiving an organization blocks its users and stops syncing/webhooks but keeps the data.

---

## Bugs found in the original code and fixed on the way
* Tokens were stored in **plain text** (`->encrypted()` is not a Laravel column modifier). Now encrypted.
* `social_posts`, `social_comments`, `ai_conversations`, `knowledge_chunks` had no `organization_id`
  although controllers and the RAG service queried it (those queries could only fail; the RAG service swallows the error, so knowledge-base retrieval would return nothing).
* Facebook login/callback routes were reachable **without authentication**.
* Meta webhook accepted **unsigned** payloads; the Instagram webhook made **one Graph call per Facebook
  account** to find the owner.
* YouTube / LinkedIn webhooks were blocked by CSRF (419); only `webhooks/meta` was exempt.
* `env()` was called outside config files (returns `null` after `config:cache`).
* Flash messages were never displayed; unknown URLs returned HTTP 200; the login form was pre-filled
  with `admin@example.com / password`.
* `password_reset_tokens` and `sessions` tables were never created (forgot-password returned 500).
* Dashboard crashed without Pusher keys. Registration created users with no organization.
* X and LinkedIn "Connect" called methods that did not exist (see below).

## Known limits — please read
* **X (Twitter) and LinkedIn:** their connect flows and reply methods were incomplete in the uploaded code
  (calls to missing methods). I implemented them from the providers' documentation and moved them to
  database credentials, but **they could not be tested against the live APIs here.** Test with a real
  account before relying on them. Facebook, Instagram (both modes), webhooks, tokens and the multi-tenant
  logic are covered by automated tests against faked Meta responses, not against live Meta.
* Graph API calls that list posts/comments still fetch a single page of results (unchanged behaviour).
* Ollama is platform-wide; an organization cannot bring its own.
* One external account ↔ one organization. Moving an account to another organization means removing it
  from the first one.

## Tests
```bash
php vendor/bin/phpunit      # 110 tests: isolation, superadmin, credentials, both Meta flows, webhooks, tokens, team
```
