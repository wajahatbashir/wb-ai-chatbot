# WB_AiChatbot — User Manual

A complete guide to installing, configuring and running the AI shopping assistant, for store
admins and anyone installing the module for the first time. For a short technical summary see
[README.md](README.md); this document goes screen by screen.

This same document is also readable inside the admin panel itself, with no need to open GitHub
or a file browser: **AI Chatbot > User Manual**.

## Contents

1. [What this module does](#1-what-this-module-does)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Quick start (first 10 minutes)](#4-quick-start-first-10-minutes)
5. [Admin menu overview](#5-admin-menu-overview)
6. [Configuration reference](#6-configuration-reference)
7. [AI Providers](#7-ai-providers)
8. [Knowledge Base](#8-knowledge-base)
9. [Q&A Pairs](#9-qa-pairs)
10. [Intent Dictionaries](#10-intent-dictionaries)
11. [Guidance Rules](#11-guidance-rules)
12. [The storefront widget (customer's point of view)](#12-the-storefront-widget-customers-point-of-view)
13. [Conversations](#13-conversations)
14. [Unanswered Questions](#14-unanswered-questions)
15. [Support Requests](#15-support-requests)
16. [Dashboard](#16-dashboard)
17. [Preview Chat](#17-preview-chat)
18. [CLI commands & cron jobs](#18-cli-commands--cron-jobs)
19. [Security & privacy](#19-security--privacy)
20. [Troubleshooting & FAQ](#20-troubleshooting--faq)
21. [Extending the module (for developers)](#21-extending-the-module-for-developers)
22. [Uninstalling](#22-uninstalling)

---

## 1. What this module does

WB_AiChatbot is a self-contained AI shopping assistant that sits in the corner of your
storefront. It:

- Answers product, shipping, payment and returns questions using content it builds itself from
  your catalog, categories and CMS pages.
- Recommends and compares products.
- Lets a customer or guest check an order, get a tracking number, download an invoice, or open
  a support ticket — from the chat, with email verification for guests.
- Runs on **your own** OpenAI / Anthropic / Google Gemini / xAI API key — no third-party SaaS,
  no license server. Only the provider API calls leave your server.

It has two engine modes, and switches between them automatically:

| | **AI mode** (a working key) | **Assist mode** (no key, key invalid/expired, quota used up, provider down) |
|---|---|---|
| Understands the question | The language model | A rule-based intent dictionary (editable in admin) |
| Answers policy/product questions | Model, grounded on your knowledge base | Best-matching knowledge base passage, shown as-is |
| Recommends products | Model reasons over search results | Keyword + price/availability filters parsed from the message |
| Orders/tracking/invoices/tickets | Model calls the same tools | Step-by-step guided flow (order number → email → code → answer) |
| Cost | Provider's usual per-token price | Free |

**The customer never sees an error.** If AI mode fails mid-conversation, the same request is
answered in Assist mode instead, and the engine keeps retrying AI on a backoff schedule.

## 2. Requirements

- Magento 2.4.x, PHP 8.1 / 8.2 / 8.3.
- MySQL (all storage — including embeddings — is plain MySQL; no external vector database).
- The `consumers_runner` cron (installed by Magento by default) running, so the
  `wbAichatbotKbIndex` message queue consumer processes knowledge-base updates.
- Optional, to use AI mode: an API key from OpenAI, Anthropic, Google AI Studio, or xAI.

## 3. Installation

```bash
# 1. Copy the module into app/code/WB/AiChatbot (or require it via Composer if you package it)
# 2. From the Magento root:
php bin/magento module:enable WB_AiChatbot
php -d memory_limit=2G bin/magento setup:upgrade
php -d memory_limit=2G bin/magento setup:di:compile
php bin/magento cache:flush
```

`setup:upgrade` creates all 20 module tables and seeds:
- a disabled **Mock** AI provider credential (safe placeholder, see [§7](#7-ai-providers)),
- the built-in **Intent Dictionaries** (see [§10](#10-intent-dictionaries)).

No manual database changes and no manual config edits are ever required — everything above is
everything a fresh install (or a staging/production deploy of this module) needs.

## 4. Quick start (first 10 minutes)

1. **Build the knowledge base.** Go to **AI Chatbot > Knowledge Base** and click **Sync Now > Everything**, or run `bin/magento wb:aichatbot:sync`. This reads your store information, CMS pages, categories and products and turns them into searchable documents. It works with no API key (keyword search) or with one (semantic search too) — see [§8](#8-knowledge-base).
2. **Add an API key** (optional but recommended). Go to **AI Chatbot > AI Providers > Add Provider Credential**, pick a provider, paste the key, save — the module checks the connection immediately. See [§7](#7-ai-providers).
3. **Enable the widget.** Go to **Stores > Configuration > WB > AI Chatbot > General**, set **Enable Chatbot** to *Yes*. Fill in **Assistant** and **Widget** to taste.
4. **Try it.** Use **AI Chatbot > Preview Chat** to test from the admin without opening the storefront, or just visit the site — the launcher appears in the corner you configured.

## 5. Admin menu overview

Everything lives under the top-level **AI Chatbot** menu (plus one section under **Stores >
Configuration**):

| Menu item | What it's for | ACL resource |
|---|---|---|
| Dashboard | Usage charts, cost, engine health at a glance | `WB_AiChatbot::dashboard` |
| Conversations | Every chat, with full transcripts | `WB_AiChatbot::conversations` |
| Knowledge Base | What the assistant knows, and where it comes from | `WB_AiChatbot::knowledge` |
| Q&A Pairs | Exact canned answers you write yourself | `WB_AiChatbot::qa` |
| Intent Dictionaries | Trigger words for Assist mode | `WB_AiChatbot::intents` |
| Guidance Rules | Extra instructions appended to the AI's system prompt | `WB_AiChatbot::guidance` |
| Unanswered Questions | Things nobody could answer, queued for follow-up | `WB_AiChatbot::unanswered` |
| Support Requests | Tickets opened from the chat | `WB_AiChatbot::requests` |
| AI Providers | Your API keys and their health | `WB_AiChatbot::credentials` |
| Preview Chat | Test the real engine from the admin | `WB_AiChatbot::preview` |
| Configuration | All settings in §6 | `WB_AiChatbot::config` |
| User Manual | This document, rendered for reading inside admin | `WB_AiChatbot::manual` |

Each is a separate ACL resource, so you can give a support agent access to **Conversations** and
**Support Requests** without giving them **AI Providers** or **Configuration**.

## 6. Configuration reference

**Stores > Configuration > WB > AI Chatbot.** Most fields are store-view scoped, so a store
selling in a different language/market can have its own assistant name, tone, questions and
excluded pages. Every field has an inline "?" comment in admin; the tables below summarise them.

### General

| Field | Purpose |
|---|---|
| Enable Chatbot | Master on/off switch for the storefront widget on this scope. |
| Engine Mode | *Auto* — use AI when a healthy key exists, fall back to Assist automatically. *Assist only* — never call an AI provider, useful to hard-cap spend. |
| Hide Widget Until Knowledge Base Is Synced | Keeps the launcher off the storefront until at least one document is indexed, so it never launches "empty". |
| Show To Customer Groups | Restrict the widget to specific customer groups. Empty = everyone, including guests. |

### Assistant

| Field | Purpose |
|---|---|
| Assistant Name | Shown in the widget header and used in its own replies. |
| Welcome Message | First thing shown when a chat starts. |
| Suggested Questions | One per line — the quick-reply chips on the Home screen and on "I don't know" fallbacks. |
| Preferred Language | Fallback locale code (e.g. `en`). AI mode still replies in whatever language the customer types. |
| Base Prompt | Top-level role framing. Leave empty for the built-in default — only touch this if you want a fundamentally different persona. |
| Instructions | Your business rules and standing context, e.g. *"We ship worldwide; COD is only available in Pakistan."* This is the field most stores should actually fill in. |
| Tone Of Voice | A short phrase, e.g. *"warm, concise, premium boutique"*. |
| Max Reply Tokens | Hard cap on how long a single AI reply can be. |
| Temperature | 0 = deterministic/literal, 1–2 = more creative. 0.3 is a sensible default for a shop assistant. |
| Max Messages Per Conversation | After this many customer messages, the assistant offers a support request instead of continuing indefinitely. 0 = unlimited. |
| Max Tool Calls Per Reply | Safety cap on how many tools (product search, order lookup, …) the model may chain in one turn. |

### Assist Mode (no API key)

| Field | Purpose |
|---|---|
| Show "AI unavailable" Notice | Shows a small banner once per conversation when running without AI. |
| Notice Text | The banner's wording. |
| Retry AI After (minutes) | After a provider call fails, how long before that provider is tried again (doubles on repeated failures, capped at 60 minutes). |
| Max Product Cards Per Reply | How many product cards Assist-mode search shows at once. |

### Widget

| Field | Purpose |
|---|---|
| Position | Bottom-right or bottom-left launcher. |
| Primary Color | Hex color driving the whole widget theme. |
| Launcher Button Label | Text next to the launcher icon; leave empty for an icon-only round button. |
| Window Title / Subtitle | Header text once the chat is open. |
| Assistant Avatar | Square image (80×80px+) shown in the header and as the bot's "avatar". |
| Invitation Message | A small popover next to the launcher after a delay, inviting the visitor to chat. Empty = disabled. |
| Invitation Delay (seconds) | How long to wait before showing it (once per browser session). |
| Mobile Bottom Offset (px) | Lifts the launcher above a sticky mobile bottom nav/cart bar. |
| Sound On Reply | Short beep when a reply arrives while the window is closed. |
| Allow Image Attachments | Lets visitors upload a photo (needs a vision-capable provider — see [§7](#7-ai-providers)). |
| Show "Talk To A Human" Button | Adds the option to the header menu and to fallback chip lists. |
| Open Links In | `_self` or `_blank` for product/CMS links the assistant sends. |
| Exclude On Pages | One per line: a full action name (`checkout_index_index`) or a `*`-wildcard URL pattern (`/customer/*`). |
| Custom CSS | Raw CSS injected into the widget's Shadow DOM for deeper styling than the color/position fields allow. |

### Limits & Security

| Field | Purpose |
|---|---|
| Messages Per Minute (per visitor) | Anti-spam rate limit on the chat endpoint. |
| Conversations Per Day (per IP) | Caps how many new conversations one IP can start per day. |
| Max Message Length | Characters allowed per message. |
| Verification Codes Per Hour (per email / per IP) | Rate limits on the one-time email verification code (see [§19](#19-security--privacy)). |
| Verification Code Lifetime (minutes) | How long a code stays valid. |
| Daily Token Budget | Once the day's AI token usage passes this, the engine serves Assist mode until midnight (UTC). 0 = no cap — useful if you want a hard spend ceiling regardless of what's in your provider account. |
| Conversation Retention (days) | Conversations older than this are purged by the nightly retention cron. 0 = keep forever. |
| Redact Emails/Phones In Stored Transcripts | Masks emails/phone numbers inside saved message text (verification codes and order lookups still work; only the *stored copy* is redacted). |

### Notifications

| Field | Purpose |
|---|---|
| Admin Notification Email | Receives new support requests and provider-failure alerts. Falls back to the store's Contact Us recipient if empty. |
| Email When AI Falls Back To Assist Mode | Alert email when a provider stops working. |
| Email On New Support Request | Alert email for every new ticket. |
| Verification Code / Request Confirmation / Request Admin / Request Reply / Provider Failure Email | Each is a normal Magento **email template** picker. To change wording: **Marketing > Communications > Email Templates > Add New Template**, load the matching default template (e.g. "AI Chatbot - Verification Code"), edit it, save, then select your copy here. This is exactly how you'd customize any Magento transactional email — nothing chatbot-specific. |

### Developer

| Field | Purpose |
|---|---|
| Debug Logging | Writes prompts, tool calls and provider responses to `var/log/wb_aichatbot.log`. Turn off in production. |
| Allow Mock Provider | Lets the key-less **Mock** provider (see [§7](#7-ai-providers)) actually run. Keep this **off** on staging/production — it exists only for development/testing without spending real API credits. |

## 7. AI Providers

**AI Chatbot > AI Providers.** Each row is one API key ("credential"). You can add several —
they form a fallback chain, tried in **Fallback Order** (lowest number first): if the top one
fails, the next is tried automatically, for both chat and, separately, for embeddings.

**Add Provider Credential** fields:

| Field | Notes |
|---|---|
| Alias | Your own label, e.g. "Production OpenAI key". |
| Provider | OpenAI, Anthropic, Google Gemini, xAI, or Mock. |
| API Key | Stored encrypted (Magento's own encryption key); never shown again once saved, and never sent to the browser. |
| Chat Model | Leave empty to use the provider's recommended default (shown as a hint under the field). |
| Embedding Model | Leave empty for the default. Anthropic and xAI have no embedding models of their own — if that's your only provider, retrieval falls back to keyword search unless another credential supplies embeddings. |
| Supports Image Input | Whether this credential's model can read photos the customer attaches (only matters if "Allow Image Attachments" is on). |
| Fallback Order | Lower = tried first. |
| Enabled | Disabled credentials are skipped entirely. |

Saving **immediately runs a connection check** and shows the result. The grid's **Status**
column shows one of: OK, Unchecked, Empty key, Invalid key, Insufficient quota, Rate limited,
Model unavailable, Transient error. Use the row action **Check connection** (or **Check All
Connections** at the top of the grid) to re-test at any time.

**A broken key never breaks the chatbot** — the pool skips it and falls through to the next
credential, or to Assist mode if none are usable. Invalid-key/empty-key/model-unavailable
statuses are "permanent" (won't be retried until you re-save the credential); rate-limit/quota/
transient errors are retried automatically on the backoff schedule from **Assist Mode > Retry AI
After**.

**Mock provider.** A harmless, key-less credential seeded automatically, disabled by default and
gated behind **Developer > Allow Mock Provider**. It answers deterministically without any real
API call — useful for testing the whole engine (including tool calls, via typing
`/tool product_search {"query":"..."}` style messages) before you have a paid key, or in CI.
**Never enable it on a live store** — customers would get placeholder, non-answers from it.

## 8. Knowledge Base

**AI Chatbot > Knowledge Base.** This is what the assistant answers from — a set of "documents"
built automatically from your store, plus anything you write by hand.

**Sources**, one document per store view so prices/currency/availability are always right for
the store the customer is on:

| Source | What becomes a document |
|---|---|
| Store Information | Name, contact details, address, shipping origin, active payment/shipping methods, currency — read from **Stores > Configuration > General > Store Information / Shipping Settings / Payment/Shipping Methods**. |
| CMS Pages | Every active page (About Us, Shipping Policy, FAQs, Terms, …), rendered through Magento's own template engine first so `{{block}}`/`{{widget}}` directives resolve to real text. |
| Categories | Name, description, breadcrumb path, live product count, URL. |
| Products | Enabled + visible products: name, SKU, price (with sale price), availability, categories, color/size/variants for configurables, description, URL. |

**Building/updating it:**
- **Sync Now** (top of the grid) — a split button: *Store Information*, *CMS Pages* and
  *Categories* run immediately; *Products* and *Everything* are queued to the
  `wbAichatbotKbIndex` message consumer so the request returns instantly even on a large catalog.
- Saving or deleting a product, category or CMS page automatically marks its document "dirty"
  and queues a re-sync — you don't need to click anything for day-to-day edits.
- A nightly cron does a full sync + index (02:30 server time) as a safety net, plus a catch-up
  indexing pass every 15 minutes.
- CLI: `bin/magento wb:aichatbot:sync` (see [§18](#18-cli-commands--cron-jobs) for all flags).

**Embeddings (semantic search).** If a credential with an embedding model is enabled (see
[§7](#7-ai-providers)), documents are also embedded for meaning-based search, blended with
keyword search. **Without one, retrieval still works** — pure keyword/full-text search — the
knowledge base is never a hard dependency on having a paid key. The status cards at the top of
the grid show how many chunks are embedded and with which model.

**Manual articles.** Click **Add Article** for anything the automatic sources don't cover (a
sizing guide, a seasonal FAQ, a policy that isn't a CMS page). Pick a store view (or "All Store
Views"), write the content in plain language — it's chunked and indexed on save.

**The grid** shows every document: source, store view, index status (Indexed / Pending / Changed
/ Failed), enabled flag, chunk/embedded counts. Row actions: **View/Edit**, **Re-sync & Index**
(re-pulls a synced document from its source right now, or re-embeds a manual one), **Delete**.
Disabling a document (instead of deleting) is the right way to permanently exclude a synced
document from answers — a delete on a synced document just comes back on the next sync.

**Retrieval Tester.** Type any customer question, pick a store view, and see exactly which
passages would be retrieved and their score — the same thing the engine uses, so this is the
fastest way to check "why did/didn't it answer X?" before touching anything else.

## 9. Q&A Pairs

**AI Chatbot > Q&A Pairs.** Exact answers you write yourself, matched **before any AI call, in
both engine modes** — cheap, instant, and 100% predictable. Use this for anything you want
worded exactly one way (a specific promise about delivery times, a legal disclaimer, a
frequently-misunderstood policy).

| Field | Notes |
|---|---|
| Question | The canonical phrasing. |
| Alternative Phrasings | One per line — other ways a customer might ask the same thing. |
| Answer | Markdown supported. |
| Read More URL | Optional link card shown with the answer. |
| Store Views | Leave empty for all stores. |
| Enabled | |

A Q&A pair fires when the customer's message is a close match to the question or one of its
alternatives (fuzzy, not exact-string). The grid's **Times used** figure shows how often each
one has fired.

## 10. Intent Dictionaries

**AI Chatbot > Intent Dictionaries.** This is what powers **Assist mode's** understanding of a
message — it has nothing to do with AI mode (the model understands language on its own) and
nothing to do with email templates.

Each row is one **trigger dictionary**: an intent (Order status, Shipping & delivery, Product
search, Greeting, …), a language, a priority, and a list of trigger phrases/regex patterns (one
per line). The module ships with a built-in English dictionary for all 15 intents (seeded on
install); add a row for another language, or add more phrases to an existing English intent, to
tune what Assist mode recognizes — no code required.

| Built-in intent | Typical trigger examples |
|---|---|
| Order status / tracking / invoice | "where is my order", "track my order", "invoice" |
| Support request status | "SR-A1B2C3", "my ticket" |
| Talk to a human | "talk to a human", "customer support", "complaint" |
| Cart | "my cart", "what's in my bag" |
| Product comparison | "compare", "X vs Y" |
| Shipping & delivery / Returns & exchanges / Payment | "delivery charges", "return policy", "cash on delivery" |
| Store information | "opening hours", "your address" |
| Product search | "show me", "do you have", "under [price]", "ready to ship" |
| Greeting / Thanks / Cancel | "hi", "thanks", "cancel" / "start over" |

A higher **Priority** wins when a message matches more than one intent's phrases.

## 11. Guidance Rules

**AI Chatbot > Guidance Rules.** Extra instructions appended to the **AI mode** system prompt
for specific situations — a lighter-weight alternative to editing the global **Instructions**
field in Configuration when the rule only applies sometimes.

| Field | Notes |
|---|---|
| Name | Internal label, e.g. "Bridal consultations". |
| Trigger (when this applies) | Plain-language description of the situation, e.g. "the customer asks about bridal or wedding sets". |
| Instructions | What the assistant should do/say/avoid in that situation. |
| Status | *Enabled*, *Disabled*, or *Testing* (only active in **Preview Chat**, not on the live storefront — safe way to try wording changes). |
| Sort Order | Rules are appended to the prompt in this order. |

Guidance rules only affect AI mode (Assist mode doesn't read the system prompt at all — use
Q&A Pairs or Intent Dictionaries to influence Assist-mode behavior instead).

## 12. The storefront widget (customer's point of view)

- A round **launcher** appears at the configured corner. An **invitation popover** may appear
  after the configured delay (once per browser session).
- Opening it shows the **Home** view: welcome message + suggested-question chips, or "Continue
  the conversation" if one is already open.
- The **Chat** view is a normal message thread: Markdown-formatted replies, product cards (image,
  price, sale price, availability badge, **View**/**Add to cart**), order/invoice/link cards,
  comparison tables, quick-reply chips, a typing indicator, and 👍/👎 on each assistant message.
- **Verification code / when does the customer receive an email:** whenever the assistant needs
  to look up an order or support request for someone who **isn't logged in** (or is asking about
  an order that isn't their own), it asks for the email on the order, emails a 6-digit code
  (valid for the configured lifetime, 5 attempts, rate-limited per email/IP — see
  [§6](#6-configuration-reference) *Limits & Security*), and continues once the customer types
  it. A logged-in customer looking up their **own** orders is never asked for this. Once an
  email is verified it's remembered for the rest of that conversation — no need to re-verify for
  a second order lookup in the same chat.
- **Image attachments:** if enabled and a vision-capable provider is configured (see
  [§7](#7-ai-providers)), the attach button appears and photos are sent straight to the model
  along with the text — the model can describe what it sees and search for matching products
  in the same reply. It only appears when both conditions are met; there's no separate
  "image search" toggle to configure.
- **"Talk to a human"** (header menu, or triggered by asking) collects an email and a
  description and creates a Support Request — see [§15](#15-support-requests).
- **End-of-chat rating** (1–5 stars + optional comment) is available from the header menu.
- The widget renders inside a **Shadow DOM**, so your theme's CSS cannot break it and it cannot
  break your theme; it boots after the page is idle so it never slows down page load, and its
  static markup is fully page-cache-safe (visitor-specific data is fetched separately after the
  page loads).

## 13. Conversations

**AI Chatbot > Conversations.** Every chat, with visitor/customer, store, language, page count,
message count, tokens, cost, mode (AI/Assist), status (Open/Closed/Escalated), rating and start
time. Open a row for the full transcript, including tool calls, sources cited, and whether each
reply was "grounded" (backed by a source or tool result, vs. a plain fallback). Mass actions:
enable/disable and delete. **Preview Chat** conversations (see [§17](#17-preview-chat)) are
flagged separately and never appear mixed in with real customer chats.

## 14. Unanswered Questions

**AI Chatbot > Unanswered Questions.** Anything the assistant couldn't confidently answer lands
here with the question text and its best retrieval score, so you can see exactly what your
knowledge base is missing. The **Convert to Q&A** action pre-fills a new Q&A Pair with that
question — write the answer once and it's covered for everyone from then on.

## 15. Support Requests

**AI Chatbot > Support Requests.** Tickets created from the chat (via "Talk to a human", or
automatically when the assistant can't help). Each gets a short public code (`SR-XXXXXX`) the
customer can use to check status later, from the chat itself.

- Creating one emails the customer a confirmation and (if enabled in Notifications) emails your
  admin address.
- Opening a request in admin lets you set its **Status** (New/In progress/Resolved/Closed) and
  write an **Admin Reply** — checking "send email" emails that reply straight to the customer
  using the *Support Request Reply* template.
- A customer can ask the chat "what's the status of SR-A1B2C3" and, after email verification,
  get the current status and your reply read back to them.

## 16. Dashboard

**AI Chatbot > Dashboard.** At a glance:

- Chats today / 7 days, active conversations right now, % answered without human help, average
  rating, and a "needs attention" count (open support requests + unanswered questions).
- Conversations-per-day and tokens-per-day charts (14 days).
- Usage and cost by provider (14 days) — including a separate "Assist mode (no AI)" row so you
  can see how much traffic is running for free.
- An **Engine** panel: whether the storefront widget is enabled, the configured mode, which
  credential is answering right now, and a live knowledge base summary (documents, pending,
  chunks, embedded) — with quick links to Conversations, Support Requests, Unanswered, AI
  Providers and Preview Chat.

## 17. Preview Chat

**AI Chatbot > Preview Chat.** Runs the **real** engine — real knowledge base, real tools, real
provider (or Assist mode if that's what's active) — from inside the admin, with a store-view
switcher and an "under the hood" panel showing engine mode, model, tokens, cost, latency, tool
calls and the exact sources used for the last reply. This is the fastest way to test a config
change, a new Q&A pair, or a Guidance Rule (including *Testing*-status ones, which only run
here) without leaving admin or affecting real customer data — Preview conversations are flagged
`is_preview` and excluded from the Dashboard and Conversations grid's real-traffic figures.

Yes — from Preview Chat an admin can search products, look up any order (their own admin access
doesn't skip the same verification-code flow a guest would go through — it exercises the real
flow end to end), request an invoice link, and open/check a support request, exactly like a
customer could.

## 18. CLI commands & cron jobs

```bash
# Engine/provider health
bin/magento wb:aichatbot:status [--check]

# Knowledge base sync
bin/magento wb:aichatbot:sync [--type=product,cms_page,category,store_info] [--store=1,2]
                              [--dry-run] [--limit=N] [--no-index] [--reembed] [--verbose-docs]

# Test retrieval from the CLI (same engine the chat uses)
bin/magento wb:aichatbot:search "do you ship to canada" [--store=1] [--limit=6] [--type=] [--keyword-only]

# Process the knowledge-base queue manually (normally handled by consumers_runner cron)
bin/magento queue:consumers:start wbAichatbotKbIndex
```

| Cron job | Schedule | Does |
|---|---|---|
| `wb_aichatbot_kb_sync` | 02:30 daily | Full knowledge base sync + index everything pending. |
| `wb_aichatbot_kb_index_pending` | Every 15 min | Catch-up indexing for anything the queue consumer missed. |
| `wb_aichatbot_retention` | 03:10 daily | Purges conversations older than *Conversation Retention (days)*, spent verification codes, and stale rate-limit counters. |

All three require Magento's own cron (`* * * * * php bin/magento cron:run`) to be scheduled, as
on any Magento install.

## 19. Security & privacy

- API keys are stored **encrypted** using Magento's own encryption key and are never sent to the
  browser or shown again in the admin form once saved.
- Guest order/ticket lookups require a **one-time 6-digit code emailed to the address on the
  order** — rate-limited per email and per IP, single-use, and expiring after the configured
  lifetime. A logged-in customer never needs this for their own orders. The assistant never
  confirms or denies that an order exists for an email that doesn't match.
- Invoice PDF links are **signed and expire after 30 minutes** — a customer can download their
  own invoice without logging in, but the link cannot be reused indefinitely or guessed.
- All chat endpoints are protected by Magento's storefront **form key**; requests without a
  valid one are rejected.
- Per-visitor and per-IP **rate limits** apply to messages, new conversations, and verification
  code requests (all configurable, see [§6](#6-configuration-reference)).
- Optional **PII redaction** masks emails/phone numbers in stored transcripts.
- A **retention cron** can auto-delete old conversations after N days.
- Nothing leaves your server except the API call to whichever AI provider you configured — there
  is no external analytics, telemetry, or license check anywhere in this module.

## 20. Troubleshooting & FAQ

**The launcher isn't showing on the storefront.**
Check, in order: *General > Enable Chatbot* is Yes on that store view; the current page isn't
listed in *Widget > Exclude On Pages*; *Hide Widget Until Knowledge Base Is Synced* isn't
waiting on a sync that hasn't run yet; the customer's group isn't excluded by *Show To Customer
Groups*.

**I added an API key — do I need to change anything else for AI replies to start working
instead of Mock/Assist replies?**
No extra step. As soon as a credential passes its connection check and is enabled, the provider
pool picks it up on the very next chat message (*Engine Mode* just needs to be *Auto*, which is
the default — not *Assist only*). If the knowledge base was only keyword-indexed because no
embedding provider existed before, the next sync/index run automatically upgrades it to
semantic search too, with no manual re-sync required.

**AI mode isn't answering — it's stuck on Assist mode / Mock.**
Check **AI Providers**: is at least one credential *Enabled* with **Status = OK**? Use **Check
connection**. If it shows *Invalid key*, *Empty key* or *Model unavailable*, that credential is
skipped until you fix and re-save it. Also check *Engine Mode* isn't set to *Assist only*, and
that the *Daily Token Budget* (if set) hasn't been reached for the day.

**Where do Q&A / Intent / Guidance "content" actually get changed?**
These are three different things, easy to conflate at a glance:
- **Q&A Pairs** ([§9](#9-qa-pairs)) — the exact wording of an answer to a specific question.
- **Intent Dictionaries** ([§10](#10-intent-dictionaries)) — only the *trigger phrases* Assist
  mode listens for (e.g. what counts as a "Greeting"); the Greeting reply itself comes from
  *Assistant > Welcome Message* in Configuration, and the Thanks/Cancel replies are short fixed
  strings in the engine.
- **Guidance Rules** ([§11](#11-guidance-rules)) — extra instructions for the AI model, not
  fixed text.
- **Email wording** ([§6](#6-configuration-reference) *Notifications*) — copy the relevant
  default template under **Marketing > Communications > Email Templates**, edit your copy, and
  select it in Configuration. This is standard Magento email template editing, unrelated to the
  three points above.

**Does uploading a photo actually search for similar products?**
The image is sent to the AI model along with the text, and the model can describe it and call
the product search tool in the same reply — this needs *Allow Image Attachments* on and a
vision-capable credential configured (*Supports Image Input = Yes* on that credential). Assist
mode has no image understanding at all — the attach button only appears when a vision-capable
AI provider is actually active.

**Knowledge base sync ran but the widget still gives generic/no answers.**
Use the **Retrieval Tester** ([§8](#8-knowledge-base)) with the exact question — it shows what
would actually be retrieved and its score. Common causes: the relevant CMS page is inactive or
not assigned to that store view; the product isn't visible/enabled for that store; the question
uses very different wording than the source content (add a Q&A Pair for that exact phrasing).

**Emails aren't arriving (verification codes, support requests).**
Confirm outbound mail works at the server level (Magento's own transactional emails, e.g. order
confirmations, must also be working) and that *Notifications > Admin Notification Email* /
`trans_email/ident_general/email` are set correctly.

## 21. Extending the module (for developers)

- **Add a knowledge base source:** implement `WB\AiChatbot\Model\Kb\Sync\SyncProviderInterface`
  and register it in your own module's `di.xml` under
  `WB\AiChatbot\Model\Kb\Sync\ProviderPool` → `providers`.
- **Add a chat tool** (a new capability the assistant can call, in both AI mode via function
  calling and Assist mode via guided flows): implement
  `WB\AiChatbot\Model\Chat\Tool\ToolInterface` and register it under
  `WB\AiChatbot\Model\Chat\Tool\ToolRegistry` → `tools`.
- **Add an AI provider:** implement `WB\AiChatbot\Api\ProviderInterface` and register it under
  `WB\AiChatbot\Model\Provider\Pool` → `providers`.

All three follow the same pattern as the module's own built-ins, so the existing classes under
`Model/Kb/Sync/Provider/`, `Model/Chat/Tool/`, and `Model/Provider/` are the best reference.

## 22. Uninstalling

```bash
php bin/magento module:disable WB_AiChatbot
# to remove the database tables and data as well:
php bin/magento module:uninstall WB_AiChatbot
```

Uninstalling removes the module's own tables (conversations, knowledge base, credentials, Q&A,
etc.). It does not touch any core Magento data (products, categories, orders, CMS pages).
