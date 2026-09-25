<?php
require __DIR__ . '/includes/app.php';
$active = '';
$pageTitle = 'Så hanterar vi dina uppgifter – Ludvika Partiaffär';
require __DIR__ . '/includes/head.php';
?>
<section class="page-hero">
  <div class="wrap">
    <span class="eyebrow">Integritet</span>
    <h1>Så hanterar vi dina uppgifter</h1>
  </div>
</section>
<section>
  <div class="wrap prose">
    <h2>Vem ansvarar?</h2>
    <p>Ludvika Partiaffär, Fläderstigen 2, 771 43 Ludvika, ansvarar för de uppgifter du lämnar på den här webbplatsen. Frågor når oss på <a href="tel:+4624018355">0240-183 55</a> eller <a href="mailto:<?php echo e($CONFIG['mail_to']); ?>"><?php echo e($CONFIG['mail_to']); ?></a>.</p>

    <h2>Vilka uppgifter sparar vi?</h2>
    <ul>
      <li><strong>Konto:</strong> företagsnamn, kontaktperson, telefon, e-post, leveransadress och ditt lösenord i krypterad form (vi kan aldrig se lösenordet).</li>
      <li><strong>Beställningar:</strong> det du skriver i beställningsformuläret, när den skickades och vilken status den har.</li>
      <li><strong>Säkerhet:</strong> vid misslyckade inloggningar sparar vi e-postadress och IP-adress i högst ett dygn för att stoppa intrångsförsök.</li>
    </ul>

    <h2>Varför?</h2>
    <p>Vi använder uppgifterna för att ta emot, bekräfta och leverera dina beställningar, och för att kunna kontakta dig om dem. Vi säljer eller lämnar inte ut uppgifterna till någon annan för marknadsföring.</p>

    <h2>Cookies</h2>
    <p>Sajten använder en enda cookie som håller dig inloggad och skyddar formulären mot missbruk. Den försvinner när du stänger webbläsaren. Vi använder inga cookies för statistik eller reklam.</p>

    <h2>Dina rättigheter</h2>
    <p>Du kan när som helst se och ändra dina kontouppgifter under <a href="profil.php">Mina uppgifter</a>. Vill du ha ut en kopia av allt vi sparat om dig, eller att vi raderar ditt konto, kontakta oss så hjälper vi dig. Beställningar kan vi behöva spara så länge bokföringsreglerna kräver det.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/foot.php'; ?>
