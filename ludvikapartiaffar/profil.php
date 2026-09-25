<?php
require __DIR__ . '/includes/app.php';
$user = require_login();

$fel = [];
$pwFel = [];
$v = ['foretag' => $user['foretag'], 'kontakt' => $user['kontakt'], 'telefon' => $user['telefon'], 'email' => $user['email'], 'adress' => $user['adress']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  if ($action === 'uppgifter') {
    $v['foretag'] = enRad($_POST['foretag'] ?? '', 120);
    $v['kontakt'] = enRad($_POST['kontakt'] ?? '', 120);
    $v['telefon'] = enRad($_POST['telefon'] ?? '', 40);
    $v['email']   = mb_strtolower(enRad($_POST['email'] ?? '', 160));
    $v['adress']  = enRad($_POST['adress'] ?? '', 200);

    if ($v['foretag'] === '') $fel['foretag'] = 'Ange restaurangens eller företagets namn.';
    if ($v['kontakt'] === '') $fel['kontakt'] = 'Ange ditt namn.';
    if (!preg_match('/^[0-9 +\-()]{6,}$/', $v['telefon'])) $fel['telefon'] = 'Ange ett telefonnummer.';
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $fel['email'] = 'Ange en giltig e-postadress.';
    if ($v['adress'] === '') $fel['adress'] = 'Ange leveransadressen.';

    if (!$fel && $v['email'] !== $user['email']) {
      if (!password_verify((string)($_POST['current_password'] ?? ''), $user['password_hash'])) {
        $fel['current_password'] = 'Ange ditt nuvarande lösenord för att byta e-postadress.';
      } else {
        $st = db()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $st->execute([$v['email'], $user['id']]);
        if ($st->fetchColumn()) $fel['email'] = 'Det finns redan ett konto med den e-postadressen.';
      }
    }

    if (!$fel) {
      db()->prepare('UPDATE users SET foretag = ?, kontakt = ?, telefon = ?, email = ?, adress = ? WHERE id = ?')
          ->execute([$v['foretag'], $v['kontakt'], $v['telefon'], $v['email'], $v['adress'], $user['id']]);
      flash('Dina uppgifter är sparade.');
      redirect('profil.php');
    }
  }

  if ($action === 'losenord') {
    $cur = (string)($_POST['current_password'] ?? '');
    $pw  = (string)($_POST['password'] ?? '');
    $pw2 = (string)($_POST['password2'] ?? '');
    if (!password_verify($cur, $user['password_hash'])) $pwFel[] = 'Nuvarande lösenord stämmer inte.';
    elseif (mb_strlen($pw) < MIN_PASSWORD) $pwFel[] = 'Det nya lösenordet måste vara minst ' . MIN_PASSWORD . ' tecken.';
    elseif (strlen($pw) > 200) $pwFel[] = 'Lösenordet får vara högst 200 tecken.';
    elseif ($pw !== $pw2) $pwFel[] = 'De nya lösenorden matchar inte.';

    if (!$pwFel) {
      $hash = password_hash($pw, PASSWORD_DEFAULT);
      db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $user['id']]);
      $user['password_hash'] = $hash;
      login_user($user);
      flash('Ditt lösenord är ändrat. Andra enheter där du var inloggad har loggats ut.');
      redirect('profil.php');
    }
  }
}

$active = 'konto';
$pageTitle = 'Mina uppgifter – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
$err = fn($k) => isset($fel[$k]) ? '<span class="err">' . e($fel[$k]) . '</span>' : '';
?>
<section>
  <div class="wrap" style="max-width:760px;">
    <a class="back-link" href="<?php echo $user['role'] === 'admin' ? 'admin.php' : 'konto.php'; ?>">&larr; Tillbaka</a>

    <form class="form-card" method="post" action="profil.php" novalidate>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="uppgifter">
      <h1 class="card-title">Mina uppgifter</h1>
      <p class="lead">Används som förval när du beställer.</p>
      <?php if ($fel): ?><div class="alert error" role="alert" style="margin-top:18px;">Uppgifterna sparades inte, se markeringarna nedan.</div><?php endif; ?>
      <div class="field-grid" style="margin-top:20px;">
        <div class="field"><label for="foretag">Restaurang / företag</label><input type="text" id="foretag" name="foretag" value="<?php echo e($v['foretag']); ?>" required><?php echo $err('foretag'); ?></div>
        <div class="field"><label for="kontakt">Ditt namn</label><input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" required><?php echo $err('kontakt'); ?></div>
        <div class="field"><label for="telefon">Telefon</label><input type="tel" id="telefon" name="telefon" value="<?php echo e($v['telefon']); ?>" required><?php echo $err('telefon'); ?></div>
        <div class="field"><label for="email">E-post (inloggning)</label><input type="email" id="email" name="email" value="<?php echo e($v['email']); ?>" autocomplete="username" required><?php echo $err('email'); ?></div>
        <div class="field full"><label for="adress">Leveransadress</label><input type="text" id="adress" name="adress" value="<?php echo e($v['adress']); ?>" required><?php echo $err('adress'); ?></div>
        <div class="field full"><label for="current_password_e">Nuvarande lösenord <span class="hint">(krävs bara om du byter e-post)</span></label><input type="password" id="current_password_e" name="current_password" autocomplete="current-password"><?php echo $err('current_password'); ?></div>
      </div>
      <div class="form-actions"><button class="btn primary" type="submit">Spara uppgifter</button></div>
    </form>

    <form class="form-card" method="post" action="profil.php" style="margin-top:18px;">
      <?php echo csrf_field(); ?>
      <input type="hidden" name="action" value="losenord">
      <h2 class="card-title" style="font-size:1.8rem;">Byt lösenord</h2>
      <?php if ($pwFel): ?><div class="alert error" role="alert" style="margin-top:14px;"><?php echo e($pwFel[0]); ?></div><?php endif; ?>
      <div class="field-grid" style="margin-top:16px;">
        <div class="field full"><label for="current_password">Nuvarande lösenord</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="password">Nytt lösenord</label><input type="password" id="password" name="password" autocomplete="new-password" minlength="<?php echo MIN_PASSWORD; ?>" required><span class="hint">Minst <?php echo MIN_PASSWORD; ?> tecken.</span></div>
        <div class="field"><label for="password2">Upprepa nytt lösenord</label><input type="password" id="password2" name="password2" autocomplete="new-password" required></div>
      </div>
      <div class="form-actions"><button class="btn primary" type="submit">Byt lösenord</button></div>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
