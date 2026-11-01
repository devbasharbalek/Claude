<?php
require __DIR__ . '/includes/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  logout_user();
  flash('Du är utloggad.');
  redirect('index.php');
}

$active = '';
$pageTitle = 'Logga ut – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section>
  <div class="wrap narrow">
    <form class="form-card" method="post" action="logga-ut.php">
      <?php echo csrf_field(); ?>
      <h1 class="card-title">Logga ut?</h1>
      <div class="form-actions"><button class="btn primary" type="submit">Logga ut</button><a class="btn ghost" href="index.php">Avbryt</a></div>
    </form>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
