<?php
require __DIR__ . '/includes/app.php';
require __DIR__ . '/includes/import.php';
$user = require_behorighet('kunder');
erp_schema();
$kanPriser = far($user, 'priser');
$av = vem($user);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$kund = $id ? kund_hamta($id) : null;
if ($id && !$kund) {
  http_response_code(404);
  exit('Kunden finns inte.');
}
$ny = !$kund;
if ($kund && !far_grupp($user, $kund['kundgrupp'])) {
  http_response_code(403);
  exit('Kunden hör till en kundgrupp du inte har behörighet till.');
}

// Fälten i formuläret, grupperade som på kundkortet i Partihandel.
$delar = [
  'Kund' => ['kundnr' => 'Kundnummer', 'namn' => 'Namn', 'orgnr' => 'Org.nr', 'momsnr' => 'Momsregistreringsnummer', 'moderbolag' => 'Huvudkund'],
  'Leverans' => ['lev_namn' => 'Leveransnamn', 'lev_adress' => 'Adress', 'lev_postnr' => 'Postnummer', 'lev_ort' => 'Ort', 'tur' => 'Tur', 'telefon' => 'Telefon',
                 'foljesedel_epost' => 'E-post för följesedel', 'foljesedel_kopia' => 'Kopia av följesedel'],
  'Faktura' => ['fakt_namn' => 'Fakturanamn', 'fakt_adress' => 'Adress', 'fakt_postnr' => 'Postnummer', 'fakt_ort' => 'Ort', 'faktura_epost' => 'E-post för faktura',
                'faktura_kopia' => 'Kopia av faktura', 'paminnelse_epost' => 'E-post för påminnelse', 'er_referens' => 'Er referens', 'var_referens' => 'Vår referens',
                'referens' => 'Referens', 'gln' => 'GLN'],
];
$fel = [];
$v = $kund ?? ['kundnr' => '', 'kundgrupp' => null, 'prisgrupp' => null, 'betalningsvillkor' => 30, 'fakturaavgift' => null, 'anteckning' => '', 'aktiv' => 1, 'land' => 'SE'];
foreach ($delar as $falt) foreach ($falt as $k => $_) $v[$k] ??= '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if (in_array($action, ['kundpris', 'kundpris_bort'], true) && $kund) {
    if (!$kanPriser) {
      http_response_code(403);
      exit('Du har inte behörighet att ändra priser.');
    }
    if ($action === 'kundpris') {
      $a = artikel_via_nr(enRad($_POST['artikelnr'] ?? '', 40));
      $pris = pris_ur_text((string)($_POST['pris'] ?? ''));
      if (!$a) flash('Artikelnumret finns inte i artikelregistret.');
      elseif ($pris === false || $pris === null) flash('Skriv ett pris, till exempel 129,95.');
      else {
        satt_kundpris($id, (int)$a['id'], $pris, $av);
        flash('Kundpriset på ' . $a['namn'] . ' är ' . pris_text($pris) . ' kr.');
      }
    } else {
      $a = artikel_hamta((int)($_POST['artikel_id'] ?? 0));
      if ($a && satt_kundpris($id, (int)$a['id'], null, $av)) flash('Kundpriset på ' . $a['namn'] . ' är borttaget. Kunden betalar ' . betalar_text($kund) . '.');
    }
    redirect("admin-kund?id=$id#kundpriser");
  }

  if (in_array($action, ['koppla', 'koppla_bort'], true) && $kund) {
    if (!far($user, 'webbkonton')) {
      http_response_code(403);
      exit('Du har inte behörighet att ändra webbkonton.');
    }
    if ($action === 'koppla') {
      $epost = mb_strtolower(enRad($_POST['epost'] ?? '', 160), 'UTF-8');
      $st = db()->prepare("SELECT * FROM users WHERE email = ? AND role = 'kund'");
      $st->execute([$epost]);
      $u = $st->fetch();
      if (!$u) flash('Det finns inget kundkonto på hemsidan med e-postadressen ' . $epost . '.');
      else {
        db()->prepare('UPDATE users SET kund_id = ? WHERE id = ?')->execute([$id, $u['id']]);
        flash('Webbkontot ' . $u['email'] . ' är kopplat till kunden.');
      }
    } else {
      $st = db()->prepare("UPDATE users SET kund_id = NULL WHERE id = ? AND kund_id = ? AND role = 'kund'");
      $st->execute([(int)($_POST['user_id'] ?? 0), $id]);
      if ($st->rowCount()) flash('Webbkontot är inte längre kopplat till kunden.');
    }
    redirect("admin-kund?id=$id#webbkonton");
  }

  if ($action === 'spara') {
    foreach ($delar as $falt) {
      foreach ($falt as $k => $_) {
        if ($k === 'kundnr' && !$ny) continue;
        $typ = $k === 'kundnr' ? ['text', 40] : KUND_FALT_TYP[$k];
        [$varde] = import_varde($typ, (string)($_POST[$k] ?? ''));
        $v[$k] = $varde ?? '';
      }
    }
    $g = (string)($_POST['kundgrupp'] ?? '');
    $v['kundgrupp'] = isset(REGISTER_KUNDGRUPPER[$g]) ? $g : null;
    $pg = (int)($_POST['prisgrupp'] ?? 0);
    $v['prisgrupp'] = isset(PRISGRUPPER[$pg]) ? $pg : null;
    $dagar = trim((string)($_POST['betalningsvillkor'] ?? ''));
    if ($dagar === '') $v['betalningsvillkor'] = null;
    elseif (ctype_digit($dagar) && (int)$dagar <= 365) $v['betalningsvillkor'] = (int)$dagar;
    else $fel['betalningsvillkor'] = 'Skriv antal dagar, till exempel 30.';
    $avg = pris_ur_text((string)($_POST['fakturaavgift'] ?? ''));
    if ($avg === false) $fel['fakturaavgift'] = 'Skriv ett belopp, till exempel 29.';
    else $v['fakturaavgift'] = $avg;
    $v['anteckning'] = mb_substr(trim((string)($_POST['anteckning'] ?? '')), 0, 5000, 'UTF-8');
    $v['aktiv'] = !empty($_POST['aktiv']) ? 1 : 0;

    if ($ny) {
      if ($v['kundnr'] === '') $fel['kundnr'] = 'Ange kundnummer.';
      elseif (kund_via_nr($v['kundnr'])) $fel['kundnr'] = 'Det finns redan en kund med det numret.';
    }
    if ($v['namn'] === '') $fel['namn'] = 'Ange kundens namn.';
    foreach (['foljesedel_epost', 'foljesedel_kopia', 'faktura_epost', 'faktura_kopia', 'paminnelse_epost'] as $k) {
      // Flera adresser kan skrivas med semikolon emellan.
      foreach (array_filter(array_map('trim', preg_split('/[;,]/', $v[$k]))) as $adress) {
        if (!filter_var($adress, FILTER_VALIDATE_EMAIL)) {
          $fel[$k] = 'Kontrollera e-postadressen.';
          break;
        }
      }
    }

    if (!$fel) {
      $kolumner = array_merge(array_keys(array_merge(...array_values($delar))), ['kundgrupp', 'prisgrupp', 'betalningsvillkor', 'fakturaavgift', 'anteckning', 'aktiv']);
      $kolumner = array_values(array_diff($kolumner, ['kundnr']));
      if ($ny) {
        db()->prepare('INSERT INTO kunder (kundnr, ' . implode(', ', $kolumner) . ', skapad, uppdaterad, uppdaterad_av) VALUES (?' . str_repeat(', ?', count($kolumner)) . ', ?, ?, ?)')
            ->execute(array_merge([$v['kundnr']], array_map(fn($k) => $v[$k], $kolumner), [now(), now(), $av]));
        $id = (int)db()->lastInsertId();
      } else {
        db()->prepare('UPDATE kunder SET ' . implode(', ', array_map(fn($k) => "$k = ?", $kolumner)) . ', uppdaterad = ?, uppdaterad_av = ? WHERE id = ?')
            ->execute(array_merge(array_map(fn($k) => $v[$k], $kolumner), [now(), $av, $id]));
      }
      flash($ny ? 'Kunden ' . $v['namn'] . ' är upplagd.' : 'Kunden är sparad.');
      redirect("admin-kund?id=$id");
    }
  }
}

$kundpriser = [];
$konton = [];
$forslag = [];
$order = [];
if ($kund) {
  [$kol, $join] = kundpris_sql();
  $st = db()->prepare("SELECT a.id, a.artikelnr, a.namn, a.enhet, a.utpris, $kol FROM kund_priser x JOIN artiklar a ON a.id = x.artikel_id $join
                       WHERE x.kund_id = ? ORDER BY a.namn");
  $st->execute([$id, (int)($kund['prisgrupp'] ?? 0), $id]);
  $kundpriser = $st->fetchAll();
  $st = db()->prepare("SELECT id, email, foretag, kontakt, active, godkand, last_login FROM users WHERE kund_id = ? AND role = 'kund' ORDER BY email");
  $st->execute([$id]);
  $konton = $st->fetchAll();
  // Webbkonton med någon av kundens e-postadresser som inte är kopplade än.
  $adresser = array_values(array_filter(array_unique([$kund['foljesedel_epost'], $kund['faktura_epost'], $kund['paminnelse_epost']])));
  if ($adresser) {
    $st = db()->prepare("SELECT email FROM users WHERE role = 'kund' AND kund_id IS NULL AND email IN (" . implode(',', array_fill(0, count($adresser), '?')) . ')');
    $st->execute($adresser);
    $forslag = $st->fetchAll(PDO::FETCH_COLUMN);
  }
  if ($konton) {
    $st = db()->prepare('SELECT id, status, created_at, leveransdag FROM orders WHERE user_id IN (' . implode(',', array_map(fn($u) => (int)$u['id'], $konton)) . ') ORDER BY created_at DESC LIMIT 8');
    $st->execute();
    $order = $st->fetchAll();
  }
}

$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
$falt = function (string $k, string $etikett) use ($v, $err, $ny) {
  $typ = str_contains($k, 'epost') || str_contains($k, 'kopia') ? 'email' : ($k === 'telefon' ? 'tel' : 'text');
  $las = $k === 'kundnr' && !$ny ? ' readonly' : '';
  $krav = in_array($k, ['kundnr', 'namn'], true) ? ' required' : '';
  // E-postfälten kan ha flera adresser, därför vanlig text.
  return '<div class="field"><label for="' . $k . '">' . e($etikett) . '</label><input type="' . ($typ === 'email' ? 'text' : $typ) . '" id="' . $k . '" name="' . $k . '" value="' . e((string)$v[$k]) . '"' . $las . $krav
       . ($typ === 'email' ? ' inputmode="email"' : '') . '>' . $err($k) . '</div>';
};

$active = 'admin';
$adminSida = 'kunder';
$pageTitle = ($ny ? 'Ny kund' : $kund['namn']) . ' – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin-kundregister">&larr; Kunder</a>
    <div class="portal-head">
      <div>
        <h1><?php echo e($ny ? 'Ny kund' : $kund['namn']); ?></h1>
        <?php if (!$ny): ?><p>Kundnr <?php echo e($kund['kundnr']); ?> &middot; <?php echo e(register_grupp_text($kund['kundgrupp'])); ?> &middot; <?php echo e($kund['prisgrupp'] === null ? 'Grundpris' : prisgrupp_text((int)$kund['prisgrupp']) . ', ' . mb_strtolower(PRISGRUPPER[(int)$kund['prisgrupp']]['for'], 'UTF-8')); ?><?php echo (int)$kund['aktiv'] ? '' : ' &middot; <strong>Inaktiv</strong>'; ?></p><?php endif; ?>
      </div>
      <?php if (!$ny): ?><div class="portal-actions">
        <?php if (far($user, 'order')): ?><a class="btn ghost small" href="admin?visa=alla&amp;kid=<?php echo $id; ?>">Kundens order</a><?php endif; ?>
        <?php if (far($user, 'artiklar')): ?><a class="btn ghost small" href="admin-artiklar?kund=<?php echo $id; ?>">Kundens prislista</a><?php endif; ?>
        <?php if (far($user, 'order_andra') && (int)$kund['aktiv']): ?><form method="post" action="admin-order" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="skapa"><input type="hidden" name="kund_id" value="<?php echo $id; ?>"><button class="btn primary small" type="submit">Ny order</button></form><?php endif; ?>
      </div><?php endif; ?>
    </div>
    <?php if ($fel): ?><div class="alert error" role="alert">Kunden sparades inte, se markeringarna nedan.</div><?php endif; ?>

    <div class="detail-grid">
      <form method="post" action="admin-kund" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="spara">
        <?php if (!$ny): ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php endif; ?>
        <div class="detail-card">
          <h2>Kund</h2>
          <div class="field-grid">
            <?php foreach ($delar['Kund'] as $k => $etikett) echo $falt($k, $etikett); ?>
            <div class="field"><label for="kundgrupp">Kundgrupp</label>
              <select id="kundgrupp" name="kundgrupp">
                <option value="">Ej angiven</option>
                <?php foreach (REGISTER_KUNDGRUPPER as $g => $namn): ?><option value="<?php echo $g; ?>"<?php echo $v['kundgrupp'] === $g ? ' selected' : ''; ?>><?php echo e($g . ' – ' . $namn); ?></option><?php endforeach; ?>
              </select></div>
            <div class="field"><label for="prisgrupp">Prisgrupp</label>
              <select id="prisgrupp" name="prisgrupp">
                <option value="">Grundpris</option>
                <?php foreach (PRISGRUPPER as $g => $pg): ?><option value="<?php echo $g; ?>"<?php echo (int)$v['prisgrupp'] === $g ? ' selected' : ''; ?>><?php echo e($pg['namn'] . ' – ' . $pg['for']); ?></option><?php endforeach; ?>
              </select></div>
          </div>
          <label class="check inline" style="margin-top:14px;"><input type="checkbox" name="aktiv" value="1"<?php echo (int)$v['aktiv'] ? ' checked' : ''; ?>> <span>Aktiv kund</span></label>
        </div>
        <div class="detail-card">
          <h2>Leverans</h2>
          <div class="field-grid"><?php foreach ($delar['Leverans'] as $k => $etikett) echo $falt($k, $etikett); ?></div>
        </div>
        <div class="detail-card">
          <h2>Faktura</h2>
          <div class="field-grid">
            <?php foreach ($delar['Faktura'] as $k => $etikett) echo $falt($k, $etikett); ?>
            <div class="field"><label for="betalningsvillkor">Betalningsvillkor (dagar)</label><input type="text" inputmode="numeric" id="betalningsvillkor" name="betalningsvillkor" value="<?php echo e((string)$v['betalningsvillkor']); ?>"><?php echo $err('betalningsvillkor'); ?></div>
            <div class="field"><label for="fakturaavgift">Fakturaavgift (kr)</label><input type="text" inputmode="decimal" id="fakturaavgift" name="fakturaavgift" value="<?php echo e(pris_text($v['fakturaavgift'])); ?>"><?php echo $err('fakturaavgift'); ?></div>
          </div>
        </div>
        <div class="detail-card">
          <h2>Anteckning</h2>
          <div class="field"><label class="sr-only" for="anteckning">Anteckning</label><textarea id="anteckning" name="anteckning" style="min-height:100px;" placeholder="Bara för personalen, syns inte för kunden."><?php echo e((string)$v['anteckning']); ?></textarea></div>
        </div>
        <div class="spara-rad"><button class="btn primary" type="submit"><?php echo $ny ? 'Lägg upp kunden' : 'Spara kunden'; ?></button><a class="btn ghost" href="admin-kundregister">Avbryt</a></div>
      </form>

      <?php if (!$ny): ?>
      <div>
        <div class="detail-card" id="kundpriser">
          <h2>Kundpriser <span class="h2-text">(<?php echo count($kundpriser); ?>), går före <?php echo e(betalar_text($kund)); ?></span></h2>
          <?php if (!$kundpriser): ?>
            <p class="form-note">Kunden har inga egna priser och betalar <?php echo e(betalar_text($kund)); ?>.</p>
          <?php else: ?>
            <ul class="logg">
              <?php foreach ($kundpriser as $kp): ?>
                <li>
                  <span><?php if (far($user, 'artiklar')): ?><a style="color:var(--brand); font-weight:700; text-decoration:none;" href="admin-artikel?id=<?php echo (int)$kp['id']; ?>"><?php echo e($kp['namn']); ?></a><?php else: echo e($kp['namn']); endif; ?><br>
                    <span class="muted" style="font-size:.82rem;"><?php echo e($kp['artikelnr']); ?> &middot; annars <?php echo e(pris_text($kp['kundgrupppris'] ?? $kp['utpris'])); ?></span></span>
                  <span style="display:flex; gap:8px; align-items:center;"><strong><?php echo e(pris_text($kp['kundpris'])); ?></strong>
                  <?php if ($kanPriser): ?>
                    <form method="post" action="admin-kund" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="artikel_id" value="<?php echo (int)$kp['id']; ?>"><button class="btn ghost small" type="submit" name="action" value="kundpris_bort" aria-label="Ta bort kundpriset på <?php echo e($kp['namn']); ?>">&times;</button></form>
                  <?php endif; ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($kanPriser): ?>
            <form class="liten-form" method="post" action="admin-kund">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <div class="field"><label for="kp_artikelnr">Artikelnr</label><input type="text" id="kp_artikelnr" name="artikelnr" required></div>
              <div class="field"><label for="kp_pris">Pris</label><input type="text" inputmode="decimal" id="kp_pris" name="pris" required></div>
              <button class="btn ghost small" type="submit" name="action" value="kundpris">Sätt kundpris</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="detail-card" id="webbkonton">
          <h2>Webbkonton</h2>
          <?php if (!$konton): ?>
            <p class="form-note">Inget konto på hemsidan är kopplat till kunden.</p>
          <?php else: ?>
            <ul class="logg">
              <?php foreach ($konton as $u): ?>
                <li><span><?php echo e($u['email']); ?><br><span class="muted" style="font-size:.82rem;"><?php echo e($u['kontakt']); ?> &middot; <?php echo !(int)$u['active'] ? 'Avstängt' : ((int)$u['godkand'] ? 'Godkänt' : 'Väntar på godkännande'); ?></span></span>
                  <?php if (far($user, 'webbkonton')): ?>
                    <form method="post" action="admin-kund" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>"><button class="btn ghost small" type="submit" name="action" value="koppla_bort" aria-label="Koppla bort <?php echo e($u['email']); ?>">&times;</button></form>
                  <?php endif; ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if (far($user, 'webbkonton')): ?>
            <form class="liten-form" method="post" action="admin-kund">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="id" value="<?php echo $id; ?>">
              <div class="field"><label for="koppla_epost">E-post för webbkontot</label><input type="text" inputmode="email" id="koppla_epost" name="epost" value="<?php echo e($forslag[0] ?? ''); ?>" required list="forslagLista"></div>
              <datalist id="forslagLista"><?php foreach ($forslag as $fe): ?><option value="<?php echo e($fe); ?>"><?php endforeach; ?></datalist>
              <button class="btn ghost small" type="submit" name="action" value="koppla">Koppla</button>
            </form>
            <?php if ($forslag): ?><p class="form-note" style="margin-top:8px;">Förslag: <?php echo e(implode(', ', $forslag)); ?> har ett konto med samma e-post som kunden.</p><?php endif; ?>
          <?php endif; ?>
        </div>

        <?php if ($order): ?>
          <div class="detail-card">
            <h2>Senaste order från hemsidan</h2>
            <ul class="logg">
              <?php foreach ($order as $o): ?>
                <li><span><?php if (far($user, 'order')): ?><a style="color:var(--brand); font-weight:700; text-decoration:none;" href="admin-order?id=<?php echo (int)$o['id']; ?>">#<?php echo (int)$o['id']; ?></a><?php else: ?>#<?php echo (int)$o['id']; endif; ?> <?php echo status_pill($o['status']); ?></span><span class="tid"><?php echo e(datum($o['created_at'])); ?></span></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <div class="detail-card">
          <h2>Visma</h2>
          <dl class="kv">
            <dt>Kund i Visma</dt><dd><?php echo $kund['visma_id'] ? 'Kopplad' : 'Inte kopplad än'; ?></dd>
            <dt>Ändrad</dt><dd><?php echo e(datum($kund['uppdaterad']) . ($kund['uppdaterad_av'] !== '' ? ' av ' . $kund['uppdaterad_av'] : '')); ?></dd>
          </dl>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
<script>
// Ny kundgrupp föreslår prisgrupp som i Partihandel: butik Grupp 1, restaurang Grupp 2, kommun Grupp 3.
(function () {
  var grupp = document.getElementById('kundgrupp'), pris = document.getElementById('prisgrupp');
  var forslag = {B: '1', L: '2', A: '3'};
  if (grupp && pris) grupp.addEventListener('change', function () {
    if (pris.value === '' && forslag[grupp.value]) pris.value = forslag[grupp.value];
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
