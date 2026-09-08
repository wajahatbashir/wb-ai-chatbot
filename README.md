# WB_AiChatbot

**Version:** 1.0.0
**Compatibility:** Magento 2.4.x · PHP 8.1 / 8.2 / 8.3

A self-contained AI shopping assistant for Magento 2: answers product and policy questions,
recommends and compares products, and lets customers and guests check orders, download
invoices and open support requests from the chat - with secure email verification.

📖 **[USER_MANUAL.md](USER_MANUAL.md)** - full admin guide: every configuration field, every
admin screen, the storefront widget from the customer's side, troubleshooting/FAQ, and how to
extend the module. This README stays a short technical summary. The same manual is also
readable inside the admin panel itself (**AI Chatbot > User Manual** - no GitHub/file access
needed).

Two engine modes, switched automatically:

- **AI mode** - a configured OpenAI / Anthropic / Google Gemini / xAI key answers in natural
  language, grounded on the knowledge base built from your own catalog and CMS pages.
- **Assist mode** - no key, invalid or expired key, quota exhausted, provider down: the same
  widget keeps working with rule-based intents, admin Q&A pairs, product search, and guided
  order / tracking / invoice / support-request flows. The customer never sees an error.

Nothing except the provider API calls leaves your server. No SaaS, no license server.

## Setup

1. `bin/magento module:enable WB_AiChatbot && bin/magento setup:upgrade && bin/magento setup:di:compile`
2. **AI Chatbot > AI Providers > Add Provider Credential** - paste an OpenAI (`sk-...`),
   Anthropic (`sk-ant-...`), Google AI Studio or xAI key. Saving runs a connection check
   immediately; the grid shows the health of every key. Add several keys to build a fallback
   chain (lower *Order* is tried first).
3. **AI Chatbot > Configuration** (Stores > Configuration > WB > AI Chatbot) - enable the
   widget, set the assistant name, welcome message, suggested questions, colors and limits.
4. Without any key the chatbot already works in Assist mode.

The **Mock** provider (seeded, inert unless *Developer > Allow Mock Provider* is on) answers
without any API and exists for development and automated tests only.

## Knowledge base

**AI Chatbot > Knowledge Base** is what the assistant answers from. It is built automatically
from four sources - store information, CMS pages, categories and products (one document per
store view, so prices, currency and URLs are right for each store) - and you can add your own
articles (**Add Article**) for anything the site does not spell out. Every document is split into
chunks; when an embedding-capable provider is usable the chunks are embedded for semantic
search, otherwise fulltext keyword search is used - **the knowledge base works without a key**.

- First build: `bin/magento wb:aichatbot:sync` (about 2 minutes per 4,000 products), or
  **Sync Now** in the grid (small sources run immediately, products run in the background
  through the `wbAichatbotKbIndex` message-queue consumer).
- Kept current automatically: saving/deleting a product, category or CMS page queues a re-sync
  of that document; cron runs a full sync nightly (02:30) and a catch-up index every 15 minutes.
- Disable a document in the grid to keep it out of answers (survives syncs); delete synced
  documents only if you also disable the source, otherwise they return.
- **Retrieval Tester** shows exactly which passages a customer question retrieves and why.
- Other modules can add sources: implement `Model\Kb\Sync\SyncProviderInterface` and register
  it in `di.xml` under `WB\AiChatbot\Model\Kb\Sync\ProviderPool` → `providers`.

## How a message is answered

1. Limits: message length, messages per minute per visitor, new conversations per day per IP.
2. **Q&A pairs** (AI Chatbot > Q&A Pairs) are matched first - deterministic and free.
3. A guided flow in progress (order lookup, support request) consumes the message; a typed
   6-digit code is verified.
4. **AI mode** (a usable key): the question is answered by the model, grounded on the top
   knowledge base passages, calling tools when it needs live data. **Assist mode** (no usable
   key, quota exhausted, provider down, or *Engine mode = Assist*): rule-based intents pick a
   verbatim knowledge base passage, a product search, a comparison, or a guided flow.
5. Everything is stored (conversation, messages, cards, tool calls, tokens, cost) for the admin
   history and dashboard; questions nobody could answer land in *Unanswered Questions*.

Self-service tools: `product_search`, `product_compare`, `cart_info`, `order_info`,
`order_tracking`, `order_invoice` (signed 30-minute PDF link), `send_verification_code`,
`verify_code`, `request_submit`, `request_info`. Guests must verify the order email with a
one-time code; logged-in customers see their own orders directly. Add your own tool by
implementing `Model\Chat\Tool\ToolInterface` and registering it in `di.xml` under `ToolRegistry`.

**AI Chatbot > Preview Chat** runs the real engine from the admin with an "under the hood" panel.

## Storefront widget

Enable it in Configuration > General. The page only carries static settings (full-page cache
stays effective); the widget mounts in a Shadow DOM so theme CSS and the chat never interfere,
and loads the visitor's conversation from `/aichatbot/chat/bootstrap`. Launcher, invitation
popover, Home tab with popular questions, chat with product cards (View / Add to cart), order,
link and file cards, quick replies, 👍/👎, star rating, image attachments (vision providers),
full-screen on mobile. Excluded pages: full action names or URL patterns (`/checkout/*`) under
Configuration > Widget; customer groups under General.

## Admin

| Screen | What it does |
|---|---|
| Dashboard | chats, AI share, ratings, open requests, 14-day charts, cost by provider, most asked |
| Conversations | grid + full transcript with mode, model, tokens, tool calls, sources, feedback |
| Knowledge Base | synced documents, manual articles, Sync Now, Retrieval Tester |
| Q&A Pairs | curated answers matched before any AI call (both modes) |
| Intent Dictionaries | trigger words / regex per intent and language for Assist mode |
| Guidance Rules | situation-specific instructions appended to the AI system prompt |
| Unanswered Questions | what nobody could answer, one click to turn into a Q&A pair |
| Support Requests | tickets created from the chat; reply by email from the form |
| AI Providers | keys (encrypted), models, fallback order, health check |
| Preview Chat | the real engine inside the admin, per store view, with debug info |

## Security & limits

Keys are encrypted and never sent to the browser. POST endpoints require the storefront form key.
Per-visitor messages/minute, per-IP conversations/day, verification-code limits per email and
IP, max message length, optional daily token budget (Assist mode when reached), optional PII
redaction of stored transcripts, retention purge (cron, 03:10), provider-failure email (max one
per credential per day). Prompt-injection phrasing is stripped from knowledge base excerpts.
For reCAPTCHA, add a plugin on `Controller\Chat\Send::execute` that validates the token the
widget sends - the module deliberately has no hard dependency on a captcha provider.

## Deployment

Deploy the code and run the standard sequence - nothing manual in the database or config:

```
bin/magento module:enable WB_AiChatbot
bin/magento setup:upgrade          # tables, data patches (mock credential, intent dictionaries)
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f   # production mode
bin/magento cache:flush
bin/magento wb:aichatbot:sync      # first knowledge base build (or wait for the nightly cron)
```

Then add a key under AI Chatbot > AI Providers (optional) and enable the widget under
Configuration > General. Cron must be running (consumers, nightly sync, retention).

## Tests

```
php phpunit.phar --bootstrap <autoload> app/code/WB/AiChatbot/Test/Unit
```

## CLI

```
bin/magento wb:aichatbot:status [--check]                 # engine mode + every credential's health
bin/magento wb:aichatbot:sync [--type=product,cms_page,category,store_info] [--store=1,2]
                              [--dry-run] [--limit=N] [--no-index] [--reembed] [--verbose-docs]
bin/magento wb:aichatbot:search "do you ship to canada" [--store=1] [--limit=6] [--type=] [--keyword-only]
bin/magento queue:consumers:start wbAichatbotKbIndex      # normally started by cron's consumers_runner
```

## Layout

```
Api/                    ProviderInterface, CredentialInterface, CredentialRepositoryInterface
Model/Provider/         OpenAi, Anthropic, Gemini, Xai, Mock, Pool (fallback chain), HealthChecker
Model/Chat/             Engine, Orchestrator (AI), AssistEngine + IntentRouter + GuidedFlow (no key), PromptBuilder,
                        ConversationManager, Verification, OrderLookup, RequestManager, ProductFinder, Guard, RateLimiter
Model/Chat/Tool/        ToolInterface, ToolRegistry and the built-in tools
Controller/Chat/        storefront JSON API (bootstrap, send, feedback, rate, reset); Controller/Order/Invoice signed PDF
Model/Kb/               Synchronizer, Indexer, Chunker, ChunkStorage, Embedder, Retriever, DocumentReindexer
Model/Kb/Sync/          SyncProviderInterface, ProviderPool, TextCleaner, Provider/{StoreInfo,CmsPage,Category,Product}
Model/Kb/Queue/         Publisher, Consumer (topic wb.aichatbot.kb.index, MysqlMq "db" connection)
Observer/EntityChanged  product / category / CMS page save+delete -> dirty + queued re-sync
Cron/                   KbSync (nightly), KbIndexPending (every 15 min)
Model/Config.php        typed access to every wb_aichatbot/* setting
Controller/Adminhtml/   AI Providers grid/form/check; Knowledge Base grid, article form, sync, reindex, tester
etc/db_schema.xml       all module tables (credentials, knowledge base, conversations, requests, ...)
```

Block/Widget + view/frontend/web/js/widget.js + css/widget.css   storefront widget (Shadow DOM)
Controller/Adminhtml/Crud/  shared admin CRUD base; per-entity controllers under Qa/, Intent/, Guidance/, ...
Cron/Retention          nightly purge per Limits & Security > Retention
