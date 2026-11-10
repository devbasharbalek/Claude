<?php
// Sidomenyn i adminportalen. Varje roll ser bara det den har behörighet till.
$admIkon = [
  'order' => '<path d="M9 5h10M9 12h10M9 19h10"/><path d="M4.5 5h.01M4.5 12h.01M4.5 19h.01"/>',
  'plock' => '<path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/>',
  'kunder' => '<path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/><circle cx="12" cy="7.5" r="3.5"/>',
  'webbkonton' => '<rect x="3" y="4" width="18" height="13" rx="2"/><path d="M8 21h8M12 17v4"/>',
  'artiklar' => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
  'superklipp' => '<path d="M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
  'veckansklipp' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  'import' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
  'personal' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20v-.5A5.5 5.5 0 0 1 8.5 14h1A5.5 5.5 0 0 1 15 19.5v.5"/><path d="M16 4.5a3.2 3.2 0 0 1 0 6.2M21 20v-.5a5.5 5.5 0 0 0-4-5.3"/>',
];
$admNyaOrder = far($navUser, 'order') ? (int)db()->query("SELECT COUNT(*) FROM orders WHERE status = 'ny'")->fetchColumn() : 0;
$admVantar = far($navUser, 'webbkonton') ? (int)db()->query("SELECT COUNT(*) FROM users WHERE role = 'kund' AND godkand = 0 AND active = 1")->fetchColumn() : 0;
$admMeny = [
  'Försäljning' => [
    'order' => ['admin.php', 'Order', 'order', $admNyaOrder],
    'plock' => ['admin-plock.php', 'Plock', 'plock', 0],
    'kunder' => ['admin-kundregister.php', 'Kunder', 'kunder', 0],
    'webbkonton' => ['admin-kunder.php', 'Webbkonton', 'webbkonton', $admVantar],
  ],
  'Sortiment' => [
    'artiklar' => ['admin-artiklar.php', 'Artiklar och priser', 'artiklar', 0],
  ],
  'Erbjudanden' => [
    'superklipp' => ['admin-superklipp.php', 'Superklipp', 'erbjudanden', 0],
    'veckansklipp' => ['admin-veckansklipp.php', 'Veckansklipp', 'erbjudanden', 0],
  ],
  'Inställningar' => [
    'import' => ['admin-import.php', 'Import', 'import', 0],
    'personal' => ['admin-personal.php', 'Personal', 'personal', 0],
  ],
];
$admInitialer = mb_strtoupper(implode('', array_map(fn($d) => mb_substr($d, 0, 1, 'UTF-8'), array_slice(preg_split('/\s+/u', trim($navUser['kontakt'] ?: $navUser['email'])), 0, 2))), 'UTF-8');
?>
<div class="adm" id="adm">
  <aside class="adm-side" id="admSide" aria-label="Adminmeny">
    <a class="adm-logo" href="admin.php"><img src="<?php echo $logoSrc; ?>" alt=""><span>Ludvika Partiaffär<small>Affärssystem</small></span></a>
    <nav class="adm-nav">
      <?php foreach ($admMeny as $admRubrik => $admPoster):
        $admSynliga = array_filter($admPoster, fn($p) => far($navUser, $p[2]));
        if (!$admSynliga) continue; ?>
        <p class="adm-rubrik"><?php echo e($admRubrik); ?></p>
        <?php foreach ($admSynliga as $admNyckel => [$admHref, $admNamn, , $admAntal]): ?>
          <a href="<?php echo $admHref; ?>"<?php echo $admNyckel === $adminSida ? ' class="on" aria-current="page"' : ''; ?>><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $admIkon[$admNyckel]; ?></svg><?php echo e($admNamn); ?><?php if ($admAntal): ?><span class="adm-badge" title="<?php echo $admNyckel === 'order' ? 'Nya order' : 'Väntar på godkännande'; ?>"><?php echo $admAntal; ?></span><?php endif; ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="adm-side-fot">
      <div class="adm-anvandare">
        <span class="adm-avatar" aria-hidden="true"><?php echo e($admInitialer); ?></span>
        <div><b><?php echo e($navUser['kontakt'] ?: $navUser['email']); ?></b><small><?php echo e(roll_text($navUser)); ?></small></div>
      </div>
      <div class="adm-fot-rad">
        <a href="profil.php"<?php echo $adminSida === 'profil' ? ' aria-current="page"' : ''; ?>>Mina uppgifter</a>
        <a href="index.php">Hemsidan</a>
      </div>
      <div class="adm-fot-rad">
        <form method="post" action="logga-ut.php"><?php echo csrf_field(); ?><button class="lank" type="submit">Logga ut</button></form>
        <button type="button" class="theme-switch" data-tema role="switch" aria-checked="false" aria-label="Mörkt läge" title="Byt till mörkt läge"><span class="knob" aria-hidden="true"><svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg></span></button>
      </div>
    </div>
  </aside>
  <div class="adm-skugga" id="admSkugga"></div>
  <div class="adm-main">
    <header class="adm-top">
      <button class="menu-toggle" id="admToggle" aria-label="Öppna menyn" aria-expanded="false" aria-controls="admSide">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
      <b>Affärssystem</b>
    </header>
    <main>
<?php if ($flashMsg = flash()): ?>
      <div class="wrap"><div class="flash" role="status"><?php echo e($flashMsg); ?></div></div>
<?php endif; ?>
