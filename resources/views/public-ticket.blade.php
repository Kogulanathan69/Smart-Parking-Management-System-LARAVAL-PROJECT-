<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive"><title>Digital Parking Ticket - ParkFlow</title>
    <style>
        :root{font-family:Segoe UI,system-ui,sans-serif;color:#102a23;background:#eef9f3}*{box-sizing:border-box}
        body{margin:0;padding:20px 14px}.wrap{max-width:560px;margin:auto}.hero{background:linear-gradient(145deg,#064e3b,#087f46);color:#fff;padding:24px;border-radius:22px 22px 10px 10px}
        .brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:21px}.logo{display:grid;place-items:center;width:42px;height:42px;background:#16c766;border-radius:12px;font-size:26px}
        h1{margin:25px 0 6px;font-size:25px}.muted{color:#64748b}.status{display:inline-block;padding:6px 11px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:700;font-size:13px}
        .plate{font:800 27px Consolas,monospace;letter-spacing:.08em;margin-top:18px}.card{background:white;border:1px solid #dfeae5;border-radius:14px;padding:18px;margin-top:12px;box-shadow:0 5px 20px #064e3b0c}
        h2{font-size:16px;margin:0 0 13px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.label{font-size:12px;color:#64748b}.value{font-weight:650;margin-top:3px}
        .duration{font-size:30px;font-weight:800;color:#066638}.total{font-size:28px;font-weight:800;color:#066638}.line{display:flex;justify-content:space-between;gap:15px;padding:7px 0;border-bottom:1px solid #edf2ef}
        footer{text-align:center;color:#64748b;font-size:12px;padding:20px}.notice{font-size:12px;color:#64748b;margin-top:10px}@media(max-width:380px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body><main class="wrap">
    <header class="hero"><div class="brand"><span class="logo">P</span><span>ParkFlow</span></div>
        <h1>Digital Parking Ticket</h1><span class="status">{{ str($ticket['status'])->replace('_',' ')->title() }}</span>
        <div class="plate">{{ $ticket['plate'] }}</div><div style="opacity:.8;margin-top:5px">{{ $ticket['ticket_reference'] }}</div>
    </header>
    <section class="card"><h2>Parking information</h2><div class="grid">
        <div><div class="label">Zone</div><div class="value">{{ $ticket['parking']['zone_code'] }} - {{ $ticket['parking']['zone'] }}</div></div>
        <div><div class="label">Slot</div><div class="value">{{ $ticket['parking']['slot'] }}</div></div>
        <div><div class="label">Package</div><div class="value">{{ $ticket['parking']['package'] }}</div></div>
        <div><div class="label">Vehicle</div><div class="value">{{ $ticket['vehicle']['category'] }}</div></div>
        @if($ticket['parking']['billing_mode'])<div><div class="label">Billing basis</div><div class="value">{{ str($ticket['parking']['billing_mode'])->replace('_',' ')->title() }}</div></div>@endif
        @if($ticket['vehicle']['fuel_type'])<div><div class="label">Fuel type</div><div class="value">{{ $ticket['vehicle']['fuel_type'] }}</div></div>@endif
        @if($ticket['vehicle']['make_model'])<div><div class="label">Make / model</div><div class="value">{{ $ticket['vehicle']['make_model'] }}</div></div>@endif
        <div><div class="label">Checked in</div><div class="value" id="entry"></div></div>
        @if($ticket['exit_at'])<div><div class="label">Checked out</div><div class="value" id="exit"></div></div>@endif
    </div></section>
    <section class="card"><h2>{{ $ticket['exit_at'] ? 'Final parking duration' : 'Live parking duration' }}</h2>
        <div class="duration" id="duration">{{ intdiv($ticket['duration_minutes'],60) }}h {{ $ticket['duration_minutes']%60 }}m</div>
        <p class="notice">Server-authoritative time - Asia/Colombo</p></section>
    <section class="card"><h2>{{ $ticket['charges']['estimated'] ? 'Estimated parking charges' : 'Final parking charges' }}</h2>
        @foreach($ticket['charges']['lines'] as $line)<div class="line"><span>{{ $line['label'] }}</span><strong>{{ $line['amount'] }}</strong></div>@endforeach
        <div class="line" style="border:0;margin-top:6px"><strong>Total {{ $ticket['charges']['estimated'] ? 'estimate' : 'billed' }}</strong><span class="total">{{ $ticket['charges']['total'] }}</span></div>
        <p class="notice">{{ $ticket['charges']['estimated'] ? 'This estimate may change until checkout. Staff calculate and confirm the final amount.' : 'Payment status: '.str($ticket['payment_status'])->replace('_',' ')->title() }}</p>
        @if($ticket['receipt_reference'])<p class="notice">Receipt: {{ $ticket['receipt_reference'] }}</p>@endif
    </section>
    <footer>This page is view-only. It cannot make payments or open a gate.<br>Last updated <span id="updated"></span>.</footer>
</main>
<script>
const entry=new Date(@json($ticket['entry_at'])), exit=@json($ticket['exit_at'])?new Date(@json($ticket['exit_at'])):null;
const serverNow=new Date(@json($ticket['server_now'])); const started=performance.now();
const fmt=d=>new Intl.DateTimeFormat('en-LK',{dateStyle:'medium',timeStyle:'short',timeZone:'Asia/Colombo'}).format(d);
document.getElementById('entry').textContent=fmt(entry); if(exit)document.getElementById('exit').textContent=fmt(exit);
document.getElementById('updated').textContent=fmt(serverNow);
function tick(){const end=exit||new Date(serverNow.getTime()+(performance.now()-started));const mins=Math.max(0,Math.floor((end-entry)/60000));document.getElementById('duration').textContent=Math.floor(mins/60)+'h '+mins%60+'m'}
tick();setInterval(tick,30000);if(!exit)setTimeout(()=>location.reload(),60000);
</script></body></html>
