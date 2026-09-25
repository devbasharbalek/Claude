<?php
require __DIR__ . '/includes/app.php';

$schema = [
  "CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(10) NOT NULL DEFAULT 'kund',
    email VARCHAR(160) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    foretag VARCHAR(120) NOT NULL,
    kontakt VARCHAR(120) NOT NULL,
    telefon VARCHAR(40) NOT NULL,
    adress VARCHAR(200) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    last_login DATETIME NULL,
    UNIQUE KEY uq_email (email)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    foretag VARCHAR(120) NOT NULL,
    kontakt VARCHAR(120) NOT NULL,
    telefon VARCHAR(40) NOT NULL,
    epost VARCHAR(160) NOT NULL,
    adress VARCHAR(200) NOT NULL,
    leveransdag VARCHAR(3) NOT NULL,
    kategorier VARCHAR(255) NOT NULL DEFAULT '',
    meddelande TEXT NOT NULL,
    status VARCHAR(12) NOT NULL DEFAULT 'ny',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_user (user_id, created_at),
    KEY idx_status (status, created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(160) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL,
    KEY idx_email (email, attempted_at),
    KEY idx_ip (ip, attempted_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",

  "CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    KEY idx_token (token_hash),
    KEY idx_user (user_id, created_at),
    CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_swedish_ci",
];

$fel = [];
$klar = false;
$v = ['email' => '', 'kontakt' => ''];

try {
  foreach ($schema as $sql) db()->exec($sql);
  $harAdmin = (bool)db()->query("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")->fetchColumn();
} catch (PDOException $ex) {
  http_response_code(500);
  exit('Kunde inte ansluta till databasen. Kontrollera uppgifterna i includes/config.php.');
}

if ($harAdmin) {
  http_response_code(403);
  exit('Installationen är redan klar. Ta bort setup.php från servern.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $v['email']   = mb_strtolower(enRad($_POST['email'] ?? '', 160));
  $v['kontakt'] = enRad($_POST['kontakt'] ?? '', 120);
  $pw = (string)($_POST['password'] ?? '');

  if (!hash_equals((string)$CONFIG['setup_key'], (string)($_POST['setup_key'] ?? ''))
      || $CONFIG['setup_key'] === 'byt-till-en-lang-hemlig-text') {
    $fel[] = 'Fel installationsnyckel, eller så har den inte bytts ut i config.php.';
  }
  if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $fel[] = 'Ange en giltig e-postadress.';
  if ($v['kontakt'] === '') $fel[] = 'Ange ditt namn.';
  if (mb_strlen($pw) < MIN_PASSWORD) $fel[] = 'Lösenordet måste vara minst ' . MIN_PASSWORD . ' tecken.';

  if (!$fel) {
    db()->prepare('INSERT INTO users (role, email, password_hash, foretag, kontakt, telefon, adress, active, created_at)
                   VALUES (\'admin\', ?, ?, \'Ludvika Partiaffär\', ?, \'0240-183 55\', \'Fläderstigen 2, 771 43 Ludvika\', 1, ?)')
        ->execute([$v['email'], password_hash($pw, PASSWORD_DEFAULT), $v['kontakt'], now()]);
    $klar = true;
  }
}

$active = '';
$pageTitle = 'Installation – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap narrow">
    <?php if ($klar): ?>
      <div class="form-card">
        <h1 class="card-title">Installationen är klar</h1>
        <p class="lead">Databasen är skapad och ditt admin-konto finns. <strong>Ta nu bort <code>setup.php</code> från servern</strong> via FileZilla.</p>
        <a class="btn primary" href="logga-in.php" style="margin-top:20px;">Logga in</a>
      </div>
    <?php else: ?>
      <form class="form-card" method="post" action="setup.php">
        <h1 class="card-title">Installation</h1>
        <p class="lead">Databastabellerna är skapade. Skapa nu det första admin-kontot.</p>
        <?php if ($fel): ?><div class="alert error" role="alert"><?php echo implode('<br>', array_map('e', $fel)); ?></div><?php endif; ?>
        <div class="field-grid" style="margin-top:18px;">
          <div class="field full"><label for="setup_key">Installationsnyckel (från config.php)</label><input type="password" id="setup_key" name="setup_key" required></div>
          <div class="field full"><label for="kontakt">Ditt namn</label><input type="text" id="kontakt" name="kontakt" value="<?php echo e($v['kontakt']); ?>" required></div>
          <div class="field full"><label for="email">E-post för inloggning</label><input type="email" id="email" name="email" value="<?php echo e($v['email']); ?>" autocomplete="username" required></div>
          <div class="field full"><label for="password">Lösenord (minst <?php echo MIN_PASSWORD; ?> tecken)</label><input type="password" id="password" name="password" autocomplete="new-password" required></div>
        </div>
        <div class="form-actions"><button class="btn primary" type="submit">Skapa admin-konto</button></div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
