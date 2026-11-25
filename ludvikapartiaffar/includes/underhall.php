<?php
// Underhållsläge: besökare och kunder ser en underhållssida, personalen loggar in och jobbar som vanligt.
// Läget är på tills en administratör stänger av det under Inställningar → Webbplats.

// Sidor som alltid fungerar, så att personalen kan logga in.
const UNDERHALL_OPPNA = ['logga-in', 'logga-ut', 'glomt-losenord', 'aterstall-losenord', 'setup'];
const UNDERHALL_STANDARDTEXT = 'Vi bygger om vår webbplats och öppnar snart igen. Under tiden tar vi emot beställningar och frågor som vanligt på telefon och e‑post.';

function underhall_pa(): bool {
  try {
    return installning('underhall') !== '0';
  } catch (PDOException $e) {
    // Tabellen finns inte än: räkna läget som på.
    return true;
  }
}

function underhall_text(): string {
  try {
    $t = trim((string)installning('underhall_text', ''));
  } catch (PDOException $e) {
    $t = '';
  }
  return $t !== '' ? $t : UNDERHALL_STANDARDTEXT;
}

// Visar underhållssidan för alla utom personalen och stoppar sidan.
function underhall_vakt(): void {
  if (PHP_SAPI === 'cli') return;
  $sida = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
  if (in_array($sida, UNDERHALL_OPPNA, true) || !underhall_pa()) return;
  if (ar_personal(current_user())) return;
  underhall_sida();
}

function underhall_sida(): never {
  global $CONFIG;
  while (ob_get_level() > 0) ob_end_clean();
  http_response_code(503);
  header('Retry-After: 3600');
  header('X-Robots-Tag: noindex');
  header('Content-Type: text/html; charset=utf-8');
  header('Cache-Control: no-store');
  $epost = filter_var($CONFIG['mail_to'] ?? '', FILTER_VALIDATE_EMAIL) ? $CONFIG['mail_to'] : '';
  $loggain = adress('logga-in');
  ?><!doctype html>
<html lang="sv">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Ludvika Partiaffär – vi uppdaterar webbplatsen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap">
<style>
  :root{--bla:#0051BA; --gul:#FFDA1A; --bg:#001233; --ink:#EDF1F9; --mjuk:#A9B6D3;}
  *{box-sizing:border-box;}
  html,body{margin:0; min-height:100%;}
  body{font-family:'Outfit', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color:var(--ink); background:var(--bg);
    background:radial-gradient(55% 45% at 85% 5%, rgba(255,218,26,.30), transparent 70%),
               radial-gradient(70% 60% at 10% 100%, rgba(0,81,186,.85), transparent 75%),
               linear-gradient(160deg, #00378C 0%, #002260 55%, #001233 100%);
    background-attachment:fixed; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px;}
  .kort{width:100%; max-width:560px; background:rgba(10,20,45,.55); border:1px solid rgba(255,255,255,.1); border-radius:26px;
    padding:40px 34px; text-align:center; -webkit-backdrop-filter:blur(22px); backdrop-filter:blur(22px); box-shadow:0 30px 70px -30px rgba(0,0,0,.7);}
  .logo{display:inline-block; background:#fff; border-radius:14px; padding:10px 16px; margin-bottom:26px;}
  .logo img{display:block; height:56px; width:auto;}
  .etikett{display:inline-block; background:var(--gul); color:#1B1D22; font-weight:700; font-size:.82rem; letter-spacing:.04em; text-transform:uppercase; padding:6px 12px; border-radius:999px;}
  h1{font-size:clamp(1.7rem, 5vw, 2.2rem); line-height:1.15; margin:16px 0 12px;}
  p{color:var(--mjuk); line-height:1.6; font-size:1.02rem; margin:0 auto; max-width:44ch; white-space:pre-line;}
  .kontakt{display:flex; flex-wrap:wrap; gap:10px; justify-content:center; margin-top:28px;}
  .kontakt a{display:inline-flex; align-items:center; min-height:44px; padding:10px 20px; border-radius:999px; font-weight:700; text-decoration:none;}
  .ring{background:var(--gul); color:#1B1D22;}
  .mejl{border:2px solid rgba(255,255,255,.8); color:#fff;}
  .personal{margin-top:30px; font-size:.86rem; color:var(--mjuk);}
  .personal a{color:#fff;}
</style>
</head>
<body>
  <main class="kort">
    <span class="logo"><img src="img/logo.png" alt="Ludvika Partiaffär"></span><br>
    <span class="etikett">Webbplatsen uppdateras</span>
    <h1>Vi är snart tillbaka</h1>
    <p><?php echo e(underhall_text()); ?></p>
    <div class="kontakt">
      <a class="ring" href="tel:+4624018355">Ring 0240-183 55</a>
      <?php if ($epost !== ''): ?><a class="mejl" href="mailto:<?php echo e($epost); ?>">Mejla oss</a><?php endif; ?>
    </div>
    <p class="personal">Mån–fre 06:00–14:30 &middot; Fläderstigen 2, Ludvika<br>Personal? <a href="<?php echo e($loggain); ?>">Logga in</a></p>
  </main>
</body>
</html>
<?php
  exit;
}
