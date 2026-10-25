<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $point->name }} — clock in</title>
    {{-- Drawn in the browser: no QR library is installed server-side, and this
         screen is a tablet with a browser on it anyway. --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 28px; padding: 32px;
            font: 16px -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0f172a; color: #e2e8f0; text-align: center;
        }
        h1 { margin: 0; font-size: clamp(1.4rem, 4vw, 2.2rem); font-weight: 800; letter-spacing: -.01em; }
        .where { margin: 0; color: #94a3b8; font-size: 15px; }
        .frame { background: #fff; padding: 20px; border-radius: 24px; line-height: 0; }
        .hint { margin: 0; color: #94a3b8; font-size: 15px; max-width: 36ch; }
        .bar { width: min(320px, 70vw); height: 6px; background: #1e293b; border-radius: 99px; overflow: hidden; }
        .bar span { display: block; height: 100%; background: #38bdf8; transition: width .95s linear; }
        .stale { opacity: .25; transition: opacity .3s; }
        footer { position: fixed; bottom: 14px; color: #475569; font-size: 12px; }
    </style>
</head>
<body>
    <div>
        <h1>{{ $point->name }}</h1>
        @if($point->officeLocation)
            <p class="where">{{ $point->officeLocation->name }}</p>
        @endif
    </div>

    <div class="frame" id="frame"><div id="qr"></div></div>

    <div class="bar"><span id="bar" style="width:100%"></span></div>
    <p class="hint">Point your phone camera at the code. It changes every {{ $point->window() }} seconds, so scan it here rather than saving it.</p>

    <footer>Leave this screen open. Close it and nobody can clock in here.</footer>

    <script>
        const el = document.getElementById('qr');
        const frame = document.getElementById('frame');
        const bar = document.getElementById('bar');
        const size = Math.min(340, Math.floor(Math.min(innerWidth, innerHeight) * 0.5));
        let qr = new QRCode(el, { text: ' ', width: size, height: size, correctLevel: QRCode.CorrectLevel.M });
        let seconds = 0, window_ = {{ $point->window() }};

        async function refresh() {
            try {
                const r = await fetch(@json(route('qr-clock.token', $point)), { headers: { 'Accept': 'application/json' } });
                if (!r.ok) throw new Error(r.status);
                const data = await r.json();
                el.innerHTML = '';
                qr = new QRCode(el, { text: data.url, width: size, height: size, correctLevel: QRCode.CorrectLevel.M });
                frame.classList.remove('stale');
                window_ = data.window;
                seconds = data.next_in;
            } catch (e) {
                // Lost the server: fade the code rather than show one that may
                // no longer be accepted.
                frame.classList.add('stale');
                seconds = 5;
            }
        }

        setInterval(() => {
            seconds -= 1;
            bar.style.width = Math.max(0, (seconds / window_) * 100) + '%';
            if (seconds <= 0) refresh();
        }, 1000);

        refresh();
    </script>
</body>
</html>
