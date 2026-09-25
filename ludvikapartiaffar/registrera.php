<?php
require __DIR__ . '/includes/app.php';

if (current_user()) redirect('konto.php');

$fel = [];
$v = ['foretag' => '', 'kontakt' => '', 'telefon' => '', 'email' => '', 'adress' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $v['foretag'] = enRad($_POST['foretag'] ?? '', 120);
  $v['kontakt'] = enRad($_POST['kontakt'] ?? '', 120);
  $v['telefon'] = enRad($_POST['telefon'] ?? '', 40);
  $v['email']   = mb_strtolower(enRad($_POST['email'] ?? '', 160));
  $v['adress']  = enRad($_POST['adress'] ?? '', 200);
  $pw  = (string)($_POST['password'] ?? '');
  $pw2 = (string)($_POST['password2'] ?? '');

  if (looks_like_bot()) redirect('index.php');

  if ($v['foretag'] === '') $fel['foretag'] = 'Ange restaurangens eller företagets namn.';
  if ($v['kontakt'] === '') $fel['kontakt'] = 'Ange ditt namn.';
  if (!preg_match('/^[0-9 +\-()]{6,}$/', $v['telefon'])) $fel['telefon'] = 'Ange ett telefonnummer, t.ex. 070-123 45 67.';
  if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $fel['email'] = 'Ange en giltig e-postadress.';
  if ($v['adress'] === '') $fel['adress'] = 'Ange leveransadressen.';
  if (mb_strlen($pw) < MIN_PASSWORD) $fel['password'] = 'Lösenordet måste vara minst ' . MIN_PASSWORD . ' tecken.';
  elseif (strlen($pw) > 200) $fel['password'] = 'Lösenordet får vara högst 200 tecken.';
  elseif ($pw !== $pw2) $fel['password2'] = 'Lösenorden matchar inte.';
  if (empty($_POST['villkor'])) $fel['villkor'] = 'Du behöver godkänna hur vi hanterar dina uppgifter.';

  if (!$fel) {
    $st = db()->prepare('SELECT 1 FROM users WHERE email = ?');
    $st->execute([$v['email']]);
    if ($st->fetchColumn()) {
      $fel['email'] = 'Det finns redan ett konto med den e-postadressen. Logga in, eller återställ lösenordet om du glömt det.';
    }
  }

  if (!$fel) {
    db()->prepare('INSERT INTO users (role, email, password_hash, foretag, kontakt, telefon, adress, active, created_at)
                   VALUES (\'kund\', ?, ?, ?, ?, ?, ?, 1, ?)')
        ->execute([$v['email'], password_hash($pw, PASSWORD_DEFAULT), $v['foretag'], $v['kontakt'], $v['telefon'], $v['adress'], now()]);
    $id = (int)db()->lastInsertId();

    send_mail($CONFIG['mail_to'], 'Ny kund registrerad: ' . $v['foretag'], implode("\n", [
      'En ny kund har skapat konto på ludvikaparti.se:',
      '',
      'Företag:  ' . $v['foretag'],
      'Kontakt:  ' . $v['kontakt'],
      'Telefon:  ' . $v['telefon'],
      'E-post:   ' . $v['email'],
      'Adress:   ' . $v['adress'],
      '',
      'Ser kontot misstänkt ut kan du stänga av det i adminpanelen:',
      $CONFIG['site_url'] . '/admin-kunder.php',
    ]), $v['email']);

    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$id]);
    login_user($st->fetch());
    flash('Välkommen! Ditt konto är skapat och du är inloggad.');
    redirect('konto.php');
  }
}

$active = 'login';
$pageTitle = 'Skapa konto – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
?>
<section>
  <div class="wrap" style="max-width:640px;">
    <form class="form-card" method="post" action="registrera.php" novalidate>
      <?php echo csrf_field(); ?>
      <h1 class="card-title">Skapa konto</h1>
      <p class="lead">För restauranger och pizzerior som beställer hos oss. Med ett konto sparas dina uppgifter och du ser alla dina beställningar.</p>
      <?php if ($fel): ?><div class="alert error" role="alert" style="margin-top:18px;"><strong>Några fält behöver kompletteras:</strong> se markeringarna nedan.</div><?php endif; ?>

      <div class="field-grid" style="margin-top:20px;">
        <div class="field">
          <label for="foretag">Restaurang / företag <span class="req">*</span></label>
          <input type="text" id="foretag" name="foretag" value="<?php echo e($v['foretag']); ?>" autocomplete="organization" required>
          <?php echo $err('foretag'); ?>
        </div>
        <div class="field">
          <label for="kontakt">Ditt namn <span class="req">*</span></label>
          <input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" autocomplete="name" required>
          <?php echo $err('kontakt'); ?>
        </div>
        <div class="field">
          <label for="telefon">Telefon <span class="req">*</span></label>
          <input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>" autocomplete="tel" required>
          <?php echo $err('telefon'); ?>
        </div>
        <div class="field">
          <label for="email">E-post <span class="req">*</span></label>
          <input type="email" id="email" name="email" value="<?php echo e($v['email']); ?>" autocomplete="email" required>
          <?php echo $err('email'); ?>
        </div>
        <div class="field full">
          <label for="adress">Leveransadress <span class="req">*</span></label>
          <input type="text" id="adress" name="adress" value="<?php echo e($v['adress']); ?>" autocomplete="street-address" placeholder="Gatuadress, postnummer och ort" required>
          <?php echo $err('adress'); ?>
        </div>
        <div class="field">
          <label for="password">Lösenord <span class="req">*</span></label>
          <input type="password" id="password" name="password" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD; ?>" required>
          <span class="hint">Minst <?php echo MIN_PASSWORD; ?> tecken.</span>
          <?php echo $err('password'); ?>
        </div>
        <div class="field">
          <label for="password2">Upprepa lösenord <span class="req">*</span></label>
          <input type="password" id="password2" name="password2" autocomplete="new-password" required>
          <?php echo $err('password2'); ?>
        </div>
        <div class="field full">
          <label class="check inline"><input type="checkbox" name="villkor" value="1"<?php echo !empty($_POST['villkor']) ? ' checked' : ''; ?>> <span>Jag har läst hur Ludvika Partiaffär <a href="integritet.php" target="_blank">hanterar mina uppgifter</a>.</span></label>
          <?php echo $err('villkor'); ?>
        </div>
      </div>

      <?php echo bot_fields(); ?>
      <div class="form-actions"><button class="btn primary" type="submit">Skapa konto</button></div>
      <p class="form-foot">Har du redan ett konto? <a href="logga-in.php">Logga in</a></p>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
