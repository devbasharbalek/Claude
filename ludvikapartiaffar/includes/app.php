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
  return preg_match('/^[a-z0-9\-]+\.php(\?[A-Za-z0-9=&%_\-]*)?$/', $next) ? $next : 'konto.php';
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
  }
  return $pdo;
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
session_start();

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
