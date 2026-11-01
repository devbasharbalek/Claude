<?php
require __DIR__ . '/includes/app.php';
superklipp_schema();

$user = current_user();
$admin = $user !== null && $user['role'] === 'admin';
$harTillgang = ser_erbjudande($user, 'superklipp');

$visade = sk_for_kunder();
$sk = null;
$valtId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($valtId) {
  $kandidat = sk_hamta($valtId);
  if ($kandidat && ((int)$kandidat['publicerad'] === 1 || $admin)) $sk = $kandidat;
}
$sk ??= $visade[0] ?? null;

$rader = $sk ? sk_rader((int)$sk['id']) : [];
$oppen = $sk !== null && sk_ar_oppen($sk);
$kanBestalla = $harTillgang && $oppen;
$dagar = $sk ? sk_leveransdagar($sk) : [];

$fel = [];
$sparfel = false;
$antal = [];
$v = ['leveransdag' => '', 'meddelande' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (!$user) redirect('logga-in.php?next=superklipp.php');
  if (!$harTillgang) redirect('superklipp.php');

  if (!$kanBestalla) {
    $fel['stangd'] = 'Beställningen för det här superklippet har stängt. Ring 0240-183 55 så hjälper vi dig.';
  } else {
    $perId = array_column($rader, null, 'id');
    [$antal, $fel] = lista_antal_fran_post($perId);
    $v['leveransdag'] = (string)($_POST['leveransdag'] ?? '');
    $v['meddelande'] = mb_substr(trim(str_replace("\0", '', (string)($_POST['meddelande'] ?? ''))), 0, 2000, 'UTF-8');
    if (!isset($dagar[$v['leveransdag']])) $fel['leveransdag'] = 'Välj leveransdag.';

    if (!$fel) {
      $dagnr = array_search($v['leveransdag'], array_keys($dagar), true) + 1;
      $leveransdatum = sk_veckodag((int)$sk['ar'], (int)$sk['vecka'], $dagnr)->format('Y-m-d');
      $etikett = sk_etikett($sk);
      $orderId = lista_spara_order($user, ['etikett' => $etikett, 'superklipp_id' => (int)$sk['id']], $perId, $antal,
                                   $v['leveransdag'], $dagar[$v['leveransdag']], $leveransdatum, $v['meddelande']);
      if ($orderId) {
        flash("Tack! Beställning #$orderId på $etikett är skickad. Vi återkommer med bekräftelse.");
        redirect('order.php?id=' . $orderId);
      }
      $sparfel = true;
    }
  }
}

$active = 'superklipp';
$pageTitle = ($harTillgang && $sk ? sk_etikett($sk) : 'Superklipp') . ' – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
if (!$harTillgang) {
  echo erbjudande_sparr('superklipp', $user, 'superklipp.php', 'Veckans bästa priser på frukt och grönt för våra kunder.');
  require __DIR__ . '/includes/foot.php';
  exit;
}
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Veckans erbjudanden</span>
    <h1><?php echo $sk ? 'Superklipp v.' . (int)$sk['vecka'] : 'Superklipp'; ?></h1>
    <?php if (!$sk): ?>
      <p>Varje vecka samlar vi våra bästa priser på frukt och grönt i ett superklipp. Nästa lista kommer snart.</p>
    <?php else: ?>
      <p>Veckans bästa priser på frukt och grönt, för leverans vecka <?php echo (int)$sk['vecka']; ?>.<?php echo $user ? '' : ' Logga in med ditt kundkonto för att se priserna och beställa direkt här.'; ?></p>
      <?php if ($oppen): ?>
        <div class="sk-deadline"><small>Beställ senast</small><?php echo e(sk_deadline_text($sk['deadline'])); ?></div>
      <?php else: ?>
        <div class="sk-deadline"><small><?php echo (int)$sk['publicerad'] === 1 ? 'Beställningen har stängt' : 'Utkast'; ?></small><?php echo (int)$sk['publicerad'] === 1 ? 'Ring 0240-183 55' : 'Syns inte för kunderna'; ?></div>
      <?php endif; ?>
      <?php if (count($visade) > 1): ?>
        <nav class="sk-veckor" aria-label="Välj vecka">
          <?php foreach ($visade as $l): ?>
            <a href="superklipp.php?id=<?php echo (int)$l['id']; ?>"<?php echo (int)$l['id'] === (int)$sk['id'] ? ' class="on" aria-current="page"' : ''; ?>>v.<?php echo (int)$l['vecka']; ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="wrap">
    <?php if (!$sk): ?>
      <div class="empty">
        <p>Det finns inget superklipp ute just nu.<?php echo $admin ? '' : ' Du kan alltid beställa som vanligt eller ringa oss.'; ?></p>
        <?php if ($admin): ?>
          <a class="btn primary" href="admin-superklipp.php">Lägg in veckans superklipp</a>
        <?php else: ?>
          <a class="btn primary" href="bestall.php">Till beställning</a>
        <?php endif; ?>
      </div>
    <?php else: ?>

      <?php if ($admin && (int)$sk['publicerad'] !== 1): ?>
        <div class="alert warn">Förhandsvisning av ett utkast. Kunderna ser det inte förrän du publicerar det i <a href="admin-superklipp-redigera.php?id=<?php echo (int)$sk['id']; ?>" style="color:var(--brand);">admin</a>.</div>
      <?php endif; ?>

      <?php if (!$user): ?>
        <div class="sk-notis">
          <p><strong>Priserna visas för inloggade kunder.</strong> Logga in för att se veckans priser och beställa direkt, eller skapa ett konto gratis.</p>
          <div class="actions">
            <a class="btn primary small" href="logga-in.php?next=superklipp.php">Logga in</a>
            <a class="btn ghost small" href="registrera.php?next=superklipp.php">Skapa konto</a>
          </div>
        </div>
      <?php elseif (!$oppen && (int)$sk['publicerad'] === 1): ?>
        <div class="sk-notis">
          <p><strong>Beställningen för v.<?php echo (int)$sk['vecka']; ?> stängde <?php echo e(sk_deadline_text($sk['deadline'])); ?>.</strong> Ring oss så kollar vi vad vi kan ordna.</p>
          <div class="actions"><a class="btn primary small" href="tel:+4624018355">0240-183 55</a></div>
        </div>
      <?php endif; ?>

      <?php if ($sparfel): ?>
        <div class="alert error" role="alert">Beställningen kunde inte sparas just nu. Ring oss på <a href="tel:+4624018355"><strong>0240-183 55</strong></a> så tar vi den direkt.</div>
      <?php elseif ($fel): ?>
        <div class="alert error" role="alert"><?php echo implode('<br>', array_map('e', $fel)); ?></div>
      <?php endif; ?>

      <?php if ($kanBestalla): ?>
      <form method="post" action="superklipp.php?id=<?php echo (int)$sk['id']; ?>" id="skForm" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$sk['id']; ?>">
      <?php endif; ?>

        <div class="sk-tools">
          <label class="sr-only" for="skSok">Sök vara</label>
          <input type="search" id="skSok" placeholder="Sök vara, t.ex. tomat" autocomplete="off">
          <p class="form-note"><?php echo $user ? 'Pris per kg eller styck. Antal anges i kolli, alltså hela lådor, säckar eller tråg.' : count($rader) . ' varor denna vecka.'; ?></p>
        </div>

        <div class="sk-table-wrap">
          <table class="sk-table sk-lista">
            <thead>
              <tr>
                <th>Produkt</th><th>Ursprung</th><th>Förpackning</th>
                <?php if ($user): ?><th class="num">Pris</th><?php endif; ?>
                <?php if ($kanBestalla): ?><th class="num">Kolli</th><?php endif; ?>
              </tr>
            </thead>
            <tbody id="skLista">
            <?php
            $kolumner = 3 + ($user ? 1 : 0) + ($kanBestalla ? 1 : 0);
            $kategori = null;
            foreach ($rader as $r):
              if ($r['kategori'] !== $kategori):
                $kategori = $r['kategori'];
                if ($kategori !== ''): ?>
              <tr class="sk-kat"><td colspan="<?php echo $kolumner; ?>"><?php echo e($kategori); ?></td></tr>
            <?php endif; endif;
              $id = (int)$r['id'];
              $varde = $antal[$id] ?? '';
              $meta = implode(' · ', array_filter([$r['ursprung'], sk_forpackning($r)])); ?>
              <tr class="sk-rad<?php echo $varde !== '' ? ' vald' : ''; ?>" data-sok="<?php echo e(mb_strtolower($r['produkt'] . ' ' . $r['ursprung'] . ' ' . $r['kategori'], 'UTF-8')); ?>">
                <td class="produkt"><?php echo e($r['produkt']); ?><?php if ((int)$r['topp'] === 1): ?><span class="pill pill-topp">Toppklipp</span><?php endif; ?><?php if ($meta !== ''): ?><span class="sk-meta"><?php echo e($meta); ?></span><?php endif; ?></td>
                <td class="ursprung muted"><?php echo e($r['ursprung']); ?></td>
                <td class="forp muted"><?php echo e(sk_forpackning($r)); ?></td>
                <?php if ($user): ?><td class="pris"><?php echo e(kr((float)$r['pris'])); ?><?php if ($r['enhet'] !== ''): ?><small>/<?php echo e($r['enhet']); ?></small><?php endif; ?></td><?php endif; ?>
                <?php if ($kanBestalla): ?><td class="antal"><input type="number" name="antal[<?php echo $id; ?>]" value="<?php echo e((string)$varde); ?>" min="0" max="<?php echo SK_MAX_ANTAL; ?>" step="1" inputmode="numeric" placeholder="0" aria-label="Antal kolli <?php echo e($r['produkt']); ?>"></td><?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <p class="empty" id="skIngenTraff" hidden style="border:none; border-radius:0;">Ingen vara matchar sökningen.</p>
        </div>

      <?php if ($kanBestalla): ?>
        <div class="form-card" id="leverans" style="margin-top:18px;">
          <fieldset>
            <legend>Leveransdag</legend>
            <div class="day-select" role="radiogroup" aria-label="Leveransdag">
              <?php foreach ($dagar as $key => $dag): ?>
                <label><input type="radio" name="leveransdag" value="<?php echo $key; ?>"<?php echo $v['leveransdag'] === $key ? ' checked' : ''; ?> required><?php echo e($dag); ?></label>
              <?php endforeach; ?>
            </div>
            <?php if (isset($fel['leveransdag'])): ?><p style="margin-top:8px; font-size:.8rem; color:var(--danger);"><?php echo e($fel['leveransdag']); ?></p><?php endif; ?>
          </fieldset>
          <fieldset>
            <legend>Meddelande</legend>
            <div class="field full">
              <label for="meddelande" class="sr-only">Meddelande</label>
              <textarea id="meddelande" name="meddelande" maxlength="2000" placeholder="Valfritt. T.ex. önskad leveranstid eller andra varor ni vill ha med." style="min-height:90px;"><?php echo e($v['meddelande']); ?></textarea>
            </div>
          </fieldset>
          <p class="form-note" style="margin-top:16px;">Levereras till <?php echo e($user['foretag']); ?>, <?php echo e($user['adress']); ?>. <a href="profil.php" style="color:var(--brand);">Ändra uppgifter</a></p>
        </div>

        <div class="sk-bar">
          <div><b id="skAntal"><?php echo count(array_filter($antal, fn($a) => $a !== '')); ?></b> <span>varor valda</span></div>
          <button class="btn primary" type="submit" id="skSkicka">Skicka beställning</button>
        </div>
      </form>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</section>

<script>
(function(){
  var lista = document.getElementById('skLista');
  if (!lista) return;
  var sok = document.getElementById('skSok');
  var ingen = document.getElementById('skIngenTraff');
  sok.addEventListener('input', function(){
    var q = sok.value.trim().toLowerCase();
    var traffar = 0, rubrik = null, synliga = 0;
    var stang = function(){ if (rubrik) rubrik.hidden = synliga === 0; };
    Array.prototype.forEach.call(lista.rows, function(tr){
      if (tr.classList.contains('sk-kat')) { stang(); rubrik = tr; synliga = 0; return; }
      var visa = q === '' || tr.getAttribute('data-sok').indexOf(q) !== -1;
      tr.hidden = !visa;
      if (visa) { synliga++; traffar++; }
    });
    stang();
    ingen.hidden = traffar !== 0;
  });

  var form = document.getElementById('skForm');
  if (!form) return;
  var raknare = document.getElementById('skAntal');
  var uppdatera = function(){
    var n = 0;
    form.querySelectorAll('td.antal input').forEach(function(inp){
      var vald = parseInt(inp.value, 10) > 0;
      inp.closest('tr').classList.toggle('vald', vald);
      if (vald) n++;
    });
    raknare.textContent = n;
  };
  form.addEventListener('input', function(ev){ if (ev.target.matches('td.antal input')) uppdatera(); });
  form.addEventListener('submit', function(ev){
    if (raknare.textContent === '0') {
      ev.preventDefault();
      alert('Fyll i antal kolli på minst en vara.');
      return;
    }
    if (!form.querySelector('input[name="leveransdag"]:checked')) {
      ev.preventDefault();
      document.getElementById('leverans').scrollIntoView({behavior: 'smooth', block: 'center'});
      alert('Välj leveransdag.');
      return;
    }
    document.getElementById('skSkicka').disabled = true;
  });
})();
</script>
<?php require __DIR__ . '/includes/foot.php'; ?>
