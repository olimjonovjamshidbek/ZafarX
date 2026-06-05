<?php
/**
* index.php — Qo'l soat (Smartwatch) sog'liq ilovasi
*/
$user = [
"name"      => "Shukurbek",
"birthdate" => "2005-03-14",
"gender"    => "Erkak",
"height"    => "178 sm",
"weight"    => "70 kg",
"blood"     => "B (III) Rh+",
"device"    => "VitaWatch Pro",
"connected" => true,
];
$age = (new DateTime($user["birthdate"]))->diff(new DateTime("now"))->y;
$banners = [
["title" => "Sog'lom yurak — uzoq umr",  "sub" => "Har kuni yurak urishingizni kuzating", "c1" => "#0EA47A", "c2" => "#2FC18B"],
["title" => "Premium obuna -50%",         "sub" => "Cheksiz tarix va hisobotlar",         "c1" => "#6C5CE7", "c2" => "#48B0F7"],
["title" => "Harorat nazorati",           "sub" => "Isitma haqida darhol ogohlantirish",  "c1" => "#FF9F45", "c2" => "#FF5E7E"],
];
$notifications = [
["t" => "Yurak urishi yuqori", "d" => "5 daqiqa oldin",  "i" => "heart"],
["t" => "Harorat me'yorida",   "d" => "1 soat oldin",    "i" => "temp"],
["t" => "Batareya 20%",        "d" => "Bugun, 09:12",    "i" => "battery"],
];
?>
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#FBF6F2">
<title>VitaWatch</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{
--bg:#F4F8F6;--bg-2:#E7EFEB;--surface:#FFFFFF;--ink:#16201C;--ink-soft:#52605A;
--muted:#8E9B95;--line:#E4ECE8;--primary:#0EA47A;--primary-2:#2FC18B;--primary-soft:#D6F2E7;
--heart:#F2566E;--heart-soft:#FFE2E8;--temp:#FF9F45;--temp-soft:#FFEEDA;
--step:#12B5C9;--step-soft:#D5F2F6;--oxy:#5B8DEF;--oxy-soft:#E3ECFE;
--r-lg:28px;--r-md:22px;--r-sm:16px;
--shadow-card:0 10px 30px -12px rgba(60,40,30,.18), 0 2px 8px -4px rgba(60,40,30,.10);
--shadow-float:0 18px 40px -10px rgba(60,40,30,.30);
--font-d:"Bricolage Grotesque",serif;--font-b:"Manrope",sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;}
body{font-family:var(--font-b);background:var(--bg-2);color:var(--ink);display:flex;justify-content:center;align-items:stretch;overflow:hidden;-webkit-user-select:none;-moz-user-select:none;user-select:none;-webkit-touch-callout:none;-webkit-text-size-adjust:100%;text-size-adjust:100%;touch-action:manipulation;overscroll-behavior:none;}
.phone{position:relative;width:100%;max-width:430px;height:100vh;height:100dvh;background:radial-gradient(120% 80% at 82% -10%, rgba(14,164,122,.10), transparent 55%),radial-gradient(120% 70% at -10% 10%, rgba(91,141,239,.07), transparent 50%),var(--bg);overflow:hidden;display:flex;flex-direction:column;}
.page{position:absolute;inset:0;overflow:hidden;padding:16px 18px 100px;display:flex;flex-direction:column;opacity:0;visibility:hidden;transform:translateY(10px);transition:opacity .3s ease, transform .3s ease;}
.page::-webkit-scrollbar{display:none;}
.page.active{opacity:1;visibility:visible;transform:none;}
#page-profile {overflow-y: auto; scrollbar-width: none; padding-bottom: 110px;}
#page-profile::-webkit-scrollbar { display: none; }
.topbar{display:flex;align-items:center;justify-content:space-between;padding-top:8px;margin-bottom:22px;}
.greet{display:flex;align-items:center;gap:12px;}
.avatar{width:46px;height:46px;border-radius:14px;flex:0 0 auto;background:linear-gradient(135deg,var(--primary),var(--primary-2));display:grid;place-items:center;color:#fff;font-family:var(--font-d);font-weight:700;font-size:20px;box-shadow:0 8px 18px -6px rgba(14,164,122,.55);}
.greet .hello{font-size:13px;color:var(--muted);font-weight:600;letter-spacing:.2px;}
.greet .uname{font-family:var(--font-d);font-size:19px;font-weight:700;line-height:1.1;}
.topbar-right {display: flex;align-items: center;gap: 8px;}
.connection-status, .battery-status{position:relative;padding: 5px 10px;height:40px;border-radius:12px;background:var(--surface);border:1px solid var(--line);display:flex;align-items:center;gap:5px;cursor:pointer;box-shadow:var(--shadow-card);transition:transform .15s ease;}
.connection-status:active, .battery-status:active{transform:scale(.92);}
.connection-status .conn-text, .battery-status .bat-text{font-family:var(--font-d);font-weight:700;font-size:12.5px;color:var(--ink-soft);}
.connection-status.connected .conn-text { color: var(--primary); }
.connection-status .conn-icon, .battery-status .bat-icon-wrapper{color: var(--muted);display: flex;align-items: center;}
.connection-status .conn-icon svg, .battery-status .bat-icon-wrapper svg {width: 18px; height: 18px;}
.connection-status.connected .conn-icon { color: var(--primary); }
.battery-status .bat-icon-wrapper { color: #2FC18B; }
.carousel{position:relative;margin-bottom:8px;}
.track{display:flex;gap:14px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding-bottom:4px;scrollbar-width:none;}
.track::-webkit-scrollbar{display:none;}
.banner{flex:0 0 100%;scroll-snap-align:center;height:118px;border-radius:var(--r-md);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center;padding:0 22px;color:#fff;box-shadow:var(--shadow-card);}
.banner::after{content:"";position:absolute;inset:0;background:radial-gradient(80% 120% at 100% 0%, rgba(255,255,255,.25), transparent 60%);}
.banner h3{font-family:var(--font-d);font-size:19px;font-weight:700;position:relative;z-index:1;}
.banner p{font-size:12.5px;opacity:.92;margin-top:4px;position:relative;z-index:1;font-weight:500;max-width:80%;}
.banner .blob{position:absolute;right:-28px;bottom:-34px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.16);}
.dots{display:flex;gap:7px;justify-content:center;margin-top:12px;}
.dots span{width:7px;height:7px;border-radius:50%;background:var(--line);transition:.25s;}
.dots span.on{width:22px;background:var(--primary);}
.section-h{display:flex;align-items:baseline;justify-content:space-between;margin:26px 2px 14px;}
.section-h h2{font-family:var(--font-d);font-size:18px;font-weight:700;}
.section-h a{font-size:13px;color:var(--primary);font-weight:700;text-decoration:none;}
.grid2{display:grid;grid-template-columns:1fr 1fr;grid-template-rows:1fr 1fr;gap:14px;flex:1;min-height:0;}
.metric{background:var(--surface);border-radius:var(--r-md);box-shadow:var(--shadow-card);border:1px solid var(--line);position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;min-height:0;cursor:pointer;transition:transform .15s ease, box-shadow .15s ease;}
.metric:active{transform:scale(.96);}
.mlabel{position:relative;z-index:1;font-size:12.5px;font-weight:700;color:var(--ink-soft);text-align:center;line-height:1.15;}
.metric::before{content:"";position:absolute;inset:0;opacity:.6;}
.heart-c::before{background:radial-gradient(circle at 50% 45%,var(--heart-soft),transparent 68%);}
.temp-c::before {background:radial-gradient(circle at 50% 45%,var(--temp-soft),transparent 68%);}
.drop-c::before {background:radial-gradient(circle at 50% 45%,var(--oxy-soft),transparent 68%);}
.pulse-c::before{background:radial-gradient(circle at 50% 45%,var(--step-soft),transparent 68%);}
.ic-anim{width:52px;height:52px;position:relative;z-index:1;}
.heart-c .ic-anim{color:var(--heart);animation:heartbeat 1.1s ease-in-out infinite;}
.temp-c  .ic-anim{color:var(--temp); animation:tilt 3.2s ease-in-out infinite;}
.drop-c  .ic-anim{color:var(--oxy);  animation:bob 1.9s ease-in-out infinite;}
.pulse-c .ic-anim{width:70px;height:38px;color:var(--step);}
.temp-c .merc{transform-box:fill-box;transform-origin:bottom;animation:merc 2.2s ease-in-out infinite;}
.pulse-c .pl{stroke-dasharray:70;animation:trace 1.9s linear infinite;}
@keyframes heartbeat{0%,100%{transform:scale(1)}12%{transform:scale(1.22)}24%{transform:scale(1)}36%{transform:scale(1.12)}48%{transform:scale(1)}}
@keyframes tilt{0%,100%{transform:rotate(-6deg)}50%{transform:rotate(2deg)}}
@keyframes bob{0%,100%{transform:translateY(-4px) scale(1)}50%{transform:translateY(4px) scaleX(.88) scaleY(1.1)}}
@keyframes merc{0%,100%{transform:scaleY(.38)}50%{transform:scaleY(1)}}
@keyframes trace{0%{stroke-dashoffset:140}100%{stroke-dashoffset:-70}}
.ic-heart{background:var(--heart-soft);color:var(--heart);}
.ic-temp{background:var(--temp-soft);color:var(--temp);}
.ic-step{background:var(--step-soft);color:var(--step);}
.ic-oxy{background:var(--oxy-soft);color:var(--oxy);}
.ic-primary{background:var(--primary-soft);color:var(--primary);}

/* YANGI: Bottom menyu overlay ustida turishi uchun z-index oshirildi */
.navbar{
position:absolute;left:50%;bottom:18px;transform:translateX(-50%);
width:calc(100% - 36px);max-width:394px;height:70px;
background:rgba(255,255,255,.85);
backdrop-filter:blur(18px) saturate(160%);
-webkit-backdrop-filter:blur(18px) saturate(160%);
border:1px solid rgba(255,255,255,.6);
border-radius:24px;
box-shadow:var(--shadow-float);
display:flex;align-items:center;justify-content:space-around;
z-index:150; /* Overlay (z-index:100) dan yuqorida turishi uchun */
}

.nav-btn{background:none;border:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:5px;color:var(--muted);font-family:var(--font-b);font-size:11px;font-weight:700;width:64px;transition:color .2s;}
.nav-btn.active{color:var(--ink);}
.nav-btn.active svg{color:var(--primary);}
.nav-btn svg{transition:transform .2s;}
.nav-btn:active svg{transform:scale(.85);}
.nav-center{color:var(--primary);}
.nav-center svg{color:var(--primary);}
.chat-container {flex: 1;overflow-y: auto;display: flex;flex-direction: column;gap: 16px;padding-bottom: 16px;scrollbar-width: none;}
.chat-container::-webkit-scrollbar { display: none; }
.chat-msg {display: flex;gap: 10px;max-width: 85%;animation: fadeIn 0.3s ease;}
.chat-msg.user {align-self: flex-end;flex-direction: row-reverse;}
.chat-avatar {width: 36px;height: 36px;border-radius: 12px;background: var(--bg-2);display: grid;place-items: center;font-size: 18px;flex: 0 0 auto;}
.chat-msg.user .chat-avatar {background: var(--primary-soft);}
.chat-bubble {background: var(--surface);border: 1px solid var(--line);padding: 12px 16px;border-radius: 18px 18px 18px 4px;font-size: 14px;line-height: 1.5;color: var(--ink);box-shadow: var(--shadow-card);}
.chat-msg.user .chat-bubble {background: var(--primary);color: #fff;border-color: var(--primary);border-radius: 18px 18px 4px 18px;}
.chat-input-area {display: flex;gap: 10px;padding: 12px 0 8px;border-top: 1px solid var(--line);background: var(--bg);}
.chat-input-area input {flex: 1;padding: 14px 18px;border-radius: 99px;border: 1px solid var(--line);background: var(--surface);font-family: var(--font-b);font-size: 14px;color: var(--ink);outline: none;transition: border-color 0.2s;}
.chat-input-area input:focus {border-color: var(--primary);}
.chat-input-area button {width: 48px;height: 48px;border-radius: 50%;border: none;background: var(--primary);color: #fff;display: grid;place-items: center;cursor: pointer;box-shadow: 0 4px 12px -4px rgba(14, 164, 122, 0.5);transition: transform 0.15s ease;}
.chat-input-area button:active {transform: scale(0.9);}
@keyframes fadeIn {from { opacity: 0; transform: translateY(10px); }to { opacity: 1; transform: translateY(0); }}
.typing-indicator {display: flex;gap: 4px;padding: 12px 16px;}
.typing-indicator span {width: 6px;height: 6px;border-radius: 50%;background: var(--muted);animation: typing 1.4s infinite ease-in-out both;}
.typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
.typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
@keyframes typing {0%, 80%, 100% { transform: scale(0); }40% { transform: scale(1); }}
.profile-head{background:linear-gradient(135deg,var(--primary),var(--primary-2));border-radius:var(--r-lg);padding:22px 20px;color:#fff;text-align:center;box-shadow:var(--shadow-float);position:relative;overflow:hidden;flex:0 0 auto;}
.profile-head .pic{width:70px;height:70px;border-radius:22px;margin:0 auto 12px;background:rgba(255,255,255,.22);display:grid;place-items:center;font-family:var(--font-d);font-size:28px;font-weight:700;border:2px solid rgba(255,255,255,.5);}
.profile-head h2{font-family:var(--font-d);font-size:22px;}
.profile-head .badge{display:inline-flex;align-items:center;gap:6px;margin-top:9px;background:rgba(255,255,255,.22);padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:700;}
.profile-head .badge .live{width:8px;height:8px;border-radius:50%;background:#9CF5C8;box-shadow:0 0 0 4px rgba(156,245,200,.35);}
.p-sub{font-size:13px;opacity:.92;margin-top:7px;font-weight:600;}
.p-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:14px;flex:0 0 auto;}
.p-stat{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);padding:14px 6px;text-align:center;box-shadow:var(--shadow-card);}
.p-stat .pv{font-family:var(--font-d);font-size:20px;font-weight:800;line-height:1;}
.p-stat .pl{font-size:10.5px;color:var(--muted);font-weight:700;margin-top:5px;}
.info-card{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);margin-top:14px;overflow:hidden;box-shadow:var(--shadow-card);flex:0 0 auto;}
.info-row{display:flex;align-items:center;gap:14px;padding:12px 16px;border-bottom:1px solid var(--line);}
.info-row:last-child{border-bottom:none;}
.info-row .ri{width:36px;height:36px;border-radius:11px;background:var(--bg-2);display:grid;place-items:center;color:var(--ink-soft);flex:0 0 auto;}
.info-row .rt{font-size:11.5px;color:var(--muted);font-weight:600;}
.info-row .rv{font-size:14.5px;font-weight:700;margin-top:1px;}
.info-row .edit{margin-left:auto;color:var(--muted);}
.logout{margin-top:14px;width:100%;padding:14px;border:1px solid var(--line);background:var(--surface);border-radius:18px;color:var(--primary);font-weight:800;font-size:14px;cursor:pointer;box-shadow:var(--shadow-card);flex:0 0 auto;}
.logout:active{transform:scale(.98);}
.connect-card {background: var(--surface);border: 1px solid var(--line);border-radius: var(--r-md);padding: 16px;display: flex;align-items: center;gap: 14px;box-shadow: var(--shadow-card);transition: all 0.3s ease;margin-bottom: 14px;}
.connect-card.scanning {border-color: var(--primary);background: var(--primary-soft);}
.connect-icon {width: 48px; height: 48px;border-radius: 14px;background: var(--primary-soft);color: var(--primary);display: grid; place-items: center;flex: 0 0 auto;transition: all 0.3s ease;}
.connect-card.scanning .connect-icon {background: var(--surface);animation: pulse-ring 1.5s infinite;}
@keyframes pulse-ring {0% { box-shadow: 0 0 0 0 rgba(14, 164, 122, 0.4); }70% { box-shadow: 0 0 0 10px rgba(14, 164, 122, 0); }100% { box-shadow: 0 0 0 0 rgba(14, 164, 122, 0); }}
.connect-info { flex: 1; min-width: 0; }
.connect-title { font-family: var(--font-d); font-size: 16px; font-weight: 700; color: var(--ink); }
.connect-status { font-size: 12.5px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.connect-btn {padding: 10px 18px;border-radius: 12px;border: none;background: var(--bg-2);color: var(--ink-soft);font-family: var(--font-b);font-weight: 700;font-size: 13px;cursor: pointer;transition: all 0.2s ease;flex: 0 0 auto;}
.connect-btn.active {background: var(--heart);color: #fff;}
.connect-btn:active { transform: scale(0.95); }
.connect-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
.notif-panel{position:absolute;top:64px;right:20px;width:270px;z-index:60;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);box-shadow:var(--shadow-float);padding:8px;opacity:0;visibility:hidden;transform:translateY(-8px) scale(.97);transform-origin:top right;transition:.22s ease;}
.notif-panel.open{opacity:1;visibility:visible;transform:none;}
.notif-panel .nh{font-family:var(--font-d);font-weight:700;font-size:14px;padding:8px 10px 4px;}
.notif-item{display:flex;gap:11px;align-items:center;padding:10px;border-radius:14px;}
.notif-item:active{background:var(--bg-2);}
.notif-item .ni{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;flex:0 0 auto;}
.notif-item .nt{font-size:13px;font-weight:700;}
.notif-item .nd{font-size:11px;color:var(--muted);margin-top:1px;}

.page-title-wrapper { display: flex; align-items: center; justify-content: space-between; margin: 4px 2px 14px; flex: 0 0 auto; }
.page-title { font-family: var(--font-d); font-size: 24px; font-weight: 700; }
.lang-switcher { position: relative; }
.lang-btn { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 6px 12px; font-family: var(--font-b); font-weight: 700; font-size: 13px; color: var(--ink); cursor: pointer; display: flex; align-items: center; gap: 4px; box-shadow: var(--shadow-card); transition: transform 0.15s ease; }
.lang-btn:active { transform: scale(0.95); }
.lang-dropdown { position: absolute; top: 110%; right: 0; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow-float); padding: 6px; min-width: 140px; opacity: 0; visibility: hidden; transform: translateY(-8px); transition: all 0.2s ease; z-index: 100; }
.lang-dropdown.open { opacity: 1; visibility: visible; transform: translateY(0); }
.lang-option { padding: 10px 12px; border-radius: 10px; font-size: 14px; font-weight: 600; color: var(--ink-soft); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 8px; }
.lang-option:hover, .lang-option.active { background: var(--primary-soft); color: var(--primary); }

.overlay{position:absolute;inset:0;z-index:100;background:radial-gradient(120% 70% at 85% -5%, rgba(14,164,122,.08), transparent 55%),var(--bg);display:flex;flex-direction:column;transform:translateY(100%);transition:transform .34s cubic-bezier(.4,0,.2,1);visibility:hidden;}
.overlay.open{transform:none;visibility:visible;}
.ov-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;flex:0 0 auto;border-bottom:1px solid var(--line);}
.ov-title{font-family:var(--font-d);font-size:17px;font-weight:700;}
.ov-back{width:42px;height:42px;border-radius:13px;border:1px solid var(--line);background:var(--surface);display:grid;place-items:center;cursor:pointer;color:var(--ink);box-shadow:var(--shadow-card);}
.ov-back:active{transform:scale(.92);}
.ov-cancel,.ov-save{background:none;border:none;font-family:var(--font-b);font-weight:800;font-size:15px;cursor:pointer;padding:6px 2px;}
.ov-cancel{color:var(--muted);}
.ov-save{color:var(--primary);}
.ov-body{flex:1;overflow-y:auto;padding:18px;scrollbar-width:none;}
.ov-body::-webkit-scrollbar{display:none;}
.d-hero{border-radius:var(--r-lg);padding:26px 22px;color:#fff;text-align:center;box-shadow:var(--shadow-float);position:relative;overflow:hidden;}
.d-hero .di{display:flex;justify-content:center;margin-bottom:8px;}
.d-hero .ic-anim{color:#fff !important;width:58px;height:58px;}
.d-hero .ic-anim.wide{width:88px;height:48px;}
.d-hero .dv{font-family:var(--font-d);font-size:52px;font-weight:800;line-height:1;}
.d-hero .du{font-size:13.5px;opacity:.9;margin-top:7px;font-weight:600;}
.d-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:16px;}
.d-stat{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);padding:14px 8px;text-align:center;box-shadow:var(--shadow-card);}
.d-stat .sv{font-family:var(--font-d);font-size:18px;font-weight:700;}
.d-stat .sl{font-size:11px;color:var(--muted);font-weight:600;margin-top:3px;}
.d-chart{background:var(--surface);border:1px solid var(--line);border-radius:var(--r-lg);padding:18px 16px 14px;margin-top:16px;box-shadow:var(--shadow-card);}
.d-chart .ch-h{font-size:13px;font-weight:700;color:var(--ink-soft);margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;}
.d-chart .ch-h span{font-size:11px;background:var(--bg-2);padding:2px 8px;border-radius:99px;color:var(--ink-soft);}
.day-bar-chart {display:flex; justify-content:space-between; align-items:flex-end; height:120px; padding-top:20px; position:relative;}
.chart-bar-item {display:flex; flex-direction:column; align-items:center; flex:1; margin:0 2px;}
.chart-bar-val {font-size:9.5px; font-weight:700; color:var(--ink-soft); margin-bottom:4px; font-family:var(--font-d);}
.chart-bar-pill {width:100%; max-width:18px; border-radius:6px 6px 3px 3px; position:relative; min-height:4px; transition: height 0.5s cubic-bezier(0.4, 0, 0.2, 1);}
.chart-bar-lbl {font-size:10px; font-weight:600; color:var(--muted); margin-top:6px;}
.d-act{margin-top:18px;width:100%;padding:16px;border:none;border-radius:18px;color:#fff;font-family:var(--font-b);font-weight:800;font-size:15px;cursor:pointer;box-shadow:var(--shadow-card);}
.d-act:active{transform:scale(.98);}
.history-tabs {display: flex;background: var(--bg-2);border-radius: 14px;padding: 4px;margin-top: 16px;gap: 4px;}
.history-tab {flex: 1;padding: 10px 0;border: none;background: transparent;border-radius: 10px;font-family: var(--font-b);font-weight: 700;font-size: 13px;color: var(--muted);cursor: pointer;transition: all 0.2s ease;}
.history-tab.active {background: var(--surface);color: var(--ink);box-shadow: 0 2px 8px -2px rgba(0,0,0,0.1);}
.history-chart-container {margin-top: 16px;animation: fadeIn 0.3s ease;}
.field-h{font-size:12px;font-weight:800;color:var(--muted);margin:20px 2px 10px;text-transform:uppercase;letter-spacing:.5px;}
.field-h.first{margin-top:2px;}
.daychips{display:flex;gap:8px;justify-content:space-between;}
.dchip{flex:1;height:46px;border-radius:14px;border:1px solid var(--line);background:var(--surface);font-family:var(--font-b);font-weight:800;font-size:13px;color:var(--ink-soft);cursor:pointer;transition:transform .12s, background .15s;}
.dchip.active{background:var(--primary);color:#fff;border-color:var(--primary);box-shadow:0 6px 14px -6px rgba(14,164,122,.7);}
.dchip:active{transform:scale(.9);}
.rep-hint{font-size:13px;color:var(--ink-soft);margin-top:12px;font-weight:700;text-align:center;}
.tw{display:flex;align-items:center;justify-content:center;gap:4px;position:relative;height:200px;margin-top:8px;}
.tw .band{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:184px;height:50px;border-radius:16px;background:var(--bg-2);z-index:0;}
.tw .col{position:relative;z-index:1;height:200px;width:80px;overflow-y:scroll;scroll-snap-type:y mandatory;padding:75px 0;scrollbar-width:none;overscroll-behavior:contain;-webkit-mask:linear-gradient(180deg,transparent,#000 30%,#000 70%,transparent);mask:linear-gradient(180deg,transparent,#000 30%,#000 70%,transparent);}
.tw .col::-webkit-scrollbar{display:none;}
.tw .col .it{height:50px;scroll-snap-align:center;display:flex;align-items:center;justify-content:center;font-family:var(--font-d);font-size:28px;font-weight:700;color:var(--muted);}
.tw .colon{position:relative;z-index:1;font-family:var(--font-d);font-size:28px;font-weight:700;}
.toast{position:absolute;left:50%;bottom:108px;transform:translate(-50%,16px);background:var(--ink);color:#fff;padding:13px 22px;border-radius:14px;font-weight:700;font-size:13.5px;box-shadow:var(--shadow-float);opacity:0;visibility:hidden;transition:.3s;z-index:200;}
.toast.show{opacity:1;visibility:visible;transform:translate(-50%,0);}
.add-sep{margin:24px 0 2px;font-family:var(--font-d);font-weight:700;font-size:15px;color:var(--ink);border-top:1px solid var(--line);padding-top:18px;}
#alarmList{margin-top:4px;}
.alarm{display:flex;align-items:center;gap:12px;background:var(--surface);border:1px solid var(--line);border-radius:var(--r-md);padding:14px 16px;box-shadow:var(--shadow-card);margin-bottom:10px;}
.alarm .ainfo{flex:1;min-width:0;}
.alarm .atime{font-family:var(--font-d);font-size:26px;font-weight:800;line-height:1;}
.alarm .atype{font-size:12px;font-weight:700;color:var(--ink-soft);margin-top:5px;}
.alarm .arep{color:var(--muted);font-weight:600;}
.alarm.off .atime,.alarm.off .atype{opacity:.4;}
.sw{width:48px;height:28px;border-radius:999px;background:var(--line);position:relative;cursor:pointer;flex:0 0 auto;transition:background .2s;}
.sw.on{background:var(--primary);}
.sw::after{content:"";position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 5px rgba(0,0,0,.25);transition:left .2s;}
.sw.on::after{left:23px;}
.adel{background:none;border:none;color:var(--muted);cursor:pointer;padding:6px;flex:0 0 auto;}
.adel:active{transform:scale(.85);}
.empty{font-size:13px;color:var(--muted);text-align:center;padding:14px 0;font-weight:600;}
.alarm-title-input {width: 100%;padding: 14px 18px;border-radius: 14px;border: 1px solid var(--line);background: var(--surface);font-family: var(--font-b);font-size: 15px;font-weight: 600;color: var(--ink);outline: none;margin-bottom: 16px;transition: border-color 0.2s;}
.alarm-title-input:focus {border-color: var(--primary);}
</style>
</head>
<body>
<div class="phone">
<section class="page active" id="page-home">
<div class="topbar">
<div class="greet">
<div class="avatar"><?= mb_substr($user["name"],0,1) ?></div>
<div>
<div class="hello" data-i18n="greeting">Salom 👋</div>
<div class="uname"><?= htmlspecialchars($user["name"]) ?></div>
</div>
</div>
<div class="topbar-right">
<div class="connection-status connected" id="connBtn">
<span class="conn-text" id="connText" data-i18n="connected">Ulangan</span>
<div class="conn-icon">
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V4h6v3"/><path d="M9 17v3h6v-3"/><path d="M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/></svg>
</div>
</div>
<div class="battery-status" id="bellBtn" aria-label="Soat holati va Bildirishnomalar">
<span class="bat-text">84%</span>
<div class="bat-icon-wrapper">
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="16" height="10" rx="2" ry="2"></rect><line x1="22" y1="11" x2="22" y2="13"></line></svg>
</div>
</div>
</div>
</div>
<div class="notif-panel" id="notifPanel">
<div class="nh" data-i18n="notifications">Bildirishnomalar</div>
<?php foreach($notifications as $n):
$map = ["heart"=>["ic-heart","M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"],
"temp"=>["ic-temp","M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z"],
"battery"=>["ic-step","M3 7h14v10H3zM20 10v4"]];
[$cls,$path]=$map[$n["i"]]; ?>
<div class="notif-item">
<div class="ni <?= $cls ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $path ?>"/></svg></div>
<div><div class="nt"><?= htmlspecialchars($n["t"]) ?></div><div class="nd"><?= htmlspecialchars($n["d"]) ?></div></div>
</div>
<?php endforeach; ?>
</div>
<div class="carousel">
<div class="track" id="track">
<?php foreach($banners as $b): ?>
<div class="banner" style="background:linear-gradient(120deg,<?= $b['c1'] ?>,<?= $b['c2'] ?>)">
<div class="blob"></div>
<h3><?= htmlspecialchars($b['title']) ?></h3>
<p><?= htmlspecialchars($b['sub']) ?></p>
</div>
<?php endforeach; ?>
</div>
<div class="dots" id="dots">
<?php foreach($banners as $i=>$b): ?><span class="<?= $i===0?'on':'' ?>"></span><?php endforeach; ?>
</div>
</div>
<div class="section-h"><h2 data-i18n="health_indicators">Sog'liq ko'rsatkichlari</h2><a href="#" data-i18n="all">Hammasi</a></div>
<div class="grid2">
<div class="metric heart-c" data-metric="heart">
<svg class="ic-anim" viewBox="0 0 24 24" fill="currentColor"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>
<div class="mlabel" data-i18n="heart_rate">Yurak impulsi</div>
</div>
<div class="metric temp-c" data-metric="temp">
<svg class="ic-anim" viewBox="0 0 24 24" fill="none"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z" stroke="currentColor" stroke-width="2"/><rect class="merc" x="10.2" y="6.4" width="2.6" height="9.6" rx="1.3" fill="currentColor"/><circle cx="11.5" cy="18.2" r="2.5" fill="currentColor"/></svg>
<div class="mlabel" data-i18n="temperature">Harorat</div>
</div>
<div class="metric drop-c" data-metric="drop">
<svg class="ic-anim" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5C12 2.5 6 9 6 14a6 6 0 0 0 12 0c0-5-6-11.5-6-11.5z"/></svg>
<div class="mlabel" data-i18n="oxygen">Kislorod</div>
</div>
<div class="metric pulse-c" data-metric="pulse">
<svg class="ic-anim" viewBox="0 0 48 24" fill="none"><path class="pl" d="M1 12 H10 l3 -8 4 16 3 -11 2 3 H47" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
<div class="mlabel" data-i18n="activity">Faollik</div>
</div>
</div>
</section>

<section class="page" id="page-ai">
<div class="page-title" data-i18n="ai_title">VitaAI Yordamchi 🤖</div>
<div class="chat-container" id="chatContainer">
<div class="chat-msg ai">
<div class="chat-avatar">🤖</div>
<div class="chat-bubble" data-i18n="ai_greeting">Salom! Men sizning sog'liq bo'yicha sun'iy intellekt yordamchingizman.</div>
</div>
</div>
<div class="chat-input-area">
<input type="text" id="chatInput" data-i18n-placeholder="chat_placeholder" placeholder="Savolingizni yozing..." autocomplete="off">
<button id="chatSendBtn">
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
</button>
</div>
</section>

<section class="page" id="page-profile">
<div class="page-title-wrapper">
<div class="page-title" data-i18n="profile_title">Profil</div>
<div class="lang-switcher">
<button class="lang-btn" id="langBtn">
<span id="currentLangLabel">UZ</span> ▾
</button>
<div class="lang-dropdown" id="langDropdown">
<div class="lang-option active" data-lang="uz">🇺🇿 O'zbekcha</div>
<div class="lang-option" data-lang="ru">🇷🇺 Русский</div>
<div class="lang-option" data-lang="uz_cyrl">🇺🇿 Ўзбекча</div>
</div>
</div>
</div>
<div class="profile-head">
<div class="pic"><?= mb_substr($user["name"],0,1) ?></div>
<h2><?= htmlspecialchars($user["name"]) ?></h2>
<div class="p-sub"><span id="profileAge"><?= $age ?></span> <span data-i18n="years_old">yosh</span> · <?= htmlspecialchars($user["gender"]) ?></div>
<div class="badge" id="connectionBadge"><span class="live"></span> <?= htmlspecialchars($user["device"]) ?></div>
</div>
<div class="p-stats">
<div class="p-stat"><div class="pv" id="statAge"><?= $age ?></div><div class="pl" data-i18n="age">Yosh</div></div>
<div class="p-stat"><div class="pv"><?= (int)$user["height"] ?></div><div class="pl" data-i18n="height_cm">Bo'y · sm</div></div>
<div class="p-stat"><div class="pv"><?= (int)$user["weight"] ?></div><div class="pl" data-i18n="weight_kg">Vazn · kg</div></div>
</div>
<div class="section-h" style="margin-top: 18px;"><h2 data-i18n="device">Qurilma</h2></div>
<div class="connect-card" id="connectCard">
<div class="connect-icon" id="connectIcon">
<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V4h6v3"/><path d="M9 17v3h6v-3"/><path d="M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/></svg>
</div>
<div class="connect-info">
<div class="connect-title" id="connectTitle"><?= htmlspecialchars($user["device"]) ?></div>
<div class="connect-status" id="connectStatus" data-i18n="connected_to_system">Tizimga ulangan</div>
</div>
<button class="connect-btn" id="connectBtn" onclick="toggleConnectionProfile()">
<span id="connectBtnTextProfile" data-i18n="disconnect">Uzish</span>
</button>
</div>
<div class="section-h"><h2 data-i18n="personal_info">Shaxsiy ma'lumotlar</h2></div>
<div class="info-card">
<?php
$rows = [
["birth_date",  date("d.m.Y",strtotime($user["birthdate"])), "M8 2v4 M16 2v4 M3 10h18 M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"],
["gender",            htmlspecialchars($user["gender"]),            "M16 14a6 6 0 1 0-8 0 M12 8V2 M9 5h6"],
["blood_type",      htmlspecialchars($user["blood"]),             "M12 2.5C12 2.5 6 9 6 14a6 6 0 0 0 12 0c0-5-6-11.5-6-11.5z"],
["connected_device", htmlspecialchars($user["device"]),            "M9 7V4h6v3 M9 17v3h6v-3 M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"],
];
foreach($rows as $r): ?>
<div class="info-row">
<div class="ri"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $r[2] ?>"/></svg></div>
<div><div class="rt" data-i18n="<?= $r[0] ?>"><?= $r[0] ?></div><div class="rv"><?= $r[1] ?></div></div>
<div class="edit"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg></div>
</div>
<?php endforeach; ?>
</div>
<button class="logout" data-i18n="logout">Hisobdan chiqish</button>
</section>

<nav class="navbar">
<button class="nav-btn active" data-page="home">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-7 9 7v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 21V12h6v9"/></svg>
<span data-i18n="nav_home">Asosiy</span>
</button>
<button class="nav-btn" data-page="ai">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
<span data-i18n="nav_ai">AI Chat</span>
</button>
<button class="nav-btn nav-center" id="fabBtn" aria-label="Eslatma">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5"/><path d="M5 3 2.5 5.5"/><path d="M19 3l2.5 2.5"/></svg>
<span data-i18n="nav_reminder">Eslatma</span>
</button>
<button class="nav-btn" data-page="profile">
<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/></svg>
<span data-i18n="nav_profile">Profil</span>
</button>
</nav>

<div class="overlay" id="detail">
<div class="ov-head">
<button class="ov-back" id="detailBack" aria-label="Orqaga">
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
</button>
<div class="ov-title" id="detailTitle">Yurak</div>
<div style="width:42px"></div>
</div>
<div class="ov-body" id="detailBody"></div>
</div>

<div class="overlay" id="connectOverlay">
<div class="ov-head">
<button class="ov-back" id="connectBack" aria-label="Orqaga">
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
</button>
<div class="ov-title" data-i18n="connect_device">Qurilmani ulash</div>
<div style="width:42px"></div>
</div>
<div class="ov-body">
<div class="connect-card" id="connectDeviceCard" style="margin-top: 16px;">
<div class="connect-icon" id="connectIconBox">
<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V4h6v3"/><path d="M9 17v3h6v-3"/><path d="M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/></svg>
</div>
<div class="connect-info">
<div class="connect-title" id="connectDeviceName">VitaWatch Pro</div>
<div class="connect-status" id="connectDeviceStatus" data-i18n="connected_to_system">Tizimga ulangan</div>
</div>
<button class="connect-btn" id="connectActionBtn">
<span id="connectActionText" data-i18n="disconnect">Uzish</span>
</button>
</div>
<div style="margin-top: 24px; text-align: center; color: var(--muted); font-size: 13px; font-weight: 600;" data-i18n="search_devices">
Bluetooth va Wi-Fi orqali qurilmalarni qidirish
</div>
</div>
</div>

<div class="overlay" id="sheet">
<div class="ov-head">
<button class="ov-cancel" id="sheetCancel" data-i18n="cancel">Bekor</button>
<div class="ov-title" data-i18n="add_reminder">Eslatma qo'shish</div>
<button class="ov-save" id="sheetSave" data-i18n="save">Saqlash</button>
</div>
<div class="ov-body">
<div class="field-h first" data-i18n="saved_reminders">Saqlangan eslatmalar</div>
<div id="alarmList"></div>
<div class="add-sep" data-i18n="add_new">Yangi eslatma qo'shish</div>
<input type="text" id="alarmTitleInput" class="alarm-title-input" data-i18n-placeholder="reminder_placeholder" placeholder="Masalan: Dori ichish 💊" maxlength="30">
<div class="field-h" data-i18n="repeat">Takrorlash</div>
<div class="daychips" id="dayChips"></div>
<div class="rep-hint" id="repHint" data-i18n="once">Bir marta</div>
<div class="field-h" data-i18n="time">Vaqt</div>
<div class="tw" id="timeWheel">
<div class="band"></div>
<div class="col" id="colH"></div>
<div class="colon">:</div>
<div class="col" id="colM"></div>
</div>
</div>
</div>
</div>

<script>
const translations = {
    uz: {
        greeting: "Salom 👋", profile_title: "Profil", years_old: "yosh", age: "Yosh", height_cm: "Bo'y · sm", weight_kg: "Vazn · kg",
        device: "Qurilma", connected_to_system: "Tizimga ulangan", disconnect: "Uzish", connect: "Ulash", searching: "Qidirilmoqda...",
        personal_info: "Shaxsiy ma'lumotlar", birth_date: "Tug'ilgan sana", gender: "Jins", blood_type: "Qon guruhi", connected_device: "Ulangan qurilma",
        logout: "Hisobdan chiqish", nav_home: "Asosiy", nav_ai: "AI Chat", nav_reminder: "Eslatma", nav_profile: "Profil",
        health_indicators: "Sog'liq ko'rsatkichlari", all: "Hammasi", heart_rate: "Yurak impulsi", temperature: "Harorat", oxygen: "Kislorod", activity: "Faollik",
        ai_title: "VitaAI Yordamchi 🤖", ai_greeting: "Salom! Men sizning sog'liq bo'yicha sun'iy intellekt yordamchingizman.", chat_placeholder: "Savolingizni yozing...",
        notifications: "Bildirishnomalar", connected: "Ulangan", disconnected: "Ulanmagan",
        connect_device: "Qurilmani ulash", search_devices: "Bluetooth va Wi-Fi orqali qurilmalarni qidirish",
        cancel: "Bekor", add_reminder: "Eslatma qo'shish", save: "Saqlash", saved_reminders: "Saqlangan eslatmalar",
        add_new: "Yangi eslatma qo'shish", reminder_placeholder: "Masalan: Dori ichish 💊", repeat: "Takrorlash",
        once: "Bir marta", daily: "Har kuni takrorlanadi", time: "Vaqt",
        today: "Bugun", yesterday: "Kecha", last_7_days: "7 kun", min: "Min", avg: "O'rtacha", max: "Maks",
        measure: "O'lchash", measuring: "O'lchanmoqda…",
        days: ["Du", "Se", "Ch", "Pa", "Ju", "Sh", "Ya"],
        chart_today: "Soatbay ko'rsatkich", chart_7days: "So'nggi 7 kunlik ko'rsatkich", span_1day: "1 kun", span_7days: "7 kun"
    },
    ru: {
        greeting: "Привет 👋", profile_title: "Профиль", years_old: "лет", age: "Возраст", height_cm: "Рост · см", weight_kg: "Вес · кг",
        device: "Устройство", connected_to_system: "Подключено к системе", disconnect: "Отключить", connect: "Подключить", searching: "Поиск...",
        personal_info: "Личные данные", birth_date: "Дата рождения", gender: "Пол", blood_type: "Группа крови", connected_device: "Подключенное устройство",
        logout: "Выйти", nav_home: "Главная", nav_ai: "AI Чат", nav_reminder: "Напоминания", nav_profile: "Профиль",
        health_indicators: "Показатели здоровья", all: "Все", heart_rate: "Пульс", temperature: "Температура", oxygen: "Кислород", activity: "Активность",
        ai_title: "VitaAI Помощник 🤖", ai_greeting: "Привет! Я ваш ИИ-помощник по здоровью.", chat_placeholder: "Напишите ваш вопрос...",
        notifications: "Уведомления", connected: "Подключено", disconnected: "Не подключено",
        connect_device: "Подключить устройство", search_devices: "Поиск устройств через Bluetooth и Wi-Fi",
        cancel: "Отмена", add_reminder: "Добавить напоминание", save: "Сохранить", saved_reminders: "Сохраненные напоминания",
        add_new: "Добавить новое", reminder_placeholder: "Например: Принять лекарство 💊", repeat: "Повтор",
        once: "Один раз", daily: "Каждый день", time: "Время",
        today: "Сегодня", yesterday: "Вчера", last_7_days: "7 дней", min: "Мин", avg: "Сред", max: "Макс",
        measure: "Измерить", measuring: "Измерение…",
        days: ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"],
        chart_today: "Почасовой показатель", chart_7days: "Показатель за последние 7 дней", span_1day: "1 день", span_7days: "7 дней"
    },
    uz_cyrl: {
        greeting: "Салом 👋", profile_title: "Профил", years_old: "ёш", age: "Ёш", height_cm: "Бўй · см", weight_kg: "Вазн · кг",
        device: "Қурилма", connected_to_system: "Тизимга уланган", disconnect: "Узиш", connect: "Улаш", searching: "Қидирилмоқда...",
        personal_info: "Шахсий маълумотлар", birth_date: "Туғилган сана", gender: "Жинс", blood_type: "Қон гуруҳи", connected_device: "Уланган қурилма",
        logout: "Ҳисобдан чиқиш", nav_home: "Асосий", nav_ai: "AI Чат", nav_reminder: "Эслатма", nav_profile: "Профил",
        health_indicators: "Соғлиқ кўрсаткичлари", all: "Ҳаммаси", heart_rate: "Юрак импулси", temperature: "Ҳарорат", oxygen: "Кислород", activity: "Фаоллик",
        ai_title: "VitaAI Ёрдамчи 🤖", ai_greeting: "Салом! Мен сизнинг соғлиқ бўйича сунъий интеллект ёрдамчингизман.", chat_placeholder: "Саволингизни ёзинг...",
        notifications: "Билдиришномалар", connected: "Уланган", disconnected: "Уланмаган",
        connect_device: "Қурилмани улаш", search_devices: "Bluetooth ва Wi-Fi орқали қурилмаларни қидириш",
        cancel: "Бекор", add_reminder: "Эслатма қўшиш", save: "Сақлаш", saved_reminders: "Сақланган эслатмалар",
        add_new: "Янги эслатма қўшиш", reminder_placeholder: "Масалан: Дори ичиш 💊", repeat: "Такрорлаш",
        once: "Бир марта", daily: "Ҳар куни такрорланади", time: "Вақт",
        today: "Бугун", yesterday: "Кеча", last_7_days: "7 кун", min: "Мин", avg: "Ўртача", max: "Макс",
        measure: "Ўлчаш", measuring: "Ўлчанмоқда…",
        days: ["Душ", "Сеш", "Чор", "Пай", "Жум", "Шан", "Як"],
        chart_today: "Соатбай кўрсаткич", chart_7days: "Сўнгги 7 кунлик кўрсаткич", span_1day: "1 кун", span_7days: "7 кун"
    }
};

let currentLang = localStorage.getItem('vita_lang') || 'uz';

function applyTranslations() {
    const t = translations[currentLang];
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (t[key]) el.textContent = t[key];
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
        const key = el.getAttribute('data-i18n-placeholder');
        if (t[key]) el.placeholder = t[key];
    });
    
    const langLabels = { uz: "UZ", ru: "RU", uz_cyrl: "ЎЗ" };
    document.getElementById('currentLangLabel').textContent = langLabels[currentLang];
    
    document.querySelectorAll('.lang-option').forEach(opt => {
        opt.classList.toggle('active', opt.dataset.lang === currentLang);
    });

    const dayWrap = document.getElementById('dayChips');
    dayWrap.innerHTML = t.days.map((d, i) => `<button class="dchip" data-i="${i}">${d}</button>`).join('');
    dayWrap.querySelectorAll('.dchip').forEach(b => b.onclick = () => {
        const i = +b.dataset.i;
        if(selDays.has(i)){selDays.delete(i);b.classList.remove('active');}
        else{selDays.add(i);b.classList.add('active');}
        repHint.textContent = repText();
    });

    if(detail.classList.contains('open')) {
        const activeTab = document.querySelector('.history-tab.active');
        if(activeTab) {
            const period = activeTab.textContent === t.today ? 'bugun' : (activeTab.textContent === t.yesterday ? 'kecha' : 'kunlar7');
            let currentKey = 'heart';
            for(let k in META) { if(META[k].title === document.getElementById('detailTitle').textContent) currentKey = k; }
            renderMetricHistory(currentKey, period, activeTab);
        }
    }
}

const langBtn = document.getElementById('langBtn');
const langDropdown = document.getElementById('langDropdown');
langBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    langDropdown.classList.toggle('open');
});
document.addEventListener('click', () => langDropdown.classList.remove('open'));
document.querySelectorAll('.lang-option').forEach(opt => {
    opt.addEventListener('click', () => {
        currentLang = opt.dataset.lang;
        localStorage.setItem('vita_lang', currentLang);
        applyTranslations();
        langDropdown.classList.remove('open');
    });
});

const pages = document.querySelectorAll('.page');
const navItems = document.querySelectorAll('[data-page]');
function go(name, el){
pages.forEach(p=>p.classList.toggle('active', p.id==='page-'+name));
document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('active'));
if(el && el.classList.contains('nav-btn')) el.classList.add('active');
if(name==='home') document.querySelector('.nav-btn[data-page="home"]').classList.add('active');
document.getElementById('notifPanel').classList.remove('open');
}
navItems.forEach(b=>b.addEventListener('click',()=>go(b.dataset.page,b)));
let isConnected = true;
const connBtn = document.getElementById('connBtn');
const connText = document.getElementById('connText');
const connectOverlay = document.getElementById('connectOverlay');
const connectBack = document.getElementById('connectBack');
const connectDeviceCard = document.getElementById('connectDeviceCard');
const connectIconBox = document.getElementById('connectIconBox');
const connectDeviceName = document.getElementById('connectDeviceName');
const connectDeviceStatus = document.getElementById('connectDeviceStatus');
const connectActionBtn = document.getElementById('connectActionBtn');
const connectActionText = document.getElementById('connectActionText');
function updateConnUI() {
const t = translations[currentLang];
if (isConnected) {
connText.textContent = t.connected;
connBtn.classList.add('connected');
} else {
connText.textContent = t.disconnected;
connBtn.classList.remove('connected');
}
}
updateConnUI();
connBtn.addEventListener('click', () => { connectOverlay.classList.add('open'); });
connectBack.addEventListener('click', () => { connectOverlay.classList.remove('open'); });
function toggleConnectionProfile() { connBtn.click(); }
connectActionBtn.addEventListener('click', () => {
const t = translations[currentLang];
if (isConnected) {
connectActionBtn.disabled = true;
connectActionText.textContent = t.disconnect + "...";
setTimeout(() => {
isConnected = false;
updateConnUI();
connectDeviceCard.classList.remove('scanning');
connectIconBox.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V4h6v3"/><path d="M9 17v3h6v-3"/><path d="M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/><line x1="4" y1="4" x2="20" y2="20"/></svg>';
connectIconBox.style.color = 'var(--muted)';
connectIconBox.style.background = 'var(--bg-2)';
connectDeviceName.textContent = t.disconnected;
connectDeviceStatus.textContent = t.connect;
connectActionBtn.disabled = false;
connectActionBtn.classList.add('active');
connectActionText.textContent = t.connect;
showToast(t.disconnected);
}, 1000);
} else {
connectActionBtn.disabled = true;
connectActionText.textContent = t.searching;
connectDeviceCard.classList.add('scanning');
connectIconBox.style.color = 'var(--primary)';
connectIconBox.style.background = 'var(--surface)';
connectIconBox.innerHTML = '<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 7V4h6v3"/><path d="M9 17v3h6v-3"/><path d="M7 7h10a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1z"/></svg>';
connectDeviceName.textContent = t.searching;
connectDeviceStatus.textContent = t.searching;
setTimeout(() => {
isConnected = true;
updateConnUI();
connectDeviceCard.classList.remove('scanning');
connectIconBox.style.color = 'var(--primary)';
connectIconBox.style.background = 'var(--primary-soft)';
connectDeviceName.textContent = "VitaWatch Pro";
connectDeviceStatus.textContent = t.connected_to_system;
connectActionBtn.disabled = false;
connectActionBtn.classList.remove('active');
connectActionText.textContent = t.disconnect;
showToast("VitaWatch Pro " + t.connected.toLowerCase());
}, 2500);
}
});
const chatContainer = document.getElementById('chatContainer');
const chatInput = document.getElementById('chatInput');
const chatSendBtn = document.getElementById('chatSendBtn');
function addMessage(text, isUser = false) {
const msgDiv = document.createElement('div');
msgDiv.className = `chat-msg ${isUser ? 'user' : 'ai'}`;
msgDiv.innerHTML = `<div class="chat-avatar">${isUser ? '👤' : '🤖'}</div><div class="chat-bubble">${text}</div>`;
chatContainer.appendChild(msgDiv);
chatContainer.scrollTop = chatContainer.scrollHeight;
}
function showTyping() {
const typingDiv = document.createElement('div');
typingDiv.className = 'chat-msg ai typing-msg';
typingDiv.innerHTML = `<div class="chat-avatar">🤖</div><div class="chat-bubble"><div class="typing-indicator"><span></span><span></span><span></span></div></div>`;
chatContainer.appendChild(typingDiv);
chatContainer.scrollTop = chatContainer.scrollHeight;
return typingDiv;
}
function handleChat() {
const text = chatInput.value.trim();
if (!text) return;
addMessage(text, true);
chatInput.value = '';
const typingEl = showTyping();
setTimeout(() => {
typingEl.remove();
let response = translations[currentLang].ai_greeting;
const lowerText = text.toLowerCase();
if (lowerText.includes('салом') || lowerText.includes('привет') || lowerText.includes('salom')) response = translations[currentLang].greeting + " " + (currentLang === 'ru' ? "Ваши показатели в норме." : "Сизнинг бугунги кўрсаткичларингиз яхши.");
else if (lowerText.includes('юрак') || lowerText.includes('серд') || lowerText.includes('yurak')) response = currentLang === 'ru' ? "Ваш пульс в норме (76 уд/мин)." : "Сизнинг юрак уришингиз меъёрида (76 bpm).";
else if (lowerText.includes('температур') || lowerText.includes('харорат') || lowerText.includes('harorat')) response = currentLang === 'ru' ? "Ваша температура 36.6°C, это нормально." : "Тана ҳароратингиз 36.6°C, бу нормал кўрсаткич.";
addMessage(response, false);
}, 1500);
}
chatSendBtn.addEventListener('click', handleChat);
chatInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleChat(); });
const bell = document.getElementById('bellBtn');
const panel = document.getElementById('notifPanel');
bell.addEventListener('click',e=>{e.stopPropagation();panel.classList.toggle('open');});
document.addEventListener('click',e=>{ if(!panel.contains(e.target)&&!bell.contains(e.target)) panel.classList.remove('open'); });
const track = document.getElementById('track');
const dots = document.getElementById('dots').children;
let idx = 0;
const total = track.children.length;
function updateDots(){
const i = Math.round(track.scrollLeft / track.clientWidth);
for(let d=0; d<dots.length; d++) dots[d].classList.toggle('on', d===i);
idx = i;
}
track.addEventListener('scroll',()=>{ clearTimeout(track._t); track._t=setTimeout(updateDots,80); });
setInterval(()=>{
idx = (idx+1)%total;
track.scrollTo({left: idx*track.clientWidth, behavior:'smooth'});
},3500);
const ICON = {
heart:`<svg class="ic-anim" viewBox="0 0 24 24" fill="currentColor"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>`,
temp:`<svg class="ic-anim" viewBox="0 0 24 24" fill="none"><path d="M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z" stroke="currentColor" stroke-width="2"/><rect class="merc" x="10.2" y="6.4" width="2.6" height="9.6" rx="1.3" fill="currentColor"/><circle cx="11.5" cy="18.2" r="2.5" fill="currentColor"/></svg>`,
drop:`<svg class="ic-anim" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5C12 2.5 6 9 6 14a6 6 0 0 0 12 0c0-5-6-11.5-6-11.5z"/></svg>`,
pulse:`<svg class="ic-anim wide" viewBox="0 0 48 24" fill="none"><path class="pl" d="M1 12 H10 l3 -8 4 16 3 -11 2 3 H47" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`
};
const META = {
heart:{title:"Yurak impulsi",accent:"linear-gradient(150deg,#F2566E,#FF8A8A)",color:"#F2566E",value:"76",unit:"zarba/daqiqa · bpm",cls:"heart-c",
stats:[["58","Eng past"],["74","O'rtacha"],["132","Eng yuqori"]],
history: {
bugun: { stats: [["58","Eng past"],["74","O'rtacha"],["132","Eng yuqori"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [62, 58, 74, 85, 78, 72] },
kecha: { stats: [["55","Eng past"],["72","O'rtacha"],["128","Eng yuqori"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [60, 55, 70, 82, 75, 68] },
kunlar7: { stats: [["52","Eng past"],["73","O'rtacha"],["135","Eng yuqori"]], hours: ["Dush","Sesh","Chor","Pay","Jum","Shan","Yak"], series: [73, 74, 72, 75, 71, 69, 74] }
}},
temp:{title:"Tana harorati",accent:"linear-gradient(150deg,#FF9F45,#FF6B6B)",color:"#FF9F45",value:"36.6",unit:"°C · normal",cls:"temp-c",
stats:[["36.2","Min"],["36.6","O'rtacha"],["37.1","Maks"]], act:"Haroratni o'lchash",
history: {
bugun: { stats: [["36.2","Min"],["36.5","O'rtacha"],["36.8","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [36.3, 36.2, 36.5, 36.7, 36.6, 36.4] },
kecha: { stats: [["36.1","Min"],["36.4","O'rtacha"],["36.7","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [36.2, 36.1, 36.4, 36.6, 36.5, 36.3] },
kunlar7: { stats: [["36.0","Min"],["36.4","O'rtacha"],["36.8","Maks"]], hours: ["Dush","Sesh","Chor","Pay","Jum","Shan","Yak"], series: [36.4, 36.5, 36.3, 36.6, 36.4, 36.2, 36.5] }
}},
drop:{title:"Qondagi kislorod",accent:"linear-gradient(150deg,#5B8DEF,#7C5CFF)",color:"#5B8DEF",value:"98",unit:"SpO₂ · %",cls:"drop-c",
stats:[["95","Min"],["97","O'rtacha"],["99","Maks"]], act:"Kislorodni o'lchash",
history: {
bugun: { stats: [["95","Min"],["97","O'rtacha"],["99","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [97, 96, 98, 99, 97, 96] },
kecha: { stats: [["94","Min"],["97","O'rtacha"],["99","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [96, 95, 98, 99, 98, 97] },
kunlar7: { stats: [["94","Min"],["97","O'rtacha"],["99","Maks"]], hours: ["Dush","Sesh","Chor","Pay","Jum","Shan","Yak"], series: [97, 98, 97, 96, 98, 97, 97] }
}},
pulse:{title:"Kunlik faollik",accent:"linear-gradient(150deg,#16B8A6,#0EA5E9)",color:"#16B8A6",value:"5 280",unit:"qadam · bugun",cls:"pulse-c",
stats:[["1200","Min"],["4500","O'rtacha"],["8200","Maks"]], act:"Yangilash",
history: {
bugun: { stats: [["1200","Min"],["4500","O'rtacha"],["8200","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [1200, 1500, 4500, 6800, 7500, 8200] },
kecha: { stats: [["1000","Min"],["4200","O'rtacha"],["7800","Maks"]], hours: ["00:00","04:00","08:00","12:00","16:00","20:00"], series: [1000, 1200, 4200, 6500, 7200, 7800] },
kunlar7: { stats: [["1000","Min"],["4300","O'rtacha"],["8500","Maks"]], hours: ["Dush","Sesh","Chor","Pay","Jum","Shan","Yak"], series: [4300, 4500, 3800, 5200, 4100, 8500, 6000] }
}}
};

function generateDayChart(hours, vals, color) {
const max = Math.max(...vals) || 1;
const min = Math.min(...vals);
let html = `<div class="day-bar-chart">`;
vals.forEach((v, i) => {
const heightPercent = max === min ? 50 : Math.max(15, ((v - (min * 0.8)) / (max - (min * 0.8))) * 100);
html += `<div class="chart-bar-item"><div class="chart-bar-val">${v}</div><div class="chart-bar-pill" style="height: ${heightPercent}px; background: ${color}; box-shadow: 0 4px 10px -2px ${color}66;"></div><div class="chart-bar-lbl">${hours[i]}</div></div>`;
});
html += `</div>`;
return html;
}

function renderMetricHistory(key, period, btnElement) {
document.querySelectorAll('.history-tab').forEach(t => t.classList.remove('active'));
if(btnElement) btnElement.classList.add('active');

const m = META[key];
const data = m.history[period];
const container = document.getElementById('historyChartContainer');
const statsContainer = document.getElementById('dStats');
const t = translations[currentLang];

statsContainer.innerHTML = data.stats.map(s =>
`<div class="d-stat"><div class="sv">${s[0]}</div><div class="sl">${t[s[1]] || s[1]}</div></div>`
).join('');

const titleText = period === 'kunlar7' ? t.chart_7days : t.chart_today;
const spanText = period === 'kunlar7' ? t.span_7days : t.span_1day;

container.innerHTML = `
<div class="d-chart">
<div class="ch-h">
${titleText}
<span>${spanText}</span>
</div>
${generateDayChart(data.hours, data.series, m.color)}
</div>
`;
}

const detail=document.getElementById('detail');
function openDetail(key){
const m=META[key]; if(!m) return;
const t = translations[currentLang];
document.getElementById('detailTitle').textContent = m.title;
let bottomContent = '';
if (m.history) {
bottomContent = `
<div class="history-tabs">
<button class="history-tab active" onclick="renderMetricHistory('${key}', 'bugun', this)">${t.today}</button>
<button class="history-tab" onclick="renderMetricHistory('${key}', 'kecha', this)">${t.yesterday}</button>
<button class="history-tab" onclick="renderMetricHistory('${key}', 'kunlar7', this)">${t.last_7_days}</button>
</div>
<div id="historyChartContainer" class="history-chart-container"></div>
`;
} else {
bottomContent = `<button class="d-act" id="dAct" style="background:${m.color}">${m.act}</button>`;
}
document.getElementById('detailBody').innerHTML=`
<div class="d-hero" style="background:${m.accent}">
<div class="di ${m.cls}">${ICON[key]}</div>
<div class="dv" id="dVal">${m.value}</div>
<div class="du">${m.unit}</div>
</div>
<div class="d-stats" id="dStats">
${m.stats.map(s=>`<div class="d-stat"><div class="sv">${s[0]}</div><div class="sl">${t[s[1]] || s[1]}</div></div>`).join('')}
</div>
${bottomContent}`;
detail.classList.add('open');
if (m.history) {
renderMetricHistory(key, 'bugun', document.querySelector('.history-tab.active'));
} else {
document.getElementById('dAct').onclick=()=>runMeasure(key,m);
}
}
function runMeasure(key,m){
const el=document.getElementById('dVal'), act=document.getElementById('dAct');
const t = translations[currentLang];
if(act._busy) return; act._busy=true; const old=act.textContent; act.textContent=t.measuring;
let t2=0;
const iv=setInterval(()=>{ t2+=0.05;
if(key==='temp')      el.textContent=(36+Math.random()*1.4).toFixed(1);
else if(key==='drop') el.textContent=94+Math.floor(Math.random()*6);
else if(key==='pulse')el.textContent=(4000+Math.floor(Math.random()*4500)).toLocaleString('ru-RU').replace(/,/g,' ');
else                  el.textContent=64+Math.floor(Math.random()*24);
if(t2>=2){ clearInterval(iv);
if(key==='temp')      el.textContent=(36.4+Math.random()*0.4).toFixed(1);
else if(key==='drop') el.textContent=96+Math.floor(Math.random()*4);
else if(key==='pulse')el.textContent=m.value;
else                  el.textContent=70+Math.floor(Math.random()*8);
act.textContent=old; act._busy=false;
}
},50);
}
document.querySelectorAll('.metric').forEach(c=>c.addEventListener('click',()=>openDetail(c.dataset.metric)));
document.getElementById('detailBack').onclick=()=>detail.classList.remove('open');
const sheet=document.getElementById('sheet');
const colH=document.getElementById('colH'), colM=document.getElementById('colM');
document.getElementById('fabBtn').addEventListener('click',()=>{
sheet.classList.add('open');
renderAlarms();
requestAnimationFrame(()=>{
colH.scrollTop=(+colH.dataset.value||0)*50;
colM.scrollTop=(+colM.dataset.value||0)*50;
styleCol(colH); styleCol(colM);
});
});
document.getElementById('sheetCancel').onclick=()=>sheet.classList.remove('open');
const dayWrap=document.getElementById('dayChips');
const repHint=document.getElementById('repHint');
const selDays=new Set();
function repText(){
const t = translations[currentLang];
if(selDays.size===0) return t.once;
if(selDays.size===7) return t.daily;
return (currentLang === 'ru' ? "Повтор: " : "Такрорланади: ") + [...selDays].sort((a,b)=>a-b).map(i=>translations[currentLang].days[i]).join(", ");
}
function styleCol(col){
const r=col.scrollTop/50, idx=Math.round(r);
col.dataset.value=Math.max(0,Math.min(col.children.length-1,idx));
[...col.children].forEach((it,i)=>{
const d=Math.abs(i-r);
it.style.opacity=Math.max(.2,1-d*0.42);
it.style.transform=`scale(${Math.max(.74,1-d*0.13)})`;
it.style.color=d<0.5?'var(--ink)':'var(--muted)';
});
}
function buildWheel(col,n,init){
col.innerHTML=Array.from({length:n},(_,i)=>`<div class="it">${String(i).padStart(2,'0')}</div>`).join('');
col.dataset.value=init;
requestAnimationFrame(()=>{ col.scrollTop=init*50; styleCol(col); });
let raf;
col.addEventListener('scroll',()=>{ if(raf)return; raf=requestAnimationFrame(()=>{styleCol(col);raf=null;}); });
}
const _now=new Date();
buildWheel(colH,24,_now.getHours());
buildWheel(colM,60,_now.getMinutes());
const PATHS={
heart:"M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z",
thermo:"M14 14.76V3.5a2.5 2.5 0 0 0-5 0v11.26a4.5 4.5 0 1 0 5 0z",
drop:"M12 2.5C12 2.5 6 9 6 14a6 6 0 0 0 12 0c0-5-6-11.5-6-11.5z",
bell:"M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9 M13.73 21a2 2 0 0 1-3.46 0"
};
const typeMap={Yurak:['ic-heart','heart',true],Harorat:['ic-temp','thermo',false],Kislorod:['ic-oxy','drop',true],Umumiy:['ic-step','bell',false],Eslatma:['ic-primary','bell',false]};
function addNotif(title,sub,type){
const [cls,pk,filled]=typeMap[type]||['ic-step','bell',false];
const d=document.createElement('div'); d.className='notif-item';
d.innerHTML=`<div class="ni ${cls}"><svg width="18" height="18" viewBox="0 0 24 24" fill="${filled?'currentColor':'none'}" stroke="${filled?'none':'currentColor'}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="${PATHS[pk]}"/></svg></div><div><div class="nt">${title}</div><div class="nd">${sub}</div></div>`;
panel.querySelector('.nh').after(d);
}
let toastEl;
function showToast(msg){
if(!toastEl){toastEl=document.createElement('div');toastEl.className='toast';document.querySelector('.phone').appendChild(toastEl);}
toastEl.textContent=msg; toastEl.classList.add('show');
clearTimeout(toastEl._t); toastEl._t=setTimeout(()=>toastEl.classList.remove('show'),1900);
}
const z2 = n => String(n).padStart(2,'0');
let alarms = [];
function loadAlarms(){ try{ alarms = JSON.parse(localStorage.getItem('vita_alarms')||'[]'); }catch(e){ alarms=[]; } }
function saveAlarms(){ try{ localStorage.setItem('vita_alarms', JSON.stringify(alarms.map(({_fired,...a})=>a))); }catch(e){} }
function renderAlarms(){
const wrap=document.getElementById('alarmList');
const t = translations[currentLang];
if(!alarms.length){ wrap.innerHTML=`<div class="empty">${t.saved_reminders === 'Сақланган эслатмалар' ? 'Ҳали эслатма йўқ' : (t.saved_reminders === 'Сохраненные напоминания' ? 'Нет напоминаний' : 'Hali eslatma yo\'q')} — ${t.add_new.toLowerCase()}</div>`; return; }
wrap.innerHTML=alarms.map((al,idx)=>{
const rep = al.days.length===0 ? t.once : (al.days.length===7 ? t.daily : [...al.days].sort((a,b)=>a-b).map(i=>translations[currentLang].days[i]).join(', '));
const title = al.title || (currentLang === 'ru' ? 'Напоминание' : (currentLang === 'uz_cyrl' ? 'Эслатма' : 'Eslatma'));
return `<div class="alarm ${al.enabled?'':'off'}">
<div class="ainfo">
<div class="atime">${z2(al.hh)}:${z2(al.mm)}</div>
<div class="atype">${title} · <span class="arep">${rep}</span></div>
</div>
<div class="sw ${al.enabled?'on':''}" data-i="${idx}"></div>
<button class="adel" data-del="${idx}" aria-label="O'chirish"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
</div>`;
}).join('');
wrap.querySelectorAll('.sw').forEach(s=>s.onclick=()=>{ const i=+s.dataset.i; alarms[i].enabled=!alarms[i].enabled; saveAlarms(); renderAlarms(); });
wrap.querySelectorAll('.adel').forEach(d=>d.onclick=()=>{ const i=+d.dataset.del; alarms.splice(i,1); saveAlarms(); renderAlarms(); });
}
function ensureNotifPerm(){ if('Notification' in window && Notification.permission==='default'){ try{ Notification.requestPermission(); }catch(e){} } }

function playLoudAlarm() {
    try {
        const A = window.AudioContext || window.webkitAudioContext;
        const ctx = new A();
        let count = 0;
        const maxBeeps = 4; 
        function triggerBeep() {
            if (count >= maxBeeps) { setTimeout(() => ctx.close(), 500); return; }
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.type = 'square'; osc.frequency.value = 1200; 
            const now = ctx.currentTime;
            gain.gain.setValueAtTime(0.3, now); 
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.15);
            osc.start(now); osc.stop(now + 0.15);
            count++; setTimeout(triggerBeep, 250); 
        }
        triggerBeep();
    } catch (e) { console.error("Audio error:", e); }
}

function fireAlarm(al){
const t_str=`${z2(al.hh)}:${z2(al.mm)}`;
const title = al.title || (currentLang === 'ru' ? 'Напоминание' : (currentLang === 'uz_cyrl' ? 'Эслатма' : 'Eslatma'));
const t = translations[currentLang];
addNotif(`⏰ ${title}`, `Hozir — soatga yuborildi ⌚`, 'Eslatma');
showToast(`⌚ ${title} — ${t_str}`);
playLoudAlarm();
if('Notification' in window && Notification.permission==='granted'){ try{ new Notification('VitaWatch', {body:`${title} · ${t_str}`}); }catch(e){} }
}
function checkAlarms(){
const now=new Date(), h=now.getHours(), m=now.getMinutes(), jsDay=now.getDay();
const stamp=`${now.getFullYear()}-${now.getMonth()}-${now.getDate()} ${h}:${m}`;
let changed=false;
alarms.forEach(al=>{
if(!al.enabled || al.hh!==h || al.mm!==m) return;
if(al.days.length && !al.days.some(i=>((i+1)%7)===jsDay)) return;
if(al._fired===stamp) return;
al._fired=stamp;
fireAlarm(al);
if(al.days.length===0){ al.enabled=false; changed=true; }
});
if(changed){ saveAlarms(); renderAlarms(); }
}
document.getElementById('sheetSave').onclick=()=>{
const t = translations[currentLang];
const title = document.getElementById('alarmTitleInput').value.trim() || (currentLang === 'ru' ? 'Напоминание' : (currentLang === 'uz_cyrl' ? 'Эслатма' : 'Eslatma'));
const al={ id:Date.now(), title: title, hh:+(colH.dataset.value||0), mm:+(colM.dataset.value||0), days:[...selDays], enabled:true };
alarms.unshift(al);
saveAlarms(); 
renderAlarms();
ensureNotifPerm();
showToast(`"${title}" ${z2(al.hh)}:${z2(al.mm)} ${t.save === 'Сақлаш' ? 'га сақланди ✓' : (t.save === 'Сохранить' ? 'сохранено ✓' : 'saqlandi ✓')}`);
document.getElementById('alarmTitleInput').value = ''; 
};

loadAlarms();
renderAlarms();
applyTranslations();
checkAlarms();
setInterval(checkAlarms, 15000);
document.addEventListener('contextmenu', e=>e.preventDefault());
document.addEventListener('selectstart', e=>e.preventDefault());
document.addEventListener('copy', e=>e.preventDefault());
document.addEventListener('gesturestart',e=>e.preventDefault());
document.addEventListener('gesturechange',e=>e.preventDefault());
let _lastTap = 0;
document.addEventListener('touchend', e=>{
const now = Date.now();
if(now - _lastTap <= 320) e.preventDefault();
_lastTap = now;
}, {passive:false});
document.addEventListener('wheel', e=>{ if(e.ctrlKey) e.preventDefault(); }, {passive:false});
document.addEventListener('keydown', e=>{
if((e.ctrlKey||e.metaKey) && ['+','-','=','0'].includes(e.key)) e.preventDefault();
});
</script>
</body>
</html>