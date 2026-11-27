<?php
require __DIR__ . '/includes/app.php';
// Bara administratörer: anslutningen ger tillgång till bokföringen.
$user = require_behorighet('personal');
erp_schema();
$av = vem($user);

// Visma skickar tillbaka hit efter inloggningen, med en kod eller ett fel.
if (isset($_GET['code']) || isset($_GET['error'])) {
  $vantad = (string)($_SESSION['spiris_state'] ?? '');
  unset($_SESSION['spiris_state']);
  if ($vantad === '' || !hash_equals($vantad, (string)($_GET['state'] ?? ''))) {
    flash('Svaret från Visma kunde inte kontrolleras. Tryck Anslut till Spiris och försök igen.');
    redirect('admin-spiris');
  }
  if (isset($_GET['error'])) {
    $orsak = enRad($_GET['error_description'] ?? $_GET['error'], 200);
    flash('Anslutningen avbröts hos Visma' . ($orsak !== '' ? ' (' . $orsak . ')' : '') . '.');
    redirect('admin-spiris');
  }
  try {
    spiris_spara_token(spiris_token_anrop(['grant_type' => 'authorization_code', 'code' => (string)$_GET['code'], 'redirect_uri' => spiris_tillbaka_url()]));
    satt_installning('spiris_ansluten_av', $av);
    satt_installning('spiris_ansluten_at', now());
    $foretag = '';
    try {
      $c = spiris_api('GET', 'companysettings');
      $foretag = enRad($c['Name'] ?? '', 160);
      satt_installning('spiris_foretag', $foretag !== '' ? $foretag : null);
    } catch (SpirisFel $e) {
      // Namnet är bara för visning.
    }
    flash('Klart! Systemet är anslutet till Spiris' . ($foretag !== '' ? ' för ' . $foretag : '') . '. Nu kan fakturautkast skickas dit.');
  } catch (SpirisFel $e) {
    flash('Anslutningen misslyckades. ' . $e->getMessage());
  }
  redirect('admin-spiris');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'spara_nycklar') {
    $id = enRad($_POST['client_id'] ?? '', 200);
    $hemlig = trim((string)($_POST['client_secret'] ?? ''));
    $miljo = ($_POST['miljo'] ?? '') === 'test' ? 'test' : 'produktion';
    $andrat = $id !== (string)installning('spiris_client_id', '') || $hemlig !== '' || $miljo !== spiris_miljo();
    satt_installning('spiris_client_id', $id !== '' ? $id : null);
    if ($hemlig !== '') hemlig_spara('spiris_client_secret', mb_substr($hemlig, 0, 500, 'UTF-8'));
    if ($id === '') hemlig_spara('spiris_client_secret', null);
    satt_installning('spiris_miljo', $miljo === 'test' ? 'test' : null);
    if ($andrat && spiris_ansluten()) {
      spiris_koppla_fran();
      flash('Nycklarna är sparade. Anslutningen gjordes med de gamla nycklarna, så tryck Anslut till Spiris igen.');
    } else {
      flash('Nycklarna är sparade.');
    }
    redirect('admin-spiris');
  }

  if ($action === 'anslut') {
    if ((string)installning('spiris_client_id', '') === '' || hemlig_las('spiris_client_secret') === null) {
      flash('Fyll i Client ID och Client secret från Spiris först.');
      redirect('admin-spiris');
    }
    $_SESSION['spiris_state'] = bin2hex(random_bytes(16));
    header('Location: ' . spiris_inloggning_url($_SESSION['spiris_state']));
    exit;
  }

  if ($action === 'testa') {
    try {
      $c = spiris_api('GET', 'companysettings');
      $foretag = enRad($c['Name'] ?? '', 160);
      if ($foretag !== '') satt_installning('spiris_foretag', $foretag);
      flash('Anslutningen fungerar' . ($foretag !== '' ? ': Spiris svarar för ' . $foretag : '') . '.');
    } catch (SpirisFel $e) {
      flash('Anslutningen fungerar inte. ' . $e->getMessage());
    }
    redirect('admin-spiris');
  }

  if ($action === 'koppla_fran') {
    spiris_koppla_fran();
    flash('Systemet är frånkopplat från Spiris. Nycklarna från Spiris finns kvar, så det går att ansluta igen.');
    redirect('admin-spiris');
  }

  if ($action === 'spara_avgift') {
    $nr = enRad($_POST['avgift_artikel'] ?? '', 40);
    satt_installning('spiris_avgift_artikel', $nr !== '' ? $nr : null);
    $text = $nr === '' ? 'Sparat. Ingen fakturaavgift läggs till på utkasten.' : 'Sparat. Kunder med fakturaavgift får en rad med artikel ' . $nr . '.';
    if ($nr !== '' && spiris_ansluten()) {
      try {
        if (!spiris_hitta('articles', 'Number', $nr)) $text .= ' Obs! Artikel ' . $nr . ' finns inte i Spiris än. Lägg upp den där innan ni skickar utkast.';
      } catch (SpirisFel $e) {
        $text .= ' Artikeln kunde inte kontrolleras i Spiris just nu.';
      }
    }
    flash($text);
    redirect('admin-spiris');
  }
  redirect('admin-spiris');
}

$ansluten = spiris_ansluten();
$clientId = (string)installning('spiris_client_id', '');
$harHemlig = installning('spiris_client_secret') !== null;
$foretag = (string)installning('spiris_foretag', '');
$avgiftArtikel = faktura_avgift_artikel();
$medAvgift = (int)db()->query('SELECT COUNT(*) FROM kunder WHERE aktiv = 1 AND fakturaavgift > 0')->fetchColumn();
$utanSpiris = (int)db()->query("SELECT COUNT(*) FROM kunder WHERE aktiv = 1 AND (visma_id IS NULL OR visma_id = '')")->fetchColumn();
$artUtanSpiris = (int)db()->query("SELECT COUNT(*) FROM artiklar WHERE aktiv = 1 AND (visma_id IS NULL OR visma_id = '')")->fetchColumn();

$active = 'admin';
$adminSida = 'spiris';
$pageTitle = 'Spiris – Admin – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap">
    <div class="portal-head">
      <div>
        <span class="eyebrow">Inställningar</span>
        <h1>Spiris</h1>
        <p>Fakturautkasten skickas till Spiris (Visma eEkonomi). Där gör ni dem till fakturor och skickar dem till kunderna, precis som med utkasten från Partihandel.</p>
      </div>
    </div>

    <div class="order-topp">
      <div class="detail-card">
        <h2>Anslutning</h2>
        <?php if ($ansluten): ?>
          <p><span class="pill pill-levererad">Ansluten</span><?php echo spiris_miljo() === 'test' ? ' <span class="pill pill-ny">Testmiljö</span>' : ''; ?></p>
          <dl class="kv">
            <?php if ($foretag !== ''): ?><dt>Företag i Spiris</dt><dd><?php echo e($foretag); ?></dd><?php endif; ?>
            <dt>Ansluten av</dt><dd><?php echo e((string)installning('spiris_ansluten_av', '')); ?>, <?php echo e(datum((string)installning('spiris_ansluten_at', now()))); ?></dd>
          </dl>
          <div class="portal-actions" style="margin-top:14px; justify-content:flex-start;">
            <form method="post" action="admin-spiris" style="margin:0;"><?php echo csrf_field(); ?><button class="btn ghost small" type="submit" name="action" value="testa">Testa anslutningen</button></form>
            <form method="post" action="admin-spiris" style="margin:0;"><?php echo csrf_field(); ?><button class="btn ghost small" type="submit" name="action" value="anslut">Anslut igen</button></form>
            <form method="post" action="admin-spiris" style="margin:0;" onsubmit="return confirm('Koppla från Spiris? Inga utkast kan skickas förrän någon ansluter igen.');"><?php echo csrf_field(); ?><button class="btn ghost small" type="submit" name="action" value="koppla_fran">Koppla från</button></form>
          </div>
        <?php else: ?>
          <p><span class="pill pill-off">Inte ansluten</span></p>
          <p class="muted">Fyll i nycklarna från Spiris nedan och tryck sedan Anslut. Du loggar in hos Visma med ett Spiris-konto som får skapa fakturor, och kommer sedan tillbaka hit.</p>
          <form method="post" action="admin-spiris" style="margin:0;"><?php echo csrf_field(); ?><button class="btn primary small" type="submit" name="action" value="anslut"<?php echo $clientId !== '' && $harHemlig ? '' : ' disabled'; ?>>Anslut till Spiris</button></form>
        <?php endif; ?>
      </div>

      <div class="detail-card">
        <h2>Kunder och artiklar</h2>
        <p class="muted" style="margin-top:0;">Kunderna och artiklarna från Partihandel har redan sitt id i Spiris, så utkasten hamnar på rätt kund och artikel.</p>
        <dl class="kv">
          <dt>Kunder utan Spiris-id</dt><dd><?php echo $utanSpiris; ?><?php echo $utanSpiris ? ' <span class="muted">(letas upp med kundnumret)</span>' : ''; ?></dd>
          <dt>Artiklar utan Spiris-id</dt><dd><?php echo $artUtanSpiris; ?><?php echo $artUtanSpiris ? ' <span class="muted">(letas upp med artikelnumret)</span>' : ''; ?></dd>
        </dl>
        <p class="muted" style="font-size:.86rem; margin-bottom:0;">En ny kund eller artikel som läggs upp här måste också finnas i Spiris, med samma kundnummer eller artikelnummer.</p>
      </div>
    </div>

    <form class="detail-card" method="post" action="admin-spiris" style="margin-top:20px;" autocomplete="off">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="spara_nycklar">
      <h2>Nycklar från Spiris</h2>
      <ol class="spiris-steg">
        <li>Mejla Spiris API-support på <strong>api@spiris.se</strong> och be om Client ID och Client secret för en egen integration. Skicka med uppgifterna i punkt 2 och 3.</li>
        <li>Redirect URI, alltså adressen Spiris skickar tillbaka till efter inloggningen:
          <div class="kopiera-rad" style="margin-top:6px;"><code id="redirectUri"><?php echo e(spiris_tillbaka_url()); ?></code><button class="btn ghost small" type="button" data-kopiera="redirectUri">Kopiera</button></div>
        </li>
        <li>Behörigheterna: <strong>ea:api</strong>, <strong>ea:sales</strong> och <strong>offline_access</strong>.</li>
        <li>När ni har fått Client ID och Client secret från Spiris: fyll i dem här, tryck Spara och sedan Anslut till Spiris.</li>
      </ol>
      <div class="field-grid" style="margin-top:16px;">
        <div class="field"><label for="client_id">Client ID</label><input type="text" id="client_id" name="client_id" value="<?php echo e($clientId); ?>" spellcheck="false"></div>
        <div class="field"><label for="client_secret">Client secret</label><input type="password" id="client_secret" name="client_secret" value="" placeholder="<?php echo $harHemlig ? 'Sparad. Lämna tomt för att behålla.' : ''; ?>" autocomplete="new-password" spellcheck="false"></div>
        <div class="field"><label for="miljo">Var utkasten hamnar</label>
          <select id="miljo" name="miljo">
            <option value="produktion"<?php echo spiris_miljo() === 'produktion' ? ' selected' : ''; ?>>Spiris, er riktiga bokföring</option>
            <option value="test"<?php echo spiris_miljo() === 'test' ? ' selected' : ''; ?>>Vismas testmiljö, för att prova först</option>
          </select>
        </div>
      </div>
      <button class="btn primary small" type="submit" style="margin-top:14px;">Spara</button>
    </form>

    <form class="detail-card" method="post" action="admin-spiris" style="margin-top:20px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="spara_avgift">
      <h2>Fakturaavgift</h2>
      <p class="muted" style="margin-top:0;"><?php echo $medAvgift; ?> kunder har fakturaavgift i kundregistret. Skriv artikelnumret som fakturaavgiften har i Spiris, så läggs avgiften till som en rad på deras utkast. Lämna tomt om Spiris lägger på avgiften själv.</p>
      <div class="field-grid">
        <div class="field"><label for="avgift_artikel">Artikelnummer för fakturaavgift i Spiris</label><input type="text" id="avgift_artikel" name="avgift_artikel" value="<?php echo e($avgiftArtikel); ?>" placeholder="Ingen avgift läggs till"></div>
      </div>
      <button class="btn primary small" type="submit" style="margin-top:14px;">Spara</button>
    </form>
  </div>
</section>
<script>
document.querySelectorAll('[data-kopiera]').forEach(function (b) {
  b.addEventListener('click', function () {
    var text = document.getElementById(b.dataset.kopiera).textContent;
    (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { b.textContent = 'Kopierad'; }, function () { b.textContent = 'Markera och kopiera'; });
  });
});
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
