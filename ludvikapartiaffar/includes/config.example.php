<?php
// Kopiera den här filen till config.php i samma mapp och fyll i era uppgifter.
// config.php ska aldrig delas eller läggas upp på GitHub.

return [
  // Databasuppgifter från Loopia Kundzon → Webbhotell → Databaser
  'db_host' => 'mysql123.loopia.se',
  'db_name' => 'databasnamn',
  'db_user' => 'anvandare@l123456',
  'db_pass' => 'lösenord',

  // Hit skickas alla beställningar och notiser om nya kunder
  'mail_to' => 'bashar@ludvikapartiaffar.se',

  // Sajtens adress utan snedstreck på slutet, används i länkar för återställning av lösenord
  'site_url' => 'https://ludvikaparti.se',

  // Hemlig nyckel som krävs för att köra setup.php en gång. Byt till en lång slumpmässig text.
  'setup_key' => 'byt-till-en-lang-hemlig-text',
];
