<?php
require __DIR__ . '/includes/sortiment-data.php';
date_default_timezone_set('Europe/Stockholm');

const MOTTAGARE = 'bashar@ludvikapartiaffar.se';
const MIN_SEKUNDER = 3;

$fel = [];
$skickfel = false;
$v = [
  'foretag' => '', 'kontakt' => '', 'telefon' => '', 'epost' => '',
  'adress' => '', 'leveransdag' => '', 'kategorier' => [], 'meddelande' => '',
];

function enRad($s, $max) {
  $s = trim(str_replace(["\r", "\n", "\0"], ' ', (string)$s));
  return mb_substr($s, 0, $max, 'UTF-8');
}

function e($s) {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $v['foretag']     = enRad($_POST['foretag'] ?? '', 120);
  $v['kontakt']     = enRad($_POST['kontakt'] ?? '', 120);
  $v['telefon']     = enRad($_POST['telefon'] ?? '', 40);
  $v['epost']       = enRad($_POST['epost'] ?? '', 160);
  $v['adress']      = enRad($_POST['adress'] ?? '', 200);
  $v['leveransdag'] = (string)($_POST['leveransdag'] ?? '');
  $v['meddelande']  = mb_substr(trim(str_replace("\0", '', (string)($_POST['meddelande'] ?? ''))), 0, 4000, 'UTF-8');
  $valda = $_POST['kategorier'] ?? [];
  $v['kategorier']  = is_array($valda) ? array_values(array_intersect(array_keys($sortiment), $valda)) : [];

  $t = (int)($_POST['t'] ?? 0);
  $arBot = trim((string)($_POST['webbplats'] ?? '')) !== ''
        || $t === 0
        || (time() - $t) < MIN_SEKUNDER;

  if ($v['foretag'] === '')  $fel['foretag'] = 'Ange restaurangens eller företagets namn.';
  if ($v['kontakt'] === '')  $fel['kontakt'] = 'Ange vem vi ska kontakta.';
  if (!preg_match('/^[0-9 +\-()]{6,}$/', $v['telefon'])) $fel['telefon'] = 'Ange ett telefonnummer, t.ex. 070-123 45 67.';
  if (!filter_var($v['epost'], FILTER_VALIDATE_EMAIL)) $fel['epost'] = 'Ange en giltig e-postadress, t.ex. namn@restaurang.se.';
  if ($v['adress'] === '')   $fel['adress'] = 'Ange leveransadressen.';
  if (!isset($leveransdagar[$v['leveransdag']])) $fel['leveransdag'] = 'Välj önskad leveransdag.';
  if (!$v['kategorier'] && $v['meddelande'] === '') $fel['meddelande'] = 'Kryssa i minst en varugrupp eller skriv vad ni vill beställa.';

  if ($arBot) {
    header('Location: tack.php');
    exit;
  }

  if (!$fel) {
    $kategoriNamn = array_map(fn($k) => $sortiment[$k]['namn'], $v['kategorier']);
    $rader = [
      'Ny beställningsförfrågan från ludvikaparti.se',
      str_repeat('-', 44),
      'Företag/restaurang: ' . $v['foretag'],
      'Kontaktperson:      ' . $v['kontakt'],
      'Telefon:            ' . $v['telefon'],
      'E-post:             ' . $v['epost'],
      'Leveransadress:     ' . $v['adress'],
      'Önskad leveransdag: ' . $leveransdagar[$v['leveransdag']],
      'Varugrupper:        ' . ($kategoriNamn ? implode(', ', $kategoriNamn) : '(inga ikryssade)'),
      '',
      'Beställning / meddelande:',
      $v['meddelande'] !== '' ? $v['meddelande'] : '(inget meddelande)',
      '',
      str_repeat('-', 44),
      'Skickat ' . date('Y-m-d H:i') . '. Svara på det här mejlet för att svara kunden direkt.',
    ];
    $body = implode("\r\n", $rader);

    $subject = 'Beställning: ' . $v['foretag'] . ' (' . $leveransdagar[$v['leveransdag']] . ')';
    $subjectEnc = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromName = '=?UTF-8?B?' . base64_encode('Ludvika Partiaffär webbformulär') . '?=';

    $headers = implode("\r\n", [
      'From: ' . $fromName . ' <' . MOTTAGARE . '>',
      'Reply-To: ' . $v['epost'],
      'MIME-Version: 1.0',
      'Content-Type: text/plain; charset=UTF-8',
      'Content-Transfer-Encoding: 8bit',
      'X-Mailer: PHP/' . PHP_VERSION,
    ]);

    $skickat = @mail(MOTTAGARE, $subjectEnc, $body, $headers, '-f' . MOTTAGARE)
            || @mail(MOTTAGARE, $subjectEnc, $body, $headers);
    if ($skickat) {
      header('Location: tack.php');
      exit;
    }
    $skickfel = true;
  }
}

$active = 'bestall';
$pageTitle = 'Beställ – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>

<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Beställ</span>
    <h1>Skicka en beställning</h1>
    <p>Fyll i formuläret så återkommer vi med bekräftelse och leveransbesked. Brådskande? Ring <a href="tel:+4624018355" style="color:#fff; font-weight:600;">0240-183 55</a> så tar vi beställningen direkt.</p>
  </div>
</section>

<section>
  <div class="wrap form-layout">
    <form class="form-card" method="post" action="bestall.php" novalidate>

      <?php if ($skickfel): ?>
        <div class="alert error" role="alert">Beställningen kunde inte skickas just nu. Ring oss på <a href="tel:+4624018355"><strong>0240-183 55</strong></a> eller mejla <a href="mailto:<?php echo MOTTAGARE; ?>"><?php echo MOTTAGARE; ?></a> så tar vi den direkt.</div>
      <?php elseif ($fel): ?>
        <div class="alert error" role="alert"><strong>Några fält behöver kompletteras:</strong> se markeringarna nedan.</div>
      <?php endif; ?>

      <fieldset>
        <legend>Era uppgifter</legend>
        <div class="field-grid">
          <div class="field">
            <label for="foretag">Restaurang / företag <span class="req">*</span></label>
            <input type="text" id="foretag" name="foretag" value="<?php echo e($v['foretag']); ?>" autocomplete="organization" required>
            <?php if (isset($fel['foretag'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['foretag']); ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label for="kontakt">Kontaktperson <span class="req">*</span></label>
            <input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" autocomplete="name" required>
            <?php if (isset($fel['kontakt'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['kontakt']); ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label for="telefon">Telefon <span class="req">*</span></label>
            <input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>" autocomplete="tel" required>
            <?php if (isset($fel['telefon'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['telefon']); ?></span><?php endif; ?>
          </div>
          <div class="field">
            <label for="epost">E-post <span class="req">*</span></label>
            <input type="email" id="epost" name="epost" value="<?php echo e($v['epost']); ?>" autocomplete="email" required>
            <?php if (isset($fel['epost'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['epost']); ?></span><?php endif; ?>
          </div>
          <div class="field full">
            <label for="adress">Leveransadress <span class="req">*</span></label>
            <input type="text" id="adress" name="adress" value="<?php echo e($v['adress']); ?>" autocomplete="street-address" placeholder="Gatuadress, postnummer och ort" required>
            <?php if (isset($fel['adress'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['adress']); ?></span><?php endif; ?>
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
        <?php if (isset($fel['leveransdag'])): ?><p class="hint" style="color:var(--danger); margin-top:8px; font-size:.8rem;"><?php echo e($fel['leveransdag']); ?></p><?php endif; ?>
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
          <?php if (isset($fel['meddelande'])): ?><span class="hint" style="color:var(--danger)"><?php echo e($fel['meddelande']); ?></span><?php endif; ?>
        </div>
      </fieldset>

      <div class="hp" aria-hidden="true">
        <label for="webbplats">Lämna tomt</label>
        <input type="text" id="webbplats" name="webbplats" tabindex="-1" autocomplete="off">
      </div>
      <input type="hidden" name="t" value="<?php echo time(); ?>">

      <div class="form-actions">
        <button type="submit" class="btn primary">Skicka beställning</button>
        <span class="form-note">Beställningen blir bindande först när vi har bekräftat den.</span>
      </div>
    </form>

    <aside>
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
