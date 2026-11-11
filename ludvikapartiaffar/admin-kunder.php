<?php
require __DIR__ . '/includes/app.php';
$admin = require_behorighet('webbkonton');
erp_schema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $kid = (int)($_POST['id'] ?? 0);
  $action = (string)($_POST['action'] ?? '');
  if ($action === 'koppla' || $action === 'koppla_bort') {
    // Kopplar webbkontot till en kund i kundregistret, så att kundens priser och uppgifter kan användas.
    $kund = $action === 'koppla' ? kund_via_nr(enRad($_POST['kundnr'] ?? '', 40)) : null;
    if ($action === 'koppla' && !$kund) {
      flash('Kundnumret "' . enRad($_POST['kundnr'] ?? '', 40) . '" finns inte i kundregistret.');
    } else {
      $st = db()->prepare("UPDATE users SET kund_id = ? WHERE id = ? AND role = 'kund'");
      $st->execute([$kund['id'] ?? null, $kid]);
      if ($st->rowCount()) flash($kund ? 'Kontot är kopplat till ' . $kund['kundnr'] . ' ' . $kund['namn'] . '.' : 'Kopplingen till kundregistret är borttagen.');
    }
  } elseif ($action === 'grupp' || $action === 'godkann') {
    $st = db()->prepare("SELECT * FROM users WHERE id = ? AND role = 'kund'");
    $st->execute([$kid]);
    $kund = $st->fetch();
    if ($kund) {
      $grupp = kundgrupp_ur_post();
      $nyGrupp = $grupp !== '' ? $grupp : $kund['kundgrupp'];
      $bytt = $nyGrupp !== $kund['kundgrupp'];
      // Ny kundgrupp: tillgången följer gruppen. Annars sparas kryssen, men bara det som skiljer sig från gruppen.
      $ser = [];
      foreach (array_keys(ERBJUDANDEN) as $vilket) {
        $vald = !empty($_POST['ser'][$vilket]);
        $ser[$vilket] = $bytt || $vald === erbjudande_enligt_grupp($vilket, $nyGrupp) ? null : (int)$vald;
      }
      $godkann = $action === 'godkann' && (int)$kund['godkand'] !== 1;
      $godkand = $godkann ? 1 : (int)$kund['godkand'];
      db()->prepare('UPDATE users SET kundgrupp = ?, ser_superklipp = ?, ser_veckansklipp = ?, godkand = ? WHERE id = ?')
          ->execute([$nyGrupp, $ser['superklipp'], $ser['veckansklipp'], $godkand, $kid]);
      $uppdaterad = ['role' => 'kund', 'kundgrupp' => $nyGrupp, 'ser_superklipp' => $ser['superklipp'], 'ser_veckansklipp' => $ser['veckansklipp'], 'godkand' => $godkand];

      if ($godkann) {
        // Kunden får veta att kontot är godkänt och var erbjudandena finns.
        $rader = ["Hej {$kund['kontakt']}!", '', "Ditt kundkonto för {$kund['foretag']} hos Ludvika Partiaffär är nu godkänt."];
        $erb = array_filter(array_keys(ERBJUDANDEN), fn($v) => erbjudande_valt($uppdaterad, $v));
        if ($erb) {
          $rader[] = '';
          $rader[] = 'När du är inloggad ser du veckans erbjudanden här:';
          foreach ($erb as $v) $rader[] = ERBJUDANDEN[$v]['namn'] . ': ' . webbadress(ERBJUDANDEN[$v]['sida']);
        }
        array_push($rader, '', 'Logga in: ' . webbadress('logga-in'), '', 'Med vänlig hälsning', 'Ludvika Partiaffär, 0240-183 55');
        send_mail($kund['email'], 'Ditt konto hos Ludvika Partiaffär är godkänt', implode("\n", $rader), $CONFIG['mail_to']);
        flash($kund['foretag'] . ' är godkänt och har fått ett mejl om det. Ser: ' . erbjudanden_text($uppdaterad) . '.');
      } else {
        flash(($bytt ? 'Kundgruppen är ändrad till ' . kundgrupp_text($nyGrupp) . '.' : 'Sparat för ' . $kund['foretag'] . '.')
            . ' Ser: ' . ($godkand ? erbjudanden_text($uppdaterad) : 'Inga förrän kontot är godkänt') . '.');
      }
    }
  } else {
    $aktiv = $action === 'aktivera' ? 1 : 0;
    $st = db()->prepare("UPDATE users SET active = ? WHERE id = ? AND role = 'kund'");
    $st->execute([$aktiv, $kid]);
    if ($st->rowCount()) {
      flash($aktiv ? 'Kontot är aktiverat igen.' : 'Kontot är avstängt. Kunden loggas ut direkt och kan inte logga in.');
    }
  }
  $tillbaka = array_filter(['sok' => (string)($_POST['sok'] ?? ''), 'visa' => ($_POST['visa'] ?? '') === 'nya' ? 'nya' : '']);
  redirect('admin-kunder' . ($tillbaka ? '?' . http_build_query($tillbaka) : ''));
}

$sok = enRad($_GET['sok'] ?? '', 80);
$params = [];
$visa = ($_GET['visa'] ?? '') === 'nya' ? 'nya' : '';
$where = "u.role = 'kund'";
if ($visa) $where .= ' AND u.godkand = 0';
if ($sok !== '') {
  $where .= ' AND (u.foretag LIKE ? OR u.kontakt LIKE ? OR u.email LIKE ? OR u.telefon LIKE ?)';
  $like = '%' . addcslashes($sok, '%_\\') . '%';
  $params = [$like, $like, $like, $like];
}
$st = db()->prepare("SELECT u.id, u.role, u.foretag, u.kundgrupp, u.ser_superklipp, u.ser_veckansklipp, u.godkand, u.kontakt, u.email, u.telefon, u.active, u.created_at, u.last_login, u.kund_id,
                            MAX(k.kundnr) AS kundnr, MAX(k.namn) AS kund_namn, COUNT(o.id) AS antal, MAX(o.created_at) AS senaste
                     FROM users u LEFT JOIN kunder k ON k.id = u.kund_id LEFT JOIN orders o ON o.user_id = u.id
                     WHERE $where GROUP BY u.id ORDER BY u.godkand, u.created_at DESC LIMIT 500");
$st->execute($params);
$kunder = $st->fetchAll();

// Förslag på koppling: kunder i registret med samma e-postadress som webbkontot.
$forslag = [];
$okopplade = array_values(array_unique(array_column(array_filter($kunder, fn($k) => !$k['kund_id']), 'email')));
if ($okopplade) {
  $in = implode(',', array_fill(0, count($okopplade), '?'));
  $st = db()->prepare("SELECT kundnr, namn, foljesedel_epost, faktura_epost, paminnelse_epost FROM kunder
                       WHERE aktiv = 1 AND (foljesedel_epost IN ($in) OR faktura_epost IN ($in) OR paminnelse_epost IN ($in)) ORDER BY kundnr");
  $st->execute(array_merge($okopplade, $okopplade, $okopplade));
  foreach ($st as $r) {
    foreach (['foljesedel_epost', 'faktura_epost', 'paminnelse_epost'] as $f) $forslag[$r[$f]] ??= $r;
  }
}
$vantar = (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'kund' AND godkand = 0 AND active = 1")->fetchColumn();

$active = 'admin';
$adminSida = 'webbkonton';
$pageTitle = 'Webbkonton – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Admin</span>
        <h1>Webbkonton</h1>
        <p><?php echo count($kunder); ?> <?php echo $sok !== '' ? 'träffar' : 'konton som kunder har skapat på hemsidan'; ?>. Koppla kontot till rätt kund i kundregistret med kundnumret. Nya konton ser inga erbjudanden förrän du godkänner dem. Kundgruppen (A, L, B) är samma som i affärssystemet. Butiker får Superklipp och restauranger Veckansklipp, men du kan kryssa i för varje kund. Stäng av konton som ser misstänkta ut.</p>
      </div>
    </div>

    <?php if ($vantar): ?>
      <div class="sk-notis">
        <p><strong><?php echo $vantar === 1 ? '1 nytt konto väntar' : $vantar . ' nya konton väntar'; ?> på godkännande.</strong> Kontrollera kundgrupp och erbjudanden och tryck <strong>Godkänn</strong>. Kunden får ett mejl när kontot är godkänt.</p>
        <div class="actions"><a class="btn <?php echo $visa ? 'ghost' : 'primary'; ?> small" href="admin-kunder<?php echo $visa ? '' : '?visa=nya'; ?>"><?php echo $visa ? 'Visa alla kunder' : 'Visa bara nya'; ?></a></div>
      </div>
    <?php elseif ($visa): ?>
      <div class="alert info">Inga konton väntar på godkännande. <a href="admin-kunder" style="color:var(--brand);">Visa alla kunder</a></div>
    <?php endif; ?>

    <form class="search" method="get" action="admin-kunder">
      <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Sök företag, namn, e-post eller telefon" aria-label="Sök kunder">
      <button class="btn ghost small" type="submit">Sök</button>
    </form>

    <?php if (!$kunder): ?>
      <div class="empty"><p><?php echo $sok !== '' ? 'Inga kunder matchar sökningen.' : 'Inga kunder har registrerat sig än.'; ?></p></div>
    <?php else: ?>
      <div class="table-wrap stack">
        <table class="list">
          <thead><tr><th>Företag</th><th>I kundregistret</th><th>Kundgrupp och erbjudanden</th><th>Kontakt</th><th>Registrerad</th><th>Beställningar</th><th>Status</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($kunder as $k): ?>
            <tr>
              <td data-label="Företag"><strong><?php echo e($k['foretag']); ?></strong></td>
              <td data-label="Kundregistret">
                <?php if ($k['kund_id']): ?>
                  <form class="grupp-form" method="post" action="admin-kunder">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                    <input type="hidden" name="sok" value="<?php echo e($sok); ?>">
                    <input type="hidden" name="visa" value="<?php echo $visa; ?>">
                    <?php if (far($admin, 'kunder')): ?><a class="row-link" href="admin-kund?id=<?php echo (int)$k['kund_id']; ?>"><?php echo e($k['kundnr']); ?></a><?php else: ?><strong><?php echo e($k['kundnr']); ?></strong><?php endif; ?>
                    <span class="muted" style="font-size:.85rem;"><?php echo e($k['kund_namn']); ?></span>
                    <button class="btn ghost small" type="submit" name="action" value="koppla_bort" title="Ta bort kopplingen" aria-label="Ta bort kopplingen till <?php echo e($k['kundnr']); ?>">&times;</button>
                  </form>
                <?php else: $f = $forslag[$k['email']] ?? null; ?>
                  <form class="grupp-form" method="post" action="admin-kunder">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                    <input type="hidden" name="sok" value="<?php echo e($sok); ?>">
                    <input type="hidden" name="visa" value="<?php echo $visa; ?>">
                    <input type="text" name="kundnr" value="<?php echo e($f['kundnr'] ?? ''); ?>" placeholder="Kundnr" aria-label="Kundnummer för <?php echo e($k['foretag']); ?>" style="width:110px; padding:7px 9px; font-size:.88rem;">
                    <button class="btn ghost small" type="submit" name="action" value="koppla">Koppla</button>
                    <?php if ($f): ?><span class="muted" style="font-size:.8rem; flex-basis:100%;">Förslag: <?php echo e($f['namn']); ?> (samma e-post)</span><?php endif; ?>
                  </form>
                <?php endif; ?>
              </td>
              <td data-label="Kundgrupp">
                <form class="grupp-form" method="post" action="admin-kunder">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                  <input type="hidden" name="sok" value="<?php echo e($sok); ?>">
                  <input type="hidden" name="visa" value="<?php echo $visa; ?>">
                  <select name="kundgrupp" aria-label="Kundgrupp för <?php echo e($k['foretag']); ?>">
                    <?php if (!isset(KUNDGRUPPER[(string)$k['kundgrupp']])): ?><option value="" selected>Ej angiven</option><?php endif; ?>
                    <?php foreach (KUNDGRUPPER as $g => $namn): ?>
                      <option value="<?php echo $g; ?>"<?php echo $k['kundgrupp'] === $g ? ' selected' : ''; ?>><?php echo e($g . ' – ' . explode(' / ', $namn)[0]); ?></option>
                    <?php endforeach; ?>
                  </select>
                  <?php if ((int)$k['godkand'] === 1): ?>
                    <button class="btn ghost small" type="submit" name="action" value="grupp">Spara</button>
                  <?php else: ?>
                    <button class="btn primary small" type="submit" name="action" value="godkann">Godkänn</button>
                  <?php endif; ?>
                  <span class="ser-rad">
                  <?php foreach (ERBJUDANDEN as $vilket => $erb): $eget = $k['ser_' . $vilket] !== null; ?>
                    <label class="ser-val<?php echo $eget ? ' eget' : ''; ?>"<?php echo $eget ? ' title="Valt för hand. Annars följer det kundgruppen."' : ''; ?>><input type="checkbox" name="ser[<?php echo $vilket; ?>]" value="1" data-grupper="<?php echo e(implode(',', $erb['grupper'])); ?>"<?php echo erbjudande_valt($k, $vilket) ? ' checked' : ''; ?>> <?php echo e($erb['namn']); ?></label>
                  <?php endforeach; ?>
                  </span>
                </form>
              </td>
              <td data-label="Kontakt"><div><?php echo e($k['kontakt']); ?><br><span class="muted" style="font-size:.85rem;"><?php echo e($k['email']); ?> &middot; <?php echo e($k['telefon']); ?></span></div></td>
              <td data-label="Registrerad" class="muted"><?php echo e(date('Y-m-d', strtotime($k['created_at']))); ?></td>
              <td data-label="Beställningar"><div>
                <?php if ((int)$k['antal']): ?>
                  <a class="row-link" href="admin?visa=alla&amp;kund=<?php echo (int)$k['id']; ?>"><?php echo (int)$k['antal']; ?> st</a>
                  <br><span class="muted" style="font-size:.8rem;">senast <?php echo e(date('Y-m-d', strtotime($k['senaste']))); ?></span>
                <?php else: ?>
                  <span class="muted">Inga</span>
                <?php endif; ?>
              </div></td>
              <td data-label="Status"><?php echo !(int)$k['active'] ? '<span class="pill pill-off">Avstängd</span>' : ((int)$k['godkand'] === 1 ? '<span class="pill pill-levererad">Aktiv</span>' : '<span class="pill pill-ny">Ny, väntar</span>'); ?></td>
              <td class="actions">
                <form method="post" action="admin-kunder" style="margin:0;">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="id" value="<?php echo (int)$k['id']; ?>">
                  <input type="hidden" name="sok" value="<?php echo e($sok); ?>">
                  <input type="hidden" name="visa" value="<?php echo $visa; ?>">
                  <?php if ((int)$k['active']): ?>
                    <button class="btn danger small" type="submit" name="action" value="stang" onclick="return confirm('Stänga av kontot? Kunden loggas ut direkt.');">Stäng av</button>
                  <?php else: ?>
                    <button class="btn ghost small" type="submit" name="action" value="aktivera">Aktivera</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>
<script>
// Byts kundgrupp visas den nya gruppens erbjudanden direkt.
document.querySelectorAll('.grupp-form select[name=kundgrupp]').forEach(function (sel) {
  sel.addEventListener('change', function () {
    sel.form.querySelectorAll('input[data-grupper]').forEach(function (cb) {
      cb.checked = cb.dataset.grupper.split(',').indexOf(sel.value) !== -1;
      cb.closest('label').classList.remove('eget');
    });
  });
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
