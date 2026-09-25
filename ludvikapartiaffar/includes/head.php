<?php
$logoSrc = 'img/logo.png';
$active = $active ?? '';
$pageTitle = $pageTitle ?? 'Ludvika Partiaffär';
$navUser = current_user();
$navItems = '';
foreach (['hem' => ['index.php', 'Hem'], 'sortiment' => ['sortiment.php', 'Sortiment'], 'bestall' => ['bestall.php', 'Beställ'], 'kontakt' => ['kontakt.php', 'Kontakt']] as $key => [$href, $label]) {
  $navItems .= '<li><a href="' . $href . '"' . ($key === $active ? ' class="active"' : '') . '>' . $label . '</a></li>';
}
?><!DOCTYPE html>
<html lang="sv">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="referrer" content="same-origin">
<title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
<meta name="description" content="Ludvika Partiaffär – grossist inom frukt, grönt och storköksvaror för restauranger och pizzerior i hela Dalarna sedan 60-talet.">
<style>
  :root{
    --bg:#FAF7F0;
    --bg-alt:#F1EADB;
    --surface:#FFFFFF;
    --ink:#0E1030;
    --ink-soft:#54536E;
    --brand:#1414F0;
    --brand-deep:#0000AD;
    --brand-ink:#FFFFFF;
    --brand-soft:#E4E3FF;
    --green:#2E8B46;
    --green-soft:#E3F3E6;
    --orange:#E1611F;
    --border:#E3DBC7;
    --danger:#C62828;
    --shadow: 0 1px 2px rgba(14,16,48,.05), 0 10px 28px -14px rgba(14,16,48,.22);
  }
  @media (prefers-color-scheme: dark){
    :root:not([data-theme="light"]){
      --bg:#0B0C22;
      --bg-alt:#12142E;
      --surface:#171939;
      --ink:#F2F1FA;
      --ink-soft:#B4B3CE;
      --brand:#7981FF;
      --brand-deep:#B4B9FF;
      --brand-ink:#0B0C22;
      --brand-soft:#242552;
      --green:#5FC77E;
      --green-soft:#173322;
      --orange:#F0925F;
      --border:#2A2C51;
      --danger:#FF8A80;
      --shadow: 0 1px 2px rgba(0,0,0,.35), 0 14px 34px -16px rgba(0,0,0,.6);
    }
  }
  :root[data-theme="dark"]{
    --bg:#0B0C22;
    --bg-alt:#12142E;
    --surface:#171939;
    --ink:#F2F1FA;
    --ink-soft:#B4B3CE;
    --brand:#7981FF;
    --brand-deep:#B4B9FF;
    --brand-ink:#0B0C22;
    --brand-soft:#242552;
    --green:#5FC77E;
    --green-soft:#173322;
    --orange:#F0925F;
    --border:#2A2C51;
    --danger:#FF8A80;
    --shadow: 0 1px 2px rgba(0,0,0,.35), 0 14px 34px -16px rgba(0,0,0,.6);
  }

  *{box-sizing:border-box;}
  html,body{margin:0;}
  body{
    background:var(--bg);
    color:var(--ink);
    font-family:'Work Sans', system-ui, sans-serif;
    font-size:15px;
    -webkit-font-smoothing:antialiased;
  }
  h1,h2,h3{
    font-family:'Bebas Neue', 'Arial Narrow', sans-serif;
    font-weight:400;
    letter-spacing:.01em;
    text-wrap:balance;
    margin:0;
    line-height:.95;
  }
  p{margin:0;}
  a{color:inherit;}
  img{max-width:100%; display:block;}
  ::selection{background:var(--brand); color:var(--brand-ink);}
  :focus-visible{outline:2px solid var(--brand); outline-offset:2px;}

  .wrap{max-width:1120px; margin-inline:auto; padding-inline:24px;}

  .eyebrow{
    display:inline-flex; align-items:center; gap:8px;
    font-family:'IBM Plex Mono', monospace;
    font-size:.76rem; text-transform:uppercase; letter-spacing:.1em;
    color:var(--green);
  }
  .eyebrow::before{content:""; width:7px; height:7px; border-radius:2px; background:var(--orange); transform:rotate(45deg);}

  /* top bar */
  .topbar{
    background:var(--bg-alt); border-bottom:1px solid var(--border);
    font-family:'IBM Plex Mono', monospace; font-size:.76rem; color:var(--ink-soft);
  }
  .topbar .wrap{padding-block:7px; display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;}
  .topbar a{text-decoration:none;}
  .topbar a:hover{color:var(--brand);}

  /* nav */
  header.nav{
    position:sticky; top:0; z-index:30;
    background:color-mix(in srgb, var(--surface) 92%, transparent);
    backdrop-filter:blur(10px);
    border-bottom:1px solid var(--border);
  }
  .nav-inner{
    max-width:1120px; margin-inline:auto; padding:12px 24px;
    display:flex; align-items:center; justify-content:space-between; gap:20px;
  }
  .logo-chip{
    background:#fff; border-radius:8px; padding:6px 10px;
    display:flex; align-items:center; box-shadow:var(--shadow); flex:none;
    text-decoration:none;
  }
  .logo-chip img{height:34px; width:auto;}
  .nav-links{display:flex; gap:26px; font-size:.94rem; color:var(--ink-soft); list-style:none; margin:0; padding:0;}
  .nav-links a{text-decoration:none; padding-block:4px; border-bottom:2px solid transparent;}
  .nav-links a:hover{color:var(--brand);}
  .nav-links a.active{color:var(--ink); font-weight:600; border-bottom-color:var(--brand);}
  .call-btn{
    display:inline-flex; align-items:center; gap:8px;
    background:var(--brand); color:var(--brand-ink);
    font-weight:600; font-size:.88rem;
    padding:10px 16px; border-radius:999px; text-decoration:none; white-space:nowrap;
  }
  .call-btn svg{width:15px; height:15px;}

  .menu-toggle{
    display:none; background:none; border:1px solid var(--border); border-radius:8px;
    padding:8px; color:var(--ink); cursor:pointer;
  }
  .menu-toggle svg{width:20px; height:20px; display:block;}
  .mobile-menu{display:none; border-top:1px solid var(--border); background:var(--surface);}
  .mobile-menu ul{list-style:none; margin:0; padding:8px 24px 16px;}
  .mobile-menu a{display:block; padding:12px 0; text-decoration:none; font-size:1rem; border-bottom:1px solid var(--border);}
  .mobile-menu li:last-child a{border-bottom:none;}
  .mobile-menu a.active{color:var(--brand); font-weight:600;}
  @media (max-width:820px){
    .nav-links{display:none;}
    .menu-toggle{display:block;}
    .mobile-menu.open{display:block;}
    .call-btn .call-label{display:none;}
    .call-btn{padding:10px 12px;}
  }

  /* buttons */
  .btn{
    font-family:'Work Sans', sans-serif; font-weight:600; font-size:.94rem;
    padding:13px 24px; border-radius:999px; text-decoration:none;
    display:inline-flex; align-items:center; gap:8px;
    border:1.5px solid transparent; cursor:pointer;
  }
  .btn.light{background:#fff; color:#0000AD;}
  .btn.outline{border-color:rgba(255,255,255,.55); color:#fff;}
  .btn.primary{background:var(--brand); color:var(--brand-ink);}
  .btn.ghost{border-color:var(--border); color:var(--ink); background:var(--surface);}
  .btn svg{width:16px; height:16px;}

  /* hero */
  .hero{background:#1414F0; color:#fff; padding-block:64px 0;}
  .hero-inner{max-width:1120px; margin-inline:auto; padding-inline:24px; display:grid; gap:16px;}
  .hero .eyebrow{color:#CFE8FF;}
  h1.headline{font-size:clamp(2.6rem, 7vw, 5rem); max-width:14ch; text-transform:uppercase;}
  .hero-sub{margin-top:14px; font-size:1.1rem; line-height:1.6; color:#E4E6FF; max-width:52ch;}
  .hero-actions{margin-top:26px; display:flex; gap:12px; flex-wrap:wrap;}

  .page-hero{background:#1414F0; color:#fff; padding-block:52px 48px;}
  .page-hero .eyebrow{color:#CFE8FF;}
  .page-hero h1{font-size:clamp(2.4rem, 6vw, 4rem); text-transform:uppercase; margin-top:10px;}
  .page-hero p{margin-top:14px; font-size:1.05rem; line-height:1.6; color:#E4E6FF; max-width:56ch;}

  .stat-strip{margin-top:44px; background:#0000AD;}
  .stat-inner{max-width:1120px; margin-inline:auto; display:grid; grid-template-columns:repeat(3,1fr);}
  .stat{padding:20px 24px; border-left:1px solid rgba(255,255,255,.18); font-family:'IBM Plex Mono', monospace;}
  .stat:first-child{border-left:none;}
  @media (max-width:640px){
    .stat-inner{grid-template-columns:1fr;}
    .stat{border-left:none; border-top:1px solid rgba(255,255,255,.18);}
    .stat:first-child{border-top:none;}
  }
  .stat b{display:block; font-family:'Bebas Neue', sans-serif; font-weight:400; font-size:1.9rem; color:#fff; letter-spacing:.02em;}
  .stat span{font-size:.78rem; color:#CFD1FF; text-transform:uppercase; letter-spacing:.06em;}

  /* sections */
  section{padding-block:60px;}
  section.alt{background:var(--bg-alt);}
  .section-head{max-width:60ch; margin-bottom:34px;}
  .section-head h2{font-size:clamp(1.8rem,4vw,2.6rem); text-transform:uppercase; margin-top:10px;}
  .section-head p{margin-top:14px; color:var(--ink-soft); font-size:1rem; line-height:1.6;}

  .about{display:grid; grid-template-columns:1.2fr .8fr; gap:36px; align-items:center;}
  @media (max-width:800px){ .about{grid-template-columns:1fr;} }
  .about h2{margin-top:10px; text-transform:uppercase; font-size:clamp(1.8rem,4vw,2.4rem);}
  .about p{color:var(--ink-soft); font-size:1.02rem; line-height:1.7; margin-top:14px;}
  .badge-stack{display:flex; flex-direction:column; gap:14px;}
  .badge-card{background:var(--brand-soft); border-radius:16px; padding:26px; display:flex; flex-direction:column; gap:6px;}
  .badge-card b{font-family:'Bebas Neue',sans-serif; font-weight:400; font-size:2.4rem; color:var(--brand-deep);}
  .badge-card span{font-size:.88rem; color:var(--ink-soft);}

  /* product grid */
  .sortiment-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(210px,1fr)); gap:14px;}
  .produkt-card{background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:20px; display:flex; flex-direction:column; gap:10px;}
  .produkt-icon{width:38px; height:38px; border-radius:10px; background:var(--green-soft); color:var(--green); display:flex; align-items:center; justify-content:center;}
  .produkt-icon svg{width:19px; height:19px;}
  .produkt-card h3{font-family:'Work Sans',sans-serif; font-weight:600; font-size:1.02rem; line-height:1.3;}
  .produkt-card p{font-size:.9rem; color:var(--ink-soft); line-height:1.55;}

  .icon-row{display:flex; flex-wrap:wrap; gap:10px;}
  .icon-pill{
    display:inline-flex; align-items:center; gap:8px;
    background:var(--surface); border:1px solid var(--border); border-radius:999px;
    padding:8px 14px 8px 8px; font-size:.9rem;
  }
  .icon-pill span.i{width:26px; height:26px; border-radius:50%; background:var(--green-soft); color:var(--green); display:flex; align-items:center; justify-content:center;}
  .icon-pill svg{width:14px; height:14px;}
  .link-arrow{display:inline-flex; align-items:center; gap:6px; margin-top:24px; font-weight:600; color:var(--brand); text-decoration:none;}
  .link-arrow svg{width:16px; height:16px;}

  /* customers */
  .kund-grid{display:grid; grid-template-columns:1fr 1fr; gap:16px;}
  @media (max-width:680px){ .kund-grid{grid-template-columns:1fr;} }
  .kund-card{background:var(--bg-alt); border-radius:16px; padding:28px; display:flex; flex-direction:column; gap:12px;}
  section.alt .kund-card{background:var(--surface);}
  .kund-icon{width:44px; height:44px; border-radius:12px; background:var(--brand); color:var(--brand-ink); display:flex; align-items:center; justify-content:center;}
  .kund-icon svg{width:22px; height:22px;}
  .kund-card h3{font-size:1.6rem; text-transform:uppercase;}
  .kund-card p{color:var(--ink-soft); font-size:.95rem; line-height:1.6;}

  /* cta band */
  .cta-band{
    background:#1414F0; color:#fff; border-radius:20px; padding:40px;
    display:flex; justify-content:space-between; align-items:center; gap:24px; flex-wrap:wrap;
  }
  .cta-band h2{font-size:clamp(1.8rem,4vw,2.6rem); text-transform:uppercase;}
  .cta-band p{margin-top:10px; color:#E4E6FF; max-width:46ch; line-height:1.6;}
  .cta-band .actions{display:flex; gap:12px; flex-wrap:wrap;}

  /* delivery */
  .leverans{
    background:#0E1030; color:#F2F1FA; border-radius:20px; padding:40px;
    display:grid; grid-template-columns:1.1fr .9fr; gap:32px; align-items:center;
  }
  @media (max-width:800px){ .leverans{grid-template-columns:1fr; padding:28px;} }
  .leverans .eyebrow{color:#9FA1D6;}
  .leverans h2{color:#fff; text-transform:uppercase; font-size:clamp(1.6rem,3.4vw,2.2rem); margin-top:10px;}
  .leverans p{color:#C9C9DA; margin-top:14px; font-size:.98rem; line-height:1.65;}
  .leverans-days{display:flex; gap:8px; margin-top:20px; flex-wrap:wrap;}
  .day-chip{font-family:'IBM Plex Mono',monospace; font-size:.76rem; padding:7px 12px; border-radius:999px; border:1px solid #33355A; color:#9FA1D6;}
  .day-chip.on{background:#E1611F; border-color:#E1611F; color:#1A0D05;}
  .leverans-area{background:#171939; border-radius:14px; padding:24px;}
  .leverans-area b{font-family:'Bebas Neue',sans-serif; font-weight:400; font-size:1.5rem; color:#fff; display:block; text-transform:uppercase;}
  .leverans-area span{font-size:.88rem; color:#AEB0DA; display:block; margin-top:8px; line-height:1.6;}

  /* contact */
  .kontakt-grid{display:grid; grid-template-columns:1fr 1fr; gap:18px;}
  @media (max-width:760px){ .kontakt-grid{grid-template-columns:1fr;} }
  .kontakt-card{background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:26px;}
  .kontakt-card h3, .form-card h3{
    font-family:'IBM Plex Mono',monospace; font-size:.74rem; font-weight:500;
    text-transform:uppercase; letter-spacing:.08em; color:var(--ink-soft); margin-bottom:14px;
  }
  .kontakt-row{display:flex; align-items:flex-start; gap:12px; margin-top:14px;}
  .kontakt-row:first-of-type{margin-top:0;}
  .kontakt-row svg{width:19px; height:19px; color:var(--brand); flex:none; margin-top:2px;}
  .kontakt-row a, .kontakt-row div{font-size:1rem; line-height:1.5; text-decoration:none;}
  .kontakt-row a:hover{color:var(--brand);}
  .maps-link{display:inline-flex; align-items:center; gap:6px; margin-top:18px; font-size:.88rem; color:var(--brand); text-decoration:none; font-weight:600;}
  .maps-link svg{width:14px; height:14px;}
  .cta-card{background:#1414F0; color:#fff; border-radius:16px; padding:26px; display:flex; flex-direction:column; justify-content:center; gap:12px;}
  .cta-card h3{font-size:1.8rem; text-transform:uppercase;}
  .cta-card p{font-size:.94rem; color:#E4E6FF; line-height:1.6;}
  .cta-card .btn{margin-top:6px; align-self:flex-start;}

  /* form */
  .form-layout{display:grid; grid-template-columns:1.5fr .9fr; gap:24px; align-items:start;}
  @media (max-width:860px){ .form-layout{grid-template-columns:1fr;} }
  .form-card{background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:28px;}
  fieldset{border:none; margin:0; padding:0;}
  fieldset + fieldset{margin-top:26px; padding-top:24px; border-top:1px solid var(--border);}
  legend{font-family:'Bebas Neue',sans-serif; font-size:1.5rem; text-transform:uppercase; padding:0; margin-bottom:14px;}
  .field-grid{display:grid; grid-template-columns:1fr 1fr; gap:14px;}
  @media (max-width:560px){ .field-grid{grid-template-columns:1fr;} }
  .field{display:flex; flex-direction:column; gap:6px;}
  .field.full{grid-column:1 / -1;}
  .field label{font-size:.86rem; font-weight:600;}
  .field label .req{color:var(--orange);}
  .field .hint{font-size:.8rem; color:var(--ink-soft);}
  input[type=text], input[type=email], input[type=tel], input[type=password], input[type=search], select, textarea{
    font:inherit; font-size:.96rem; color:var(--ink);
    background:var(--bg); border:1px solid var(--border); border-radius:10px;
    padding:11px 12px; width:100%;
  }
  input:focus, select:focus, textarea:focus{outline:none; border-color:var(--brand); box-shadow:0 0 0 3px color-mix(in srgb, var(--brand) 22%, transparent);}
  textarea{min-height:140px; resize:vertical; line-height:1.5;}
  .check-grid{display:grid; grid-template-columns:repeat(auto-fill, minmax(170px,1fr)); gap:8px;}
  .check{
    display:flex; align-items:center; gap:10px;
    border:1px solid var(--border); border-radius:10px; padding:10px 12px;
    background:var(--bg); cursor:pointer; font-size:.92rem;
  }
  .check input{width:17px; height:17px; accent-color:var(--brand); margin:0; flex:none;}
  .check:has(input:checked){border-color:var(--brand); background:var(--brand-soft);}
  .day-select{display:flex; flex-wrap:wrap; gap:8px;}
  .day-select label{
    font-family:'IBM Plex Mono',monospace; font-size:.8rem;
    border:1px solid var(--border); border-radius:999px; padding:8px 14px; cursor:pointer; background:var(--bg);
  }
  .day-select input{position:absolute; opacity:0; pointer-events:none;}
  .day-select label:has(input:checked){background:var(--brand); border-color:var(--brand); color:var(--brand-ink);}
  .day-select label:has(input:focus-visible){outline:2px solid var(--brand); outline-offset:2px;}
  .hp{position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;}
  .form-actions{margin-top:26px; display:flex; align-items:center; gap:16px; flex-wrap:wrap;}
  .form-note{font-size:.84rem; color:var(--ink-soft); line-height:1.5;}
  .alert{border-radius:12px; padding:14px 16px; margin-bottom:20px; font-size:.94rem; line-height:1.5;}
  .alert.error{background:color-mix(in srgb, var(--danger) 12%, transparent); border:1px solid color-mix(in srgb, var(--danger) 45%, transparent); color:var(--ink);}
  .side-card{background:var(--bg-alt); border-radius:16px; padding:24px;}
  .side-card + .side-card{margin-top:14px;}
  .side-card h3{font-size:1.5rem; text-transform:uppercase;}
  .side-card p, .side-card li{font-size:.92rem; color:var(--ink-soft); line-height:1.6;}
  .side-card p{margin-top:8px;}
  .side-card ol{margin:10px 0 0; padding-left:20px;}
  .side-card li + li{margin-top:6px;}

  /* thank you */
  .tack{max-width:620px; margin-inline:auto; text-align:center; padding-block:40px;}
  .tack-icon{width:64px; height:64px; border-radius:50%; background:var(--green-soft); color:var(--green); display:flex; align-items:center; justify-content:center; margin:0 auto 18px;}
  .tack-icon svg{width:30px; height:30px;}
  .tack h1{font-size:clamp(2.2rem,5vw,3.2rem); text-transform:uppercase;}
  .tack p{margin-top:14px; color:var(--ink-soft); font-size:1.02rem; line-height:1.65;}
  .tack .actions{margin-top:26px; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;}

  /* account & portal */
  .account-btn{
    display:inline-flex; align-items:center; gap:7px;
    border:1px solid var(--border); background:var(--surface); color:var(--ink);
    font-weight:600; font-size:.88rem; padding:9px 14px; border-radius:999px; text-decoration:none; white-space:nowrap;
  }
  .account-btn:hover, .account-btn.active{border-color:var(--brand); color:var(--brand);}
  .account-btn svg{width:15px; height:15px;}
  @media (max-width:820px){ .account-btn .account-label{display:none;} .account-btn{padding:9px 11px;} }

  .flash{margin-top:20px; background:var(--green-soft); color:var(--ink); border:1px solid color-mix(in srgb, var(--green) 40%, transparent); border-radius:12px; padding:13px 16px; font-size:.95rem;}
  .wrap.narrow{max-width:520px;}
  .card-title{font-size:2.2rem; text-transform:uppercase;}
  .lead{margin-top:10px; color:var(--ink-soft); line-height:1.6;}
  .form-foot{margin-top:18px; font-size:.9rem; color:var(--ink-soft); line-height:1.7;}
  .form-foot a{color:var(--brand); font-weight:600; text-decoration:none;}
  .field .err{font-size:.8rem; color:var(--danger);}
  .check.inline{border:none; background:none; padding:0; align-items:flex-start; font-size:.9rem;}
  .check.inline:has(input:checked){background:none;}
  .check.inline a{color:var(--brand);}

  .portal-head{display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:24px;}
  .portal-head h1{font-size:clamp(2rem,5vw,2.8rem); text-transform:uppercase;}
  .portal-head p{margin-top:8px; color:var(--ink-soft);}
  .portal-actions{display:flex; gap:10px; flex-wrap:wrap;}
  .btn.small{padding:8px 14px; font-size:.84rem;}
  .btn.danger{background:none; border-color:color-mix(in srgb, var(--danger) 50%, transparent); color:var(--danger);}

  .tabs{display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px;}
  .tabs a{font-family:'IBM Plex Mono',monospace; font-size:.8rem; text-decoration:none; padding:7px 13px; border-radius:999px; border:1px solid var(--border); background:var(--surface); color:var(--ink-soft);}
  .tabs a.on{background:var(--brand); border-color:var(--brand); color:var(--brand-ink);}
  .tabs a .count{opacity:.75; margin-left:4px;}

  .table-wrap{overflow-x:auto; background:var(--surface); border:1px solid var(--border); border-radius:14px;}
  table.list{width:100%; border-collapse:collapse; font-size:.92rem; min-width:620px;}
  table.list th{text-align:left; font-family:'IBM Plex Mono',monospace; font-weight:500; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink-soft); padding:12px 14px; border-bottom:1px solid var(--border); background:var(--bg-alt);}
  table.list td{padding:13px 14px; border-bottom:1px solid var(--border); vertical-align:middle; font-variant-numeric:tabular-nums;}
  table.list tr:last-child td{border-bottom:none;}
  table.list td.muted{color:var(--ink-soft);}
  table.list td.actions{text-align:right;}
  table.list a.row-link{font-weight:600; color:var(--brand); text-decoration:none;}
  @media (max-width:680px){
    .table-wrap.stack{background:none; border:none; overflow:visible;}
    .stack table.list{min-width:0;}
    .stack table.list thead{display:none;}
    .stack table.list tr{display:block; background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:6px 16px; margin-bottom:10px;}
    .stack table.list td{display:flex; justify-content:space-between; align-items:center; gap:14px; padding:9px 0; border-bottom:1px solid var(--border); text-align:right;}
    .stack table.list tr td:last-child{border-bottom:none;}
    .stack table.list td[data-label]::before{content:attr(data-label); font-family:'IBM Plex Mono',monospace; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); text-align:left; flex:none;}
    .stack table.list td.actions{justify-content:flex-end;}
    .stack table.list td.actions:empty{display:none;}
  }
  .empty{background:var(--surface); border:1px dashed var(--border); border-radius:14px; padding:36px; text-align:center; color:var(--ink-soft);}
  .empty .btn{margin-top:16px;}

  .pill{display:inline-block; font-family:'IBM Plex Mono',monospace; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.04em; padding:4px 9px; border-radius:999px; white-space:nowrap;}
  .pill-ny{background:color-mix(in srgb, var(--orange) 18%, transparent); color:var(--orange);}
  .pill-bekraftad{background:var(--brand-soft); color:var(--brand-deep);}
  .pill-levererad{background:var(--green-soft); color:var(--green);}
  .pill-avbruten{background:color-mix(in srgb, var(--ink-soft) 16%, transparent); color:var(--ink-soft);}
  .pill-off{background:color-mix(in srgb, var(--danger) 14%, transparent); color:var(--danger);}

  .detail-grid{display:grid; grid-template-columns:1.4fr .9fr; gap:18px; align-items:start;}
  @media (max-width:820px){ .detail-grid{grid-template-columns:1fr;} }
  .detail-card{background:var(--surface); border:1px solid var(--border); border-radius:16px; padding:24px;}
  .detail-card + .detail-card{margin-top:16px;}
  .detail-card h2{font-family:'IBM Plex Mono',monospace; font-weight:500; font-size:.74rem; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-soft); margin-bottom:14px; line-height:1.3;}
  dl.kv{display:grid; grid-template-columns:auto 1fr; gap:10px 18px; margin:0; font-size:.95rem;}
  dl.kv dt{color:var(--ink-soft);}
  dl.kv dd{margin:0; overflow-wrap:anywhere;}
  .order-text{white-space:pre-wrap; font-size:.96rem; line-height:1.6; background:var(--bg); border-radius:10px; padding:14px; overflow-wrap:anywhere;}
  .status-form{display:flex; gap:8px; flex-wrap:wrap; align-items:center;}
  .status-form select{width:auto;}
  .back-link{display:inline-flex; align-items:center; gap:6px; color:var(--ink-soft); text-decoration:none; font-size:.9rem; margin-bottom:14px;}
  .back-link:hover{color:var(--brand);}
  .stat-row{display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:24px;}
  @media (max-width:720px){ .stat-row{grid-template-columns:1fr 1fr;} }
  .stat-tile{background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:16px 18px;}
  .stat-tile b{display:block; font-family:'Bebas Neue',sans-serif; font-weight:400; font-size:2.2rem; line-height:1; font-variant-numeric:tabular-nums;}
  .stat-tile span{font-size:.8rem; color:var(--ink-soft);}
  .stat-tile.warn b{color:var(--orange);}
  .search{display:flex; gap:8px; margin-bottom:16px; max-width:420px;}

  .sr-only{position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap;}
  .prose{max-width:68ch;}
  .prose h2{font-size:1.7rem; text-transform:uppercase; margin-top:34px;}
  .prose h2:first-child{margin-top:0;}
  .prose p, .prose li{color:var(--ink-soft); line-height:1.7; font-size:1rem;}
  .prose p{margin-top:10px;}
  .prose ul{margin:10px 0 0; padding-left:20px;}
  .prose li + li{margin-top:6px;}
  .prose a{color:var(--brand);}

  /* footer */
  footer.site-footer{background:var(--bg-alt); border-top:1px solid var(--border); padding-block:40px;}
  .footer-grid{display:grid; grid-template-columns:1.3fr 1fr 1fr 1fr; gap:28px;}
  @media (max-width:820px){ .footer-grid{grid-template-columns:1fr 1fr;} }
  @media (max-width:480px){ .footer-grid{grid-template-columns:1fr;} }
  .footer-grid h4{font-family:'IBM Plex Mono',monospace; font-weight:500; font-size:.72rem; text-transform:uppercase; letter-spacing:.08em; color:var(--ink-soft); margin:0 0 12px;}
  .footer-grid ul{list-style:none; margin:0; padding:0;}
  .footer-grid li{font-size:.92rem; line-height:1.9;}
  .footer-grid a{text-decoration:none;}
  .footer-grid a:hover{color:var(--brand);}
  .footer-grid .logo-chip{display:inline-flex; margin-bottom:14px;}
  .footer-grid .logo-chip img{height:30px;}
  .footer-tag{font-size:.9rem; color:var(--ink-soft); line-height:1.6; max-width:30ch;}
  .footer-bottom{margin-top:30px; padding-top:18px; border-top:1px solid var(--border); font-family:'IBM Plex Mono',monospace; font-size:.74rem; color:var(--ink-soft);}
</style>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Work+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap">
</head>
<body>

<div class="topbar">
  <div class="wrap">
    <span>Mån&ndash;fre 06:00&ndash;14:30 &middot; Fläderstigen 2, Ludvika</span>
    <a href="tel:+4624018355">0240-183 55</a>
  </div>
</div>

<header class="nav">
  <div class="nav-inner">
    <a class="logo-chip" href="index.php" aria-label="Ludvika Partiaffär – till startsidan"><img src="<?php echo $logoSrc; ?>" alt="Ludvika Partiaffär"></a>
    <ul class="nav-links"><?php echo $navItems; ?></ul>
    <div style="display:flex; gap:10px; align-items:center;">
      <?php if ($navUser): ?>
        <a class="account-btn<?php echo in_array($active, ['konto', 'admin'], true) ? ' active' : ''; ?>" href="<?php echo $navUser['role'] === 'admin' ? 'admin.php' : 'konto.php'; ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
          <span class="account-label"><?php echo $navUser['role'] === 'admin' ? 'Admin' : 'Mitt konto'; ?></span>
        </a>
      <?php else: ?>
        <a class="account-btn<?php echo $active === 'login' ? ' active' : ''; ?>" href="logga-in.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/></svg>
          <span class="account-label">Logga in</span>
        </a>
      <?php endif; ?>
      <a class="call-btn" href="tel:+4624018355" aria-label="Ring 0240-183 55">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .8 3a2 2 0 0 1-.4 2.1L8.1 10a16 16 0 0 0 6 6l1.2-1.4a2 2 0 0 1 2.1-.4c1 .4 2 .7 3 .8a2 2 0 0 1 1.7 2Z"/></svg>
        <span class="call-label">0240-183 55</span>
      </a>
      <button class="menu-toggle" id="menuToggle" aria-label="Öppna meny" aria-expanded="false" aria-controls="mobileMenu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
      </button>
    </div>
  </div>
  <nav class="mobile-menu" id="mobileMenu" aria-label="Mobilmeny">
    <ul>
      <?php echo $navItems; ?>
      <?php if ($navUser): ?>
        <li><a href="<?php echo $navUser['role'] === 'admin' ? 'admin.php' : 'konto.php'; ?>"><?php echo $navUser['role'] === 'admin' ? 'Admin' : 'Mitt konto'; ?></a></li>
        <li><a href="logga-ut.php">Logga ut</a></li>
      <?php else: ?>
        <li><a href="logga-in.php">Logga in</a></li>
        <li><a href="registrera.php">Skapa konto</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<main>
<?php if ($flashMsg = flash()): ?>
  <div class="wrap"><div class="flash" role="status"><?php echo e($flashMsg); ?></div></div>
<?php endif; ?>
