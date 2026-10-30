</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-grid">
      <div>
        <a class="logo-chip" href="index.php"><img src="<?php echo $logoSrc; ?>" alt="Ludvika Partiaffär"></a>
        <p class="footer-tag">Grossist inom frukt, grönt och storköksvaror för kommuner, restauranger och livsbutiker sedan 60-talet.</p>
      </div>
      <div>
        <h4>Sidor</h4>
        <ul>
          <li><a href="index.php">Hem</a></li>
          <li><a href="sortiment.php">Sortiment</a></li>
          <li><a href="bestall.php">Beställ</a></li>
          <li><a href="kontakt.php">Kontakt</a></li>
          <li><a href="<?php echo current_user() ? 'konto.php' : 'logga-in.php'; ?>"><?php echo current_user() ? 'Mitt konto' : 'Logga in'; ?></a></li>
          <li><a href="integritet.php">Integritet</a></li>
        </ul>
      </div>
      <div>
        <h4>Kontakt</h4>
        <ul>
          <li><a href="tel:+4624018355">0240-183 55</a></li>
          <li><a href="mailto:bashar@ludvikapartiaffar.se">bashar@ludvikapartiaffar.se</a></li>
          <li>Fläderstigen 2</li>
          <li>771 43 Ludvika</li>
        </ul>
      </div>
      <div>
        <h4>Öppettider</h4>
        <ul>
          <li>Mån&ndash;fre 06:00&ndash;14:30</li>
          <li>Lör&ndash;sön stängt</li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">&copy; <?php echo date('Y'); ?> Ludvika Partiaffär &middot; Partihandel sedan 60-talet</div>
  </div>
</footer>

<script>
(function(){
  var btn = document.getElementById('menuToggle');
  var menu = document.getElementById('mobileMenu');
  if(btn && menu){
    btn.addEventListener('click', function(){
      var open = menu.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // Ljust/mörkt läge. Utan eget val följer sidan enhetens inställning.
  var root = document.documentElement;
  var mq = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var knappar = document.querySelectorAll('[data-tema]');
  function tema(){ return root.getAttribute('data-theme') || (mq && mq.matches ? 'dark' : 'light'); }
  function visa(){
    var mork = tema() === 'dark';
    for (var i = 0; i < knappar.length; i++) {
      knappar[i].setAttribute('aria-checked', mork ? 'true' : 'false');
      knappar[i].title = mork ? 'Byt till ljust läge' : 'Byt till mörkt läge';
    }
  }
  for (var i = 0; i < knappar.length; i++) {
    knappar[i].addEventListener('click', function(){
      var nytt = tema() === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', nytt);
      try { localStorage.setItem('lp-tema', nytt); } catch (e) {}
      visa();
    });
  }
  if (mq && mq.addEventListener) mq.addEventListener('change', visa);
  visa();
})();
</script>
</body>
</html>
