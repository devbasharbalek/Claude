<?php
require __DIR__ . '/includes/app.php';
$user = require_behorighet('order');
erp_schema();
veckansklipp_schema();
$kanAndra = far($user, 'order_andra');
$kanPris = far($user, 'priser');
$seInpris = far($user, 'inpris_se');
$av = vem($user);

function order_krav_andra(bool $kanAndra): void {
  if (!$kanAndra) {
    http_response_code(403);
    exit('Du har inte behörighet att ändra order.');
  }
}

// Fyller i orderns kunduppgifter från kundregistret.
function order_kundfalt(array $kund): array {
  $epost = trim(explode(';', str_replace(',', ';', $kund['foljesedel_epost'] ?: $kund['faktura_epost']))[0]);
  return [
    'kund_id' => (int)$kund['id'],
    'foretag' => mb_substr($kund['namn'], 0, 120, 'UTF-8'),
    'kundgrupp' => $kund['kundgrupp'],
    'kontakt' => mb_substr($kund['er_referens'] ?: $kund['lev_namn'], 0, 120, 'UTF-8'),
    'telefon' => mb_substr($kund['telefon'], 0, 40, 'UTF-8'),
    'epost' => mb_substr($epost, 0, 160, 'UTF-8'),
    'adress' => mb_substr(trim(implode(', ', array_filter([$kund['lev_namn'] !== $kund['namn'] ? $kund['lev_namn'] : '', $kund['lev_adress'], trim($kund['lev_postnr'] . ' ' . $kund['lev_ort'])]))), 0, 200, 'UTF-8'),
  ];
}

// ---------- ny order: välj kund ----------
if (!empty($_GET['ny']) || ($_POST['action'] ?? '') === 'skapa') {
  order_krav_andra($kanAndra);
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kund = kund_hamta((int)($_POST['kund_id'] ?? 0));
    if (!$kund || !(int)$kund['aktiv'] || !far_grupp($user, $kund['kundgrupp'])) {
      flash('Välj en aktiv kund.');
      redirect('admin-order.php?ny=1');
    }
    $datum = forslag_leveransdatum($kund);
    $k = order_kundfalt($kund);
    db()->prepare("INSERT INTO orders (user_id, kund_id, foretag, kundgrupp, kontakt, telefon, epost, adress, leveransdag, leveransdatum, kategorier, meddelande,
                     var_referens, er_referens, betalningsvillkor, kalla, skapad_av, andrad_av, status, created_at, updated_at)
                   VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', ?, ?, ?, 'personal', ?, ?, 'bekraftad', ?, ?)")
        ->execute([$k['kund_id'], $k['foretag'], $k['kundgrupp'], $k['kontakt'], $k['telefon'], $k['epost'], $k['adress'],
                   VECKODAG_KOD[(int)date('N', strtotime($datum))], $datum, mb_substr($kund['var_referens'] ?: $av, 0, 80, 'UTF-8'),
                   mb_substr($kund['er_referens'], 0, 120, 'UTF-8'), $kund['betalningsvillkor'], $av, $av, now(), now()]);
    redirect('admin-order.php?id=' . (int)db()->lastInsertId());
  }
  $sok = enRad($_GET['sok'] ?? '', 80);
  $traffar = [];
  if ($sok !== '') {
    [$gv, $params] = grupp_villkor($user, 'k.kundgrupp');
    $where = ['k.aktiv = 1', $gv];
    foreach (array_slice(preg_split('/\s+/u', $sok, -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $ord) {
      $esc = addcslashes($ord, '%_\\');
      $where[] = '(k.namn LIKE ? OR k.lev_namn LIKE ? OR k.kundnr LIKE ? OR k.lev_ort LIKE ?)';
      array_push($params, "%$esc%", "%$esc%", "$esc%", "%$esc%");
    }
    $st = db()->prepare('SELECT k.* FROM kunder k WHERE ' . implode(' AND ', $where) . ' ORDER BY k.kundnr = ? DESC, k.namn LIMIT 40');
    $st->execute(array_merge($params, [$sok]));
    $traffar = $st->fetchAll();
  }
  $active = 'admin';
  $adminSida = 'order';
  $pageTitle = 'Ny order – Admin – Ludvika Partiaffär';
  require __DIR__ . '/includes/head.php';
  ?>
  <section>
    <div class="wrap">
      <a class="back-link" href="admin.php">&larr; Order</a>
      <div class="portal-head"><div><h1>Ny order</h1><p>Välj kund. Kundens priser, referenser och betalningsvillkor följer med till ordern.</p></div></div>
      <form class="verktyg" method="get" action="admin-order.php">
        <input type="hidden" name="ny" value="1">
        <input type="search" name="sok" value="<?php echo e($sok); ?>" placeholder="Kundnamn, kundnr eller ort" aria-label="Sök kund" autofocus>
        <button class="btn primary small" type="submit">Sök kund</button>
      </form>
      <?php if ($sok !== '' && !$traffar): ?>
        <div class="empty"><p>Ingen aktiv kund matchar sökningen.<?php if (far($user, 'kunder')): ?> <a href="admin-kund.php?ny=1" style="color:var(--brand);">Lägg upp en ny kund</a><?php endif; ?></p></div>
      <?php elseif ($traffar): ?>
        <div class="table-wrap">
          <table class="erp">
            <thead><tr><th>Kundnr</th><th>Kund</th><th>Grupp</th><th>Prisgrupp</th><th>Ort</th><th>Tur</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($traffar as $k): ?>
              <tr>
                <td class="nr"><?php echo e($k['kundnr']); ?></td>
                <td><strong><?php echo e($k['namn']); ?></strong><?php if ($k['lev_namn'] !== '' && $k['lev_namn'] !== $k['namn']): ?><br><span class="muted" style="font-size:.84rem;"><?php echo e($k['lev_namn']); ?></span><?php endif; ?></td>
                <td><?php echo grupp_pill($k['kundgrupp']); ?></td>
                <td class="muted"><?php echo e(prisgrupp_text($k['prisgrupp'] === null ? null : (int)$k['prisgrupp'])); ?></td>
                <td class="muted"><?php echo e($k['lev_ort']); ?></td>
                <td class="muted"><?php echo e($k['tur']); ?></td>
                <td class="num"><form method="post" action="admin-order.php" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="action" value="skapa"><input type="hidden" name="kund_id" value="<?php echo (int)$k['id']; ?>"><button class="btn primary small" type="submit">Välj</button></form></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </section>
  <?php
  require __DIR__ . '/includes/foot.php';
  exit;
}

// ---------- befintlig order ----------
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$order = order_hamta($id);
if (!$order) {
  http_response_code(404);
  exit('Beställningen finns inte.');
}
if (!far_grupp($user, order_grupp($order))) {
  http_response_code(403);
  exit('Ordern hör till en kundgrupp du inte har behörighet till.');
}
$osk = order_superklipp($id);
$kund = $order['register_id'] ? kund_hamta((int)$order['register_id']) : null;
$alla = order_rader_alla($id);
$listRader = array_values(array_filter($alla, fn($r) => $r['typ'] === 'lista'));
$rader = array_values(array_filter($alla, fn($r) => $r['typ'] !== 'lista'));
$last = in_array($order['status'], ['fakturerad', 'avbruten'], true);
$redigerbar = $kanAndra && !$last;
$fel = [];

$statusMejl = [
  'bekraftad' => "Din beställning #%d är nu bekräftad. Vi levererar enligt överenskommelse.",
  'levererad' => "Din beställning #%d är levererad. Tack för att du handlar hos oss!",
  'avbruten'  => "Din beställning #%d har avbrutits. Hör av dig på 0240-183 55 om du har frågor.",
];

// Läser en rad från formuläret. Nya rader hämtar artikel och kundens pris, befintliga behåller sina sparade uppgifter.
function order_rad_fran_post(array $p, array $befintliga, ?array $kund, bool $kanPris, array $user, array &$fel): ?array {
  $typ = ($p['typ'] ?? '') === 'text' ? 'text' : 'artikel';
  $fore = isset($p['id']) && isset($befintliga[(int)$p['id']]) ? $befintliga[(int)$p['id']] : null;
  if ($fore && $fore['typ'] !== $typ) $fore = null;
  if ($typ === 'text') {
    $text = enRad($p['text'] ?? '', 160);
    if ($text === '') return null;
    return ['id' => $fore['id'] ?? null, 'typ' => 'text', 'produkt' => $text, 'pris' => 0, 'antal' => 0, 'enhet' => '', 'per_kolli' => 0, 'viktvara' => 0];
  }
  if ($fore) {
    $r = $fore;
  } else {
    $a = artikel_hamta((int)($p['artikel_id'] ?? 0));
    if (!$a || !far_grupp($user, $a['artikelgrupp'], 'artikelgrupper')) return null;
    $r = order_rad_for_artikel($a, $kund);
    $r['plockat'] = null;
    $r['plockvikt'] = null;
  }
  $namn = $r['artikelnr'] . ' ' . $r['produkt'];
  $kolli = tal_ur_text((string)($p['kolli'] ?? ''));
  $enheter = tal_ur_text((string)($p['enheter'] ?? ''));
  foreach (['kolli' => $kolli, 'enheter' => $enheter] as $f => $t) {
    if ($t === false || ($t !== null && ($t < 0 || $t > 99999))) {
      $fel[] = "$namn: skriv antal $f som ett tal.";
      return array_merge($r, ['kolli_text' => $p['kolli'] ?? '', 'enheter_text' => $p['enheter'] ?? '', 'pris_text' => $p['pris'] ?? '']);
    }
  }
  if ($kolli !== null && $enheter === null) $enheter = round($kolli * (float)$r['enheter_per_kolli'], 3);
  if ($kolli === null && $enheter === null) {
    $fel[] = "$namn: fyll i antal kolli eller antal enheter.";
    return array_merge($r, ['kolli_text' => '', 'enheter_text' => '', 'pris_text' => $p['pris'] ?? '']);
  }
  // Ändras antalet på en plockad rad behöver den plockas om.
  $andratAntal = $fore && (!samma_pris($fore['kolli'] === null ? null : (float)$fore['kolli'], $kolli) || !samma_pris($fore['enheter'] === null ? null : (float)$fore['enheter'], $enheter));
  $r['kolli'] = $kolli === null ? null : round($kolli, 3);
  $r['enheter'] = round((float)$enheter, 3);
  if ($andratAntal) {
    $r['plockat'] = null;
    $r['plockvikt'] = null;
  }
  if ($kanPris) {
    $pris = pris_ur_text((string)($p['pris'] ?? ''));
    if ($pris === false) {
      $fel[] = "$namn: priset ska vara ett belopp, till exempel 129,95.";
      return array_merge($r, ['kolli_text' => $p['kolli'] ?? '', 'enheter_text' => $p['enheter'] ?? '', 'pris_text' => $p['pris'] ?? '']);
    }
    if ($pris !== null && !samma_pris($pris, (float)$r['pris'])) {
      $r['pris'] = $pris;
      $r['priskalla'] = 'manuell';
    }
  }
  $r['plockkommentar'] = enRad($p['plockkommentar'] ?? '', 200);
  return $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? 'status');

  if ($action === 'status') {
    $ny = (string)($_POST['status'] ?? '');
    if (isset(ORDER_STATUS[$ny]) && $ny !== $order['status']) {
      $extra = match ($ny) {
        'plockad' => ', plockad_at = COALESCE(plockad_at, NOW())',
        'fakturerad' => ', fakturerad_at = NOW()',
        default => '',
      };
      db()->prepare("UPDATE orders SET status = ?, updated_at = ?, andrad_av = ? $extra WHERE id = ?")->execute([$ny, now(), $av, $id]);
      $meddelat = false;
      if (!empty($_POST['meddela']) && isset($statusMejl[$ny]) && filter_var($order['epost'], FILTER_VALIDATE_EMAIL)) {
        $text = ["Hej {$order['kontakt']}!", '', sprintf($statusMejl[$ny], $id)];
        if ($order['user_id']) {
          $text[] = '';
          $text[] = 'Se beställningen: ' . $CONFIG['site_url'] . '/order.php?id=' . $id;
        }
        array_push($text, '', 'Med vänlig hälsning', 'Ludvika Partiaffär, 0240-183 55');
        $meddelat = send_mail($order['epost'], "Beställning #$id: " . mb_strtolower(ORDER_STATUS[$ny]), implode("\n", $text), $CONFIG['mail_to']);
      }
      flash("Beställning #$id är nu " . mb_strtolower(ORDER_STATUS[$ny]) . ($meddelat ? ', och kunden har fått ett mejl.' : '.'));
    }
    redirect('admin-order.php?id=' . $id);
  }

  order_krav_andra($kanAndra);

  if ($action === 'koppla_kund') {
    $ny = kund_via_nr(enRad($_POST['kundnr'] ?? '', 40));
    if (!$ny || !far_grupp($user, $ny['kundgrupp'])) {
      flash('Kundnumret finns inte i kundregistret.');
    } else {
      // Orderns kontaktuppgifter från hemsidan behålls. Bara kopplingen och kundgruppen ändras.
      db()->prepare('UPDATE orders SET kund_id = ?, kundgrupp = COALESCE(?, kundgrupp), betalningsvillkor = COALESCE(betalningsvillkor, ?), updated_at = ?, andrad_av = ? WHERE id = ?')
          ->execute([$ny['id'], $ny['kundgrupp'], $ny['betalningsvillkor'], now(), $av, $id]);
      flash('Ordern är kopplad till ' . $ny['kundnr'] . ' ' . $ny['namn'] . '. Nya rader får kundens priser.');
    }
    redirect('admin-order.php?id=' . $id);
  }

  if ($action === 'kopiera') {
    $datum = forslag_leveransdatum($kund);
    db()->prepare("INSERT INTO orders (user_id, kund_id, foretag, kundgrupp, kontakt, telefon, epost, adress, leveransdag, leveransdatum, kategorier, meddelande,
                     kundens_ordernr, kundtext, plocktext, var_referens, er_referens, betalningsvillkor, kalla, skapad_av, andrad_av, status, created_at, updated_at)
                   SELECT user_id, ?, foretag, kundgrupp, kontakt, telefon, epost, adress, ?, ?, '', '', '', kundtext, plocktext, var_referens, er_referens,
                          betalningsvillkor, 'personal', ?, ?, 'bekraftad', ?, ? FROM orders WHERE id = ?")
        ->execute([$kund['id'] ?? null, VECKODAG_KOD[(int)date('N', strtotime($datum))], $datum, $av, $av, now(), now(), $id]);
    $nyId = (int)db()->lastInsertId();
    // Raderna kopieras med dagens priser för kunden.
    $i = 0;
    foreach ($rader as $r) {
      if ($r['typ'] === 'artikel' && $r['artikel_id'] && ($a = artikel_hamta((int)$r['artikel_id'])) && (int)$a['aktiv']) {
        $ny = order_rad_for_artikel($a, $kund);
        $ny['kolli'] = $r['kolli'] === null ? null : (float)$r['kolli'];
        $ny['enheter'] = $r['kolli'] === null ? (float)$r['enheter'] : round((float)$r['kolli'] * (float)$ny['enheter_per_kolli'], 3);
        $ny['plockkommentar'] = $r['plockkommentar'];
        order_rad_spara($nyId, $ny, 1000 + $i++);
      } elseif ($r['typ'] === 'text') {
        order_rad_spara($nyId, ['typ' => 'text', 'produkt' => $r['produkt'], 'pris' => 0, 'antal' => 0], 1000 + $i++);
      }
    }
    flash("Order #$nyId är en kopia av #$id med dagens priser. Kontrollera leveransdatum och antal.");
    redirect('admin-order.php?id=' . $nyId);
  }

  if ($action === 'orderbekraftelse') {
    $till = trim(explode(';', str_replace(',', ';', (string)($kund['foljesedel_epost'] ?? '') ?: $order['epost']))[0]);
    $s = order_summor($rader);
    if (!filter_var($till, FILTER_VALIDATE_EMAIL)) {
      flash('Kunden har ingen giltig e-postadress för följesedel eller order.');
    } elseif (!$s['har_pris']) {
      flash('Ordern har inga artikelrader att bekräfta.');
    } else {
      $text = ['Hej!', '', "Tack för er beställning. Här är orderbekräftelsen för order #$id.", '',
               'Kund:           ' . ($kund ? $kund['kundnr'] . ' ' : '') . $order['foretag'],
               'Leveransdatum:  ' . leverans_text($order['leveransdatum'], $order['leveransdag'])];
      if ($order['kundens_ordernr'] !== '') $text[] = 'Ert ordernummer: ' . $order['kundens_ordernr'];
      if ($order['er_referens'] !== '') $text[] = 'Er referens:    ' . $order['er_referens'];
      $text[] = '';
      foreach ($rader as $r) {
        if ($r['typ'] === 'text') {
          $text[] = '  ' . $r['produkt'];
          continue;
        }
        $antal = $r['kolli'] !== null ? tal_text($r['kolli']) . ' kolli, ' : '';
        $text[] = sprintf('  %s %s – %s%s %s à %s = %s kr', $r['artikelnr'], $r['produkt'], $antal, tal_text($r['enheter']), $r['enhet'],
                          pris_text($r['pris']) . ((int)$r['per_kolli'] ? '/kolli' : '/' . $r['enhet']), pris_text($r['belopp']));
      }
      array_push($text, '', 'Summa exkl. moms: ' . pris_text($s['netto']) . ' kr', 'Moms:             ' . pris_text($s['moms']) . ' kr',
                 'Att betala:       ' . pris_text($s['totalt']) . ' kr');
      if ($order['kundtext'] !== '') array_push($text, '', $order['kundtext']);
      array_push($text, '', 'Hör av er om något inte stämmer.', '', 'Med vänlig hälsning', 'Ludvika Partiaffär, 0240-183 55');
      if (send_mail($till, "Orderbekräftelse #$id från Ludvika Partiaffär", implode("\n", $text), $CONFIG['mail_to'])) {
        if ($order['status'] === 'ny') db()->prepare("UPDATE orders SET status = 'bekraftad', updated_at = ?, andrad_av = ? WHERE id = ?")->execute([now(), $av, $id]);
        flash("Orderbekräftelsen är skickad till $till.");
      } else {
        flash('Mejlet kunde inte skickas. Försök igen om en stund.');
      }
    }
    redirect('admin-order.php?id=' . $id);
  }

  if ($action === 'spara') {
    if ($last) {
      flash('Ordern är ' . mb_strtolower(ORDER_STATUS[$order['status']]) . ' och kan inte ändras.');
      redirect('admin-order.php?id=' . $id);
    }
    $befintliga = [];
    foreach ($rader as $r) $befintliga[(int)$r['id']] = $r;
    $nyaRader = [];
    foreach ((array)($_POST['rad'] ?? []) as $p) {
      if (!is_array($p)) continue;
      $r = order_rad_fran_post($p, $befintliga, $kund, $kanPris, $user, $fel);
      if ($r !== null) $nyaRader[] = $r;
    }
    $datum = trim((string)($_POST['leveransdatum'] ?? ''));
    $datum = $datum === '' ? null : giltigt_datum($datum);
    if (trim((string)($_POST['leveransdatum'] ?? '')) !== '' && $datum === null) $fel[] = 'Leveransdatum ska vara ett datum.';
    $dagar = trim((string)($_POST['betalningsvillkor'] ?? ''));
    if ($dagar !== '' && (!ctype_digit($dagar) || (int)$dagar > 365)) $fel[] = 'Betalningsvillkor ska vara antal dagar.';
    $huvud = [
      'leveransdatum' => $datum,
      'kundens_ordernr' => enRad($_POST['kundens_ordernr'] ?? '', 60),
      'plocktext' => enRad($_POST['plocktext'] ?? '', 500),
      'kundtext' => enRad($_POST['kundtext'] ?? '', 500),
      'var_referens' => enRad($_POST['var_referens'] ?? '', 80),
      'er_referens' => enRad($_POST['er_referens'] ?? '', 120),
      'betalningsvillkor' => $dagar === '' ? null : (int)$dagar,
    ];
    if (!$fel) {
      db()->beginTransaction();
      $behall = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $nyaRader));
      foreach ($befintliga as $rid => $r) {
        if (!in_array($rid, $behall, true)) db()->prepare('DELETE FROM order_rader WHERE id = ? AND order_id = ?')->execute([$rid, $id]);
      }
      foreach ($nyaRader as $i => $r) {
        order_rad_spara($id, $r, 1000 + $i);
        // Plockstatus nollställs när antalet har ändrats.
        if (!empty($r['id']) && $r['typ'] === 'artikel' && ($r['plockat'] ?? null) === null) db()->prepare('UPDATE order_rader SET plockat = NULL, plockvikt = NULL, plockad_av = \'\' WHERE id = ?')->execute([(int)$r['id']]);
      }
      $dagKod = $datum !== null ? VECKODAG_KOD[(int)date('N', strtotime($datum))] : $order['leveransdag'];
      db()->prepare('UPDATE orders SET leveransdatum = ?, leveransdag = ?, kundens_ordernr = ?, plocktext = ?, kundtext = ?, var_referens = ?, er_referens = ?,
                     betalningsvillkor = ?, updated_at = ?, andrad_av = ? WHERE id = ?')
          ->execute([$huvud['leveransdatum'], $dagKod, $huvud['kundens_ordernr'], $huvud['plocktext'], $huvud['kundtext'], $huvud['var_referens'],
                     $huvud['er_referens'], $huvud['betalningsvillkor'], now(), $av, $id]);
      if ($order['status'] === 'ny' && $nyaRader) db()->prepare("UPDATE orders SET status = 'bekraftad' WHERE id = ?")->execute([$id]);
      order_plockstatus($id, $av);
      db()->commit();
      flash('Ordern är sparad.');
      redirect('admin-order.php?id=' . $id);
    }
    // Felen visas med det som skrevs, så att inget behöver skrivas om.
    $order = array_merge($order, $huvud, ['leveransdatum' => trim((string)($_POST['leveransdatum'] ?? ''))]);
    $rader = $nyaRader;
  }
}

$summor = order_summor(array_map(fn($r) => rad_berakna($r), $rader));
$kundensPrisgrupp = $kund && $kund['prisgrupp'] !== null ? (int)$kund['prisgrupp'] : null;
$plockade = count(array_filter($alla, fn($r) => $r['typ'] !== 'text' && $r['plockat'] !== null));
$attPlocka = count(array_filter($alla, fn($r) => $r['typ'] !== 'text'));

function rad_cell_pris(array $r): string {
  return isset($r['pris_text']) ? (string)$r['pris_text'] : pris_text($r['pris']);
}

$active = 'admin';
$adminSida = 'order';
$pageTitle = 'Order #' . $id . ' – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <a class="back-link" href="admin.php">&larr; Alla order</a>
    <div class="portal-head">
      <div>
        <span class="eyebrow">Beställning</span>
        <h1>Order #<?php echo $id; ?> <?php echo status_pill($order['status']); ?></h1>
        <p><?php echo e($osk['etikett'] ?? (ORDER_KALLOR[$order['kalla']] ?? $order['kalla'])); ?> &middot; inkom <?php echo e(datum($order['created_at'])); ?><?php echo $order['skapad_av'] !== '' ? ' av ' . e($order['skapad_av']) : ''; ?><?php if ($order['updated_at'] !== $order['created_at']): ?> &middot; ändrad <?php echo e(datum($order['updated_at'])); ?><?php echo $order['andrad_av'] !== '' ? ' av ' . e($order['andrad_av']) : ''; ?><?php endif; ?></p>
      </div>
      <div class="portal-actions">
        <?php if (far($user, 'plock')): ?>
          <a class="btn ghost small" href="admin-utskrift.php?typ=plocksedel&amp;id=<?php echo $id; ?>" target="_blank">Plocksedel</a>
        <?php endif; ?>
        <a class="btn ghost small" href="admin-utskrift.php?typ=foljesedel&amp;id=<?php echo $id; ?>" target="_blank">Följesedel</a>
        <?php if ($kanAndra): ?>
          <form method="post" action="admin-order.php" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn ghost small" type="submit" name="action" value="orderbekraftelse">Mejla orderbekräftelse</button></form>
          <form method="post" action="admin-order.php" style="margin:0;"><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><button class="btn ghost small" type="submit" name="action" value="kopiera">Kopiera</button></form>
        <?php endif; ?>
        <?php if ($order['telefon'] !== ''): ?><a class="btn ghost small" href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $order['telefon'])); ?>">Ring kunden</a><?php endif; ?>
        <?php if ($order['epost'] !== ''): ?><a class="btn ghost small" href="mailto:<?php echo e($order['epost']); ?>?subject=<?php echo rawurlencode('Er beställning #' . $id); ?>">Mejla kunden</a><?php endif; ?>
      </div>
    </div>

    <?php if ($fel): ?><div class="alert error" role="alert"><strong>Ordern sparades inte:</strong><ul><?php foreach ($fel as $m): ?><li><?php echo e($m); ?></li><?php endforeach; ?></ul></div><?php endif; ?>

    <?php if (!$kund && $kanAndra && !$last): ?>
      <form class="kund-banner" method="post" action="admin-order.php">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        <span><strong>Ordern är inte kopplad till en kund i kundregistret.</strong> Koppla den med kundnumret, så får artikelraderna kundens priser.</span>
        <span style="display:flex; gap:8px;"><input type="text" name="kundnr" placeholder="Kundnr" aria-label="Kundnummer" style="width:120px; padding:7px 10px;"><button class="btn ghost small" type="submit" name="action" value="koppla_kund">Koppla</button></span>
      </form>
    <?php endif; ?>

    <div class="order-topp">
        <form class="detail-card" method="post" action="admin-order.php">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="id" value="<?php echo $id; ?>">
          <input type="hidden" name="action" value="status">
          <h2>Status</h2>
          <div class="status-form">
            <label for="status" class="sr-only">Ny status</label>
            <select id="status" name="status">
              <?php foreach (ORDER_STATUS as $key => $label): ?>
                <option value="<?php echo $key; ?>"<?php echo $key === $order['status'] ? ' selected' : ''; ?>><?php echo $label; ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn primary small" type="submit">Spara</button>
          </div>
          <?php if (filter_var($order['epost'], FILTER_VALIDATE_EMAIL)): ?>
            <label class="check inline" style="margin-top:14px;"><input type="checkbox" name="meddela" value="1"<?php echo $order['user_id'] ? ' checked' : ''; ?>> <span>Mejla kunden om ändringen (<?php echo e($order['epost']); ?>)</span></label>
          <?php endif; ?>
          <dl class="kv" style="margin-top:16px;">
            <dt>Plockare</dt><dd><?php echo e($order['plockare_namn'] ?? 'Ej tilldelad'); ?></dd>
            <?php if ($order['plockad_at']): ?><dt>Plockad</dt><dd><?php echo e(datum($order['plockad_at'])); ?></dd><?php endif; ?>
            <dt>Kontroll</dt><dd><?php echo $order['kontrollerad_at'] ? 'Kontrollerad av ' . e($order['kontrollerad_av']) . ', ' . e(datum($order['kontrollerad_at'])) : 'Ej kontrollerad'; ?></dd>
          </dl>
        </form>

        <div class="detail-card">
          <h2>Kund</h2>
          <dl class="kv">
            <?php if ($kund): ?>
              <dt>Kundregistret</dt><dd><?php if (far($user, 'kunder')): ?><a href="admin-kund.php?id=<?php echo (int)$kund['id']; ?>" style="color:var(--brand);"><?php echo e($kund['kundnr'] . ' ' . $kund['namn']); ?></a><?php else: echo e($kund['kundnr'] . ' ' . $kund['namn']); endif; ?></dd>
            <?php endif; ?>
            <dt>Företag</dt><dd><?php echo e($order['foretag']); ?></dd>
            <dt>Kundgrupp</dt><dd><?php echo e(register_grupp_text(order_grupp($order))); ?></dd>
            <?php if ($kund): ?><dt>Prisgrupp</dt><dd><?php echo e($kund['prisgrupp'] === null ? 'Grundpris' : prisgrupp_text($kundensPrisgrupp)); ?></dd><?php endif; ?>
            <dt>Kontakt</dt><dd><?php echo e($order['kontakt']); ?></dd>
            <dt>Telefon</dt><dd><?php echo e($order['telefon']); ?></dd>
            <dt>E-post</dt><dd><?php echo e($order['epost']); ?></dd>
            <dt>Adress</dt><dd><?php echo e($order['adress']); ?></dd>
            <dt>Konto</dt><dd>
              <?php if ($order['user_id']): ?>
                <a href="admin.php?visa=alla&amp;kund=<?php echo (int)$order['user_id']; ?>" style="color:var(--brand);">Alla beställningar från kontot</a>
                <?php if (!(int)$order['kund_active']): ?><br><span class="pill pill-off">Avstängt konto</span><?php endif; ?>
              <?php elseif ($order['kalla'] === 'personal'): ?>
                Lagd av personalen
              <?php else: ?>
                Gäst, beställde utan konto
              <?php endif; ?>
            </dd>
          </dl>
        </div>
    </div>

    <div class="order-huvud">
      <div>
        <?php if ($redigerbar): ?><form method="post" action="admin-order.php" id="orderForm" novalidate><?php echo csrf_field(); ?><input type="hidden" name="id" value="<?php echo $id; ?>"><input type="hidden" name="action" value="spara"><?php endif; ?>
        <div class="detail-card">
          <h2>Order</h2>
          <?php if ($redigerbar): ?>
            <div class="field-grid tre">
              <div class="field"><label for="leveransdatum">Leveransdatum</label><input type="date" id="leveransdatum" name="leveransdatum" value="<?php echo e((string)$order['leveransdatum']); ?>"></div>
              <div class="field"><label for="kundens_ordernr">Kundens ordernr</label><input type="text" id="kundens_ordernr" name="kundens_ordernr" value="<?php echo e($order['kundens_ordernr']); ?>"></div>
              <div class="field"><label for="betalningsvillkor">Betalningsvillkor (dagar)</label><input type="text" inputmode="numeric" id="betalningsvillkor" name="betalningsvillkor" value="<?php echo e((string)$order['betalningsvillkor']); ?>"></div>
              <div class="field"><label for="var_referens">Vår referens</label><input type="text" id="var_referens" name="var_referens" value="<?php echo e($order['var_referens']); ?>"></div>
              <div class="field"><label for="er_referens">Er referens</label><input type="text" id="er_referens" name="er_referens" value="<?php echo e($order['er_referens']); ?>"></div>
              <div class="field"><label>Leveransdag</label><input type="text" readonly value="<?php echo e(leverans_text($order['leveransdatum'] !== '' ? $order['leveransdatum'] : null, $order['leveransdag'])); ?>"></div>
            </div>
            <div class="field-grid" style="margin-top:14px;">
              <div class="field"><label for="plocktext">Text på plocksedeln</label><input type="text" id="plocktext" name="plocktext" value="<?php echo e($order['plocktext']); ?>" placeholder="Till lagret, t.ex. Ställ på 1/2 pall"></div>
              <div class="field"><label for="kundtext">Meddelande till kund</label><input type="text" id="kundtext" name="kundtext" value="<?php echo e($order['kundtext']); ?>" placeholder="Skrivs på följesedeln"></div>
            </div>
          <?php else: ?>
            <dl class="kv">
              <dt>Leveransdatum</dt><dd><?php echo e(leverans_text($order['leveransdatum'], $order['leveransdag'])); ?></dd>
              <?php if ($order['kundens_ordernr'] !== ''): ?><dt>Kundens ordernr</dt><dd><?php echo e($order['kundens_ordernr']); ?></dd><?php endif; ?>
              <?php if ($order['plocktext'] !== ''): ?><dt>Plocksedel</dt><dd><?php echo e($order['plocktext']); ?></dd><?php endif; ?>
              <?php if ($order['kundtext'] !== ''): ?><dt>Till kund</dt><dd><?php echo e($order['kundtext']); ?></dd><?php endif; ?>
              <?php if ($order['var_referens'] !== ''): ?><dt>Vår referens</dt><dd><?php echo e($order['var_referens']); ?></dd><?php endif; ?>
              <?php if ($order['er_referens'] !== ''): ?><dt>Er referens</dt><dd><?php echo e($order['er_referens']); ?></dd><?php endif; ?>
            </dl>
          <?php endif; ?>
        </div>

        <div class="detail-card">
          <div class="rubrik-rad"><h2>Rader <span class="h2-text">exkl. moms<?php echo $kund ? ', ' . e($kund['prisgrupp'] === null ? 'grundpris' : prisgrupp_text($kundensPrisgrupp)) . ' och kundpriser' : ''; ?></span></h2>
            <?php if ($attPlocka): ?><span class="form-note">Plockat <?php echo $plockade; ?> av <?php echo $attPlocka; ?> rader</span><?php endif; ?></div>
          <div class="table-wrap bred">
            <table class="erp orderrader" id="orderRader">
              <thead><tr>
                <th>Art.nr</th><th>Artikel</th><th>Plockkommentar</th><th class="num">Kolli</th><th class="num">Enheter</th>
                <?php if ($seInpris): ?><th class="num">Inpris</th><?php endif; ?><th class="num">Pris</th><th class="num">Belopp</th>
                <?php if ($seInpris): ?><th class="num">Vinst</th><th class="num">TG</th><?php endif; ?><th></th>
              </tr></thead>
              <tbody>
              <?php foreach ($rader as $i => $r): $r = rad_berakna($r) + ['plockat' => null, 'plockvikt' => null]; $k = 'r' . $i; ?>
                <?php if ($r['typ'] === 'text'): ?>
                  <tr class="textrad">
                    <td colspan="<?php echo $seInpris ? 10 : 7; ?>">
                      <?php if ($redigerbar): ?><input type="hidden" name="rad[<?php echo $k; ?>][typ]" value="text"><?php if (!empty($r['id'])): ?><input type="hidden" name="rad[<?php echo $k; ?>][id]" value="<?php echo (int)$r['id']; ?>"><?php endif; ?>
                        <input type="text" class="textrad-in" name="rad[<?php echo $k; ?>][text]" value="<?php echo e($r['produkt']); ?>" aria-label="Textrad">
                      <?php else: ?><em><?php echo e($r['produkt']); ?></em><?php endif; ?>
                    </td>
                    <td><?php if ($redigerbar): ?><button type="button" class="ta-bort" aria-label="Ta bort raden">&times;</button><?php endif; ?></td>
                  </tr>
                <?php else: $under = $r['inpris'] !== null && (float)$r['pris'] < (float)$r['inpris']; ?>
                  <tr class="artrad" data-epk="<?php echo e((string)$r['enheter_per_kolli']); ?>" data-per-kolli="<?php echo (int)$r['per_kolli']; ?>" data-viktvara="<?php echo (int)$r['viktvara']; ?>"
                      data-plockvikt="<?php echo e((string)($r['plockvikt'] ?? '')); ?>" data-inpris="<?php echo e((string)($r['inpris'] ?? '')); ?>" data-moms="<?php echo e((string)$r['moms']); ?>" data-pris="<?php echo e((string)$r['pris']); ?>" data-kg="<?php echo e((string)($r['kg_per_kolli'] ?? '')); ?>" data-enhet="<?php echo e($r['enhet']); ?>">
                    <td class="nr"><?php echo e($r['artikelnr']); ?></td>
                    <td><?php echo e($r['produkt']); ?><?php if ((int)$r['viktvara']): ?> <span class="vikt-markering" title="Viktvara: den plockade vikten gäller">!</span><?php endif; ?>
                      <span class="kalla"><?php echo e(($r['priskalla'] === 'manuell' ? 'Eget pris' : priskalla_text($r['priskalla'], $kundensPrisgrupp)) . ((int)$r['per_kolli'] ? ', pris per kolli' : '') . ($r['plockat'] !== null ? ', plockad ' . tal_text($r['plockat']) . ((int)$r['viktvara'] && $r['plockvikt'] !== null ? ' (' . tal_text($r['plockvikt']) . ' kg)' : '') : '')); ?></span>
                      <?php if ($redigerbar): ?><input type="hidden" name="rad[<?php echo $k; ?>][typ]" value="artikel"><?php if (!empty($r['id'])): ?><input type="hidden" name="rad[<?php echo $k; ?>][id]" value="<?php echo (int)$r['id']; ?>"><?php else: ?><input type="hidden" name="rad[<?php echo $k; ?>][artikel_id]" value="<?php echo (int)$r['artikel_id']; ?>"><?php endif; ?><?php endif; ?>
                    </td>
                    <?php if ($redigerbar): ?>
                      <td><input type="text" class="kommentar-in" name="rad[<?php echo $k; ?>][plockkommentar]" value="<?php echo e($r['plockkommentar']); ?>" aria-label="Plockkommentar"></td>
                      <td class="num"><input class="pris antal-kolli" type="text" inputmode="decimal" name="rad[<?php echo $k; ?>][kolli]" value="<?php echo e($r['kolli_text'] ?? mangd_text($r['kolli'])); ?>" aria-label="Antal kolli"></td>
                      <td class="num"><span class="enhet-ruta"><input class="pris antal-enheter" type="text" inputmode="decimal" name="rad[<?php echo $k; ?>][enheter]" value="<?php echo e($r['enheter_text'] ?? mangd_text($r['enheter'])); ?>" aria-label="Antal enheter"><small><?php echo e($r['enhet']); ?></small></span></td>
                    <?php else: ?>
                      <td class="muted"><?php echo e($r['plockkommentar']); ?></td>
                      <td class="num"><?php echo e(mangd_text($r['kolli'])); ?></td>
                      <td class="num"><?php echo e(mangd_text($r['enheter']) . ' ' . $r['enhet']); ?></td>
                    <?php endif; ?>
                    <?php if ($seInpris): ?><td class="num muted"><?php echo e(pris_text($r['inpris'])); ?></td><?php endif; ?>
                    <td class="num<?php echo $under ? ' under-inpris' : ''; ?>"><?php if ($redigerbar && $kanPris): ?><input class="pris rad-pris" type="text" inputmode="decimal" name="rad[<?php echo $k; ?>][pris]" value="<?php echo e(rad_cell_pris($r)); ?>" aria-label="Pris"><?php else: ?><?php echo e(pris_text($r['pris'])); ?><?php endif; ?></td>
                    <td class="num rad-belopp"><?php echo e(pris_text($r['belopp'])); ?></td>
                    <?php if ($seInpris): ?>
                      <td class="num rad-vinst"><?php echo $r['kostnad'] === null ? '' : e(pris_text((float)$r['belopp'] - (float)$r['kostnad'])); ?></td>
                      <td class="num rad-tg"><?php $m = marginal($r['inpris'] === null ? null : (float)$r['inpris'], (float)$r['pris']); echo $m === null ? '' : e(str_replace('.', ',', (string)$m)) . ' %'; ?></td>
                    <?php endif; ?>
                    <td><?php if ($redigerbar): ?><button type="button" class="ta-bort" aria-label="Ta bort <?php echo e($r['produkt']); ?>">&times;</button><?php endif; ?></td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
              <?php if (!$rader): ?><tr class="tom-rad"><td colspan="<?php echo $seInpris ? 11 : 8; ?>" class="muted" style="text-align:center; padding:22px;"><?php echo $redigerbar ? 'Inga artikelrader än. Sök fram en artikel nedan.' : 'Inga artikelrader.'; ?></td></tr><?php endif; ?>
              </tbody>
            </table>
          </div>
          <?php if ($redigerbar): ?>
            <div class="artikelsok">
              <input type="search" id="artSok" placeholder="Sök artikel med namn eller nummer och tryck Enter" autocomplete="off" aria-label="Sök artikel" aria-controls="artLista" aria-expanded="false" role="combobox" data-kund="<?php echo (int)($kund['id'] ?? 0); ?>">
              <ul class="art-lista" id="artLista" role="listbox" hidden></ul>
              <button type="button" class="btn ghost small" id="nyTextrad">+ Textrad</button>
            </div>
          <?php endif; ?>
          <div class="summor" id="summor">
            <div><span>Ordervärde</span><b id="sNetto"><?php echo e(pris_text($summor['netto'])); ?></b></div>
            <?php if ($seInpris): ?>
              <div><span>Inköp</span><b id="sKostnad"><?php echo e(pris_text($summor['kostnad'])); ?></b></div>
              <div><span>Vinst</span><b id="sVinst"><?php echo e(pris_text($summor['vinst'])); ?></b></div>
              <div><span>TG</span><b id="sTg"><?php echo $summor['marginal'] === null ? '–' : e(str_replace('.', ',', (string)$summor['marginal'])) . ' %'; ?></b></div>
            <?php endif; ?>
            <div><span>Moms</span><b id="sMoms"><?php echo e(pris_text($summor['moms'])); ?></b></div>
            <div><span>Att betala</span><b id="sTotalt"><?php echo e(pris_text($summor['totalt'])); ?></b></div>
            <div><span>Kolli</span><b id="sKolli"><?php echo e(tal_text($summor['kolli'])); ?></b></div>
            <div><span>Vikt</span><b id="sVikt"><?php echo e(tal_text(round($summor['vikt'], 1))); ?> kg</b></div>
          </div>
        </div>
        <?php if ($redigerbar): ?>
          <div class="sk-bar"><span id="sparaInfo">Ändra antal eller pris och spara.</span><button class="btn primary small" type="submit">Spara order</button></div>
          </form>
        <?php endif; ?>

        <?php if ($osk || $listRader || $order['meddelande'] !== '' || $order['kategorier'] !== ''): ?>
          <div class="detail-card">
            <h2>Från kunden</h2>
            <dl class="kv">
              <dt>Leveransdag</dt><dd><?php echo e(($leveransdagar[$order['leveransdag']] ?? $order['leveransdag']) . ($osk ? ' ' . date('j/n', strtotime($osk['leveransdatum'])) : '')); ?></dd>
              <?php if ($osk): ?>
                <dt>Från</dt><dd><?php if ($osk['superklipp_id'] && far($user, 'erbjudanden')): ?><a href="admin-superklipp-redigera.php?id=<?php echo (int)$osk['superklipp_id']; ?>" style="color:var(--brand);"><?php echo e($osk['etikett']); ?></a><?php elseif (!empty($osk['veckansklipp_id']) && far($user, 'erbjudanden')): ?><a href="admin-veckansklipp.php?id=<?php echo (int)$osk['veckansklipp_id']; ?>" style="color:var(--brand);"><?php echo e($osk['etikett']); ?></a><?php else: ?><?php echo e($osk['etikett']); ?><?php endif; ?></dd>
              <?php elseif ($order['kategorier'] !== ''): ?>
                <dt>Varugrupper</dt><dd><?php echo e(kategori_namn($order['kategorier'])); ?></dd>
              <?php endif; ?>
            </dl>
            <?php if ($listRader) echo sk_order_tabell($listRader); ?>
            <div class="order-text" style="margin-top:16px;"><?php echo $order['meddelande'] !== '' ? e($order['meddelande']) : '<span style="color:var(--ink-soft)">Inget meddelande</span>'; ?></div>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>
<?php if ($redigerbar): ?>
<template id="radMall">
  <tr class="artrad ny">
    <td class="nr"></td>
    <td><span class="art-namn"></span> <span class="vikt-markering" title="Viktvara: den plockade vikten gäller" hidden>!</span><span class="kalla"></span>
      <input type="hidden" data-f="typ" value="artikel"><input type="hidden" data-f="artikel_id"></td>
    <td><input type="text" class="kommentar-in" data-f="plockkommentar" aria-label="Plockkommentar"></td>
    <td class="num"><input class="pris antal-kolli" type="text" inputmode="decimal" data-f="kolli" aria-label="Antal kolli"></td>
    <td class="num"><span class="enhet-ruta"><input class="pris antal-enheter" type="text" inputmode="decimal" data-f="enheter" aria-label="Antal enheter"><small></small></span></td>
    <?php if ($seInpris): ?><td class="num muted rad-inpris"></td><?php endif; ?>
    <td class="num"><?php if ($kanPris): ?><input class="pris rad-pris" type="text" inputmode="decimal" data-f="pris" aria-label="Pris"><?php else: ?><span class="rad-pris-text"></span><?php endif; ?></td>
    <td class="num rad-belopp"></td>
    <?php if ($seInpris): ?><td class="num rad-vinst"></td><td class="num rad-tg"></td><?php endif; ?>
    <td><button type="button" class="ta-bort" aria-label="Ta bort raden">&times;</button></td>
  </tr>
</template>
<template id="textMall">
  <tr class="textrad ny"><td colspan="<?php echo $seInpris ? 10 : 7; ?>"><input type="hidden" data-f="typ" value="text"><input type="text" class="textrad-in" data-f="text" placeholder="Text som syns på plocksedel och följesedel" aria-label="Textrad"></td><td><button type="button" class="ta-bort" aria-label="Ta bort raden">&times;</button></td></tr>
</template>
<script>
(function () {
  var form = document.getElementById('orderForm');
  var tbody = document.querySelector('#orderRader tbody');
  var sok = document.getElementById('artSok');
  var lista = document.getElementById('artLista');
  var info = document.getElementById('sparaInfo');
  var andrad = false, sparar = false, raknare = 0, traffar = [], aktiv = -1, timer = null;

  function tal(s) {
    s = String(s == null ? '' : s).replace(/[\s ]/g, '').replace(/kr$/i, '');
    if (s === '') return null;
    if (s.indexOf(',') !== -1 && s.indexOf('.') !== -1) s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
    else s = s.replace(',', '.');
    return /^-?\d+(\.\d+)?$/.test(s) ? parseFloat(s) : NaN;
  }
  function kr(n) { return n == null || isNaN(n) ? '' : n.toLocaleString('sv-SE', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
  function antal(n) { return n == null || isNaN(n) ? '' : String(Math.round(n * 1000) / 1000).replace('.', ','); }

  function radBerakna(tr) {
    var d = tr.dataset, epk = parseFloat(d.epk) || 1;
    var kolli = tal(tr.querySelector('.antal-kolli').value), enh = tal(tr.querySelector('.antal-enheter').value);
    var prisIn = tr.querySelector('.rad-pris'), pris = prisIn ? tal(prisIn.value) : parseFloat(d.pris);
    if (prisIn && pris === null) pris = parseFloat(prisIn.placeholder.replace(/\s/g, '').replace(',', '.'));
    var antalPris = d.perKolli === '1' ? (kolli != null ? kolli : (enh || 0) / epk) : (d.viktvara === '1' && d.plockvikt !== '' ? parseFloat(d.plockvikt) : (enh || 0));
    var belopp = isNaN(pris) || isNaN(antalPris) ? NaN : Math.round(antalPris * pris * 100) / 100;
    var inpris = d.inpris === '' ? null : parseFloat(d.inpris);
    tr.querySelector('.rad-belopp').textContent = kr(belopp);
    var v = tr.querySelector('.rad-vinst'), tg = tr.querySelector('.rad-tg');
    if (v) v.textContent = inpris == null || isNaN(belopp) ? '' : kr(belopp - Math.round(antalPris * inpris * 100) / 100);
    if (tg) tg.textContent = inpris == null || !pris ? '' : String(Math.round((pris - inpris) / pris * 1000) / 10).replace('.', ',') + ' %';
    var cell = prisIn ? prisIn.closest('td') : null;
    if (cell) cell.classList.toggle('under-inpris', inpris != null && pris < inpris);
    var kolliEff = kolli != null ? kolli : (enh || 0) / epk;
    var vikt = d.enhet === 'kg' ? (d.viktvara === '1' && d.plockvikt !== '' ? parseFloat(d.plockvikt) : (enh || 0)) : kolliEff * (parseFloat(d.kg) || 0);
    return {belopp: belopp || 0, kostnad: inpris == null ? 0 : Math.round(antalPris * inpris * 100) / 100, moms: Math.round((belopp || 0) * (parseFloat(d.moms) || 0)) / 100, kolli: kolliEff || 0, vikt: vikt || 0};
  }
  function summera() {
    var s = {belopp: 0, kostnad: 0, moms: 0, kolli: 0, vikt: 0};
    tbody.querySelectorAll('tr.artrad').forEach(function (tr) {
      var r = radBerakna(tr);
      for (var k in s) s[k] += r[k];
    });
    var satt = function (id, t) { var el = document.getElementById(id); if (el) el.textContent = t; };
    satt('sNetto', kr(s.belopp)); satt('sKostnad', kr(s.kostnad)); satt('sVinst', kr(s.belopp - s.kostnad));
    satt('sTg', s.belopp > 0 ? String(Math.round((s.belopp - s.kostnad) / s.belopp * 1000) / 10).replace('.', ',') + ' %' : '–');
    satt('sMoms', kr(s.moms)); satt('sTotalt', kr(s.belopp + s.moms)); satt('sKolli', antal(s.kolli)); satt('sVikt', antal(Math.round(s.vikt * 10) / 10) + ' kg');
  }
  function markera() { andrad = true; if (info) info.textContent = 'Ändringar som inte är sparade.'; }

  tbody.addEventListener('input', function (e) {
    var tr = e.target.closest('tr');
    if (e.target.classList.contains('antal-kolli')) {
      var k = tal(e.target.value), epk = parseFloat(tr.dataset.epk) || 1;
      if (k != null && !isNaN(k)) tr.querySelector('.antal-enheter').value = antal(k * epk);
    }
    markera();
    summera();
  });
  tbody.addEventListener('click', function (e) {
    if (!e.target.classList.contains('ta-bort')) return;
    e.target.closest('tr').remove();
    markera();
    summera();
  });

  function namnge(tr) {
    var k = 'n' + (raknare++);
    tr.querySelectorAll('[data-f]').forEach(function (el) { el.name = 'rad[' + k + '][' + el.dataset.f + ']'; });
  }
  function laggTill(a) {
    var tom = tbody.querySelector('.tom-rad'); if (tom) tom.remove();
    var tr = document.getElementById('radMall').content.firstElementChild.cloneNode(true);
    tr.dataset.epk = a.epk; tr.dataset.perKolli = a.per_kolli; tr.dataset.viktvara = a.viktvara; tr.dataset.plockvikt = '';
    tr.dataset.inpris = a.inpris == null ? '' : a.inpris; tr.dataset.moms = a.moms; tr.dataset.enhet = a.enhet; tr.dataset.pris = a.pris; tr.dataset.kg = a.kg || '';
    tr.querySelector('.nr').textContent = a.artikelnr;
    tr.querySelector('.art-namn').textContent = a.namn;
    tr.querySelector('.vikt-markering').hidden = a.viktvara !== 1;
    tr.querySelector('.kalla').textContent = a.kalla + (a.per_kolli === 1 ? ', pris per kolli' : '');
    tr.querySelector('[data-f=artikel_id]').value = a.artikel_id;
    tr.querySelector('.enhet-ruta small').textContent = a.enhet;
    var p = tr.querySelector('.rad-pris'); if (p) { p.value = kr(a.pris); p.placeholder = kr(a.pris); }
    var pt = tr.querySelector('.rad-pris-text'); if (pt) pt.textContent = kr(a.pris);
    var ip = tr.querySelector('.rad-inpris'); if (ip) ip.textContent = kr(a.inpris);
    namnge(tr);
    tbody.appendChild(tr);
    markera();
    summera();
    var k = tr.querySelector('.antal-kolli'); k.focus();
  }

  function stang() { lista.hidden = true; sok.setAttribute('aria-expanded', 'false'); aktiv = -1; }
  function visa() {
    lista.innerHTML = '';
    traffar.forEach(function (a, i) {
      var li = document.createElement('li');
      li.setAttribute('role', 'option');
      li.id = 'artVal' + i;
      if (i === aktiv) { li.className = 'aktiv'; li.setAttribute('aria-selected', 'true'); }
      var b = document.createElement('b'); b.textContent = a.artikelnr;
      var n = document.createElement('span'); n.textContent = a.namn + (a.kolli ? ' · kolli ' + a.kolli + ' ' + a.enhet : '');
      var pr = document.createElement('em'); pr.textContent = kr(a.pris) + ' kr/' + (a.per_kolli === 1 ? 'kolli' : a.enhet) + ' · ' + a.kalla;
      li.append(b, n, pr);
      li.addEventListener('mousedown', function (e) { e.preventDefault(); valj(i); });
      lista.appendChild(li);
    });
    if (!traffar.length) { var li = document.createElement('li'); li.className = 'inga'; li.textContent = 'Ingen aktiv artikel matchar.'; lista.appendChild(li); }
    lista.hidden = false;
    sok.setAttribute('aria-expanded', 'true');
    if (aktiv >= 0) sok.setAttribute('aria-activedescendant', 'artVal' + aktiv); else sok.removeAttribute('aria-activedescendant');
  }
  function valj(i) { if (!traffar[i]) return; laggTill(traffar[i]); sok.value = ''; traffar = []; stang(); }
  function hamta(q, sedan) {
    fetch('admin-artikelsok.php?q=' + encodeURIComponent(q) + '&kund=' + sok.dataset.kund, {credentials: 'same-origin'})
      .then(function (r) { return r.json(); })
      .then(function (data) { if (sok.value.trim() !== q) return; traffar = data; aktiv = data.length ? 0 : -1; visa(); if (sedan) sedan(); })
      .catch(function () {});
  }
  sok.addEventListener('input', function () {
    clearTimeout(timer);
    var q = sok.value.trim();
    if (q === '') { traffar = []; stang(); return; }
    timer = setTimeout(function () { hamta(q); }, 180);
  });
  sok.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown' && traffar.length) { e.preventDefault(); aktiv = Math.min(aktiv + 1, traffar.length - 1); visa(); }
    else if (e.key === 'ArrowUp' && traffar.length) { e.preventDefault(); aktiv = Math.max(aktiv - 1, 0); visa(); }
    else if (e.key === 'Escape') { stang(); }
    else if (e.key === 'Enter') {
      e.preventDefault();
      var q = sok.value.trim();
      if (traffar.length && aktiv >= 0) valj(aktiv);
      else if (q !== '') { clearTimeout(timer); hamta(q, function () { if (traffar.length) valj(0); }); }
    }
  });
  sok.addEventListener('blur', function () { setTimeout(stang, 150); });
  // Enter i antal-rutan hoppar tillbaka till sökningen, så att nästa artikel kan läggas till direkt.
  tbody.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.tagName === 'INPUT') { e.preventDefault(); sok.focus(); }
  });
  document.getElementById('nyTextrad').addEventListener('click', function () {
    var tom = tbody.querySelector('.tom-rad'); if (tom) tom.remove();
    var tr = document.getElementById('textMall').content.firstElementChild.cloneNode(true);
    namnge(tr);
    tbody.appendChild(tr);
    tr.querySelector('.textrad-in').focus();
    markera();
  });
  form.addEventListener('input', function (e) { if (!tbody.contains(e.target) && e.target !== sok) markera(); });
  form.addEventListener('submit', function () { sparar = true; });
  window.addEventListener('beforeunload', function (e) { if (andrad && !sparar) { e.preventDefault(); e.returnValue = ''; } });
  summera();
})();
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/foot.php'; ?>
