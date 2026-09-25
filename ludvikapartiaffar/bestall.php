<?php
require __DIR__ . '/includes/app.php';

$user = current_user();
$fel = [];
$sparfel = false;
$v = [
  'foretag' => $user['foretag'] ?? '', 'kontakt' => $user['kontakt'] ?? '',
  'telefon' => $user['telefon'] ?? '', 'epost' => $user['email'] ?? '',
  'adress' => $user['adress'] ?? '', 'leveransdag' => '', 'kategorier' => [], 'meddelande' => '',
];

$igen = (int)($_GET['igen'] ?? 0);
if ($igen && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  $user = require_login();
  $gammal = order_for_user($igen, (int)$user['id']);
  if ($gammal) {
    $v['leveransdag'] = $gammal['leveransdag'];
    $v['kategorier'] = array_values(array_intersect(array_keys($sortiment), explode(',', $gammal['kategorier'])));
    $v['meddelande'] = $gammal['meddelande'];
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();

  $v['foretag']     = enRad($_POST['foretag'] ?? '', 120);
  $v['kontakt']     = enRad($_POST['kontakt'] ?? '', 120);
  $v['telefon']     = enRad($_POST['telefon'] ?? '', 40);
  $v['epost']       = mb_strtolower(enRad($_POST['epost'] ?? '', 160));
  $v['adress']      = enRad($_POST['adress'] ?? '', 200);
  $v['leveransdag'] = (string)($_POST['leveransdag'] ?? '');
  $v['meddelande']  = mb_substr(trim(str_replace("\0", '', (string)($_POST['meddelande'] ?? ''))), 0, 4000, 'UTF-8');
  $valda = $_POST['kategorier'] ?? [];
  $v['kategorier']  = is_array($valda) ? array_values(array_intersect(array_keys($sortiment), $valda)) : [];

  if (looks_like_bot()) redirect('tack.php');

  if ($v['foretag'] === '')  $fel['foretag'] = 'Ange restaurangens eller företagets namn.';
  if ($v['kontakt'] === '')  $fel['kontakt'] = 'Ange vem vi ska kontakta.';
  if (!preg_match('/^[0-9 +\-()]{6,}$/', $v['telefon'])) $fel['telefon'] = 'Ange ett telefonnummer, t.ex. 070-123 45 67.';
  if (!filter_var($v['epost'], FILTER_VALIDATE_EMAIL)) $fel['epost'] = 'Ange en giltig e-postadress, t.ex. namn@restaurang.se.';
  if ($v['adress'] === '')   $fel['adress'] = 'Ange leveransadressen.';
  if (!isset($leveransdagar[$v['leveransdag']])) $fel['leveransdag'] = 'Välj önskad leveransdag.';
  if (!$v['kategorier'] && $v['meddelande'] === '') $fel['meddelande'] = 'Kryssa i minst en varugrupp eller skriv vad ni vill beställa.';

  if (!$fel) {
    try {
      db()->prepare('INSERT INTO orders (user_id, foretag, kontakt, telefon, epost, adress, leveransdag, kategorier, meddelande, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'ny\', ?, ?)')
          ->execute([
            $user['id'] ?? null, $v['foretag'], $v['kontakt'], $v['telefon'], $v['epost'], $v['adress'],
            $v['leveransdag'], implode(',', $v['kategorier']), $v['meddelande'], now(), now(),
          ]);
      $orderId = (int)db()->lastInsertId();
    } catch (PDOException $ex) {
      $orderId = 0;
    }

    if ($orderId) {
      $kategoriNamn = kategori_namn(implode(',', $v['kategorier']));
      $dag = $leveransdagar[$v['leveransdag']];
      $body = implode("\n", [
        "Ny beställning #$orderId från ludvikaparti.se" . ($user ? ' (inloggad kund)' : ' (utan konto)'),
        str_repeat('-', 44),
        'Företag/restaurang: ' . $v['foretag'],
        'Kontaktperson:      ' . $v['kontakt'],
        'Telefon:            ' . $v['telefon'],
        'E-post:             ' . $v['epost'],
        'Leveransadress:     ' . $v['adress'],
        'Önskad leveransdag: ' . $dag,
        'Varugrupper:        ' . $kategoriNamn,
        '',
        'Beställning / meddelande:',
        $v['meddelande'] !== '' ? $v['meddelande'] : '(inget meddelande)',
        '',
        str_repeat('-', 44),
        'Hantera beställningen: ' . $CONFIG['site_url'] . '/admin-order.php?id=' . $orderId,
        'Svara på det här mejlet för att svara kunden direkt.',
      ]);
      send_mail($CONFIG['mail_to'], "Beställning #$orderId: {$v['foretag']} ($dag)", $body, $v['epost']);

      if ($user) {
        send_mail($user['email'], "Vi har tagit emot din beställning #$orderId", implode("\n", [
          "Hej {$user['kontakt']}!",
          '',
          "Tack för din beställning #$orderId. Vi går igenom den och återkommer med bekräftelse.",
          '',
          'Önskad leveransdag: ' . $dag,
          'Varugrupper: ' . $kategoriNamn,
          '',
          'Du kan följa beställningen under Mitt konto:',
          $CONFIG['site_url'] . '/order.php?id=' . $orderId,
          '',
          'Med vänlig hälsning',
          'Ludvika Partiaffär, 0240-183 55',
        ]), $CONFIG['mail_to']);
        flash("Tack! Beställning #$orderId är skickad. Vi återkommer med bekräftelse.");
        redirect('order.php?id=' . $orderId);
      }
      redirect('tack.php');
    }
    $sparfel = true;
  }
}

$active = 'bestall';
$pageTitle = 'Beställ – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';

function faltfel(array $fel, string $k): string {
  return isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
}
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Beställ</span>
    <h1><?php echo $igen ? 'Beställ igen' : 'Skicka en beställning'; ?></h1>
    <p>Fyll i formuläret så återkommer vi med bekräftelse och leveransbesked. Brådskande? Ring <a href="tel:+4624018355" style="color:#fff; font-weight:600;">0240-183 55</a> så tar vi beställningen direkt.</p>
  </div>
</section>

<section>
  <div class="wrap form-layout">
    <form class="form-card" method="post" action="bestall.php" novalidate>
      <?php echo csrf_field(); ?>

      <?php if ($sparfel): ?>
        <div class="alert error" role="alert">Beställningen kunde inte sparas just nu. Ring oss på <a href="tel:+4624018355"><strong>0240-183 55</strong></a> eller mejla <a href="mailto:<?php echo e($CONFIG['mail_to']); ?>"><?php echo e($CONFIG['mail_to']); ?></a> så tar vi den direkt.</div>
      <?php elseif ($fel): ?>
        <div class="alert error" role="alert"><strong>Några fält behöver kompletteras:</strong> se markeringarna nedan.</div>
      <?php endif; ?>

      <fieldset>
        <legend>Era uppgifter</legend>
        <?php if ($user): ?>
          <p class="hint" style="margin:-6px 0 14px; font-size:.86rem; color:var(--ink-soft);">Ifyllt från ditt konto. Ändringar här gäller bara den här beställningen, <a href="profil.php" style="color:var(--brand);">ändra kontouppgifterna här</a>.</p>
        <?php endif; ?>
        <div class="field-grid">
          <div class="field">
            <label for="foretag">Restaurang / företag <span class="req">*</span></label>
            <input type="text" id="foretag" name="foretag" value="<?php echo e($v['foretag']); ?>" autocomplete="organization" required>
            <?php echo faltfel($fel, 'foretag'); ?>
          </div>
          <div class="field">
            <label for="kontakt">Kontaktperson <span class="req">*</span></label>
            <input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" autocomplete="name" required>
            <?php echo faltfel($fel, 'kontakt'); ?>
          </div>
          <div class="field">
            <label for="telefon">Telefon <span class="req">*</span></label>
            <input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>" autocomplete="tel" required>
            <?php echo faltfel($fel, 'telefon'); ?>
          </div>
          <div class="field">
            <label for="epost">E-post <span class="req">*</span></label>
            <input type="email" id="epost" name="epost" value="<?php echo e($v['epost']); ?>" autocomplete="email" required>
            <?php echo faltfel($fel, 'epost'); ?>
          </div>
          <div class="field full">
            <label for="adress">Leveransadress <span class="req">*</span></label>
            <input type="text" id="adress" name="adress" value="<?php echo e($v['adress']); ?>" autocomplete="street-address" placeholder="Gatuadress, postnummer och ort" required>
            <?php echo faltfel($fel, 'adress'); ?>
          </div>
        </div>
      </fieldset>

      <fieldset>
        <legend>Önskad leveransdag</legend>
        <div class="day-select" role="radiogroup" aria-label="Önskad leveransdag">
          <?php foreach ($leveransdagar as $key => $dag): ?>
            <label><input type="radio" name="leveransdag" value="<?php echo $key; ?>"<?php echo $v['leveransdag'] === $key ? ' checked' : ''; ?>><?php echo $dag; ?></label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($fel['leveransdag'])): ?><p class="err" style="margin-top:8px; font-size:.8rem; color:var(--danger);"><?php echo e($fel['leveransdag']); ?></p><?php endif; ?>
      </fieldset>

      <fieldset>
        <legend>Vad vill ni beställa?</legend>
        <div class="check-grid">
          <?php foreach ($sortiment as $key => $s): ?>
            <label class="check"><input type="checkbox" name="kategorier[]" value="<?php echo $key; ?>"<?php echo in_array($key, $v['kategorier'], true) ? ' checked' : ''; ?>><?php echo e($s['namn']); ?></label>
          <?php endforeach; ?>
        </div>
        <div class="field full" style="margin-top:16px;">
          <label for="meddelande">Varor, mängder och övrigt</label>
          <textarea id="meddelande" name="meddelande" placeholder="T.ex. 2 lådor tomater, 10 kg lök, 1 back isbergssallad, 5 burkar krossade tomater 2,5 kg"><?php echo e($v['meddelande']); ?></textarea>
          <span class="hint">Ju mer detaljer, desto snabbare kan vi bekräfta beställningen.</span>
          <?php echo faltfel($fel, 'meddelande'); ?>
        </div>
      </fieldset>

      <?php echo bot_fields(); ?>

      <div class="form-actions">
        <button type="submit" class="btn primary">Skicka beställning</button>
        <span class="form-note">Beställningen blir bindande först när vi har bekräftat den.</span>
      </div>
    </form>

    <aside>
      <?php if (!$user): ?>
        <div class="side-card">
          <h3>Beställer du ofta?</h3>
          <p>Med ett konto slipper du fylla i uppgifterna varje gång, ser alla dina beställningar och kan beställa samma sak igen med ett klick.</p>
          <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;">
            <a class="btn primary small" href="registrera.php">Skapa konto</a>
            <a class="btn ghost small" href="logga-in.php?next=bestall.php">Logga in</a>
          </div>
        </div>
      <?php endif; ?>
      <div class="side-card">
        <h3>Så går det till</h3>
        <ol>
          <li>Du skickar formuläret med det du behöver.</li>
          <li>Vi går igenom beställningen och bekräftar per telefon eller mejl.</li>
          <li>Varorna levereras på den dag vi kommit överens om.</li>
        </ol>
      </div>
      <div class="side-card">
        <h3>Bråttom?</h3>
        <p>Ring oss under öppettiderna, mån&ndash;fre 06:00&ndash;14:30.</p>
        <a class="btn primary" href="tel:+4624018355" style="margin-top:14px;">0240-183 55</a>
      </div>
    </aside>
  </div>
</section>

<?php require __DIR__ . '/includes/foot.php'; ?>
