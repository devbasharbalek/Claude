<?php
$logoSrc = 'img/logo.png';
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Ludvika Partiaffär';
$navUser = current_user();
$navItems = '';
foreach (['hem' => ['index.php', 'Hem'], 'sortiment' => ['sortiment.php', 'Sortiment'], 'superklipp' => ['superklipp.php', 'Superklipp'], 'veckansklipp' => ['veckansklipp.php', 'Veckansklipp'], 'bestall' => ['bestall.php', 'Beställ'], 'kontakt' => ['kontakt.php', 'Kontakt']] as $key => [$href, $label]) {
  // Erbjudandena syns bara i menyn för kunder som har dem.
  if (isset(ERBJUDANDEN[$key]) && !ser_erbjudande($navUser, $key)) continue;
  $navItems .= '<li><a href="' . $href . '"' . ($key === $active ? ' class="active" aria-current="page"' : '') . '>' . $label . '</a></li>';
}
$kontoHref = $navUser ? (ar_personal($navUser) ? 'admin.php' : 'konto.php') : 'logga-in.php';
$kontoNamn = $navUser ? (ar_personal($navUser) ? 'Admin' : 'Mitt konto') : 'Logga in';
// Adminportalen har egen sidomeny i stället för hemsidans meny och sidfot. Bara personal får den.
$adminSida = isset($adminSida) && ar_personal($navUser) ? (string)$adminSida : null;
$skapaAktiv = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'registrera.php';
$kontoAktiv = !$skapaAktiv && in_array($active, ['konto', 'admin', 'login'], true);
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="same-origin">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="description" content="<?php echo e($metaBeskrivning ?? 'Ludvika Partiaffär – grossist inom frukt, grönt och storköksvaror för kommuner, restauranger, pizzerior och livsbutiker sedan 60-talet.'); ?>">
<?php if (!empty($ogBild)): ?>
<meta property="og:type" content="website">
<meta property="og:site_name" content="Ludvika Partiaffär">
<meta property="og:title" content="<?php echo e($pageTitle); ?>">
<meta property="og:description" content="<?php echo e($metaBeskrivning ?? ''); ?>">
<meta property="og:image" content="<?php echo e($ogBild); ?>">
<?php endif; ?>
<script>try{var t=localStorage.getItem('lp-tema');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800&display=swap">
<style>
  /* Profilfärger: logoblå #032CB9, gul #F7CC11 och vitt. Mörkt läge heter Grafit. */
  :root{
    color-scheme:light;
    --font:'Figtree', system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
    --radius:12px;
    --bg:#FFFFFF;
    --bg-alt:#F4F4F2;
    --surface:#FFFFFF;
    --ink:#23262D;
    --ink-soft:#5A606D;
    --brand:#032CB9;
    --brand-deep:#021F86;
    --brand-ink:#FFFFFF;
    --brand-soft:#E8EDFC;
    --accent:#F7CC11;
    --accent-ink:#1B1D22;
    --accent-soft:#FEF6D2;
    --accent-text:#7A5A00;
    --hero:#032CB9;
    --hero-deep:#021F86;
    --hero-soft:#DDE4FF;
    --fill:#032CB9;
    --fill-ink:#FFFFFF;
    --panel:#021F86;
    --panel-2:rgba(255,255,255,.09);
    --topbar-bg:#F4F4F4;
    --topbar-ink:#23262D;
    --footer-bg:#021F86;
    --footer-ink:#FFFFFF;
    --footer-soft:#CDD6FF;
    --footer-line:rgba(255,255,255,.2);
    --green:#1E7A3A;
    --green-soft:#E3F3E6;
    --border:#E0DEDB;
    --border-strong:#D3D1CD;
    --danger:#C62828;
    --shadow: 0 1px 2px rgba(20,22,28,.05), 0 10px 28px -16px rgba(20,22,28,.25);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      color-scheme:dark;
      --bg:#121316;
      --bg-alt:#191A1E;
      --surface:#1F2126;
      --ink:#F2F3F5;
      --ink-soft:#A3A7B1;
      --brand:#8EA4FF;
      --brand-deep:#BFCBFF;
      --brand-ink:#0B0F1F;
      --brand-soft:#232A45;
      --accent:#F7CC11;
      --accent-ink:#1B1D22;
      --accent-soft:#2E2A14;
      --accent-text:#F7CC11;
      --hero:#032CB9;
      --hero-deep:#021F86;
      --hero-soft:#DDE4FF;
      --fill:#032CB9;
      --fill-ink:#FFFFFF;
      --panel:#1F2126;
      --panel-2:#2A2D33;
      --topbar-bg:#191A1E;
      --topbar-ink:#C9CCD3;
      --footer-bg:#24262B;
      --footer-ink:#F2F3F5;
      --footer-soft:#A9ADB7;
      --footer-line:#363940;
      --green:#5FC77E;
      --green-soft:#16291C;
      --border:#2D3036;
      --border-strong:#3A3D44;
      --danger:#FF8A80;
      --shadow: 0 1px 2px rgba(0,0,0,.35), 0 14px 34px -16px rgba(0,0,0,.6);
    }
  }
  :root[data-theme="dark"]{
    color-scheme:dark;
    --bg:#121316;
    --bg-alt:#191A1E;
    --surface:#1F2126;
    --ink:#F2F3F5;
    --ink-soft:#A3A7B1;
    --brand:#8EA4FF;
    --brand-deep:#BFCBFF;
    --brand-ink:#0B0F1F;
    --brand-soft:#232A45;
    --accent:#F7CC11;
    --accent-ink:#1B1D22;
    --accent-soft:#2E2A14;
    --accent-text:#F7CC11;
    --hero:#032CB9;
    --hero-deep:#021F86;
    --hero-soft:#DDE4FF;
    --fill:#032CB9;
    --fill-ink:#FFFFFF;
    --panel:#1F2126;
    --panel-2:#2A2D33;
    --topbar-bg:#191A1E;
    --topbar-ink:#C9CCD3;
    --footer-bg:#24262B;
    --footer-ink:#F2F3F5;
    --footer-soft:#A9ADB7;
    --footer-line:#363940;
    --green:#5FC77E;
    --green-soft:#16291C;
    --border:#2D3036;
    --border-strong:#3A3D44;
    --danger:#FF8A80;
    --shadow: 0 1px 2px rgba(0,0,0,.35), 0 14px 34px -16px rgba(0,0,0,.6);
  }

  *{box-sizing:border-box;}
  html,body{margin:0;}
  body{
    background:var(--bg);
    color:var(--ink);
    font-family:var(--font);
    font-size:15px;
    line-height:1.5;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3,h4{
    font-family:var(--font);
    font-weight:700;
    letter-spacing:-.01em;
    text-wrap:balance;
    margin:0;
    line-height:1.15;
  }
  p{margin:0;}
  a{color:inherit;}
  img{max-width:100%; display:block;}
  button, input, select, textarea{font-family:inherit;}
  ::selection{background:var(--accent); color:var(--accent-ink);}
  :focus-visible{outline:2px solid var(--brand); outline-offset:2px;}

  .wrap{max-width:1100px; margin-inline:auto; padding-inline:24px;}
  @media (max-width:640px){ .wrap{padding-inline:18px;} }

  .eyebrow{
    display:inline-block;
    font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    color:var(--ink-soft);
  }

  /* toppremsa */
  .topbar{background:var(--topbar-bg); color:var(--topbar-ink); font-size:.86rem;}
  .topbar .wrap{min-height:34px; display:flex; justify-content:space-between; align-items:center; gap:12px;}
  .tb-item{display:inline-flex; align-items:center; gap:7px; text-decoration:none; white-space:nowrap;}
  .tb-item svg{width:15px; height:15px; flex:none;}
  a.tb-item:hover{text-decoration:underline;}
  @media (max-width:640px){ .topbar .tb-adress{display:none;} .topbar{font-size:.8rem;} }

  /* sidhuvud */
  header.nav{
    position:sticky; top:0; z-index:30;
    background:var(--bg);
    border-bottom:1px solid var(--border);
  }
  .nav-inner{
    max-width:1100px; margin-inline:auto; padding:12px 24px;
    display:flex; align-items:center; justify-content:space-between; gap:20px;
  }
  .logo-chip{
    background:#fff; border-radius:8px; padding:4px 8px;
    display:flex; align-items:center; flex:none;
    text-decoration:none;
  }
  .logo-chip img{height:42px; width:auto;}
  .nav-actions{display:flex; gap:22px; align-items:center;}
  .nav-text{font-size:1rem; text-decoration:none; color:var(--ink); white-space:nowrap; padding-block:4px; border-bottom:2px solid transparent;}
  .nav-text:hover{border-bottom-color:var(--border-strong);}
  .nav-text.active{font-weight:700; border-bottom-color:var(--accent);}
  .icon-btn{
    width:42px; height:42px; flex:none;
    display:inline-flex; align-items:center; justify-content:center;
    border-radius:999px; color:var(--ink); text-decoration:none;
  }
  .icon-btn:hover{background:var(--bg-alt);}
  .icon-btn.active{color:var(--brand);}
  .icon-btn svg{width:24px; height:24px;}
  .account-btn{display:none;}
  .nav-row{max-width:1100px; margin-inline:auto; padding:0 24px;}
  .nav-links{display:flex; gap:34px; font-size:1rem; list-style:none; margin:0; padding:0;}
  .nav-links a{display:block; text-decoration:none; color:var(--ink); padding:4px 0 12px; border-bottom:3px solid transparent;}
  .nav-links a:hover{border-bottom-color:var(--border-strong);}
  .nav-links a.active{font-weight:700; border-bottom-color:var(--accent);}

  .menu-toggle{
    display:none; background:none; border:none; border-radius:999px;
    width:42px; height:42px; padding:0; color:var(--ink); cursor:pointer;
    align-items:center; justify-content:center;
  }
  .menu-toggle:hover{background:var(--bg-alt);}
  .menu-toggle svg{width:26px; height:26px; display:block;}
  .mobile-menu{display:none; border-top:1px solid var(--border); background:var(--bg);}
  .mobile-menu ul{list-style:none; margin:0; padding:6px 18px 14px;}
  .mobile-menu a{display:block; padding:13px 0; text-decoration:none; font-size:1.05rem; border-bottom:1px solid var(--border);}
  .mobile-menu li:last-child a{border-bottom:none;}
  .mobile-menu a.active{font-weight:700; color:var(--brand);}
  @media (max-width:900px){
    .nav-inner{padding:10px 18px;}
    .logo-chip img{height:36px;}
    .nav-row, .nav-text, .nav-theme{display:none;}
    .nav-actions{gap:2px;}
    .account-btn{display:inline-flex;}
    .menu-toggle{display:inline-flex;}
    .mobile-menu.open{display:block;}
  }

  /* knappar */
  .btn{
    font-family:var(--font); font-weight:700; font-size:1rem; line-height:1.1;
    min-height:44px; padding:11px 24px; border-radius:999px; text-decoration:none;
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    border:2px solid transparent; cursor:pointer; text-align:center;
  }
  .btn.accent{background:var(--accent); color:var(--accent-ink);}
  .btn.accent:hover{background:#FFD83A;}
  .btn.outline{border-color:#fff; color:#fff; background:transparent;}
  .btn.outline:hover{background:rgba(255,255,255,.1);}
  .btn.primary{background:var(--fill); color:var(--fill-ink);}
  .btn.primary:hover{background:var(--hero-deep);}
  .btn.ghost{border:1px solid var(--border-strong); padding:12px 25px; color:var(--ink); background:var(--surface);}
  .btn.ghost:hover{border-color:var(--ink-soft);}
  .btn svg{width:17px; height:17px;}

  /* startsidans hero */
  .hero{padding-block:24px 0;}
  .hero-card{
    position:relative; background:var(--hero); color:#fff; border-radius:var(--radius);
    padding:60px 58px; min-height:340px;
    display:grid; grid-template-columns:1.1fr .9fr; gap:36px; align-items:center;
  }
  .hero .eyebrow, .page-hero .eyebrow{color:var(--accent);}
  h1.headline{font-size:clamp(2rem, 3.6vw, 2.75rem); line-height:1.1; max-width:15ch; margin-top:10px;}
  .hero-sub{margin-top:16px; font-size:1.05rem; line-height:1.6; color:var(--hero-soft); max-width:48ch;}
  .hero-actions{margin-top:28px; display:flex; gap:14px; flex-wrap:wrap;}
  .hero-sticker{
    position:absolute; top:-12px; right:-12px; z-index:1;
    background:var(--accent); color:var(--accent-ink);
    font-weight:800; font-size:1.05rem; line-height:1.2; padding:8px 13px;
    transform:rotate(6deg);
  }
  .hero-art{display:grid; grid-template-columns:repeat(3, 92px); gap:18px; justify-content:center;}
  .hero-art span{
    aspect-ratio:1; border:2px solid rgba(255,255,255,.85); border-radius:16px;
    display:flex; align-items:center; justify-content:center; color:#fff;
  }
  .hero-art span:nth-child(2n){border-color:var(--accent); color:var(--accent);}
  .hero-art svg{width:42px; height:42px; stroke-width:1.6;}
  @media (max-width:860px){
    .hero-card{grid-template-columns:1fr; padding:44px 26px 28px; gap:30px; min-height:0;}
    .hero-art{grid-template-columns:repeat(6, minmax(0,1fr)); gap:8px; justify-content:stretch;}
    .hero-art span{border-radius:10px; border-width:1.5px;}
    .hero-art svg{width:24px; height:24px;}
    .hero-sticker{top:-10px; right:-6px; font-size:.92rem; padding:6px 10px;}
  }

  /* undersidornas hero, samma rundade ruta */
  .page-hero{padding-block:24px 0; color:#fff;}
  .page-hero > .wrap{
    width:calc(100% - 48px); max-width:1052px;
    background:var(--hero); border-radius:var(--radius); padding:50px 58px;
  }
  .page-hero h1{font-size:clamp(2rem, 4.4vw, 2.75rem); margin-top:10px;}
  .page-hero p{margin-top:14px; font-size:1.05rem; line-height:1.6; color:var(--hero-soft); max-width:58ch;}
  @media (max-width:640px){
    .page-hero > .wrap{width:calc(100% - 36px); padding:34px 24px;}
  }

  /* gamla statistikremsan, används inte på startsidan längre */
  .stat-strip{margin-top:44px; background:var(--hero-deep);}
  .stat-inner{max-width:1100px; margin-inline:auto; display:grid; grid-template-columns:repeat(3,1fr);}
  .stat{padding:20px 24px; border-left:1px solid rgba(255,255,255,.18);}
  .stat:first-child{border-left:none;}
  @media (max-width:640px){
    .stat-inner{grid-template-columns:1fr;}
    .stat{border-left:none; border-top:1px solid rgba(255,255,255,.18);}
    .stat:first-child{border-top:none;}
  }
  .stat b{display:block; font-weight:800; font-size:1.6rem; color:#fff;}
  .stat span{font-size:.82rem; color:var(--hero-soft);}

  /* sektioner */
  section{padding-block:64px;}
  section.alt{background:var(--bg-alt);}
  @media (max-width:640px){ section{padding-block:48px;} }
  .section-head{max-width:62ch; margin-bottom:32px;}
  .section-head.center{text-align:center; margin-inline:auto;}
  .section-head h2{font-size:clamp(1.6rem,3vw,2rem); margin-top:8px;}
  .section-head p{margin-top:12px; color:var(--ink-soft); font-size:1rem; line-height:1.6;}

  /* startsidan: kategorirad */
  .kat-head{display:flex; justify-content:space-between; align-items:baseline; gap:12px 24px; flex-wrap:wrap; margin-bottom:14px;}
  .kat-head h2{font-size:1.3rem;}
  .kat-rad{display:grid; grid-template-columns:repeat(10, minmax(0,1fr)); gap:4px;}
  .kat{
    display:flex; flex-direction:column; align-items:center; gap:10px;
    padding:16px 6px; border-radius:var(--radius); text-decoration:none; color:var(--ink);
    text-align:center; font-weight:700; font-size:.9rem; line-height:1.25;
  }
  .kat:hover{background:var(--bg-alt);}
  .kat svg{width:30px; height:30px; stroke-width:1.6; color:var(--ink);}
  @media (max-width:1000px){
    .kat-rad{display:flex; gap:0; overflow-x:auto; scroll-snap-type:x mandatory; margin-inline:-18px; padding-inline:12px; scrollbar-width:thin;}
    .kat{flex:0 0 104px; scroll-snap-align:start;}
  }

  /* startsidan: stort citat */
  .statement{max-width:820px; margin-inline:auto; text-align:center;}
  .statement p{font-size:clamp(1.3rem, 2.4vw, 1.75rem); font-weight:700; line-height:1.55; letter-spacing:-.005em;}

  /* startsidan: två kort bredvid varandra */
  .duo{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
  .duo-kort{border-radius:var(--radius); padding:50px; min-height:420px; display:flex; flex-direction:column;}
  .duo-kort.ljus{background:var(--bg-alt);}
  .duo-kort.bla{background:var(--hero); color:#fff;}
  .duo-kort.ljus h2{font-size:clamp(2rem, 4.2vw, 3.2rem); line-height:1.15; margin-top:12px;}
  .duo-kort.ljus p{margin-top:20px; color:var(--ink-soft); line-height:1.7; font-size:1rem;}
  .duo-kort.bla h3{font-size:clamp(1.4rem, 2.4vw, 1.75rem); line-height:1.35;}
  .duo-kort.bla p{margin-top:22px; color:var(--hero-soft); line-height:1.7; font-size:.98rem;}
  mark.hl{background:var(--accent); color:var(--accent-ink); padding:0 .22em; -webkit-box-decoration-break:clone; box-decoration-break:clone;}
  .pil-lankar{margin-top:auto; padding-top:28px; display:flex; gap:12px 30px; flex-wrap:wrap;}
  .pil{display:inline-flex; align-items:center; gap:8px; font-weight:700; text-decoration:none; color:var(--accent);}
  .pil svg{width:17px; height:17px; transition:transform .15s ease;}
  .pil:hover svg{transform:translateX(3px);}
  .pil.mork{color:var(--brand);}
  @media (max-width:820px){
    .duo{grid-template-columns:1fr;}
    .duo-kort{padding:30px 26px; min-height:0;}
  }

  /* startsidan och sortiment: blå helbredd uppmaning */
  .cta-full{background:var(--hero); color:#fff; padding-block:64px;}
  .cta-full h2{font-size:clamp(2.4rem, 6vw, 4rem); line-height:1.1;}
  .cta-full p{margin-top:18px; font-size:1.1rem; line-height:1.6; color:var(--hero-soft); max-width:56ch;}
  .cta-full .hero-actions{margin-top:30px;}
  .cta-inner{max-width:760px;}

  /* startsidan: kort med bildyta överst */
  .kort-grid{display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:14px;}
  @media (max-width:860px){ .kort-grid{grid-template-columns:1fr; gap:28px;} .kort-bild{height:150px;} }
  .kort{display:flex; flex-direction:column;}
  .kort-bild{height:190px; border-radius:var(--radius); display:flex; align-items:center; justify-content:center; background:var(--brand-soft); color:var(--brand);}
  .kort-bild.bla{background:var(--hero); color:#fff;}
  .kort-bild.gul{background:var(--accent); color:var(--accent-ink);}
  .kort-bild svg{width:64px; height:64px; stroke-width:1.5;}
  .kort-meta{margin-top:14px; font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft);}
  .kort h3{margin-top:10px; font-size:1.05rem; text-transform:uppercase; letter-spacing:.02em;}
  .kort p{margin-top:10px; font-size:.95rem; color:var(--ink-soft); line-height:1.6;}

  .about{display:grid; grid-template-columns:1.2fr .8fr; gap:36px; align-items:center;}
  @media (max-width:800px){ .about{grid-template-columns:1fr;} }
  .about h2{margin-top:10px; font-size:clamp(1.6rem,3vw,2rem);}
  .about p{color:var(--ink-soft); font-size:1.02rem; line-height:1.7; margin-top:14px;}
  .badge-stack{display:flex; flex-direction:column; gap:14px;}
  .badge-card{background:var(--brand-soft); border-radius:var(--radius); padding:26px; display:flex; flex-direction:column; gap:6px;}
  .badge-card b{font-weight:800; font-size:1.5rem; color:var(--brand-deep);}
  .badge-card span{font-size:.9rem; color:var(--ink-soft);}

  /* sortiment */
  .sortiment-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(210px,1fr)); gap:14px;}
  .produkt-card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:22px; display:flex; flex-direction:column; gap:10px; scroll-margin-top:140px;}
  .produkt-card:target{border-color:var(--brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 20%, transparent);}
  .produkt-icon{width:44px; height:44px; border-radius:12px; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center;}
  .produkt-icon svg{width:22px; height:22px;}
  .produkt-card h3{font-weight:700; font-size:1.05rem; line-height:1.3;}
  .produkt-card p{font-size:.92rem; color:var(--ink-soft); line-height:1.55;}

  .icon-row{display:flex; flex-wrap:wrap; gap:10px;}
  .icon-pill{
    display:inline-flex; align-items:center; gap:8px;
    background:var(--surface); border:1px solid var(--border-strong); border-radius:999px;
    padding:8px 16px 8px 8px; font-size:.9rem; font-weight:700;
  }
  .icon-pill span.i{width:26px; height:26px; border-radius:50%; background:var(--brand-soft); color:var(--brand); display:flex; align-items:center; justify-content:center;}
  .icon-pill svg{width:14px; height:14px;}
  .link-arrow{display:inline-flex; align-items:center; gap:6px; font-weight:700; color:var(--brand); text-decoration:none;}
  .link-arrow svg{width:17px; height:17px;}

  /* kunder (äldre kortstil) */
  .kund-grid{display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:16px;}
  @media (max-width:860px){ .kund-grid{grid-template-columns:1fr;} }
  .kund-card{background:var(--bg-alt); border-radius:var(--radius); padding:28px; display:flex; flex-direction:column; gap:12px;}
  section.alt .kund-card{background:var(--surface);}
  .kund-icon{width:44px; height:44px; border-radius:12px; background:var(--fill); color:var(--fill-ink); display:flex; align-items:center; justify-content:center;}
  .kund-icon svg{width:22px; height:22px;}
  .kund-card h3{font-size:1.2rem;}
  .kund-card p{color:var(--ink-soft); font-size:.95rem; line-height:1.6;}

  /* uppmaning i blå ruta */
  .cta-band{
    background:var(--hero); color:#fff; border-radius:var(--radius); padding:48px 50px;
    display:flex; justify-content:space-between; align-items:center; gap:24px; flex-wrap:wrap;
  }
  @media (max-width:640px){ .cta-band{padding:32px 24px;} }
  .cta-band h2{font-size:clamp(1.7rem,3.4vw,2.3rem);}
  .cta-band p{margin-top:10px; color:var(--hero-soft); max-width:46ch; line-height:1.6;}
  .cta-band .actions{display:flex; gap:12px; flex-wrap:wrap;}

  /* leverans */
  .leverans{
    background:var(--panel); color:#EEF1FA; border-radius:var(--radius); padding:50px;
    display:grid; grid-template-columns:1.1fr .9fr; gap:32px; align-items:center;
  }
  @media (max-width:800px){ .leverans{grid-template-columns:1fr; padding:30px 24px;} }
  .leverans .eyebrow{color:var(--accent);}
  .leverans h2{color:#fff; font-size:clamp(1.6rem,3vw,2.1rem); margin-top:10px;}
  .leverans p{color:var(--hero-soft); margin-top:14px; font-size:.98rem; line-height:1.65;}
  .leverans-days{display:flex; gap:8px; margin-top:22px; flex-wrap:wrap;}
  .day-chip{font-size:.8rem; font-weight:700; padding:8px 13px; border-radius:999px; border:1px solid rgba(255,255,255,.25); color:#C3CBEA;}
  .day-chip.on{background:var(--accent); border-color:var(--accent); color:var(--accent-ink);}
  .leverans-area{background:var(--panel-2); border-radius:var(--radius); padding:26px;}
  .leverans-area b{font-weight:700; font-size:1.25rem; color:#fff; display:block;}
  .leverans-area span{font-size:.92rem; color:var(--hero-soft); display:block; margin-top:8px; line-height:1.6;}

  /* kontakt */
  .kontakt-grid{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
  @media (max-width:760px){ .kontakt-grid{grid-template-columns:1fr;} }
  .kontakt-card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:32px;}
  @media (max-width:640px){ .kontakt-card{padding:26px 22px;} }
  .kontakt-card h3, .form-card h3{
    font-size:.78rem; font-weight:700;
    text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin-bottom:16px;
  }
  .kontakt-row{display:flex; align-items:flex-start; gap:12px; margin-top:14px;}
  .kontakt-row:first-of-type{margin-top:0;}
  .kontakt-row svg{width:20px; height:20px; color:var(--brand); flex:none; margin-top:1px;}
  .kontakt-row a, .kontakt-row div{font-size:1rem; line-height:1.5; text-decoration:none;}
  .kontakt-row a:hover{color:var(--brand);}
  .maps-link{display:inline-flex; align-items:center; gap:6px; margin-top:20px; font-size:.92rem; color:var(--brand); text-decoration:none; font-weight:700;}
  .maps-link svg{width:15px; height:15px;}
  .cta-card{background:var(--hero); color:#fff; border-radius:var(--radius); padding:32px; display:flex; flex-direction:column; justify-content:center; gap:12px;}
  @media (max-width:640px){ .cta-card{padding:28px 22px;} }
  .cta-card h3{font-size:1.6rem;}
  .cta-card p{font-size:.96rem; color:var(--hero-soft); line-height:1.6;}
  .cta-card .btn{margin-top:6px; align-self:flex-start;}

  /* formulär */
  .form-layout{display:grid; grid-template-columns:1.5fr .9fr; gap:24px; align-items:start;}
  @media (max-width:860px){ .form-layout{grid-template-columns:1fr;} }
  .form-card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:32px;}
  @media (max-width:640px){ .form-card{padding:24px 20px;} }
  fieldset{border:none; margin:0; padding:0; min-width:0;}
  fieldset + fieldset{margin-top:26px; padding-top:24px; border-top:1px solid var(--border);}
  legend{font-weight:700; font-size:1.25rem; padding:0; margin-bottom:14px;}
  .field-grid{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
  @media (max-width:560px){ .field-grid{grid-template-columns:1fr;} }
  .field{display:flex; flex-direction:column; gap:6px; min-width:0;}
  .field.full{grid-column:1 / -1;}
  .field label{font-size:.88rem; font-weight:700;}
  .field label .req{color:var(--accent-text);}
  .field .hint{font-size:.82rem; color:var(--ink-soft);}
  .field .field-label{font-size:.88rem; font-weight:700;}
  .kundgrupp-val .check{align-items:flex-start; font-weight:400; font-size:.92rem;}
  .kundgrupp-val .check input{margin-top:2px;}
  .kundgrupp-val .check span{display:flex; flex-direction:column; gap:2px; line-height:1.3;}
  .kundgrupp-val small{font-size:.82rem; color:var(--ink-soft);}
  input[type=text], input[type=email], input[type=tel], input[type=password], input[type=search], input[type=number], input[type=date], input[type=time], select, textarea{
    font:inherit; font-size:.98rem; color:var(--ink);
    background:var(--bg); border:1px solid var(--border-strong); border-radius:10px;
    padding:11px 13px; width:100%;
  }
  input[type=search]{border-radius:999px; padding-inline:18px;}
  input:focus, select:focus, textarea:focus{outline:none; border-color:var(--brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 22%, transparent);}
  textarea{min-height:140px; resize:vertical; line-height:1.5;}
  .check-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:8px;}
  .check{
    display:flex; align-items:center; gap:10px;
    border:1px solid var(--border-strong); border-radius:10px; padding:11px 13px;
    background:var(--bg); cursor:pointer; font-size:.92rem;
  }
  .check input{width:17px; height:17px; accent-color:var(--brand); margin:0; flex:none;}
  .check:has(input:checked){border-color:var(--brand); background:var(--brand-soft);}
  .day-select{display:flex; flex-wrap:wrap; gap:8px;}
  .day-select label{
    position:relative;
    font-size:.88rem; font-weight:700;
    border:1px solid var(--border-strong); border-radius:999px; padding:9px 16px; cursor:pointer; background:var(--bg);
  }
  .day-select input{position:absolute; opacity:0; pointer-events:none;}
  .day-select label:has(input:checked){background:var(--fill); border-color:var(--fill); color:var(--fill-ink);}
  .day-select label:has(input:focus-visible){outline:2px solid var(--brand); outline-offset:2px;}
  .hp{position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;}
  .form-actions{margin-top:26px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;}
  .form-note{font-size:.86rem; color:var(--ink-soft); line-height:1.5;}
  .alert{border-radius:var(--radius); padding:14px 16px; margin-bottom:20px; font-size:.94rem; line-height:1.5;}
  .alert.error{background:color-mix(in srgb, var(--danger) 12%, transparent); border:1px solid color-mix(in srgb, var(--danger) 45%, transparent); color:var(--ink);}
  .side-card{background:var(--bg-alt); border-radius:var(--radius); padding:26px;}
  .side-card + .side-card{margin-top:14px;}
  .side-card h3{font-size:1.2rem;}
  .side-card p, .side-card li{font-size:.94rem; color:var(--ink-soft); line-height:1.6;}
  .side-card p{margin-top:8px;}
  .side-card ol{margin:10px 0 0; padding-left:20px;}
  .side-card li + li{margin-top:6px;}

  /* tack */
  .tack{max-width:620px; margin-inline:auto; text-align:center; padding-block:40px;}
  .tack-icon{width:64px; height:64px; border-radius:50%; background:var(--green-soft); color:var(--green); display:flex; align-items:center; justify-content:center; margin:0 auto 18px;}
  .tack-icon svg{width:30px; height:30px;}
  .tack h1{font-size:clamp(2rem,4.4vw,2.6rem);}
  .tack p{margin-top:14px; color:var(--ink-soft); font-size:1.02rem; line-height:1.65;}
  .tack .actions{margin-top:26px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;}

  /* konto och portal */
  .flash{margin-top:20px; background:var(--green-soft); color:var(--ink); border:1px solid color-mix(in srgb, var(--green) 40%, transparent); border-radius:var(--radius); padding:13px 16px; font-size:.95rem;}
  .wrap.narrow{max-width:540px;}
  .card-title{font-size:1.9rem;}
  .lead{margin-top:10px; color:var(--ink-soft); line-height:1.6;}
  .form-foot{margin-top:18px; font-size:.92rem; color:var(--ink-soft); line-height:1.7;}
  .form-foot a{color:var(--brand); font-weight:700; text-decoration:none;}
  .field .err{font-size:.82rem; color:var(--danger);}
  .check.inline{border:none; background:none; padding:0; align-items:flex-start; font-size:.92rem;}
  .check.inline:has(input:checked){background:none;}
  .check.inline a{color:var(--brand);}

  .portal-head{display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:24px;}
  .portal-head h1{font-size:clamp(1.9rem,4vw,2.5rem); margin-top:6px;}
  .portal-head p{margin-top:8px; color:var(--ink-soft); line-height:1.6;}
  .portal-actions{display:flex; gap:10px; flex-wrap:wrap;}
  .btn.small{min-height:36px; padding:7px 16px; font-size:.88rem;}
  .btn.ghost.small{padding:8px 17px;}
  .btn.danger{background:none; border-color:color-mix(in srgb, var(--danger) 50%, transparent); color:var(--danger);}
  .list-rubrik{font-size:1.5rem; margin:36px 0 14px;}

  .tabs{display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;}
  .tabs a{font-size:.88rem; font-weight:700; text-decoration:none; padding:8px 16px; border-radius:999px; border:1px solid var(--border-strong); background:var(--surface); color:var(--ink);}
  .tabs a:hover{border-color:var(--ink-soft);}
  .tabs a.on{background:var(--fill); border-color:var(--fill); color:var(--fill-ink);}
  .tabs a .count{opacity:.7; margin-left:4px; font-weight:600;}

  .table-wrap{position:relative; overflow-x:auto; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);}
  table.list{width:100%; border-collapse:collapse; font-size:.93rem; min-width:620px;}
  table.list th{text-align:left; font-weight:700; font-size:.74rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); padding:12px 14px; border-bottom:1px solid var(--border); background:var(--bg-alt);}
  table.list td{padding:13px 14px; border-bottom:1px solid var(--border); vertical-align:middle; font-variant-numeric:tabular-nums;}
  table.list tr:last-child td{border-bottom:none;}
  table.list td.muted{color:var(--ink-soft);}
  table.list td.actions{text-align:right;}
  table.list a.row-link{font-weight:700; color:var(--brand); text-decoration:none;}
  @media (max-width:680px){
    .table-wrap.stack{background:none; border:none; overflow:visible;}
    .stack table.list, .stack table.list tbody{display:block; min-width:0;}
    .stack table.list td > *{min-width:0; overflow-wrap:anywhere;}
    .stack table.list thead{display:none;}
    .stack table.list tr{display:block; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:6px 16px; margin-bottom:10px;}
    .stack table.list td{display:flex; justify-content:space-between; align-items:center; gap:14px; padding:9px 0; border-bottom:1px solid var(--border); text-align:right;}
    .stack table.list tr td:last-child{border-bottom:none;}
    .stack table.list td[data-label]::before{content:attr(data-label); font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-soft); text-align:left; flex:none;}
    .stack table.list td.actions{justify-content:flex-end;}
    .stack table.list td.actions:empty{display:none;}
  }
  .empty{background:var(--surface); border:1px dashed var(--border-strong); border-radius:var(--radius); padding:36px; text-align:center; color:var(--ink-soft);}
  .empty .btn{margin-top:16px;}

  .pill{display:inline-block; font-size:.74rem; font-weight:700; text-transform:uppercase; letter-spacing:.03em; padding:4px 10px; border-radius:999px; white-space:nowrap;}
  .pill-ny{background:var(--accent-soft); color:var(--accent-text);}
  .pill-bekraftad{background:var(--brand-soft); color:var(--brand-deep);}
  .pill-levererad{background:var(--green-soft); color:var(--green);}
  .pill-plockad{background:color-mix(in srgb, var(--green) 14%, transparent); color:var(--green);}
  .pill-fakturerad{background:color-mix(in srgb, var(--ink-soft) 16%, transparent); color:var(--ink);}
  .pill-avbruten{background:color-mix(in srgb, var(--ink-soft) 16%, transparent); color:var(--ink-soft);}
  .pill-grupp{background:var(--brand-soft); color:var(--brand-deep); margin-left:8px; vertical-align:1px;}
  .grupp-form{display:flex; flex-wrap:wrap; gap:8px 6px; align-items:center; justify-content:flex-start; margin:0; min-width:0;}
  .grupp-form .ser-rad{display:flex; flex-wrap:wrap; gap:4px 14px; flex-basis:100%; justify-content:inherit;}
  @media (max-width:680px){ .stack .grupp-form{justify-content:flex-end;} }
  .ser-val{display:inline-flex; align-items:center; gap:5px; font-size:.86rem; white-space:nowrap; cursor:pointer;}
  .ser-val input{width:16px; height:16px; margin:0; accent-color:var(--brand);}
  .ser-val.eget{text-decoration:underline dotted; text-underline-offset:3px;}
  .grupp-form select{width:auto; min-width:0; max-width:100%; padding:7px 8px; font-size:.88rem;}
  table.list td.actions .btn{white-space:nowrap;}
  .pill-off{background:color-mix(in srgb, var(--danger) 14%, transparent); color:var(--danger);}

  .detail-grid{display:grid; grid-template-columns:1.4fr .9fr; gap:18px; align-items:start;}
  @media (max-width:820px){ .detail-grid{grid-template-columns:1fr;} }
  .detail-grid > *{min-width:0;}
  .detail-card{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:26px;}
  @media (max-width:640px){ .detail-card{padding:22px 18px;} }
  .detail-card + .detail-card{margin-top:16px;}
  .detail-card h2{font-weight:700; font-size:.78rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); margin-bottom:14px; line-height:1.3;}
  dl.kv{display:grid; grid-template-columns:auto 1fr; gap:10px 18px; margin:0; font-size:.95rem;}
  dl.kv dt{color:var(--ink-soft);}
  dl.kv dd{margin:0; overflow-wrap:anywhere;}
  .order-text{white-space:pre-wrap; font-size:.96rem; line-height:1.6; background:var(--bg-alt); border-radius:10px; padding:14px; overflow-wrap:anywhere;}
  .status-form{display:flex; gap:8px; flex-wrap:wrap; align-items:center;}
  .status-form select{width:auto;}
  .back-link{display:inline-flex; align-items:center; gap:6px; color:var(--ink-soft); text-decoration:none; font-size:.92rem; font-weight:600; margin-bottom:14px;}
  .back-link:hover{color:var(--brand);}
  .stat-row{display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;}
  @media (max-width:720px){ .stat-row{grid-template-columns:1fr 1fr;} }
  .stat-tile{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:16px 18px;}
  .stat-tile b{display:block; font-weight:800; font-size:1.9rem; line-height:1.1; font-variant-numeric:tabular-nums;}
  .stat-tile span{font-size:.84rem; color:var(--ink-soft);}
  .stat-tile.warn b{color:var(--accent-text);}
  .search{display:flex; gap:8px; margin-bottom:16px; max-width:440px;}

  /* superklipp */
  .pill-utkast{background:color-mix(in srgb, var(--ink-soft) 16%, transparent); color:var(--ink-soft);}
  .pill-oppen{background:var(--green-soft); color:var(--green);}
  .pill-stangd{background:var(--brand-soft); color:var(--brand-deep);}
  .pill-topp{background:var(--accent); color:var(--accent-ink); font-size:.64rem; padding:3px 7px; margin-left:6px; vertical-align:2px;}
  .alert.info{background:var(--green-soft); border:1px solid color-mix(in srgb, var(--green) 40%, transparent); color:var(--ink);}
  .alert.warn{background:var(--accent-soft); border:1px solid color-mix(in srgb, var(--accent) 70%, transparent); color:var(--ink);}
  .alert ul{margin:6px 0 0; padding-left:20px;}

  .sk-deadline{display:inline-flex; flex-direction:column; gap:2px; background:var(--accent); color:var(--accent-ink); border-radius:10px; padding:11px 16px; font-weight:800; font-size:1.02rem; margin-top:22px;}
  .sk-deadline small{font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:color-mix(in srgb, var(--accent-ink) 75%, transparent); font-weight:700;}
  .sk-veckor{display:flex; gap:8px; flex-wrap:wrap; margin-top:18px;}
  .sk-veckor a{font-size:.86rem; font-weight:700; text-decoration:none; padding:8px 15px; border-radius:999px; border:1px solid rgba(255,255,255,.55); color:#fff;}
  .sk-veckor a.on{background:var(--accent); color:var(--accent-ink); border-color:var(--accent);}
  .sk-tools{display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between; margin-bottom:14px;}
  .sk-tools input[type=search]{max-width:320px;}
  .sk-tools .form-note{margin:0;}
  .sk-notis{display:flex; gap:16px; align-items:center; justify-content:space-between; flex-wrap:wrap; background:var(--brand-soft); border:1px solid color-mix(in srgb, var(--brand) 30%, transparent); border-radius:var(--radius); padding:18px 20px; margin-bottom:18px; line-height:1.55;}
  .sk-notis .actions{display:flex; gap:8px; flex-wrap:wrap;}

  .sk-table-wrap{background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden;}
  table.sk-table{width:100%; border-collapse:collapse; font-size:.95rem;}
  .sk-table th{text-align:left; font-weight:700; font-size:.74rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); padding:11px 14px; border-bottom:1px solid var(--border); background:var(--bg-alt);}
  .sk-table th.num{text-align:right;}
  .sk-table td{padding:9px 14px; border-bottom:1px solid var(--border); vertical-align:middle; font-variant-numeric:tabular-nums;}
  .sk-table tr:last-child td{border-bottom:none;}
  .sk-table tr.sk-kat td{background:var(--bg-alt); font-weight:800; font-size:1.05rem; padding:13px 14px 8px; color:var(--brand);}
  .sk-table td.muted{color:var(--ink-soft);}
  .sk-table td.pris{text-align:right; font-weight:700; white-space:nowrap;}
  .sk-table td.pris small{font-weight:500; color:var(--ink-soft);}
  .sk-table td.antal{width:100px; text-align:right;}
  .sk-table td.antal input{width:78px; text-align:center; padding:8px 6px;}
  .sk-table .sk-meta{display:none;}
  .sk-table .sk-sub{display:block; font-size:.82rem; color:var(--ink-soft); margin-top:2px;}
  .sk-scroll{position:relative; overflow-x:auto; margin-top:16px; font-size:.92rem;}
  .sk-scroll table.sk-table{font-size:inherit;}
  .sk-table tr.sk-rad.vald td{background:var(--brand-soft);}
  @media (max-width:680px){
    .sk-lista thead{display:none;}
    .sk-table tr.sk-rad{display:grid; grid-template-columns:1fr auto auto; align-items:center; gap:10px; padding:10px 14px; border-bottom:1px solid var(--border);}
    .sk-table tr.sk-rad td{padding:0; border:none;}
    .sk-table tr.sk-rad.vald, .sk-table tr.sk-rad.vald td{background:var(--brand-soft);}
    .sk-table tr.sk-rad td.ursprung, .sk-table tr.sk-rad td.forp{display:none;}
    .sk-table .sk-meta{display:block; font-size:.82rem; color:var(--ink-soft); margin-top:3px;}
    .sk-table td.antal{width:auto;}
    .sk-table td.antal input{width:62px;}
    .sk-order th, .sk-order td{padding-left:10px; padding-right:10px;}
    .sk-table tr.sk-kat, .sk-table tr.sk-kat td{display:block;}
  }
  .sk-table tr[hidden]{display:none !important;}
  .sk-bar{position:sticky; bottom:12px; z-index:20; margin-top:18px; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap; background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:12px 16px; box-shadow:var(--shadow);}
  .sk-bar b{font-weight:800; font-size:1.4rem; line-height:1;}
  .sk-bar span{color:var(--ink-soft); font-size:.92rem;}

  .sk-form-grid{display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:14px;}
  @media (max-width:720px){ .sk-form-grid{grid-template-columns:repeat(2, minmax(0,1fr));} }
  .sk-form-grid input{min-width:0; width:100%;}
  .sk-edit-wrap{position:relative; overflow-x:auto; border:1px solid var(--border); border-radius:var(--radius); margin-top:16px;}
  table.sk-edit{width:100%; border-collapse:collapse; min-width:880px;}
  .sk-edit th{text-align:left; font-weight:700; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); padding:9px 6px; border-bottom:1px solid var(--border); background:var(--bg-alt);}
  .sk-edit td{padding:4px 6px; border-bottom:1px solid var(--border);}
  .sk-edit tr:last-child td{border-bottom:none;}
  .sk-edit input[type=text]{padding:7px 8px; font-size:.9rem; border-radius:8px;}
  .sk-edit .c-kat{width:130px;} .sk-edit .c-urs{width:70px;} .sk-edit .c-vikt{width:100px;} .sk-edit .c-enh{width:70px;} .sk-edit .c-pris{width:96px;}
  .sk-edit .c-topp{width:64px; text-align:center;}
  .sk-edit .c-topp input{width:18px; height:18px; accent-color:var(--brand);}
  .sk-edit .c-x{width:40px; text-align:center;}
  .sk-edit .ta-bort{background:none; border:none; color:var(--ink-soft); cursor:pointer; font-size:1.2rem; line-height:1; padding:6px 8px; border-radius:8px;}
  .sk-edit .ta-bort:hover{color:var(--danger); background:color-mix(in srgb, var(--danger) 10%, transparent);}
  textarea.mejl{min-height:380px; font-size:.9rem;}
  details.sk-import summary{cursor:pointer; font-weight:700; color:var(--brand);}

  /* ljust/mörkt läge */
  .theme-switch{
    position:relative; flex:none; width:58px; height:32px; padding:0; cursor:pointer;
    border:1px solid var(--border-strong); border-radius:999px; background:var(--bg-alt);
  }
  .theme-switch .knob{
    position:absolute; top:3px; left:3px; width:24px; height:24px; border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    background:var(--accent); color:var(--accent-ink); box-shadow:0 1px 3px rgba(0,0,0,.25);
    transition:transform .2s ease, background-color .2s ease;
  }
  .theme-switch svg{width:14px; height:14px;}
  .theme-switch .i-moon{display:none;}
  .theme-switch[aria-checked="true"] .knob{transform:translateX(26px); background:var(--brand); color:var(--brand-ink);}
  .theme-switch[aria-checked="true"] .i-sun{display:none;}
  .theme-switch[aria-checked="true"] .i-moon{display:block;}
  .theme-switch:hover{border-color:var(--brand);}
  @media (prefers-reduced-motion: reduce){ .theme-switch .knob, .pil svg{transition:none;} }
  .menu-theme{display:flex; align-items:center; justify-content:space-between; gap:12px; padding:13px 0; font-size:1.05rem;}

  /* veckansklipp */
  .vk-sidor{display:flex; flex-direction:column; gap:18px; max-width:860px; margin-inline:auto;}
  .vk-sidor img{width:100%; height:auto; border-radius:var(--radius); box-shadow:var(--shadow); background:#fff;}
  .vk-pdf{display:block; width:100%; max-width:860px; height:80vh; margin-inline:auto; border:1px solid var(--border); border-radius:var(--radius);}
  .vk-tumnaglar{display:flex; gap:10px; flex-wrap:wrap; margin-top:12px;}
  .vk-tumnaglar img{width:96px; height:auto; border-radius:6px; border:1px solid var(--border); background:#fff; display:block;}
  .vk-status{font-size:.9rem; color:var(--ink-soft); margin-top:8px; line-height:1.5;}
  .vk-status:empty{display:none;}
  .vk-status.fel{color:var(--danger);}
  textarea.sms{min-height:120px; font-size:.95rem;}
  .vk-bestall{max-width:960px; margin:40px auto 0; scroll-margin-top:130px;}
  .vk-bestall-rubrik{font-size:clamp(1.6rem,3vw,2rem); margin-bottom:16px;}
  .sk-table .vk-info{color:var(--ink-soft); font-size:.88rem;}
  .vk-rubrik{font-weight:700; font-size:1.2rem; margin:22px 0 6px;}
  table.vk-varor{min-width:760px;}
  .vk-varor .c-forp{width:190px;} .vk-varor .c-info{width:200px;} .vk-varor .c-pris{width:90px;} .vk-varor .c-enh{width:78px;}
  .sk-edit input.ogiltig{border-color:var(--danger); box-shadow:0 0 0 2px color-mix(in srgb, var(--danger) 25%, transparent);}
  .detail-grid:has(#vkVarorDel:not([hidden])){grid-template-columns:1fr;}
  input[type=file]{font:inherit; font-size:.92rem; color:var(--ink-soft); max-width:100%;}
  input[type=file]::file-selector-button{font:inherit; font-weight:700; font-size:.9rem; color:var(--ink); background:var(--surface); border:1px solid var(--border-strong); border-radius:999px; padding:9px 16px; margin-right:12px; cursor:pointer;}
  input[type=file]::file-selector-button:hover{border-color:var(--brand); color:var(--brand);}
  .detail-grid > .detail-card + .detail-card{margin-top:0;}
  .page-hero .hero-actions{margin-top:22px;}

  .sr-only{position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap;}
  .prose{max-width:68ch;}
  .prose h2{font-size:1.45rem; margin-top:36px;}
  .prose h2:first-child{margin-top:0;}
  .prose p, .prose li{color:var(--ink-soft); line-height:1.7; font-size:1rem;}
  .prose p{margin-top:10px;}
  .prose ul{margin:10px 0 0; padding-left:20px;}
  .prose li + li{margin-top:6px;}
  .prose a{color:var(--brand);}

  /* sidfot */
  footer.site-footer{background:var(--footer-bg); color:var(--footer-ink); border-top:1px solid var(--footer-line); padding-top:48px;}
  .footer-info{display:grid; grid-template-columns:1fr 1fr; gap:32px 48px; padding-bottom:42px; border-bottom:1px solid var(--footer-line);}
  @media (max-width:760px){ .footer-info{grid-template-columns:1fr;} }
  .info-ikon{width:38px; height:38px; border-radius:50%; background:var(--accent); color:var(--accent-ink); display:flex; align-items:center; justify-content:center;}
  .info-ikon svg{width:19px; height:19px;}
  .info-blk h4{margin-top:18px; font-size:1rem;}
  .info-blk p{margin-top:12px; font-size:.95rem; line-height:1.65; color:var(--footer-soft); max-width:56ch;}
  .info-blk .pil-lankar{padding-top:18px;}
  .footer-grid{display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr; gap:28px; padding-block:42px;}
  @media (max-width:820px){ .footer-grid{grid-template-columns:1fr 1fr;} }
  @media (max-width:480px){ .footer-grid{grid-template-columns:1fr;} }
  .footer-grid h4{font-size:1rem; margin:0 0 12px;}
  .footer-grid ul{list-style:none; margin:0; padding:0;}
  .footer-grid li{font-size:.95rem; line-height:1.9; color:var(--footer-soft);}
  .footer-grid a{text-decoration:none; color:var(--footer-ink);}
  .footer-grid a:hover{text-decoration:underline;}
  .footer-grid .logo-chip{display:inline-flex; margin-bottom:16px;}
  .footer-grid .logo-chip img{height:38px;}
  .footer-tag{font-size:.95rem; color:var(--footer-soft); line-height:1.65; max-width:32ch;}
  .footer-bottom{padding-block:22px 28px; border-top:1px solid var(--footer-line); font-size:.86rem; color:var(--footer-soft); line-height:1.7;}
  .footer-bottom b{color:var(--footer-ink); text-transform:uppercase; letter-spacing:.02em;}
<?php if ($adminSida !== null) readfile(__DIR__ . '/admin.css'); ?>
</style>
</head>
<body<?php echo $adminSida !== null ? ' class="adm-body"' : ''; ?>>
<?php if ($adminSida !== null): require __DIR__ . '/admin-meny.php'; else: ?>

<div class="topbar">
  <div class="wrap">
    <span class="tb-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/></svg>Mån&ndash;fre 06:00&ndash;14:30</span>
    <span class="tb-item tb-adress"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s7-6.1 7-11.5A7 7 0 0 0 5 9.5C5 14.9 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.4"/></svg>Fläderstigen 2, Ludvika</span>
    <a class="tb-item" href="tel:+4624018355"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 3a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.2-1.4a2 2 0 0 1 2.1-.4c1 .4 2 .7 3 .8a2 2 0 0 1 1.7 2Z"/></svg>0240-183 55</a>
  </div>
</div>

<header class="nav">
  <div class="nav-inner">
    <a class="logo-chip" href="index.php" aria-label="Ludvika Partiaffär – till startsidan"><img src="<?php echo $logoSrc; ?>" alt="Ludvika Partiaffär"></a>
    <div class="nav-actions">
      <button type="button" class="theme-switch nav-theme" data-tema role="switch" aria-checked="false" aria-label="Mörkt läge" title="Byt till mörkt läge"><span class="knob" aria-hidden="true"><svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg></span></button>
      <a class="nav-text<?php echo $kontoAktiv ? ' active' : ''; ?>" href="<?php echo $kontoHref; ?>"><?php echo $kontoNamn; ?></a>
      <?php if ($navUser): ?>
        <a class="nav-text" href="logga-ut.php">Logga ut</a>
      <?php else: ?>
        <a class="nav-text<?php echo $skapaAktiv ? ' active' : ''; ?>" href="registrera.php">Skapa konto</a>
      <?php endif; ?>
      <a class="icon-btn account-btn<?php echo $kontoAktiv ? ' active' : ''; ?>" href="<?php echo $kontoHref; ?>" aria-label="<?php echo $kontoNamn; ?>" title="<?php echo $kontoNamn; ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
      </a>
      <a class="icon-btn call-btn" href="tel:+4624018355" aria-label="Ring 0240-183 55" title="Ring 0240-183 55">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 3a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.2-1.4a2 2 0 0 1 2.1-.4c1 .4 2 .7 3 .8a2 2 0 0 1 1.7 2Z"/></svg>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Öppna meny" aria-expanded="false" aria-controls="mobileMenu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
  <nav class="nav-row" aria-label="Huvudmeny">
    <ul class="nav-links"><?php echo $navItems; ?></ul>
  </nav>
  <nav class="mobile-menu" id="mobileMenu" aria-label="Mobilmeny">
    <ul>
      <?php echo $navItems; ?>
      <?php if ($navUser): ?>
        <li><a href="<?php echo $kontoHref; ?>"<?php echo $kontoAktiv ? ' class="active"' : ''; ?>><?php echo $kontoNamn; ?></a></li>
        <li><a href="logga-ut.php">Logga ut</a></li>
      <?php else: ?>
        <li><a href="logga-in.php"<?php echo $kontoAktiv ? ' class="active"' : ''; ?>>Logga in</a></li>
        <li><a href="registrera.php"<?php echo $skapaAktiv ? ' class="active"' : ''; ?>>Skapa konto</a></li>
      <?php endif; ?>
      <li class="menu-theme"><span>Mörkt läge</span><button type="button" class="theme-switch" data-tema role="switch" aria-checked="false" aria-label="Mörkt läge" title="Byt till mörkt läge"><span class="knob" aria-hidden="true"><svg class="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg><svg class="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/></svg></span></button></li>
    </ul>
  </nav>
</header>

<main>
<?php if ($flashMsg = flash()): ?>
  <div class="wrap"><div class="flash" role="status"><?php echo e($flashMsg); ?></div></div>
<?php endif; ?>
<?php endif; ?>
