<?php
require __DIR__ . '/includes/app.php';
$admin = require_behorighet('personal');
erp_schema();

const INBJUDAN_DAGAR = 7;

// Skapar en länk där personen väljer sitt lösenord, och mejlar den. Länken visas också för admin en gång.
function skicka_inbjudan(array $u, bool $ny): string {
  global $CONFIG;
  $token = bin2hex(random_bytes(32));
  db()->prepare('INSERT INTO password_resets (user_id, token_hash, created_at, expires_at) VALUES (?, ?, ?, ?)')
      ->execute([$u['id'], hash('sha256', $token), now(), date('Y-m-d H:i:s', time() + INBJUDAN_DAGAR * 86400)]);
  $lank = webbadress('aterstall-losenord') . '?token=' . $token;
  send_mail($u['email'], $ny ? 'Ditt konto i Ludvika Partiaffärs affärssystem' : 'Ny länk till affärssystemet', implode("\n", [
    "Hej {$u['kontakt']}!",
    '',
    ($ny ? 'Du har fått ett konto' : 'Här är en ny länk till ditt konto') . ' i Ludvika Partiaffärs affärssystem, med rollen ' . mb_strtolower(roll_namn($u['role']), 'UTF-8') . '.',
    'Klicka på länken och välj ett lösenord. Länken gäller i ' . INBJUDAN_DAGAR . ' dagar och kan bara användas en gång.',
    '',
    $lank,
    '',
    'Sedan loggar du in här med din e-postadress: ' . webbadress('logga-in'),
    '',
    'Ludvika Partiaffär',
  ]));
  return $lank;
}

function aktiva_admin(): int {
  return (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1")->fetchColumn();
}

$fel = [];
$v = ['kontakt' => '', 'email' => '', 'telefon' => '', 'role' => 'kontor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'ny') {
    $v['kontakt'] = enRad($_POST['kontakt'] ?? '', 120);
    $v['email'] = mb_strtolower(enRad($_POST['email'] ?? '', 160), 'UTF-8');
    $v['telefon'] = enRad($_POST['telefon'] ?? '', 40);
    $v['role'] = isset(ROLLER[$_POST['role'] ?? '']) ? $_POST['role'] : '';
    if ($v['kontakt'] === '') $fel['kontakt'] = 'Ange namnet.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $fel['email'] = 'Ange en giltig e-postadress.';
    else {
      $st = db()->prepare('SELECT role FROM users WHERE email = ?');
      $st->execute([$v['email']]);
      $finns = $st->fetchColumn();
      if ($finns !== false) $fel['email'] = $finns === 'kund' ? 'Adressen används redan av ett kundkonto på hemsidan. Använd en annan adress.' : 'Personen har redan ett konto.';
    }
    if ($v['role'] === '') $fel['role'] = 'Välj roll.';
    if (!$fel) {
      // Lösenordet går inte att gissa. Personen väljer ett eget via länken.
      db()->prepare("INSERT INTO users (role, email, password_hash, foretag, kontakt, telefon, adress, active, godkand, created_at)
                     VALUES (?, ?, ?, 'Ludvika Partiaffär', ?, ?, '', 1, 1, ?)")
          ->execute([$v['role'], $v['email'], password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $v['kontakt'], $v['telefon'], now()]);
      $u = ['id' => (int)db()->lastInsertId(), 'email' => $v['email'], 'kontakt' => $v['kontakt'], 'role' => $v['role']];
      $_SESSION['inbjudan'] = ['namn' => $u['kontakt'], 'lank' => skicka_inbjudan($u, true)];
      flash($u['kontakt'] . ' har fått ett konto och ett mejl med en länk för att välja lösenord.');
      redirect('admin-personal');
    }
  } else {
    $st = db()->prepare("SELECT * FROM users WHERE id = ? AND role <> 'kund'");
    $st->execute([(int)($_POST['id'] ?? 0)]);
    $u = $st->fetch();
    if (!$u) redirect('admin-personal');
    $sjalv = (int)$u['id'] === (int)$admin['id'];
    $sistaAdmin = $u['role'] === 'admin' && (int)$u['active'] === 1 && aktiva_admin() <= 1;

    if ($action === 'roll') {
      $roll = (string)($_POST['role'] ?? '');
      if (!isset(ROLLER[$roll]) || $roll === $u['role']) {
        // Ingen ändring.
      } elseif ($sjalv) {
        flash('Du kan inte ändra din egen roll. Be en annan administratör.');
      } elseif ($sistaAdmin) {
        flash('Det måste finnas minst en administratör.');
      } else {
        // Ny roll börjar från rollens mall. Egna kryss sätts på personens sida.
        db()->prepare('UPDATE users SET role = ?, behorigheter = NULL WHERE id = ?')->execute([$roll, $u['id']]);
        flash($u['kontakt'] . ' är nu ' . mb_strtolower(roll_namn($roll), 'UTF-8') . '.');
      }
    } elseif ($action === 'stang' || $action === 'aktivera') {
      if ($action === 'stang' && $sjalv) flash('Du kan inte stänga av ditt eget konto.');
      elseif ($action === 'stang' && $sistaAdmin) flash('Det måste finnas minst en aktiv administratör.');
      else {
        db()->prepare('UPDATE users SET active = ? WHERE id = ?')->execute([$action === 'aktivera' ? 1 : 0, $u['id']]);
        flash($action === 'aktivera' ? $u['kontakt'] . ' kan logga in igen.' : $u['kontakt'] . ' är avstängd och loggas ut direkt.');
      }
    } elseif ($action === 'inbjudan' && (int)$u['active'] === 1) {
      $_SESSION['inbjudan'] = ['namn' => $u['kontakt'], 'lank' => skicka_inbjudan($u, false)];
      flash($u['kontakt'] . ' har fått en ny länk via e-post.');
    }
    redirect('admin-personal');
  }
}

$inbjudan = $_SESSION['inbjudan'] ?? null;
unset($_SESSION['inbjudan']);
$personal = db()->query("SELECT id, role, email, kontakt, telefon, active, last_login, created_at, behorigheter, kundgrupper, artikelgrupper FROM users WHERE role <> 'kund' ORDER BY active DESC, kontakt")->fetchAll();
$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';

$active = 'admin';
$adminSida = 'personal';
$pageTitle = 'Personal – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <h1>Personal</h1>
        <p>Konton för alla som jobbar i systemet. Rollen är en mall. Klicka på namnet för att kryssa i exakt vad personen får göra och vilka kundgrupper personen jobbar med.</p>
      </div>
    </div>

    <?php if ($inbjudan): ?>
      <div class="sk-notis" style="display:block;">
        <p><strong>Länk till <?php echo e($inbjudan['namn']); ?>.</strong> Mejlet är skickat. Kommer det inte fram kan du skicka länken på annat sätt, till exempel sms. Den gäller i <?php echo INBJUDAN_DAGAR; ?> dagar och visas bara nu.</p>
        <div class="lank-ruta"><input type="text" readonly value="<?php echo e($inbjudan['lank']); ?>" aria-label="Länk för att välja lösenord" onclick="this.select()"></div>
      </div>
    <?php endif; ?>

    <div class="detail-grid">
      <div>
        <div class="table-wrap stack">
          <table class="list">
            <thead><tr><th>Namn</th><th>Roll</th><th>Senast inloggad</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($personal as $p): $sjalv = (int)$p['id'] === (int)$admin['id']; ?>
              <tr>
                <td data-label="Namn"><div><a class="row-link" href="admin-person?id=<?php echo (int)$p['id']; ?>"><?php echo e($p['kontakt']); ?></a><?php echo $sjalv ? ' <span class="muted">(du)</span>' : ''; ?><br><span class="muted" style="font-size:.85rem;"><?php echo e($p['email']); ?></span>
                  <?php $grp = array_filter([($g = anv_grupper($p, 'kundgrupper')) ? 'Kundgrupp ' . implode(', ', $g) : '', ($g = anv_grupper($p, 'artikelgrupper')) ? 'Artikelgrupp ' . implode(', ', $g) : '', $p['role'] !== 'admin' && $p['behorigheter'] !== null ? 'Egna behörigheter' : '']); ?>
                  <?php if ($grp): ?><br><span class="muted" style="font-size:.8rem;"><?php echo e(implode(' · ', $grp)); ?></span><?php endif; ?></div></td>
                <td data-label="Roll">
                  <?php if ($sjalv): ?>
                    <?php echo e(roll_text($p)); ?>
                  <?php else: ?>
                    <form class="grupp-form" method="post" action="admin-personal">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                      <select name="role" aria-label="Roll för <?php echo e($p['kontakt']); ?>">
                        <?php foreach (ROLLER as $r => $info): ?><option value="<?php echo $r; ?>"<?php echo $p['role'] === $r ? ' selected' : ''; ?>><?php echo e($info['namn']); ?></option><?php endforeach; ?>
                      </select>
                      <button class="btn ghost small" type="submit" name="action" value="roll">Spara</button>
                    </form>
                  <?php endif; ?>
                </td>
                <td data-label="Senast inloggad" class="muted"><?php echo $p['last_login'] ? e(datum($p['last_login'])) : 'Aldrig'; ?></td>
                <td data-label="Status"><?php echo (int)$p['active'] ? '<span class="pill pill-levererad">Aktiv</span>' : '<span class="pill pill-off">Avstängd</span>'; ?></td>
                <td class="actions">
                  <?php if (!$sjalv): ?>
                    <form method="post" action="admin-personal" style="margin:0; display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap;">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                      <?php if ((int)$p['active']): ?>
                        <?php if (!$p['last_login']): ?><button class="btn ghost small" type="submit" name="action" value="inbjudan">Skicka ny länk</button><?php endif; ?>
                        <button class="btn danger small" type="submit" name="action" value="stang" onclick="return confirm('Stänga av kontot? Personen loggas ut direkt.');">Stäng av</button>
                      <?php else: ?>
                        <button class="btn ghost small" type="submit" name="action" value="aktivera">Aktivera</button>
                      <?php endif; ?>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="detail-card" style="margin-top:16px;">
          <h2>Roller</h2>
          <dl class="kv">
            <?php foreach (ROLLER as $info): ?><dt><strong style="color:var(--ink);"><?php echo e($info['namn']); ?></strong></dt><dd><?php echo e($info['text']); ?></dd><?php endforeach; ?>
          </dl>
        </div>
      </div>

      <form class="detail-card" method="post" action="admin-personal" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="ny">
        <h2>Lägg till personal</h2>
        <div class="field-grid" style="grid-template-columns:1fr;">
          <div class="field"><label for="kontakt">Namn</label><input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" required><?php echo $err('kontakt'); ?></div>
          <div class="field"><label for="email">E-post</label><input type="email" id="email" name="email" value="<?php echo e($v['email']); ?>" required autocomplete="off"><?php echo $err('email'); ?></div>
          <div class="field"><label for="telefon">Telefon <span class="hint">(valfritt)</span></label><input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>"></div>
        </div>
        <span class="field-label" style="display:block; margin:16px 0 8px; font-weight:700; font-size:.88rem;">Roll</span>
        <div class="val-kort" style="grid-template-columns:1fr;" role="radiogroup" aria-label="Roll">
          <?php foreach (ROLLER as $r => $info): ?>
            <label class="check"><input type="radio" name="role" value="<?php echo $r; ?>"<?php echo $v['role'] === $r ? ' checked' : ''; ?>><span><strong><?php echo e($info['namn']); ?></strong><small><?php echo e($info['text']); ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <?php echo $err('role'); ?>
        <p class="form-note" style="margin-top:14px;">Personen får ett mejl med en länk för att välja ett eget lösenord.</p>
        <div class="form-actions" style="margin-top:14px;"><button class="btn primary" type="submit">Skapa konto</button></div>
      </form>
    </div>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
