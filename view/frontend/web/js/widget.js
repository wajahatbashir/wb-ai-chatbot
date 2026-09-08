/**
 * WB_AiChatbot storefront widget.
 *
 * Renders inside a Shadow DOM host so theme CSS cannot break it and it cannot break the theme. The page only
 * ships static config (FPC-safe); everything visitor-specific comes from /aichatbot/chat/bootstrap.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Customer/js/customer-data',
    'text!WB_AiChatbot/css/widget.css'
], function ($, $t, customerData, css) {
    'use strict';

    var STORAGE_OPEN = 'wb_aichatbot_open';
    var STORAGE_INVITED = 'wb_aichatbot_invited';
    var STORAGE_RATED = 'wb_aichatbot_rated';

    var ICONS = {
        chat: '<svg viewBox="0 0 24 24"><path d="M21 12a8 8 0 0 1-8 8H8l-5 3 1.2-4.2A8 8 0 1 1 21 12z"/></svg>',
        close: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>',
        minimize: '<svg viewBox="0 0 24 24"><path d="M5 12h14"/></svg>',
        more: '<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.6" fill="currentColor"/><circle cx="12" cy="12" r="1.6" fill="currentColor"/><circle cx="12" cy="19" r="1.6" fill="currentColor"/></svg>',
        send: '<svg viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>',
        attach: '<svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 16l5-5 4 4 3-3 6 6"/><circle cx="16" cy="9" r="1.5"/></svg>',
        back: '<svg viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>',
        up: '<svg viewBox="0 0 24 24"><path d="M7 11v9H4v-9h3zm2 0l4-8a2 2 0 0 1 2 2v4h5a2 2 0 0 1 2 2l-1.5 8a2 2 0 0 1-2 2H9v-9z"/></svg>',
        down: '<svg viewBox="0 0 24 24"><path d="M17 13V4h3v9h-3zm-2 0l-4 8a2 2 0 0 1-2-2v-4H4a2 2 0 0 1-2-2l1.5-8a2 2 0 0 1 2-2H15v9z"/></svg>',
        bot: '<svg viewBox="0 0 24 24"><rect x="4" y="7" width="16" height="12" rx="4"/><path d="M12 3v4M8 13h.01M16 13h.01M9 16h6"/></svg>'
    };

    function Widget(host, config) {
        this.host = host;
        this.config = config;
        this.state = {open: false, view: 'home', booted: false, sending: false, messages: [], mode: 'assist', unread: 0, pending: [], allowAttachments: false, conversationId: null, rating: null};
        this.init();
    }

    Widget.prototype = {
        init: function () {
            if (this.isExcludedUrl()) {
                return;
            }
            this.root = this.host.attachShadow({mode: 'open'});
            var style = document.createElement('style');
            style.textContent = css + (this.config.customCss ? '\n' + this.config.customCss : '');
            this.root.appendChild(style);
            this.el = document.createElement('div');
            this.el.className = 'wb' + (this.config.position === 'bottom_left' ? ' wb--left' : '');
            this.el.style.setProperty('--c', this.config.primaryColor);
            this.el.style.setProperty('--c-soft', this.hexToRgba(this.config.primaryColor, 0.08));
            this.el.style.setProperty('--mobile-offset', (parseInt(this.config.bottomOffsetMobile, 10) || 20) + 'px');
            this.root.appendChild(this.el);
            this.render();
            this.bind();

            var wasOpen = this.storage(STORAGE_OPEN) === '1';
            if (wasOpen) {
                this.open();
            } else {
                this.scheduleInvitation();
            }
        },

        /* ---------- rendering ---------- */
        render: function () {
            var c = this.config;
            var launcherLabel = c.launcherTitle ? '<span class="wb__launcher-label">' + this.esc(c.launcherTitle) + '</span>' : '';
            this.el.innerHTML =
                '<div class="wb__launcher-wrap">' +
                    '<div class="wb__invite" data-el="invite" hidden><button type="button" class="wb__invite-close" data-act="dismiss-invite" aria-label="' + this.esc($t('Close')) + '">×</button><span data-el="invite-text"></span></div>' +
                    '<button type="button" class="wb__launcher' + (launcherLabel ? '' : ' wb__launcher--icon') + '" data-act="open" aria-label="' + this.esc($t('Open chat')) + '">' + ICONS.chat + launcherLabel + '</button>' +
                    '<span class="wb__badge" data-el="badge"></span>' +
                '</div>' +
                '<div class="wb__win" role="dialog" aria-label="' + this.esc(c.windowTitle) + '">' +
                    '<div class="wb__head">' +
                        '<button type="button" class="wb__iconbtn" data-act="home" aria-label="' + this.esc($t('Back')) + '" data-el="back" hidden>' + ICONS.back + '</button>' +
                        '<div class="wb__avatar">' + (c.avatar ? '<img src="' + this.esc(c.avatar) + '" alt=""/>' : ICONS.bot) + '</div>' +
                        '<div><div class="wb__title">' + this.esc(c.windowTitle) + '</div><div class="wb__subtitle"><span class="wb__dot"></span>' + this.esc(c.windowSubtitle || $t('Online')) + '</div></div>' +
                        '<div class="wb__head-actions">' +
                            '<button type="button" class="wb__iconbtn" data-act="menu" aria-label="' + this.esc($t('More')) + '">' + ICONS.more + '</button>' +
                            '<button type="button" class="wb__iconbtn" data-act="close" aria-label="' + this.esc($t('Close chat')) + '">' + ICONS.close + '</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="wb__menu" data-el="menu">' +
                        '<button type="button" data-act="new">' + this.esc($t('Start a new conversation')) + '</button>' +
                        '<button type="button" data-act="rate">' + this.esc($t('Rate this conversation')) + '</button>' +
                        (c.allowHumanHandoff ? '<button type="button" data-act="human">' + this.esc($t('Talk to a human')) + '</button>' : '') +
                    '</div>' +
                    '<div class="wb__notice" data-el="notice" hidden></div>' +
                    '<div class="wb__body" data-el="body"></div>' +
                    '<div class="wb__composer" data-el="composer" hidden>' +
                        '<div class="wb__pending" data-el="pending" hidden></div>' +
                        '<div class="wb__row">' +
                            '<button type="button" class="wb__attach" data-act="attach" data-el="attach" aria-label="' + this.esc($t('Attach an image')) + '" hidden>' + ICONS.attach + '</button>' +
                            '<input type="file" accept="image/*" data-el="file" hidden multiple/>' +
                            '<textarea class="wb__input" data-el="input" rows="1" maxlength="' + (parseInt(c.maxMessageLength, 10) || 1000) + '" placeholder="' + this.esc($t('Type your message…')) + '" aria-label="' + this.esc($t('Your message')) + '"></textarea>' +
                            '<button type="button" class="wb__send" data-act="send" data-el="send" aria-label="' + this.esc($t('Send')) + '" disabled>' + ICONS.send + '</button>' +
                        '</div>' +
                        '<div class="wb__foot">' + this.esc($t('Answers are generated automatically and may be imperfect. Personal data is only used to help you.')) + '</div>' +
                    '</div>' +
                '</div>';
            this.$ = function (name) { return this.el.querySelector('[data-el="' + name + '"]'); };
            this.showHome();
        },

        showHome: function () {
            this.state.view = 'home';
            var c = this.config;
            var name = this.state.customerName;
            var html = '<div class="wb__home-card"><h3>' + this.esc(name ? $t('Hi %1!').replace('%1', name) : $t('Hi there!')) + '</h3><p>' + this.esc(c.welcomeMessage) + '</p>' +
                '<button type="button" class="wb__primary" data-act="start">' + ICONS.chat.replace('<svg', '<svg style="width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2"') + (this.state.messages.length ? this.esc($t('Continue the conversation')) : this.esc($t('Start a conversation'))) + '</button></div>';
            if (c.defaultQuestions && c.defaultQuestions.length) {
                html += '<div class="wb__section-title">' + this.esc($t('Popular questions')) + '</div><div class="wb__qlist">';
                c.defaultQuestions.forEach(function (q) {
                    html += '<button type="button" class="wb__q" data-act="ask" data-text="' + this.esc(q) + '">' + this.esc(q) + '</button>';
                }, this);
                html += '</div>';
            }
            this.$('body').innerHTML = html;
            this.$('composer').hidden = true;
            this.$('back').hidden = true;
        },

        showChat: function () {
            this.state.view = 'chat';
            this.$('composer').hidden = false;
            this.$('back').hidden = false;
            this.$('attach').hidden = !this.state.allowAttachments;
            var body = this.$('body');
            body.innerHTML = '';
            if (!this.state.messages.length) {
                this.appendMessage({role: 'assistant', text: this.config.welcomeMessage, chips: (this.config.defaultQuestions || []).map(function (q) { return {label: q, send: q}; })}, false);
            } else {
                this.state.messages.forEach(function (m) { this.appendMessage(m, false); }, this);
            }
            this.scrollDown();
            this.focusInput();
        },

        appendMessage: function (m, animate) {
            var body = this.$('body');
            this.el.querySelectorAll('.wb__chips').forEach(function (n) { n.remove(); });
            var wrap = document.createElement('div');
            wrap.className = 'wb__msg wb__msg--' + (m.role === 'user' ? 'user' : 'bot');
            if (m.id) { wrap.dataset.id = m.id; }
            var html = '';
            if (m.attachments && m.attachments.length) {
                html += '<div class="wb__attach-preview">' + m.attachments.map(function (a) { return a.preview ? '<img src="' + a.preview + '" alt=""/>' : ''; }).join('') + '</div>';
            }
            html += '<div class="wb__bubble">' + this.md(m.text || '') + '</div>';
            if (m.role !== 'user' && m.id) {
                html += '<div class="wb__meta"><button type="button" class="wb__fb' + (m.feedback === 1 ? ' wb__fb--on' : '') + '" data-act="fb" data-v="1" aria-label="' + this.esc($t('Helpful')) + '">' + ICONS.up + '</button>' +
                    '<button type="button" class="wb__fb' + (m.feedback === -1 ? ' wb__fb--on' : '') + '" data-act="fb" data-v="-1" aria-label="' + this.esc($t('Not helpful')) + '">' + ICONS.down + '</button></div>';
            }
            wrap.innerHTML = html;
            body.appendChild(wrap);
            if (m.cards && m.cards.length) {
                body.appendChild(this.renderCards(m.cards));
            }
            var hasProducts = (m.cards || []).some(function (c) { return c.type === 'product'; });
            if (m.sources && m.sources.length && m.role !== 'user' && !hasProducts) {
                var withUrl = m.sources.filter(function (s) { return s.url; });
                if (withUrl.length) {
                    var src = document.createElement('div');
                    src.className = 'wb__sources';
                    src.innerHTML = this.esc($t('Source:')) + ' ' + withUrl.slice(0, 2).map(function (s) { return '<a href="' + this.esc(s.url) + '" target="' + this.esc(this.config.linkTarget) + '">' + this.esc(s.title) + '</a>'; }, this).join(', ');
                    body.appendChild(src);
                }
            }
            if (m.chips && m.chips.length) {
                var chips = document.createElement('div');
                chips.className = 'wb__chips';
                chips.innerHTML = m.chips.map(function (ch) { return '<button type="button" class="wb__chip" data-act="ask" data-text="' + this.esc(ch.send || ch.label) + '">' + this.esc(ch.label) + '</button>'; }, this).join('');
                body.appendChild(chips);
            }
            if (animate !== false) { this.scrollDown(); }
        },

        renderCards: function (cards) {
            var products = cards.filter(function (c) { return c.type === 'product'; });
            var others = cards.filter(function (c) { return c.type !== 'product'; });
            var frag = document.createDocumentFragment();
            var self = this;
            if (products.length) {
                var row = document.createElement('div');
                row.className = 'wb__cards';
                row.innerHTML = products.map(function (p) {
                    return '<div class="wb__pcard">' +
                        '<a href="' + self.esc(p.url) + '" target="' + self.esc(self.config.linkTarget) + '">' + (p.image ? '<img class="wb__pcard-img" src="' + self.esc(p.image) + '" alt="' + self.esc(p.name) + '" loading="lazy"/>' : '<div class="wb__pcard-img"></div>') + '</a>' +
                        '<div class="wb__pcard-body"><a class="wb__pcard-name" href="' + self.esc(p.url) + '" target="' + self.esc(self.config.linkTarget) + '">' + self.esc(p.name) + '</a>' +
                        '<div class="wb__pcard-price">' + self.esc(p.price_text) + (p.regular_price_text ? '<s>' + self.esc(p.regular_price_text) + '</s>' : '') + '</div>' +
                        '<span class="wb__av wb__av--' + self.esc(p.availability) + '">' + self.esc(p.availability_label) + '</span>' +
                        '<div class="wb__pcard-actions"><a class="wb__btn" href="' + self.esc(p.url) + '" target="' + self.esc(self.config.linkTarget) + '">' + self.esc($t('View')) + '</a>' +
                        (p.can_add_to_cart ? '<button type="button" class="wb__btn wb__btn--fill" data-act="add" data-id="' + parseInt(p.id, 10) + '">' + self.esc($t('Add to cart')) + '</button>' : '') + '</div></div></div>';
                }).join('');
                frag.appendChild(row);
            }
            others.forEach(function (c) {
                var box = document.createElement('div');
                box.className = 'wb__wide';
                if (c.type === 'link' || c.type === 'file') {
                    box.innerHTML = (c.type === 'file' ? '📄 ' : '🔗 ') + '<a href="' + self.esc(c.url) + '" target="' + (c.type === 'file' ? '_blank' : self.esc(self.config.linkTarget)) + '" rel="noopener">' + self.esc(c.label) + '</a>' + (c.description ? '<small>' + self.esc(c.description) + '</small>' : '');
                } else if (c.type === 'order') {
                    box.innerHTML = '<strong>' + self.esc($t('Order %1').replace('%1', c.increment_id)) + '</strong> · ' + self.esc(c.status) + (c.created_at ? ' · ' + self.esc(c.created_at) : '') +
                        '<ul>' + (c.items || []).map(function (i) { return '<li>' + self.esc(i.name) + ' × ' + self.esc(i.qty) + ' – ' + self.esc(i.price) + '</li>'; }).join('') + '</ul>' +
                        '<div style="margin-top:6px"><strong>' + self.esc($t('Total')) + ':</strong> ' + self.esc(c.grand_total) + '</div>' +
                        (c.shipping_address ? '<small>' + self.esc(c.shipping_address) + '</small>' : '');
                } else if (c.type === 'table') {
                    box.innerHTML = (c.title ? '<strong>' + self.esc(c.title) + '</strong>' : '') + '<div class="wb__tablewrap"><table class="wb__table"><tr>' +
                        (c.columns || []).map(function (x) { return '<th>' + self.esc(x) + '</th>'; }).join('') + '</tr>' +
                        (c.rows || []).map(function (r) { return '<tr>' + r.map(function (x) { return '<td>' + self.esc(x) + '</td>'; }).join('') + '</tr>'; }).join('') + '</table></div>';
                } else {
                    return;
                }
                frag.appendChild(box);
            });
            return frag;
        },

        showTyping: function () {
            this.hideTyping();
            var t = document.createElement('div');
            t.className = 'wb__typing';
            t.dataset.el = 'typing';
            t.innerHTML = '<i></i><i></i><i></i>';
            this.$('body').appendChild(t);
            this.scrollDown();
        },

        hideTyping: function () {
            var t = this.$('typing');
            if (t) { t.remove(); }
        },

        showRating: function () {
            if (this.$('rating')) { return; }
            var box = document.createElement('div');
            box.className = 'wb__rate';
            box.dataset.el = 'rating';
            box.innerHTML = '<div>' + this.esc($t('How was this conversation?')) + '</div><div class="wb__stars">' +
                [1, 2, 3, 4, 5].map(function (n) { return '<button type="button" class="wb__star" data-act="star" data-v="' + n + '" aria-label="' + n + '">★</button>'; }).join('') + '</div>' +
                '<textarea rows="2" data-el="rating-comment" placeholder="' + this.esc($t('Anything we could do better? (optional)')) + '"></textarea>' +
                '<button type="button" class="wb__primary" data-act="rate-send" disabled>' + this.esc($t('Send rating')) + '</button>';
            this.$('body').appendChild(box);
            this.scrollDown();
        },

        /* ---------- behaviour ---------- */
        bind: function () {
            var self = this;
            this.el.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-act]');
                if (!btn) { self.$('menu').classList.remove('wb__menu--on'); return; }
                var act = btn.dataset.act;
                switch (act) {
                    case 'open': self.open(); break;
                    case 'close': self.close(); break;
                    case 'dismiss-invite': self.hideInvite(); break;
                    case 'home': self.showHome(); break;
                    case 'start': self.showChat(); break;
                    case 'ask': self.showChat(); self.send(btn.dataset.text); break;
                    case 'send': self.send(self.$('input').value); break;
                    case 'menu': self.$('menu').classList.toggle('wb__menu--on'); e.stopPropagation(); return;
                    case 'new': self.$('menu').classList.remove('wb__menu--on'); self.reset(); break;
                    case 'rate': self.$('menu').classList.remove('wb__menu--on'); self.showChat(); self.showRating(); break;
                    case 'human': self.$('menu').classList.remove('wb__menu--on'); self.showChat(); self.send($t('I want to talk to a human')); break;
                    case 'fb': self.feedback(btn); break;
                    case 'add': self.addToCart(btn); break;
                    case 'attach': self.$('file').click(); break;
                    case 'remove-pending': self.state.pending.splice(parseInt(btn.dataset.i, 10), 1); self.renderPending(); break;
                    case 'star': self.pickStar(parseInt(btn.dataset.v, 10)); break;
                    case 'rate-send': self.sendRating(); break;
                }
                self.$('menu').classList.remove('wb__menu--on');
            });
            var input = this.$('input');
            input.addEventListener('input', function () {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 110) + 'px';
                self.$('send').disabled = !input.value.trim() && !self.state.pending.length;
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); self.send(input.value); }
            });
            this.$('file').addEventListener('change', function () { self.readFiles(this.files); this.value = ''; });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && self.state.open) { self.close(); } });
        },

        open: function () {
            this.state.open = true;
            this.el.classList.add('wb--open');
            this.storage(STORAGE_OPEN, '1');
            this.hideInvite();
            this.setUnread(0);
            var self = this;
            this.boot().then(function () {
                if (self.state.messages.length) { self.showChat(); } else { self.showHome(); }
            });
        },

        close: function () {
            this.state.open = false;
            this.el.classList.remove('wb--open');
            this.storage(STORAGE_OPEN, '0');
        },

        boot: function () {
            var self = this;
            if (this.state.booted) { return $.Deferred().resolve().promise(); }
            return $.getJSON(this.config.urls.bootstrap, {page_url: location.href}).then(function (data) {
                self.state.booted = true;
                if (!data || data.enabled === false) {
                    self.el.style.display = 'none';
                    return;
                }
                self.state.mode = data.mode;
                self.state.customerName = data.customer && data.customer.name ? data.customer.name.split(' ')[0] : null;
                self.state.allowAttachments = !!(data.settings && data.settings.allow_attachments);
                self.state.conversationId = data.conversation ? data.conversation.id : null;
                self.state.rating = data.conversation ? data.conversation.rating : null;
                self.state.messages = (data.conversation && data.conversation.messages || []).map(function (m) {
                    return {id: m.id, role: m.role, text: m.text, cards: m.cards || [], sources: m.sources || [], feedback: m.feedback};
                });
            }, function () {
                self.state.booted = true;
            });
        },

        send: function (text) {
            text = (text || '').trim();
            if (this.state.sending || (!text && !this.state.pending.length)) { return; }
            var self = this;
            var attachments = this.state.pending.slice();
            this.state.pending = [];
            this.renderPending();
            var userMsg = {role: 'user', text: text, attachments: attachments};
            this.state.messages.push(userMsg);
            this.appendMessage(userMsg);
            this.$('input').value = '';
            this.$('input').style.height = 'auto';
            this.$('send').disabled = true;
            this.state.sending = true;
            this.showTyping();
            $.ajax({
                url: this.config.urls.send,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: this.formKey(),
                    message: text,
                    page_url: location.href,
                    attachments: JSON.stringify(attachments.map(function (a) { return {name: a.name, mime: a.mime, data: a.data}; }))
                }
            }).done(function (r) {
                self.hideTyping();
                if (!r || !r.ok) {
                    self.appendMessage({role: 'assistant', text: (r && r.message) || $t('Sorry, something went wrong. Please try again.')});
                    return;
                }
                var reply = r.reply;
                var msg = {id: reply.message_id, role: 'assistant', text: reply.text, cards: reply.cards || [], chips: reply.chips || [], sources: reply.sources || []};
                self.state.messages.push(msg);
                self.state.mode = reply.mode || self.state.mode;
                self.notice(reply.notice);
                self.appendMessage(msg);
                if (!self.state.open) { self.setUnread(self.state.unread + 1); }
                self.beep();
            }).fail(function (xhr) {
                self.hideTyping();
                var msg = xhr.status === 403 ? $t('Your session expired. Please reload the page and try again.') : $t('Sorry, I could not reach the assistant. Please try again in a moment.');
                self.appendMessage({role: 'assistant', text: msg});
            }).always(function () {
                self.state.sending = false;
                self.focusInput();
            });
        },

        reset: function () {
            var self = this;
            $.post(this.config.urls.reset, {form_key: this.formKey()}).always(function () {
                self.state.messages = [];
                self.state.rating = null;
                self.notice(null);
                self.showChat();
            });
        },

        feedback: function (btn) {
            var wrap = btn.closest('.wb__msg');
            var id = wrap && parseInt(wrap.dataset.id, 10);
            if (!id) { return; }
            var value = parseInt(btn.dataset.v, 10);
            var wasOn = btn.classList.contains('wb__fb--on');
            wrap.querySelectorAll('.wb__fb').forEach(function (b) { b.classList.remove('wb__fb--on'); });
            if (!wasOn) { btn.classList.add('wb__fb--on'); }
            $.post(this.config.urls.feedback, {form_key: this.formKey(), message_id: id, value: wasOn ? 0 : value});
        },

        pickStar: function (n) {
            this.state.pendingRating = n;
            this.el.querySelectorAll('.wb__star').forEach(function (s) { s.classList.toggle('wb__star--on', parseInt(s.dataset.v, 10) <= n); });
            this.el.querySelector('[data-act="rate-send"]').disabled = false;
        },

        sendRating: function () {
            var self = this;
            var comment = this.$('rating-comment') ? this.$('rating-comment').value : '';
            $.post(this.config.urls.rate, {form_key: this.formKey(), rating: this.state.pendingRating, comment: comment}).always(function () {
                var box = self.$('rating');
                if (box) { box.outerHTML = '<div class="wb__system">' + self.esc($t('Thank you for your feedback!')) + '</div>'; }
                self.storage(STORAGE_RATED, '1');
            });
        },

        addToCart: function (btn) {
            var self = this;
            var id = parseInt(btn.dataset.id, 10);
            btn.disabled = true;
            var label = btn.textContent;
            btn.textContent = $t('Adding…');
            $.ajax({
                url: this.config.urls.addToCart,
                type: 'POST',
                dataType: 'json',
                data: {form_key: this.formKey(), product: id, qty: 1}
            }).done(function () {
                btn.textContent = $t('Added ✓');
                customerData.invalidate(['cart']);
                customerData.reload(['cart'], true);
                setTimeout(function () { btn.textContent = label; btn.disabled = false; }, 2500);
            }).fail(function () {
                btn.textContent = label;
                btn.disabled = false;
                window.location.href = self.config.urls.cart;
            });
        },

        readFiles: function (files) {
            var self = this;
            Array.prototype.slice.call(files, 0, 3 - this.state.pending.length).forEach(function (file) {
                if (!/^image\//.test(file.type) || file.size > 10 * 1024 * 1024) { return; }
                var reader = new FileReader();
                reader.onload = function () {
                    var dataUrl = String(reader.result);
                    self.state.pending.push({name: file.name, mime: file.type, data: dataUrl.split(',')[1], preview: dataUrl});
                    self.renderPending();
                    self.$('send').disabled = false;
                };
                reader.readAsDataURL(file);
            });
        },

        renderPending: function () {
            var box = this.$('pending');
            box.hidden = !this.state.pending.length;
            box.innerHTML = this.state.pending.map(function (a, i) {
                return '<span><img src="' + a.preview + '" alt=""/><button type="button" data-act="remove-pending" data-i="' + i + '" aria-label="' + this.esc($t('Remove')) + '">×</button></span>';
            }, this).join('');
        },

        notice: function (text) {
            var n = this.$('notice');
            n.hidden = !text;
            n.textContent = text || '';
        },

        scheduleInvitation: function () {
            var delay = parseInt(this.config.invitationDelay, 10);
            if (!this.config.invitationMessage || !delay || this.storage(STORAGE_INVITED) === '1') { return; }
            var self = this;
            setTimeout(function () {
                if (self.state.open) { return; }
                self.$('invite-text').textContent = self.config.invitationMessage;
                self.$('invite').hidden = false;
                self.storage(STORAGE_INVITED, '1');
            }, delay * 1000);
        },

        hideInvite: function () {
            this.$('invite').hidden = true;
        },

        setUnread: function (n) {
            this.state.unread = n;
            var b = this.$('badge');
            b.textContent = n;
            b.classList.toggle('wb__badge--on', n > 0);
        },

        beep: function () {
            if (!this.config.sound) { return; }
            try {
                var ctx = new (window.AudioContext || window.webkitAudioContext)();
                var o = ctx.createOscillator(), g = ctx.createGain();
                o.frequency.value = 880; g.gain.value = 0.04;
                o.connect(g); g.connect(ctx.destination);
                o.start(); o.stop(ctx.currentTime + 0.12);
            } catch (e) { /* audio blocked */ }
        },

        /* ---------- helpers ---------- */
        isExcludedUrl: function () {
            var path = location.pathname;
            return (this.config.excludedUrlPatterns || []).some(function (p) {
                var re = new RegExp('^' + p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$');
                return re.test(path);
            });
        },

        formKey: function () {
            var m = document.cookie.match(/(?:^|;\s*)form_key=([^;]+)/);
            return m ? decodeURIComponent(m[1]) : (window.FORM_KEY || '');
        },

        storage: function (key, value) {
            try {
                if (value === undefined) { return sessionStorage.getItem(key); }
                sessionStorage.setItem(key, value);
            } catch (e) { return null; }
            return value;
        },

        scrollDown: function () {
            var body = this.$('body');
            body.scrollTop = body.scrollHeight;
        },

        focusInput: function () {
            if (window.matchMedia('(min-width: 768px)').matches && this.state.view === 'chat') {
                this.$('input').focus();
            }
        },

        esc: function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (ch) {
                return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch];
            });
        },

        /**
         * Minimal, safe Markdown: paragraphs, **bold**, [text](url), bare URLs, "- " lists. Everything is escaped first.
         */
        md: function (text) {
            var self = this;
            var blocks = this.esc(text).split(/\n{2,}/);
            return blocks.map(function (block) {
                var lines = block.split('\n');
                var isList = lines.length && lines.every(function (l) { return /^\s*[-•]\s+/.test(l); });
                var inline = function (s) {
                    return s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                        .replace(/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/g, '<a href="$2" target="' + self.esc(self.config.linkTarget) + '">$1</a>')
                        .replace(/(^|[\s(])(https?:\/\/[^\s<)]+)/g, '$1<a href="$2" target="' + self.esc(self.config.linkTarget) + '">$2</a>');
                };
                if (isList) {
                    return '<ul>' + lines.map(function (l) { return '<li>' + inline(l.replace(/^\s*[-•]\s+/, '')) + '</li>'; }).join('') + '</ul>';
                }
                return '<p>' + lines.map(inline).join('<br/>') + '</p>';
            }).join('');
        },

        hexToRgba: function (hex, alpha) {
            var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex || '');
            if (!m) { return 'rgba(0,0,0,' + alpha + ')'; }
            return 'rgba(' + parseInt(m[1], 16) + ',' + parseInt(m[2], 16) + ',' + parseInt(m[3], 16) + ',' + alpha + ')';
        }
    };

    return function (config, element) {
        var start = function () {
            if (!element.shadowRoot) {
                new Widget(element, config);
            }
        };
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(start, {timeout: 2500});
        } else {
            setTimeout(start, 1200);
        }
    };
});
