<?php
$svgOpen = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';

$sortiment = [
  'frukt' => [
    'namn' => 'Frukt',
    'text' => 'Färsk frukt i jämn kvalitet, levererad på fasta dagar.',
    'ikon' => '<path d="M12 9c-3.5 0-6 2.8-6 6.2C6 18.7 8.2 21 10 21c.8 0 1.3-.4 2-.4s1.2.4 2 .4c1.8 0 4-2.3 4-5.8 0-2.2-1.1-4-2.7-5"/><path d="M12 9V6M12 6c0-1.2 1-2 2.2-2"/>',
  ],
  'gront' => [
    'namn' => 'Grönt',
    'text' => 'Ett brett utbud grönsaker för både à la carte, lunch och pizzakök.',
    'ikon' => '<path d="M5 19c8 0 14-6 14-14 0 0-11-1-14 6-2 4-1 8 0 8Z"/><path d="M5 19c2-4 5-7 9-9"/>',
  ],
  'skalat' => [
    'namn' => 'Skalat & förberett',
    'text' => 'Skalade och förberedda råvaror som sparar tid i köket.',
    'ikon' => '<path d="M4 20 16 8"/><path d="M13 5l6 6-3 3-6-6Z"/>',
  ],
  'lask' => [
    'namn' => 'Läsk & dryck',
    'text' => 'Läsk och drycker till matsal, take away och leverans.',
    'ikon' => '<path d="M10 2h4v3l2 2v13a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V7l2-2Z"/><path d="M9 12h6"/>',
  ],
  'kylvaror' => [
    'namn' => 'Kylvaror',
    'text' => 'Kylda varor som levereras med obruten kylkedja.',
    'ikon' => '<rect x="5" y="3" width="14" height="18" rx="1.5"/><path d="M5 11h14M9 6v3M9 15v3"/>',
  ],
  'fryst' => [
    'namn' => 'Frysta varor',
    'text' => 'Fryst sortiment för jämnare planering och mindre svinn.',
    'ikon' => '<path d="M12 2v20M4.5 6l15 12M19.5 6l-15 12"/>',
  ],
  'konserv' => [
    'namn' => 'Konserver',
    'text' => 'Hållbara basvaror som alltid ska finnas på hyllan.',
    'ikon' => '<path d="M5 6h14v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1Z"/><ellipse cx="12" cy="6" rx="7" ry="2.2"/>',
  ],
  'kryddor' => [
    'namn' => 'Kryddor',
    'text' => 'Kryddor i storköksförpackningar för dagligt bruk.',
    'ikon' => '<path d="M9 3h6l1 4v12a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V7Z"/><path d="M10 10h.01M13 10h.01M10 13h.01M13 13h.01M10 16h.01M13 16h.01"/>',
  ],
  'nonfood' => [
    'namn' => 'Non-food',
    'text' => 'Förbrukningsmaterial och emballage till verksamheten.',
    'ikon' => '<path d="M3 8l9-5 9 5-9 5-9-5Z"/><path d="M3 8v9l9 5 9-5V8M12 13v9"/>',
  ],
  'ovrigt' => [
    'namn' => 'Övrigt',
    'text' => 'Saknar du något? Fråga oss, så ser vi vad vi kan ordna.',
    'ikon' => '<circle cx="6" cy="6" r="1.4"/><circle cx="12" cy="6" r="1.4"/><circle cx="18" cy="6" r="1.4"/><circle cx="6" cy="12" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="18" cy="12" r="1.4"/><circle cx="6" cy="18" r="1.4"/><circle cx="12" cy="18" r="1.4"/><circle cx="18" cy="18" r="1.4"/>',
  ],
];

$leveransdagar = [
  'man' => 'Måndag',
  'tis' => 'Tisdag',
  'ons' => 'Onsdag',
  'tor' => 'Torsdag',
  'fre' => 'Fredag',
];

function ikon($key) {
  global $sortiment, $svgOpen;
  return $svgOpen . $sortiment[$key]['ikon'] . '</svg>';
}
