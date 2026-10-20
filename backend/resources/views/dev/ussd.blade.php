<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>USSD simulator</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f3f4f6; display: flex; justify-content: center; padding: 24px; }
        .phone { width: 320px; background: #111827; padding: 16px; }
        .screen { background: #d1fae5; color: #064e3b; min-height: 220px; padding: 12px; white-space: pre-wrap; font-family: monospace; }
        input, button { width: 100%; padding: 10px; margin-top: 8px; font-size: 1rem; box-sizing: border-box; }
        label { color: #9ca3af; font-size: .85rem; display: block; margin-top: 8px; }
    </style>
</head>
<body>
<div class="phone">
    <label for="phone">Farmer's number</label>
    <input id="phone" value="0244445566">
    <button id="start">Dial</button>
    <div class="screen" id="screen">Local test page. No real gateway.</div>
    <input id="reply" placeholder="Reply" disabled>
    <button id="send" disabled>Send</button>
</div>
<script>
    const $ = (id) => document.getElementById(id);
    let session = null;

    async function dial(input, isNew) {
        const response = await fetch('/dev/ussd/dial', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
            body: JSON.stringify({ phone: $('phone').value, session, new: isNew, input }),
        });
        const reply = await response.json();

        $('screen').textContent = reply.message ?? 'Something went wrong.';
        $('reply').disabled = $('send').disabled = !reply.continueSession;
        $('reply').value = '';
        if (reply.continueSession) $('reply').focus();
    }

    $('start').onclick = () => { session = 'sim-' + Math.random().toString(36).slice(2, 10); dial('', true); };
    $('send').onclick = () => dial($('reply').value, false);
    $('reply').onkeydown = (event) => { if (event.key === 'Enter') $('send').click(); };
</script>
</body>
</html>
