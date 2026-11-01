<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Stockholm');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
  http_response_code(500);
  exit('Sajten är inte konfigurerad: includes/config.php saknas.');
}
$CONFIG = require $configFile;

const ORDER_STATUS = [
  'ny'        => 'Ny',
  'bekraftad' => 'Bekräftad',
  'levererad' => 'Levererad',
  'avbruten'  => 'Avbruten',
];
const MIN_PASSWORD = 10;

// Kundgrupper, samma bokstäver som i affärssystemet.
const KUNDGRUPPER = [
  'A' => 'Kommun',
  'L' => 'Restaurang / pizzeria',
  'B' => 'Livsbutik',
];
// Veckans erbjudanden syns bara för inloggade kunder med tillgång. Utan eget val
// följer tillgången kundgruppen: butiker ser Superklipp, restauranger och pizzerior Veckansklipp.
const ERBJUDANDEN = [
  'superklipp'   => ['namn' => 'Superklipp',   'sida' => 'superklipp.php',   'grupper' => ['B']],
  'veckansklipp' => ['namn' => 'Veckansklipp', 'sida' => 'veckansklipp.php', 'grupper' => ['L']],
];
const KUNDGRUPP_EXEMPEL = [
  'A' => 'Skola, förskola, äldreboende m.m.',
  'L' => 'Lunch, à la carte eller pizza',
  'B' => 'T.ex. ICA eller Coop',
];

// ---------- helpers ----------

function e($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function enRad($s, int $max): string {
  $s = trim(str_replace(["\r", "\n", "\0"], ' ', (string)$s));
  return mb_substr($s, 0, $max, 'UTF-8');
}

function now(): string {
  return date('Y-m-d H:i:s');
}

function redirect(string $to): never {
  header('Location: ' . $to);
  exit;
}

function flash(?string $msg = null): ?string {
  if ($msg !== null) {
    $_SESSION['flash'] = $msg;
    return null;
  }
  $m = $_SESSION['flash'] ?? null;
  unset($_SESSION['flash']);
  return $m;
}

function datum(string $dt): string {
  return date('Y-m-d H:i', strtotime($dt));
}

function safe_next(?string $next): string {
  $next = (string)$next;
  return preg_match('/^[a-z0-9\-]+\.php(\?[A-Za-z0-9=&%_\-]*)?(#[a-z0-9\-]+)?$/', $next) ? $next : 'konto.php';
}

function client_ip(): string {
  return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function looks_like_bot(): bool {
  $t = (int)($_POST['t'] ?? 0);
  return trim((string)($_POST['webbplats'] ?? '')) !== '' || $t === 0 || (time() - $t) < 3;
}

function bot_fields(): string {
  return '<div class="hp" aria-hidden="true"><label for="webbplats">Lämna tomt</label>'
       . '<input type="text" id="webbplats" name="webbplats" tabindex="-1" autocomplete="off"></div>'
       . '<input type="hidden" name="t" value="' . time() . '">';
}

// ---------- database ----------

function db(): PDO {
  static $pdo = null;
  global $CONFIG;
  if ($pdo === null) {
    $pdo = new PDO(
      'mysql:host=' . $CONFIG['db_host'] . ';dbname=' . $CONFIG['db_name'] . ';charset=utf8mb4',
      $CONFIG['db_user'],
      $CONFIG['db_pass'],
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );
    kundgrupp_kolumner($pdo);
  }
  return $pdo;
}

// Lägger till kolumnen kundgrupp på sajter som installerades innan den fanns.
function kundgrupp_kolumner(PDO $pdo): void {
  // Vem som ser vilket erbjudande. NULL betyder enligt kundgruppen.
  try {
    $pdo->query('SELECT ser_superklipp, ser_veckansklipp FROM users LIMIT 0');
  } catch (PDOException $e) {
    foreach (['ser_superklipp', 'ser_veckansklipp'] as $kolumn) {
      try {
        $pdo->exec("ALTER TABLE users ADD COLUMN $kolumn TINYINT(1) NULL");
      } catch (PDOException $e) {
        // Finns redan, eller så finns tabellen inte än.
      }
    }
  }
  foreach (['users', 'orders'] as $tabell) {
    try {
      $pdo->query("SELECT kundgrupp FROM $tabell LIMIT 0");
    } catch (PDOException $e) {
      try {
        $pdo->exec("ALTER TABLE $tabell ADD COLUMN kundgrupp CHAR(1) NULL AFTER foretag");
      } catch (PDOException $e) {
        // Tabellen finns inte än. setup.php skapar den med kolumnen.
      }
    }
  }
}

function erbjudande_enligt_grupp(string $vilket, ?string $grupp): bool {
  return in_array($grupp, ERBJUDANDEN[$vilket]['grupper'], true);
}

// Ser användaren Superklipp eller Veckansklipp? Admin ser allt.
function ser_erbjudande(?array $user, string $vilket): bool {
  if (!$user) return false;
  if ($user['role'] === 'admin') return true;
  $eget = $user['ser_' . $vilket] ?? null;
  if ($eget !== null) return (int)$eget === 1;
  return erbjudande_enligt_grupp($vilket, $user['kundgrupp'] ?? null);
}

function erbjudanden_text(array $user): string {
  $ser = array_filter(array_keys(ERBJUDANDEN), fn($v) => ser_erbjudande($user, $v));
  return $ser ? implode(' och ', array_map(fn($v) => ERBJUDANDEN[$v]['namn'], $ser)) : 'Inga';
}

// Visas i stället för erbjudandet för gäster och kunder utan tillgång.
function erbjudande_sparr(string $vilket, ?array $user, string $tillbaka, string $intro): string {
  $namn = ERBJUDANDEN[$vilket]['namn'];
  $nasta = rawurlencode($tillbaka);
  $html = '<section class="page-hero"><div class="wrap"><span class="eyebrow">Veckans erbjudanden</span><h1>' . e($namn) . '</h1>'
        . '<p>' . e($intro) . '</p></div></section><section><div class="wrap" style="max-width:760px;"><div class="sk-notis">';
  if (!$user) {
    $html .= '<p><strong>Logga in för att se ' . e($namn) . '.</strong> Erbjudandena är för våra kunder. Har du inget konto skapar du ett gratis på en minut.</p>'
           . '<div class="actions"><a class="btn primary small" href="logga-in.php?next=' . e($nasta) . '">Logga in</a>'
           . '<a class="btn ghost small" href="registrera.php?next=' . e($nasta) . '">Skapa konto</a></div>';
  } else {
    $html .= '<p><strong>' . e($namn) . ' ingår inte i ert konto.</strong> Hör av er om ni vill ta del av erbjudandena, så hjälper vi er.</p>'
           . '<div class="actions"><a class="btn primary small" href="tel:+4624018355">Ring 0240-183 55</a>'
           . '<a class="btn ghost small" href="bestall.php">Till beställning</a></div>';
  }
  return $html . '</div></div></section>';
}

function kundgrupp_text(?string $k): string {
  return $k !== null && isset(KUNDGRUPPER[$k]) ? $k . ' – ' . KUNDGRUPPER[$k] : 'Ej angiven';
}

function kundgrupp_ur_post(): string {
  $k = (string)($_POST['kundgrupp'] ?? '');
  return isset(KUNDGRUPPER[$k]) ? $k : '';
}

// Val av kundgrupp i formulär. Kunden ser namnen, bokstäverna visas bara för admin.
function kundgrupp_falt(string $valt): string {
  $html = '<span class="field-label" aria-hidden="true">Typ av verksamhet <span class="req">*</span></span>'
        . '<div class="check-grid kundgrupp-val" role="radiogroup" aria-label="Typ av verksamhet">';
  foreach (KUNDGRUPPER as $k => $namn) {
    $html .= '<label class="check"><input type="radio" name="kundgrupp" value="' . $k . '"' . ($valt === $k ? ' checked' : '') . ' required>'
           . '<span><strong>' . e($namn) . '</strong><small>' . e(KUNDGRUPP_EXEMPEL[$k]) . '</small></span></label>';
  }
  return $html . '</div>';
}

// ---------- session & auth ----------

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('lpsess');
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => $https,
  'httponly' => true,
  'samesite' => 'Lax',
]);
// Filservern för bladets bilder läser bara sessionen: den låser den inte, startar ingen ny
// och skickar inga no-cache-rubriker, så att webbläsaren kan spara bilderna.
if (!defined('LP_SESSION_LAS')) {
  session_start();
} elseif (isset($_COOKIE[session_name()])) {
  session_start(['read_and_close' => true, 'cache_limiter' => '']);
}

function password_fingerprint(string $hash): string {
  return substr(hash('sha256', $hash), 0, 16);
}

function current_user(): ?array {
  static $user = false;
  if ($user !== false) return $user;
  $user = null;
  $uid = $_SESSION['uid'] ?? null;
  if (!$uid) return null;
  $st = db()->prepare('SELECT * FROM users WHERE id = ? AND active = 1');
  $st->execute([$uid]);
  $row = $st->fetch();
  // Logs out the session if the account was deactivated or its password changed elsewhere.
  if (!$row || !hash_equals($_SESSION['pwh'] ?? '', password_fingerprint($row['password_hash']))) {
    logout_user();
    return null;
  }
  $user = $row;
  return $user;
}

function is_admin(): bool {
  $u = current_user();
  return $u !== null && $u['role'] === 'admin';
}

function login_user(array $user): void {
  session_regenerate_id(true);
  $_SESSION['uid'] = (int)$user['id'];
  $_SESSION['pwh'] = password_fingerprint($user['password_hash']);
  db()->prepare('UPDATE users SET last_login = ? WHERE id = ?')->execute([now(), $user['id']]);
}

function logout_user(): void {
  unset($_SESSION['uid'], $_SESSION['pwh']);
  session_regenerate_id(true);
}

function require_login(): array {
  $u = current_user();
  if (!$u) {
    $next = basename($_SERVER['SCRIPT_NAME']) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
    redirect('logga-in.php?next=' . rawurlencode(safe_next($next)));
  }
  return $u;
}

function require_admin(): array {
  $u = require_login();
  if ($u['role'] !== 'admin') {
    http_response_code(403);
    exit('Du har inte behörighet till den här sidan.');
  }
  return $u;
}

// ---------- CSRF ----------

function csrf_token(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}

function csrf_field(): string {
  return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void {
  if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    http_response_code(400);
    exit('Formuläret hade gått ut. Gå tillbaka, ladda om sidan och försök igen.');
  }
}

// ---------- login throttling ----------

function login_throttled(string $email): bool {
  $since = date('Y-m-d H:i:s', time() - 15 * 60);
  $st = db()->prepare('SELECT
      SUM(email = ?) AS by_email,
      SUM(ip = ?) AS by_ip
    FROM login_attempts WHERE attempted_at > ?');
  $st->execute([$email, client_ip(), $since]);
  $r = $st->fetch();
  return (int)$r['by_email'] >= 5 || (int)$r['by_ip'] >= 20;
}

function record_failed_login(string $email): void {
  db()->prepare('INSERT INTO login_attempts (email, ip, attempted_at) VALUES (?, ?, ?)')
      ->execute([$email, client_ip(), now()]);
  db()->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')
      ->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

function clear_failed_logins(string $email): void {
  db()->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
}

// ---------- mail ----------

function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
  global $CONFIG;
  $from = $CONFIG['mail_to'];
  $headers = [
    'From: =?UTF-8?B?' . base64_encode('Ludvika Partiaffär') . '?= <' . $from . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
  ];
  if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
    $headers[] = 'Reply-To: ' . $replyTo;
  }
  $h = implode("\r\n", $headers);
  $s = '=?UTF-8?B?' . base64_encode(enRad($subject, 200)) . '?=';
  $b = str_replace(["\r\n", "\n"], "\r\n", $body);
  return @mail($to, $s, $b, $h, '-f' . $from) || @mail($to, $s, $b, $h);
}

// ---------- orders ----------

function order_for_user(int $orderId, int $userId): ?array {
  $st = db()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
  $st->execute([$orderId, $userId]);
  return $st->fetch() ?: null;
}

function status_pill(string $status): string {
  return '<span class="pill pill-' . e($status) . '">' . e(ORDER_STATUS[$status] ?? $status) . '</span>';
}

function kategori_namn(string $csv): string {
  global $sortiment;
  $namn = [];
  foreach (array_filter(explode(',', $csv)) as $k) {
    if (isset($sortiment[$k])) $namn[] = $sortiment[$k]['namn'];
  }
  return $namn ? implode(', ', $namn) : '–';
}

require __DIR__ . '/sortiment-data.php';
require __DIR__ . '/superklipp.php';
require __DIR__ . '/veckansklipp.php';
