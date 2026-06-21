<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Magetsi {{ $mode === 'customer' ? 'Customer' : 'Agents' }} — WhatsApp</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #111;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── WhatsApp Web shell ─────────────────── */
        .wa-shell {
            width: 100%;
            max-width: 900px;
            height: 95vh;
            max-height: 700px;
            background: #fff;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 6px 40px rgba(0,0,0,.5);
            position: relative;
        }

        /* ── Header ─────────────────────────── */
        .wa-header {
            background: #008069;
            padding: 10px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
            height: 59px;
        }
        .wa-header .avatar {
            width: 40px; height: 40px;
            border-radius: 50%;
            background: #00a884;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            color: #fff;
            flex-shrink: 0;
        }
        .wa-header .contact-info { flex: 1; color: #fff; }
        .wa-header .contact-name { font-size: 16px; font-weight: 400; }
        .wa-header .contact-status { font-size: 13px; opacity: .75; }
        .wa-header .header-icons {
            display: flex; gap: 20px; color: rgba(255,255,255,.85);
        }
        .wa-header .header-icons svg { width: 22px; height: 22px; cursor: pointer; }

        /* ── Chat area ──────────────────────── */
        .wa-chat {
            flex: 1;
            overflow-y: auto;
            padding: 20px 60px 8px;
            background-color: #efeae2;
            background-image: url("data:image/svg+xml,%3Csvg width='400' height='400' xmlns='http://www.w3.org/2000/svg'%3E%3Cdefs%3E%3Cpattern id='p' width='100' height='100' patternUnits='userSpaceOnUse'%3E%3Cpath d='M20 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm50 30a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm-20 40a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zm60-50a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3zm-30 70a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm70-30a3 3 0 1 0 0-6 3 3 0 0 0 0 6z' fill='%23d6d0c5' fill-opacity='.18'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='400' height='400' fill='url(%23p)'/%3E%3C/svg%3E");
            scroll-behavior: smooth;
            display: flex;
            flex-direction: column;
        }

        /* System pill */
        .sys-pill {
            align-self: center;
            background: #fef3c7;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            color: #54656f;
            margin-bottom: 8px;
            box-shadow: 0 1px 1px rgba(0,0,0,.06);
        }
        .date-pill {
            align-self: center;
            background: #fff;
            border-radius: 8px;
            padding: 4px 12px;
            font-size: 12px;
            color: #54656f;
            margin-bottom: 8px;
            box-shadow: 0 1px 1px rgba(0,0,0,.06);
        }

        /* ── Bubbles ────────────────────────── */
        .msg {
            max-width: 65%;
            margin-bottom: 2px;
            position: relative;
            animation: msgIn .25s ease;
        }
        @keyframes msgIn {
            from { opacity: 0; transform: translateY(6px) scale(.97); }
            to { opacity: 1; transform: none; }
        }
        .msg.in { align-self: flex-start; }
        .msg.out { align-self: flex-end; }

        .msg .bubble {
            padding: 6px 7px 8px 9px;
            font-size: 14.2px;
            line-height: 19px;
            color: #111b21;
            word-wrap: break-word;
            position: relative;
        }
        .msg.in .bubble {
            background: #fff;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 1px .5px rgba(11,20,26,.13);
        }
        .msg.in.tail::before {
            content: '';
            position: absolute;
            top: 0; left: -8px;
            border-right: 8px solid #fff;
            border-bottom: 13px solid transparent;
        }
        .msg.out .bubble {
            background: #d9fdd3;
            border-radius: 8px 0 8px 8px;
            box-shadow: 0 1px .5px rgba(11,20,26,.13);
        }
        .msg.out.tail::after {
            content: '';
            position: absolute;
            top: 0; right: -8px;
            border-left: 8px solid #d9fdd3;
            border-bottom: 13px solid transparent;
        }

        .msg .meta {
            float: right;
            margin: -2px 0 -5px 12px;
            font-size: 11px;
            color: rgba(17,27,33,.45);
            display: flex; align-items: center; gap: 3px;
            padding-top: 3px;
        }
        .msg.out .meta .ticks { color: #53bdeb; font-size: 15px; letter-spacing: -3px; }

        /* ── Reply buttons ──────────────────── */
        .reply-btns {
            max-width: 65%;
            align-self: flex-start;
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 4px;
            animation: msgIn .25s ease;
        }
        .reply-btns .rbtn {
            background: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 14px;
            color: #00a884;
            font-weight: 400;
            cursor: pointer;
            text-align: center;
            box-shadow: 0 1px .5px rgba(11,20,26,.13);
            transition: background .1s;
            font-family: inherit;
        }
        .reply-btns .rbtn:hover { background: #f0f2f5; }
        .reply-btns .rbtn:active { background: #e9edef; }

        /* ── Flow CTA buttons ──────────────── */
        .flow-cta-msg {
            max-width: 65%;
            align-self: flex-start;
            margin-bottom: 2px;
            animation: msgIn .25s ease;
        }
        .flow-cta-msg .cta-bubble {
            background: #fff;
            border-radius: 0 8px 8px 8px;
            box-shadow: 0 1px .5px rgba(11,20,26,.13);
            overflow: hidden;
        }
        .flow-cta-msg.tail::before {
            content: '';
            position: absolute;
            top: 0; left: -8px;
            border-right: 8px solid #fff;
            border-bottom: 13px solid transparent;
        }
        .flow-cta-msg .cta-body {
            padding: 6px 9px 4px;
            font-size: 14.2px;
            line-height: 19px;
            color: #111b21;
        }
        .flow-cta-msg .cta-body .meta {
            float: right;
            margin: -2px 0 -5px 12px;
            font-size: 11px;
            color: rgba(17,27,33,.45);
            padding-top: 3px;
        }
        .flow-cta-msg .cta-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 10px 16px;
            border: none;
            border-top: 1px solid #e9edef;
            background: #fff;
            color: #00a884;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            font-family: inherit;
            transition: background .1s;
        }
        .flow-cta-msg .cta-btn:hover { background: #f0f2f5; }
        .flow-cta-msg .cta-btn:active { background: #e9edef; }
        .flow-cta-msg .cta-btn svg {
            width: 16px; height: 16px;
            flex-shrink: 0;
        }

        /* ── Success card ───────────────────── */
        .success-msg {
            max-width: 65%;
            align-self: flex-start;
            margin-bottom: 2px;
            position: relative;
            animation: msgIn .3s ease;
        }
        .success-msg::before {
            content: '';
            position: absolute;
            top: 0; left: -8px;
            border-right: 8px solid #fff;
            border-bottom: 13px solid transparent;
        }
        .success-msg .card {
            background: #fff;
            border-radius: 0 8px 8px 8px;
            padding: 8px 9px;
            box-shadow: 0 1px .5px rgba(11,20,26,.13);
        }
        .success-msg .card-header {
            display: flex; align-items: center; gap: 6px;
            margin-bottom: 6px;
        }
        .success-msg .card-header .icon { font-size: 18px; }
        .success-msg .card-header .title { font-size: 14px; font-weight: 600; color: #155724; }
        .success-msg .card-details {
            background: #f0f2f5;
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 6px;
        }
        .success-msg .card-row {
            display: flex; justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px solid #e9edef;
            font-size: 13px;
        }
        .success-msg .card-row:last-child { border-bottom: none; }
        .success-msg .card-row .lbl { color: #667781; }
        .success-msg .card-row .val { color: #111b21; font-weight: 500; }
        .success-msg .card-row .val.token { font-family: 'SF Mono', 'Consolas', monospace; color: #008069; font-weight: 700; letter-spacing: .5px; }
        .success-msg .sms-note {
            background: #e7f8fd;
            border-radius: 6px;
            padding: 6px 8px;
            font-size: 12px;
            color: #0b6985;
        }

        /* ── Input bar ──────────────────────── */
        .wa-input-bar {
            background: #f0f2f5;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            flex-shrink: 0;
        }
        .wa-input-bar .icon-btn {
            width: 36px; height: 36px;
            border-radius: 50%;
            border: none;
            background: none;
            display: flex; align-items: center; justify-content: center;
            color: #54656f;
            cursor: pointer;
            font-size: 20px;
            flex-shrink: 0;
        }
        .wa-input-bar .text-field {
            flex: 1;
            background: #fff;
            border: none;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 15px;
            font-family: inherit;
            color: #3b4a54;
            outline: none;
        }
        .wa-input-bar .send-btn {
            width: 36px; height: 36px;
            border-radius: 50%; border: none;
            background: none;
            display: flex; align-items: center; justify-content: center;
            color: #54656f;
            cursor: pointer;
            flex-shrink: 0;
        }
        .wa-input-bar .send-btn:hover { color: #00a884; }
        .wa-input-bar .send-btn svg { width: 24px; height: 24px; }

        /* ── Flow bottom sheet ──────────────── */
        .flow-sheet {
            position: absolute;
            inset: 0;
            background: rgba(11,20,26,.4);
            z-index: 50;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            animation: fadeIn .2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .flow-panel {
            background: #fff;
            border-radius: 16px 16px 0 0;
            max-height: 90%;
            display: flex;
            flex-direction: column;
            animation: sheetUp .3s ease;
        }
        @keyframes sheetUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        .flow-panel-header {
            background: #008069;
            color: #fff;
            padding: 14px 16px;
            border-radius: 16px 16px 0 0;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .flow-panel-header .back-btn {
            background: none; border: none; color: #fff;
            font-size: 20px; cursor: pointer; padding: 4px;
            display: flex; align-items: center;
        }
        .flow-panel-header .back-btn svg { width: 20px; height: 20px; }
        .flow-panel-header .flow-title { font-size: 16px; font-weight: 600; }
        .flow-panel-header .flow-from { font-size: 12px; opacity: .8; }

        .flow-panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            background: #f0f2f5;
        }

        /* ── Generic flow component styles ──── */
        .flow-card {
            background: #fff;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .flow-heading {
            font-size: 18px;
            font-weight: 600;
            color: #111b21;
            margin-bottom: 4px;
        }
        .flow-subheading {
            font-size: 14px;
            font-weight: 500;
            color: #111b21;
            margin-bottom: 4px;
            margin-top: 4px;
        }
        .flow-body-text {
            font-size: 14px;
            color: #667781;
            line-height: 1.5;
            margin-bottom: 4px;
        }
        .flow-label {
            font-size: 13px;
            font-weight: 500;
            color: #667781;
            margin-bottom: 6px;
        }
        .flow-input {
            display: block; width: 100%;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            color: #111b21;
            transition: border-color .15s;
        }
        .flow-input:focus { border-color: #00a884; }
        .flow-input.valid { border-color: #00a884; background: #f0fdf4; }
        .flow-input.invalid { border-color: #ea4335; background: #fef7f7; }
        .flow-hint { font-size: 12px; color: #8696a0; margin-top: 4px; }
        .flow-required { color: #ea4335; }

        /* Radio buttons group */
        .radio-group { display: flex; flex-direction: column; gap: 6px; }
        .radio-option {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all .12s;
            font-size: 14px;
            color: #111b21;
        }
        .radio-option:hover { border-color: #00a884; }
        .radio-option.selected { border-color: #00a884; background: #f0fdf4; }
        .radio-option .radio-dot {
            width: 18px; height: 18px;
            border-radius: 50%;
            border: 2px solid #8696a0;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
            transition: border-color .12s;
        }
        .radio-option.selected .radio-dot {
            border-color: #00a884;
        }
        .radio-option.selected .radio-dot::after {
            content: '';
            width: 10px; height: 10px;
            border-radius: 50%;
            background: #00a884;
        }

        /* Meter validation feedback */
        .meter-fb {
            margin-top: 6px; padding: 8px 10px;
            border-radius: 8px;
            display: flex; align-items: center; gap: 8px;
            font-size: 13px;
            animation: msgIn .2s ease;
        }
        .meter-fb.checking { background: #fefce8; border: 1px solid #facc15; color: #854d0e; }
        .meter-fb.valid { background: #f0fdf4; border: 1px solid #86efac; color: #166534; }
        .meter-fb.invalid { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
        .meter-fb .cust-name { font-weight: 600; font-size: 14px; }

        @keyframes spin { to { transform: rotate(360deg); } }
        .spin {
            width: 14px; height: 14px; border-radius: 50%;
            border: 2px solid currentColor; border-top-color: transparent;
            animation: spin .7s linear infinite; flex-shrink: 0;
        }

        /* Footer / submit */
        .flow-panel-footer {
            padding: 12px 16px;
            background: #fff;
            border-top: 1px solid #e9edef;
            flex-shrink: 0;
        }
        .flow-submit {
            width: 100%; padding: 12px;
            border: none; border-radius: 24px;
            font-size: 15px; font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all .15s;
        }
        .flow-submit.active { background: #00a884; color: #fff; }
        .flow-submit.active:hover { background: #008069; }
        .flow-submit.disabled { background: #e9edef; color: #8696a0; cursor: default; }

        /* NavigationList */
        .nav-list-item {
            display: flex; align-items: center; gap: 12px;
            padding: 14px;
            cursor: pointer;
            transition: background .12s;
            border-bottom: 1px solid #f0f2f5;
        }
        .nav-list-item:last-child { border-bottom: none; }
        .nav-list-item:hover { background: #f7f9fa; }
        .nav-list-item:active { background: #eef1f3; }
        .nav-img {
            width: 40px; height: 40px; border-radius: 8px;
            background-size: cover; background-position: center;
            flex-shrink: 0;
            background-color: #f0f2f5;
        }
        .nav-item-content { flex: 1; min-width: 0; }
        .nav-item-title { font-size: 15px; font-weight: 500; color: #111b21; }
        .nav-item-desc { font-size: 13px; color: #667781; margin-top: 2px; }
        .nav-chevron { width: 20px; height: 20px; color: #8696a0; flex-shrink: 0; }

        /* Image, Caption, Link, OptIn */
        .flow-image { width: 100%; display: block; border-radius: 6px; }
        .flow-caption { font-size: 12px; color: #667781; padding: 0 14px; }
        .flow-link { color: #00a884; text-decoration: underline; cursor: pointer; font-size: 14px; padding: 8px 0; display: inline-block; }
        .flow-link:hover { color: #008069; }
        .optin-label { display: flex; align-items: center; gap: 10px; padding: 4px 0; cursor: pointer; font-size: 14px; }
        .optin-label input[type=checkbox] { width: 18px; height: 18px; accent-color: #00a884; }
    </style>
</head>
<body>

<div class="wa-shell">
        <div class="wa-header">
            <div class="avatar">{{ $mode === 'customer' ? '👤' : '🤖' }}</div>
            <div class="contact-info">
                <div class="contact-name">Magetsi {{ $mode === 'customer' ? 'Customer' : 'Bot' }}</div>
                <div class="contact-status">{{ $mode === 'customer' ? 'Customer Service' : 'online' }}</div>
            </div>
        <div class="header-icons">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.9 14.3H15l-.3-.3c1-1.1 1.6-2.7 1.6-4.3 0-3.7-3-6.7-6.7-6.7S3 6 3 9.7s3 6.7 6.7 6.7c1.6 0 3.2-.6 4.3-1.6l.3.3v.8l5.1 5.1 1.5-1.5-5-5.2zm-6.2 0c-2.6 0-4.6-2.1-4.6-4.6s2.1-4.6 4.6-4.6 4.6 2.1 4.6 4.6-2 4.6-4.6 4.6z"/></svg>
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7a2 2 0 1 0-.001-4.001A2 2 0 0 0 12 7zm0 2a2 2 0 1 0-.001 3.999A2 2 0 0 0 12 9zm0 6a2 2 0 1 0-.001 3.999A2 2 0 0 0 12 15z"/></svg>
        </div>
    </div>

    <div class="wa-chat" id="chat">
        <div class="sys-pill">🔒 Messages and calls are end-to-end encrypted.</div>
        <div class="date-pill">TODAY</div>
    </div>

    <div id="flowContainer"></div>

    <div class="wa-input-bar">
        <button class="icon-btn">😊</button>
        <button class="icon-btn">📎</button>
        <input type="text" class="text-field" id="chatInput" placeholder="Type a message" autocomplete="off">
        <button class="send-btn" onclick="sendMessage()">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M1.101 21.757 23.8 12.028 1.101 2.3l.011 7.912 13.623 1.816-13.623 1.817-.011 7.912z"/></svg>
        </button>
    </div>
</div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const chat = document.getElementById('chat');
const chatInput = document.getElementById('chatInput');
const flowContainer = document.getElementById('flowContainer');
let lastSender = null;

// ── API helpers ──
const SIM_MODE = '{{ $mode }}';

async function api(action, payload = {}) {
    const res = await fetch('{{ route('admin.simulate.post') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ action, payload, mode: SIM_MODE }),
    });
    return res.json();
}
function delay(ms) { return new Promise(r => setTimeout(r, ms)); }
function now() { return new Date().toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }); }

// ── Send text message ──
async function sendMessage() {
    const text = chatInput.value.trim();
    if (!text) return;
    addBubble(text, 'out');
    chatInput.value = '';
    await delay(350);
    const data = await api('text', { text });
    renderMessages(data.messages);
}
chatInput.addEventListener('keydown', e => { if (e.key === 'Enter') sendMessage(); });

// ── Render bot messages ──
function renderMessages(messages) {
    for (const msg of messages) {
        switch (msg.type) {
            case 'text': addBubble(fmt(msg.text), 'in'); break;
            case 'buttons':
                if (msg.text) addBubble(fmt(msg.text), 'in');
                addReplyButtons(msg.buttons);
                break;
            case 'flow': addFlowCta(msg); break;
            case 'success': addSuccessCard(msg); break;
        }
    }
}

function fmt(t) {
    return t
        .replace(/\*([^*]+)\*/g, '<strong>$1</strong>')
        .replace(/`([^`]+)`/g, '<span style="font-family:monospace;background:#e9edef;padding:1px 4px;border-radius:3px">$1</span>')
        .replace(/\n/g, '<br>');
}

function addBubble(html, dir) {
    const showTail = lastSender !== dir;
    lastSender = dir;
    const d = document.createElement('div');
    d.className = `msg ${dir}${showTail ? ' tail' : ''}`;
    const ticks = dir === 'out' ? '<span class="ticks">✓✓</span>' : '';
    d.innerHTML = `<div class="bubble">${html}<span class="meta">${now()} ${ticks}</span></div>`;
    chat.appendChild(d);
    scroll();
}

function addReplyButtons(buttons) {
    lastSender = null;
    const d = document.createElement('div');
    d.className = 'reply-btns';
    buttons.forEach(b => {
        const btn = document.createElement('button');
        btn.className = 'rbtn';
        btn.textContent = b.title;
        btn.onclick = () => handleButton(b.id, b.title);
        d.appendChild(btn);
    });
    chat.appendChild(d);
    scroll();
}

function addFlowCta(msg) {
    const showTail = lastSender !== 'in';
    lastSender = 'in';
    const d = document.createElement('div');
    d.className = `flow-cta-msg${showTail ? ' tail' : ''}`;
    const bodyText = msg.text ? fmt(msg.text) : '';
    const ctaLabel = msg.cta || msg.flow_id.replace(/_/g, ' ');

    d.innerHTML = `<div class="cta-bubble">
        ${bodyText ? `<div class="cta-body">${bodyText}<span class="meta">${now()}</span></div>` : ''}
        <button class="cta-btn" onclick="openFlow('${esc(msg.flow_id)}')">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 7h-8v6H5v-4L1 13l4 4v-4h8v6h6V7zm0 10h-6V9h6v8z"/></svg>
            ${esc(ctaLabel)}
        </button>
    </div>`;
    chat.appendChild(d);
    scroll();
}

function addSuccessCard(msg) {
    lastSender = 'in';
    const d = document.createElement('div');
    d.className = 'success-msg';
    let rows = msg.data.map(r =>
        `<div class="card-row"><span class="lbl">${r.label}</span><span class="val${r.highlight ? ' token' : ''}">${r.value}</span></div>`
    ).join('');
    d.innerHTML = `<div class="card">
        <div class="card-header"><span class="icon">✅</span><span class="title">Transaction Successful</span></div>
        <div class="card-details">${rows}</div>
        ${msg.sms_note ? `<div class="sms-note">📱${msg.sms_note}</div>` : ''}
        <span class="meta" style="float:right;font-size:11px;color:rgba(17,27,33,.45);padding-top:4px">${now()} <span style="color:#53bdeb;font-size:15px;letter-spacing:-3px">✓✓</span></span>
    </div>`;
    chat.appendChild(d);
    scroll();
}

function scroll() { requestAnimationFrame(() => chat.scrollTop = chat.scrollHeight); }

async function handleButton(id, label) {
    addBubble(label, 'out');
    await delay(300);
    const data = await api('button', { button_id: id });
    renderMessages(data.messages);
}

// ══════════════════════════════════════════════
//  GENERIC FLOW RENDERER — reads Flow JSON
// ══════════════════════════════════════════════
let activeFlowId = null;
let flowFormState = {};
let flowFooter = null; // { label, payload keys }
let meterTimer = null;
let meterValid = false;
let flowSchema = null;       // full schema for multi-screen navigation
let flowScreensMap = {};     // screen name → screen object
let currentScreenName = null;

function closeFlow() {
    flowContainer.innerHTML = '';
    activeFlowId = null;
    flowSchema = null;
    flowScreensMap = {};
    currentScreenName = null;
}

async function openFlow(flowId) {
    activeFlowId = flowId;
    flowFormState = {};
    meterValid = false;

    // Fetch schema from backend
    const res = await fetch('/admin/simulate/flow/' + flowId + '?mode=' + SIM_MODE);
    const fetched = await res.json();
    flowSchema = fetched.schema;

    // Build screen map for multi-screen navigation
    flowScreensMap = {};
    for (const s of flowSchema.screens) {
        flowScreensMap[s.id] = s;
    }

    const screen = flowSchema.screens[0]; // start at first screen
    const initial_data = fetched.initial_data || {};

    // Pre-fill form state with initial data
    for (const [key, value] of Object.entries(initial_data)) {
        flowFormState[key] = value;
    }

    renderFlowScreen(screen.title, screen.name, screen.layout.children, flowFormState);
}

function renderFlowScreen(title, screenName, children, data) {
    currentScreenName = screenName;

    let bodyHTML = '';
    let footerLabel = 'Submit';
    let footerPayloadKeys = [];

    for (const child of children) {
        bodyHTML += renderComponent(child, data);
        if (child.type === 'Footer') {
            footerLabel = child.label || 'Submit';
            if (child['on-click-action']?.payload) {
                footerPayloadKeys = Object.keys(child['on-click-action'].payload);
            }
        }
    }

    flowFooter = { label: footerLabel, payloadKeys: footerPayloadKeys };

    flowContainer.innerHTML = `
    <div class="flow-sheet" onclick="if(event.target===this)closeFlow()">
      <div class="flow-panel">
        <div class="flow-panel-header">
          <button class="back-btn" onclick="closeFlow()">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z" transform="rotate(180 12 12)"/></svg>
          </button>
          <div>
            <div class="flow-title">${esc(title)}</div>
            <div class="flow-from">Magetsi ${SIM_MODE === 'customer' ? 'Customer' : 'Agents'}</div>
          </div>
        </div>
        <div class="flow-panel-body">${bodyHTML}</div>
        <div class="flow-panel-footer">
          <button class="flow-submit disabled" id="fSubmit" onclick="submitFlow()">${footerLabel}</button>
        </div>
      </div>
    </div>`;

    updateSubmitState();
}

function navigateTo(screenName) {
    if (!flowScreensMap[screenName]) {
        closeFlow();
        addBubble('⚠️ Screen not found: ' + screenName, 'in');
        return;
    }
    const screen = flowScreensMap[screenName];
    renderFlowScreen(screen.title, screen.name, screen.layout.children, flowFormState);
}

// ── Component renderers ──────────────────
function renderComponent(c, data) {
    switch (c.type) {
        case 'TextHeading':
            return `<div class="flow-card"><div class="flow-heading">${esc(resolveValue(c.text, data))}</div></div>`;

        case 'TextSubheading':
            return `<div class="flow-subheading" style="padding:0 14px">${esc(resolveValue(c.text, data))}</div>`;

        case 'TextBody':
            return `<div class="flow-body-text" style="padding:0 14px">${esc(resolveValue(c.text, data))}</div>`;

        case 'TextCaption':
            return `<div class="flow-card"><div class="flow-caption">${esc(resolveValue(c.text, data))}</div></div>`;

        case 'TextInput':
            return renderTextInput(c, data);

        case 'RadioButtonsGroup':
            return renderRadioGroup(c, data);

        case 'Dropdown':
            return renderDropdown(c, data);

        case 'CheckboxGroup':
            return renderCheckboxGroup(c, data);

        case 'TextArea':
            return renderTextArea(c, data);

        case 'NavigationList':
            return renderNavigationList(c, data);

        case 'Image':
            return `<div class="flow-card" style="padding:0"><img src="${esc(c.src)}" class="flow-image" alt=""></div>`;

        case 'OptIn':
            return renderOptIn(c);

        case 'EmbeddedLink':
            return renderEmbeddedLink(c);

        case 'If':
            return renderIf(c, data);

        case 'Form':
            return renderForm(c, data);

        case 'Footer':
            return ''; // handled separately

        default:
            return `<div class="flow-card"><div class="flow-body-text" style="color:#ea4335">Unsupported: ${c.type}</div></div>`;
    }
}

function resolveValue(template, data) {
    if (typeof template !== 'string') return template;
    const m = template.match(/^\${([^}]+)}$/);
    if (!m) return template;
    const path = m[1].trim();
    const parts = path.split('.');
    // ${data.field} → data; ${form.field} → flowFormState
    const source = (parts[0] === 'form') ? flowFormState : data;
    const start = (parts[0] === 'data' || parts[0] === 'form') ? 1 : 0;
    let val = source;
    for (let i = start; i < parts.length; i++) {
        if (val && typeof val === 'object') val = val[parts[i]];
        else return '';
    }
    return (val === undefined || val === null) ? '' : val;
}

function evaluateCondition(condition, data) {
    if (!condition) return false;
    // Handle ${data.field} != ''
    const m = condition.match(/^\${([^}]+)}\s*!=\s*''$/);
    if (m) {
        const val = resolveValue('${' + m[1] + '}', data);
        return val !== '';
    }
    // Handle ${data.field} (truthy check)
    const m2 = condition.match(/^\${([^}]+)}$/);
    if (m2) {
        const val = resolveValue(condition, data);
        return val === 'true' || val === true || val === '1' || val === 1;
    }
    return false;
}

function renderForm(c, data) {
    // Apply init-values to form state
    if (c['init-values']) {
        for (const [key, val] of Object.entries(c['init-values'])) {
            let resolved = resolveValue(val, data);
            if (resolved) flowFormState[key] = resolved;
        }
    }
    // Render children
    let html = '';
    for (const child of (c.children || [])) {
        html += renderComponent(child, data);
    }
    return html;
}

function renderIf(c, data) {
    const condResult = evaluateCondition(c.condition, data);
    const branch = condResult ? (c.then || []) : (c.else || []);
    let html = '';
    for (const child of branch) {
        html += renderComponent(child, data);
    }
    return html;
}

function renderEmbeddedLink(c) {
    const payload = JSON.stringify(c['on-click-action'] || {}).replace(/'/g, "\\'");
    return `<div class="flow-card"><a href="#" class="flow-link" onclick="handleEmbeddedLink('${esc(c.text)}', '${esc(payload)}');return false">${esc(c.text)}</a></div>`;
}

function renderOptIn(c) {
    return `<div class="flow-card">
        <label class="optin-label">
            <input type="checkbox" name="${esc(c.name)}" onchange="flowFormState['${esc(c.name)}'] = this.checked ? 'true' : ''; updateSubmitState()">
            <span>${esc(c.label)}${c.required ? '<span class="flow-required"> *</span>' : ''}</span>
        </label>
    </div>`;
}

function renderNavigationList(c, data) {
    let items = c['list-items'] || [];
    if (typeof items === 'string' && items.startsWith('${')) {
        const resolved = resolveValue(items, data);
        items = Array.isArray(resolved) ? resolved : [];
    }
    let html = `<div class="flow-card" style="padding:0">`;
    if (c.label) {
        html += `<div class="flow-label" style="padding:12px 14px 0 14px">${esc(c.label)}</div>`;
    }
    for (let i = 0; i < items.length; i++) {
        const item = items[i];
        const start = item.start || {};
        const main = item['main-content'] || {};
        const onClick = item['on-click-action'] || {};
        const nextScreen = onClick.next?.name;
        const nextPayload = onClick.payload || {};

        // Build inline style from image if present
        let imgHTML = '';
        if (start.image) {
            // Meta sends a data: URI or URL; render as small icon
            imgHTML = `<div class="nav-img" style="background-image:url('${esc(start.image)}')"></div>`;
        }

        const payloadEnc = encodeURIComponent(JSON.stringify(nextPayload));
        const actionName = onClick.name || 'navigate';
        let clickAttr = '';
        if (actionName === 'navigate' && nextScreen) {
            clickAttr = `onclick="handleNavClick('${nextScreen}', '${esc(main.title || '')}', '${payloadEnc}')"`;
        } else if (actionName === 'data_exchange') {
            clickAttr = `onclick="handleNavDataExchange('${esc(main.title || '')}', '${payloadEnc}')"`;
        }

        html += `<div class="nav-list-item" ${clickAttr}>
            ${imgHTML}
            <div class="nav-item-content">
                <div class="nav-item-title">${esc(main.title || '')}</div>
                ${main.description ? `<div class="nav-item-desc">${esc(main.description)}</div>` : ''}
            </div>
            <svg class="nav-chevron" viewBox="0 0 24 24" fill="currentColor"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>
        </div>`;
    }
    html += '</div>';
    return html;
}

function handleNavClick(screenName, label, payloadEnc) {
    addBubble(label, 'out');
    // Merge navigation payload into form state so target screen has the data
    if (payloadEnc) {
        const payload = JSON.parse(decodeURIComponent(payloadEnc));
        for (const [k, v] of Object.entries(payload)) {
            flowFormState[k] = v;
        }
    }
    setTimeout(() => navigateTo(screenName), 300);
}

async function handleNavDataExchange(label, payloadEnc) {
    addBubble(label, 'out');
    await delay(300);
    const payload = JSON.parse(decodeURIComponent(payloadEnc));
    const data = await api('data_exchange', payload);
    if (data && data.data) {
        for (const [k, v] of Object.entries(data.data)) {
            flowFormState[k] = v;
        }
        if (data.screen) {
            navigateTo(data.screen);
        } else if (currentScreenName && flowScreensMap[currentScreenName]) {
            const screen = flowScreensMap[currentScreenName];
            renderFlowScreen(screen.title, screen.name, screen.layout.children, flowFormState);
        }
    }
}

async function handleEmbeddedLink(text, payloadStr) {
    addBubble(text, 'out');
    await delay(300);
    const payload = JSON.parse(payloadStr);
    const data = await api('data_exchange', payload.payload || {});
    if (data && data.data) {
        for (const [k, v] of Object.entries(data.data)) {
            flowFormState[k] = v;
        }
    }
    if (data && data.messages) {
        renderMessages(data.messages);
    }
    // Re-render the current screen with updated data
    if (currentScreenName && flowScreensMap[currentScreenName]) {
        const screen = flowScreensMap[currentScreenName];
        renderFlowScreen(screen.title, screen.name, screen.layout.children, flowFormState);
    }
}

function renderTextInput(c, data) {
    const name = c.name;
    const initVal = data[name] || '';
    const required = c.required ? '<span class="flow-required"> *</span>' : '';
    const inputType = c['input-type'] === 'number' ? 'number' : (c['input-type'] === 'phone' ? 'tel' : 'text');
    const isMeter = name === 'meter_number';

    return `<div class="flow-card">
        <div class="flow-label">${esc(c.label)}${required}</div>
        <input type="${inputType}" class="flow-input${isMeter ? ' mono' : ''}"
               id="f_${name}" name="${name}"
               value="${esc(initVal)}"
               placeholder="${esc(c['helper-text'] || '')}"
               oninput="onFieldInput('${name}', this)"
               ${isMeter ? 'maxlength="11"' : ''}>
        ${isMeter ? `<div id="meter_fb"></div><div class="flow-hint" id="meter_count">0/11 digits</div>` : ''}
        ${!isMeter && c['helper-text'] ? `<div class="flow-hint">${esc(c['helper-text'])}</div>` : ''}
    </div>`;
}

function renderRadioGroup(c, data) {
    const name = c.name;
    let options = c['data-source'] || [];
    if (typeof options === 'string' && options.startsWith('${')) {
        const resolved = resolveValue(options, data);
        options = Array.isArray(resolved) ? resolved : [];
    }
    const initVal = data[name] || '';

    let html = `<div class="flow-card">
        <div class="flow-label">${esc(c.label || name)}${c.required ? '<span class="flow-required"> *</span>' : ''}</div>
        <div class="radio-group" id="rg_${name}">`;

    for (const opt of options) {
        const sel = opt.id === initVal ? ' selected' : '';
        html += `<div class="radio-option${sel}" data-name="${name}" data-value="${esc(opt.id)}" onclick="selectRadio('${name}', '${esc(opt.id)}', this)">
            <div class="radio-dot"></div>
            <span>${esc(opt.title)}</span>
        </div>`;
    }
    html += '</div></div>';
    return html;
}

function renderDropdown(c, data) {
    const name = c.name;
    let options = c['data-source'] || [];
    if (typeof options === 'string' && options.startsWith('${')) {
        const resolved = resolveValue(options, data);
        options = Array.isArray(resolved) ? resolved : [];
    }
    const initVal = data[name] || '';

    let html = `<div class="flow-card">
        <div class="flow-label">${esc(c.label || name)}${c.required ? '<span class="flow-required"> *</span>' : ''}</div>
        <select class="flow-input" id="f_${name}" onchange="onFieldInput('${name}', this)">
            <option value="">Select…</option>`;
    for (const opt of options) {
        html += `<option value="${esc(opt.id)}"${opt.id === initVal ? ' selected' : ''}>${esc(opt.title)}</option>`;
    }
    html += '</select></div>';
    return html;
}

function renderCheckboxGroup(c, data) {
    const name = c.name;
    let html = `<div class="flow-card">
        <div class="flow-label">${esc(c.label || name)}</div>`;
    for (const opt of (c['data-source'] || [])) {
        html += `<label style="display:flex;align-items:center;gap:8px;padding:6px 0;cursor:pointer">
            <input type="checkbox" value="${esc(opt.id)}" onchange="onCheckbox('${name}')"> ${esc(opt.title)}
        </label>`;
    }
    html += '</div>';
    return html;
}

function renderTextArea(c, data) {
    const name = c.name;
    const initVal = data[name] || '';
    return `<div class="flow-card">
        <div class="flow-label">${esc(c.label || name)}${c.required ? '<span class="flow-required"> *</span>' : ''}</div>
        <textarea class="flow-input" id="f_${name}" rows="3" placeholder="${esc(c['helper-text'] || '')}"
            oninput="onFieldInput('${name}', this)">${esc(initVal)}</textarea>
    </div>`;
}

// ── Field event handlers ──────────────────
function onFieldInput(name, el) {
    flowFormState[name] = el.value;

    // Special: meter number validation
    if (name === 'meter_number') {
        const digits = el.value.replace(/\D/g, '').slice(0, 11);
        el.value = digits;
        flowFormState[name] = digits;
        const countEl = document.getElementById('meter_count');
        if (countEl) countEl.textContent = `${digits.length}/11 digits`;
        el.className = el.className.replace(/ valid| invalid/g, '');
        clearTimeout(meterTimer);
        meterValid = false;
        const fb = document.getElementById('meter_fb');
        if (digits.length === 11) {
            if (fb) fb.innerHTML = '<div class="meter-fb checking"><span class="spin"></span> Validating meter…</div>';
            meterTimer = setTimeout(async () => {
                const r = await api('validate_meter', { meter_number: digits });
                if (r.valid) {
                    meterValid = true;
                    el.classList.add('valid');
                    if (fb) fb.innerHTML = `<div class="meter-fb valid">✅ <div><div class="cust-name">${r.name}</div><div style="font-size:12px">${r.address}</div></div></div>`;
                } else {
                    el.classList.add('invalid');
                    if (fb) fb.innerHTML = `<div class="meter-fb invalid">❌ ${r.error}</div>`;
                }
                updateSubmitState();
            }, 1200);
        } else {
            if (fb) fb.innerHTML = '';
        }
    }

    updateSubmitState();
}

function selectRadio(name, value, el) {
    flowFormState[name] = value;
    document.querySelectorAll(`#rg_${name} .radio-option`).forEach(o => o.classList.remove('selected'));
    el.classList.add('selected');
    updateSubmitState();
}

function onCheckbox(name) {
    const checked = [...document.querySelectorAll(`input[type=checkbox][onchange*="${name}"]:checked`)].map(c => c.value);
    flowFormState[name] = checked;
    updateSubmitState();
}

// ── Submit state ──
function updateSubmitState() {
    const btn = document.getElementById('fSubmit');
    if (!btn || !flowFooter) return;

    // Check if required fields from the footer payload are filled
    let ready = true;
    for (const key of flowFooter.payloadKeys) {
        const val = flowFormState[key];
        // Skip optional fields (those without a value are OK if not required)
        if (!val && val !== 0) {
            // Check if this field was required in the schema
            // For simplicity, we check if a value exists in formState
            // The meter_number field has special validation
            if (key === 'meter_number') {
                ready = false;
            }
            // Non-required fields like custom_amount, recipient_phone can be empty
        }
    }

    // meter_number special: must be validated
    if (flowFormState.meter_number !== undefined && flowFormState.meter_number.length === 11 && !meterValid) {
        ready = false;
    }
    if (flowFormState.meter_number !== undefined && flowFormState.meter_number.length < 11) {
        ready = false;
    }

    btn.className = ready ? 'flow-submit active' : 'flow-submit disabled';
    btn.textContent = ready ? flowFooter.label : flowFooter.label;
}

// ── Submit flow ──
async function submitFlow() {
    const btn = document.getElementById('fSubmit');
    if (btn.classList.contains('disabled')) return;

    btn.textContent = 'Processing…';
    btn.className = 'flow-submit disabled';

    // Build payload from form state
    const payload = { flow_id: activeFlowId };
    for (const key of flowFooter.payloadKeys) {
        payload[key] = flowFormState[key] || '';
    }

    const flowId = activeFlowId;
    closeFlow();

    // Build a summary bubble
    const summaryParts = [];
    for (const [k, v] of Object.entries(payload)) {
        if (k === 'flow_id' || !v) continue;
        summaryParts.push(`${k.replace(/_/g, ' ')}: ${v}`);
    }
    addBubble(summaryParts.slice(0, 3).join(' · '), 'out');

    await delay(600);
    const data = await api('flow_complete', payload);
    renderMessages(data.messages);
}

function esc(s) {
    if (typeof s !== 'string') return s;
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ── Boot ──
window.addEventListener('DOMContentLoaded', async () => {
    const data = await api('start', { text: 'hi' });
    renderMessages(data.messages);
});
</script>
</body>
</html>
