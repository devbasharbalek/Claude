<?php if (!empty($adminSida)): ?>
    </main>
  </div>
</div>
<?php else: ?>
</main>

<footer class="site-footer">
  <div class="wrap">
    <div class="footer-info">
      <div class="info-blk">
        <span class="info-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"/></svg></span>
        <h4>Beställning</h4>
        <p>Fyll i vårt beställningsformulär så återkommer vi med bekräftelse, eller ring oss direkt under öppettiderna.</p>
        <div class="pil-lankar">
          <a class="pil" href="bestall">Till beställningen <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <a class="pil" href="integritet">Integritet <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
      </div>
      <div class="info-blk">
        <span class="info-ikon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 21l1.9-5.4A8 8 0 1 1 21 12Z"/></svg></span>
        <h4>Kontakt</h4>
        <p>Ring för att beställa eller prata sortiment, eller skicka en förfrågan via beställningsformuläret. Vi svarar under våra öppettider.</p>
        <div class="pil-lankar">
          <a class="pil" href="kontakt">Till kontaktsidan <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
          <a class="pil" href="tel:+4624018355">0240-183 55 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></a>
        </div>
      </div>
    </div>

    <div class="footer-grid">
      <div>
        <a class="logo-chip" href="./"><img src="<?php echo $logoSrc; ?>" alt="Ludvika Partiaffär"></a>
        <p class="footer-tag">Grossist inom frukt, grönt och storköksvaror för kommuner, restauranger och livsbutiker sedan 60-talet.</p>
      </div>
      <div>
        <h4>Sidor</h4>
        <ul>
          <li><a href="./">Hem</a></li>
          <li><a href="sortiment">Sortiment</a></li>
          <li><a href="bestall">Beställ</a></li>
          <li><a href="kontakt">Kontakt</a></li>
          <li><a href="<?php echo current_user() ? 'konto' : 'logga-in'; ?>"><?php echo current_user() ? 'Mitt konto' : 'Logga in'; ?></a></li>
          <li><a href="integritet">Integritet</a></li>
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
    <div class="footer-bottom"><b>&copy; <?php echo date('Y'); ?> Ludvika Partiaffär</b> &middot; Partihandel sedan 60-talet</div>
  </div>
</footer>
<?php endif; ?>

<script>
(function(){
  // Adminportalens sidomeny i mobilen.
  var adm = document.getElementById('adm');
  var admBtn = document.getElementById('admToggle');
  if (adm && admBtn) {
    var admSatt = function (open) {
      adm.classList.toggle('oppen', open);
      admBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      admBtn.setAttribute('aria-label', open ? 'Stäng menyn' : 'Öppna menyn');
    };
    admBtn.addEventListener('click', function () { admSatt(!adm.classList.contains('oppen')); });
    document.getElementById('admSkugga').addEventListener('click', function () { admSatt(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') admSatt(false); });
  }

  var btn = document.getElementById('menuToggle');
  var menu = document.getElementById('mobileMenu');
  if(btn && menu){
    btn.addEventListener('click', function(){
      var open = menu.classList.toggle('open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Stäng meny' : 'Öppna meny');
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
