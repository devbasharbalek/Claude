<?php
require __DIR__ . '/includes/app.php';
veckansklipp_schema();

$user = current_user();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$vk = $id ? vk_hamta($id) : null;
$vk ??= vk_senaste();

$rader = $vk ? array_map('vk_som_listrad', vk_rader((int)$vk['id'])) : [];
$dagar = $vk ? vk_leveransdagar($vk) : [];
$oppen = $rader && $dagar;
$harTillgang = ser_erbjudande($user, 'veckansklipp');
$kanBestalla = $harTillgang && $oppen;
$tillbaka = 'veckansklipp.php' . ($id ? '?id=' . $id : '');

$fel = [];
$sparfel = false;
$antal = [];
$v = ['leveransdag' => '', 'meddelande' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  if (!$user) redirect('logga-in.php?next=' . rawurlencode($tillbaka));
  if (!$harTillgang) redirect($tillbaka);

  if (!$kanBestalla) {
    $fel['stangd'] = 'Det går inte längre att beställa från det här bladet på hemsidan. Ring 0240-183 55 så hjälper vi dig.';
  } else {
    $perId = array_column($rader, null, 'id');
    [$antal, $fel] = lista_antal_fran_post($perId);
    $v['leveransdag'] = (string)($_POST['leveransdag'] ?? '');
    $v['meddelande'] = mb_substr(trim(str_replace("\0", '', (string)($_POST['meddelande'] ?? ''))), 0, 2000, 'UTF-8');
    if (!isset($dagar[$v['leveransdag']])) $fel['leveransdag'] = 'Välj leveransdag.';

    if (!$fel) {
      $dagnr = array_search($v['leveransdag'], array_keys($leveransdagar), true) + 1;
      $leveransdatum = sk_veckodag((int)$vk['ar'], (int)$vk['vecka'], $dagnr)->format('Y-m-d');
      $etikett = vk_etikett($vk);
      $orderId = lista_spara_order($user, ['etikett' => $etikett, 'veckansklipp_id' => (int)$vk['id']], $perId, $antal,
                                   $v['leveransdag'], $dagar[$v['leveransdag']], $leveransdatum, $v['meddelande']);
      if ($orderId) {
        flash("Tack! Beställning #$orderId från $etikett är skickad. Vi återkommer med bekräftelse.");
        redirect('order.php?id=' . $orderId);
      }
      $sparfel = true;
    }
  }
}

$active = 'veckansklipp';
$pageTitle = ($harTillgang && $vk ? vk_etikett($vk) : 'Veckansklipp') . ' – Ludvika Partiaffär';
$metaBeskrivning = 'Veckans erbjudanden för restauranger och pizzerior från Ludvika Partiaffär. Logga in med ditt kundkonto för att se bladet.';
require __DIR__ . '/includes/head.php';
if (!$harTillgang) {
  echo erbjudande_sparr('veckansklipp', $user, 'Veckans erbjudanden för restauranger och pizzerior.');
  require __DIR__ . '/includes/foot.php';
  exit;
}
$sidor = $vk ? (int)$vk['sidor'] : 0;
?>
<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Veckans erbjudanden</span>
    <h1><?php echo $vk ? 'Veckansklipp v.' . (int)$vk['vecka'] : 'Veckansklipp'; ?></h1>
    <p>Veckans bästa priser för restauranger och pizzerior. Priserna är exkl. moms och pant.</p>
    <?php if ($vk): ?>
      <div class="sk-deadline"><small>Gäller</small><?php echo e(vk_period($vk)); ?></div>
      <div class="hero-actions">
        <?php if ($rader): ?>
          <a class="btn accent" href="#bestall">Beställ från bladet</a>
        <?php else: ?>
          <a class="btn accent" href="bestall.php">Beställ</a>
        <?php endif; ?>
        <a class="btn outline" href="tel:+4624018355">Ring 0240-183 55</a>
        <?php if ((int)$vk['pdf_storlek'] > 0): ?>
          <a class="btn outline" href="<?php echo e(vk_url($vk, 'pdf', true)); ?>">Ladda ner PDF</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section>
  <div class="wrap">
    <?php if (!$vk): ?>
      <div class="empty">
        <p>Veckansklippet är inte ute just nu. Ett nytt blad kommer varje söndag.</p>
        <a class="btn primary" href="bestall.php">Till beställning</a>
      </div>
    <?php else: ?>
      <?php if (vk_har_passerat($vk)): ?>
        <div class="alert warn">Det här bladet gällde vecka <?php echo (int)$vk['vecka']; ?>. Nästa veckansklipp kommer på söndag.</div>
      <?php endif; ?>

      <?php if ($sidor > 0): ?>
        <div class="vk-sidor">
          <?php for ($i = 1; $i <= $sidor; $i++): ?>
            <img src="<?php echo e(vk_url($vk, $i)); ?>" width="<?php echo (int)$vk['bild_b']; ?>" height="<?php echo (int)$vk['bild_h']; ?>"
                 alt="<?php echo e(vk_etikett($vk) . ', sida ' . $i . ' av ' . $sidor); ?>" loading="<?php echo $i === 1 ? 'eager' : 'lazy'; ?>" decoding="async">
          <?php endfor; ?>
        </div>
      <?php else: ?>
        <object class="vk-pdf" data="<?php echo e(vk_url($vk, 'pdf')); ?>" type="application/pdf">
          <div class="empty"><p>Bladet kan inte visas här.</p><a class="btn primary" href="<?php echo e(vk_url($vk, 'pdf')); ?>">Öppna veckansklippet som PDF</a></div>
        </object>
      <?php endif; ?>

      <?php if ($rader): ?>
        <div class="vk-bestall" id="bestall">
          <h2 class="vk-bestall-rubrik">Beställ från bladet</h2>

          <?php if (!$oppen): ?>
            <div class="sk-notis">
              <p><strong>Beställningen via hemsidan är stängd för v.<?php echo (int)$vk['vecka']; ?>.</strong> Ring oss så kollar vi vad vi kan ordna.</p>
              <div class="actions"><a class="btn primary small" href="tel:+4624018355">0240-183 55</a></div>
            </div>
          <?php endif; ?>

          <?php if ($sparfel): ?>
            <div class="alert error" role="alert">Beställningen kunde inte sparas just nu. Ring oss på <a href="tel:+4624018355"><strong>0240-183 55</strong></a> så tar vi den direkt.</div>
          <?php elseif ($fel): ?>
            <div class="alert error" role="alert"><?php echo implode('<br>', array_map('e', $fel)); ?></div>
          <?php endif; ?>

          <?php if ($kanBestalla): ?>
          <form method="post" action="<?php echo e($tillbaka); ?>#bestall" id="skForm" novalidate>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo (int)$vk['id']; ?>">
          <?php endif; ?>

            <p class="form-note" style="margin-bottom:12px;">Antal anges i hela förpackningar, alltså lådor, säckar eller hinkar. Varor utan förpackning beställs per styck.</p>
            <div class="sk-table-wrap">
              <table class="sk-table sk-lista">
                <thead>
                  <tr>
                    <th>Vara</th><th>Förpackning</th><th class="num">Pris</th>
                    <?php if ($kanBestalla): ?><th class="num">Antal</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody id="skLista">
                <?php foreach ($rader as $r):
                  $rid = (int)$r['id'];
                  $varde = $antal[$rid] ?? '';
                  $meta = implode(' · ', array_filter([$r['ursprung'], sk_forpackning($r)])); ?>
                  <tr class="sk-rad<?php echo $varde !== '' ? ' vald' : ''; ?>">
                    <td class="produkt"><?php echo e($r['produkt']); ?><?php if ($r['ursprung'] !== ''): ?> <span class="vk-info"><?php echo e($r['ursprung']); ?></span><?php endif; ?><?php if ($meta !== ''): ?><span class="sk-meta"><?php echo e($meta); ?></span><?php endif; ?></td>
                    <td class="forp muted"><?php echo e(sk_forpackning($r)); ?></td>
                    <td class="pris"><?php echo e(kr((float)$r['pris'])); ?><?php if ($r['enhet'] !== ''): ?><small>/<?php echo e($r['enhet']); ?></small><?php endif; ?></td>
                    <?php if ($kanBestalla): ?><td class="antal"><input type="number" name="antal[<?php echo $rid; ?>]" value="<?php echo e((string)$varde); ?>" min="0" max="<?php echo SK_MAX_ANTAL; ?>" step="1" inputmode="numeric" placeholder="0" aria-label="Antal <?php echo e($r['produkt'] . ($r['ursprung'] !== '' ? ' ' . $r['ursprung'] : '')); ?>"></td><?php endif; ?>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
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
                <p class="form-note" style="margin-top:8px;">Behöver ni leverans i dag? Ring oss på 0240-183 55.</p>
                <?php if (isset($fel['leveransdag'])): ?><p style="margin-top:8px; font-size:.8rem; color:var(--danger);"><?php echo e($fel['leveransdag']); ?></p><?php endif; ?>
              </fieldset>
              <fieldset>
                <legend>Meddelande</legend>
                <div class="field full">
                  <label for="meddelande" class="sr-only">Meddelande</label>
                  <textarea id="meddelande" name="meddelande" maxlength="2000" placeholder="Valfritt. T.ex. vilka sorter läsk ni vill ha, önskad leveranstid eller andra varor." style="min-height:90px;"><?php echo e($v['meddelande']); ?></textarea>
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
        </div>
      <?php else: ?>
        <div class="cta-band" style="margin-top:32px;">
          <div>
            <h2>Vill du beställa?</h2>
            <p>Skicka en beställning via formuläret eller ring oss under öppettiderna, mån&ndash;fre 06:00&ndash;14:30.</p>
          </div>
          <div class="actions">
            <a class="btn accent" href="bestall.php">Till beställningen</a>
            <a class="btn outline" href="tel:+4624018355">0240-183 55</a>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<script>
(function(){
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
      alert('Fyll i antal på minst en vara.');
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
