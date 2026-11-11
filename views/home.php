<?php
/* Public homepage. Included by controllers/dashboard-api.php when nobody is signed in:
   $setupMode and $usmHomeStats are already in scope. The page never renders a login form —
   every call to action leads to index.php?action=login_page. The guide clip is discovered
   exactly like the Panduan dialog discovers it, and every media URL carries ?v=<mtime> so a
   replaced file is fetched fresh instead of replaying a stale browser cache. */
$usmHomeVideoDir = USM_ROOT . '/media/manual/';
$usmHomeVideo = null;
if (is_file($usmHomeVideoDir . 'tarik-rekod-uid.mp4')) {
    $usmHomeVideo = array(
        'slug' => 'tarik-rekod-uid',
        'v' => (int)@filemtime($usmHomeVideoDir . 'tarik-rekod-uid.mp4'),
        'poster' => is_file($usmHomeVideoDir . 'tarik-rekod-uid.jpg') ? (int)@filemtime($usmHomeVideoDir . 'tarik-rekod-uid.jpg') : 0,
        'vtt' => is_file($usmHomeVideoDir . 'tarik-rekod-uid.vtt') ? (int)@filemtime($usmHomeVideoDir . 'tarik-rekod-uid.vtt') : 0,
    );
}
$usmHomeStats = isset($usmHomeStats) && is_array($usmHomeStats) ? $usmHomeStats : array('ok' => false, 'total' => 0, 'online' => 0, 'today' => 0);
$usmHomeLoginUrl = 'index.php?action=login_page';
$usmHomeCta = $setupMode ? 'Cipta akaun pentadbir' : 'Log masuk ke portal';
?>
<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="USMSmart — portal pengurusan kad Kampus Kejuruteraan UDA. Tarik rekod, semak UID, analisis dan eksport dalam satu ruang yang selamat.">
    <meta name="color-scheme" content="light dark">

<script id="usmsmart-per-tab-auth">
(function(){
  const key="usmsmart_tab_token";
  let token=sessionStorage.getItem(key);
  if(!token){const bytes=new Uint8Array(32);crypto.getRandomValues(bytes);token=Array.from(bytes,b=>b.toString(16).padStart(2,"0")).join("");sessionStorage.setItem(key,token);}
  const u=new URL(location.href);
  if(u.searchParams.get("usm_tab")!==token){u.searchParams.set("usm_tab",token);location.replace(u.pathname+u.search+u.hash);return;}
  const nativeFetch=window.fetch.bind(window);
  window.fetch=function(resource,options){
    try{const url=new URL(typeof resource==="string"?resource:resource.url,location.href);
      if(url.origin===location.origin){url.searchParams.set("usm_tab",token);resource=url.toString();}
    }catch(e){}
    const opts=Object.assign({},options||{});opts.headers=new Headers(opts.headers||{});opts.headers.set("X-USM-Tab",token);
    return nativeFetch(resource,opts);
  };
})();
</script>

    <title>USMSmart — Portal Pengurusan Kad Kampus</title>
    <meta name="description" content="USMSmart memusatkan pengurusan kad akses, sesi dan laporan Kampus Kejuruteraan USM dalam satu portal — selamat, pantas dan boleh dipercayai.">
    <link rel="preload" as="image" href="usmsmart_login_bg.jpg" fetchpriority="high">
    <link rel="preload" as="font" type="font/woff2" href="assets/vendor/fonts/plus-jakarta-sans-latin.woff2" crossorigin>
    <link rel="stylesheet" href="assets/vendor/fonts.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/fonts.css'); ?>">
    <link rel="stylesheet" href="assets/vendor/font-awesome.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/font-awesome.css'); ?>">
    <link rel="stylesheet" href="assets/usmsmart-home.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-home.css'); ?>">
    <link rel="stylesheet" href="assets/usmsmart-i18n.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.css'); ?>">

    <script id="usmsmart-home-theme">
    /* Applied before first paint so a stored dark choice never flashes white. */
    (function () {
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (saved ? saved === 'dark' : prefersDark) document.documentElement.classList.add('dark');
    })();
    </script>
    <script>document.documentElement.classList.add('js-reveal');</script>
</head>
<body>
    <a href="#hm-main" class="hm-skip">Langkau ke kandungan utama</a>

    <!-- Fixed navigation: transparent over the hero photograph, frosted surface once scrolled. -->
    <header class="hm-nav" id="hm-nav">
        <div class="hm-container hm-nav-inner">
            <a class="hm-brand" href="#top" aria-label="USMSmart — kembali ke atas">
                <span class="hm-brand-mark">USM</span>
                <span class="hm-brand-text"><span class="hm-brand-name">USMSmart</span><span class="hm-brand-sub">Kampus Kejuruteraan UDA</span></span>
            </a>
            <nav class="hm-nav-links" id="hm-nav-links" aria-label="Navigasi laman">
                <a href="#ciri" class="hm-nav-link">Ciri</a>
                <a href="#aliran" class="hm-nav-link">Aliran Kerja</a>
                <?php if ($usmHomeVideo): ?><a href="#demo" class="hm-nav-link">Demo</a><?php endif; ?>
                <a href="#faq" class="hm-nav-link">FAQ</a>
                <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-primary hm-nav-cta hm-nav-cta-mobile"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i><span><?php echo $setupMode ? 'Sediakan portal' : 'Log masuk'; ?></span></a>
            </nav>
            <div class="hm-nav-actions">
                <button type="button" class="hm-icon-btn" id="hm-theme-toggle" aria-label="Tukar kepada mod gelap" title="Mod gelap"><i class="fa-solid fa-moon" aria-hidden="true"></i></button>
                <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-primary hm-nav-cta"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i><span><?php echo $setupMode ? 'Sediakan portal' : 'Log masuk'; ?></span></a>
                <button type="button" class="hm-icon-btn hm-menu-btn" id="hm-menu-btn" aria-label="Buka menu" aria-expanded="false" aria-controls="hm-nav-links"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            </div>
        </div>
    </header>

    <main id="hm-main">
        <!-- HERO: the sign-in page's photograph and product window, without the form. -->
        <section class="hm-hero" id="top" aria-label="Pengenalan USMSmart">
            <div class="hm-hero-bg" aria-hidden="true">
                <div class="hm-hero-photo" id="hm-hero-photo"></div>
                <div class="hm-hero-scrim"></div>
                <span class="hm-aurora hm-aurora-a"></span>
                <span class="hm-aurora hm-aurora-b"></span>
                <span class="hm-aurora hm-aurora-c"></span>
                <div class="hm-grain"></div>
            </div>
            <div class="hm-container hm-hero-grid">
                <div class="hm-hero-copy">
                    <span class="hm-eyebrow hm-eyebrow-glass"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>PORTAL PENGURUSAN KAD</span>
                    <h1>Pengurusan yang lebih <span>pintar.</span></h1>
                    <p class="hm-hero-lede">Satu ruang untuk mengurus rekod pelajar, kad akses dan aktiviti sistem — daripada tarikan data sehingga semakan UID, eksport dan penghantaran ke Sysweb.</p>
                    <div class="hm-hero-cta">
                        <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-primary hm-btn-lg"><span><?php echo $usmHomeCta; ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        <a href="#ciri" class="hm-btn hm-btn-ghost hm-btn-lg">Terokai ciri</a>
                        <?php if ($usmHomeVideo): ?>
                        <a href="#demo" class="hm-play-link"><span class="hm-play-dot"><i class="fa-solid fa-play" aria-hidden="true"></i></span><span>Tonton demo<small>34 saat</small></span></a>
                        <?php endif; ?>
                    </div>
                    <ul class="hm-hero-ticks">
                        <li><i class="fa-solid fa-circle-check" aria-hidden="true"></i>Rekod pelajar dan kad dalam satu tempat</li>
                        <li><i class="fa-solid fa-chart-line" aria-hidden="true"></i>Analitik dan sejarah aktiviti yang jelas</li>
                        <li><i class="fa-solid fa-lock" aria-hidden="true"></i>Akses mengikut peranan pengguna</li>
                    </ul>
                </div>
                <div class="hm-hero-visual reveal reveal-from-right">
                    <div class="preview" aria-hidden="true">
                        <div class="preview-bar">
                            <span class="preview-dots"><i></i><i></i><i></i></span>
                            <span class="preview-bar-title">Modul USMSmart</span>
                            <span class="preview-tag">PRATONTON</span>
                        </div>
                        <div class="preview-stage">
                            <div class="preview-track">
                                <!-- Tarik rekod -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-cloud-arrow-down"></i>Tarik Rekod</span><em>Transporter</em></div>
                                    <p class="preview-desc">Tarik rekod pelajar dan staf terus dari pangkalan data kampus.</p>
                                    <div class="preview-toolbar">
                                        <span class="preview-chip"><i class="fa-solid fa-filter"></i>StatusKad 02</span>
                                        <span class="preview-chip"><i class="fa-solid fa-graduation-cap"></i>Pelajar</span>
                                        <span class="preview-action"><i class="fa-solid fa-cloud-arrow-down"></i>Tarik rekod</span>
                                    </div>
                                    <div class="preview-panel">
                                        <div class="preview-panel-head"><span>Rekod diterima</span><b>128</b></div>
                                        <div class="preview-progress"><span></span></div>
                                        <div class="preview-note"><i class="fa-solid fa-circle-check"></i>Sedia untuk semakan UID</div>
                                    </div>
                                </div>
                                <!-- Semakan UID -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-shield-halved"></i>Semakan UID</span><em>StatusKad 02</em></div>
                                    <p class="preview-desc">Sahkan UID layak, kesan pendua dan eksport senarai bersih.</p>
                                    <div class="preview-head"><span>Nama</span><span>UID</span><span>Semakan</span></div>
                                    <div class="preview-row">
                                        <span class="preview-name">Nur Aisyah</span>
                                        <span class="preview-uid">A19F2C4B</span>
                                        <span class="preview-badge"><span class="preview-badge-ok"><i class="fa-solid fa-check"></i>Layak</span></span>
                                    </div>
                                    <div class="preview-row">
                                        <span class="preview-name">Muhammad Danish</span>
                                        <span class="preview-uid">A19F2C4B</span>
                                        <span class="preview-badge"><span class="preview-badge-bad"><i class="fa-solid fa-triangle-exclamation"></i>Berulang</span></span>
                                    </div>
                                    <div class="preview-row">
                                        <span class="preview-name">Tan Wei Ling</span>
                                        <span class="preview-uid">7C3E91AF</span>
                                        <span class="preview-badge"><span class="preview-badge-ok"><i class="fa-solid fa-check"></i>Layak</span></span>
                                    </div>
                                    <div class="preview-uid-summary"><span>2 layak · 1 disekat</span><span><i class="fa-solid fa-file-csv"></i>Eksport CSV</span></div>
                                </div>
                                <!-- Analisis grafik -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-chart-column"></i>Analisis Grafik</span><em>2026</em></div>
                                    <p class="preview-desc">Pantau jumlah rekod, kadar layak dan taburan sekolah.</p>
                                    <div class="preview-kpis">
                                        <span><b>1,284</b><small>rekod</small></span>
                                        <span><b>98.2%</b><small>layak</small></span>
                                        <span><b>46</b><small>sekolah</small></span>
                                    </div>
                                    <div class="preview-chart">
                                        <span style="--h:38%"></span><span style="--h:61%"></span><span style="--h:47%"></span><span style="--h:76%"></span><span style="--h:55%"></span><span style="--h:88%"></span><span style="--h:69%"></span>
                                    </div>
                                </div>
                                <!-- Sysweb -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-network-wired"></i>Integrasi Sysweb</span><em class="preview-live">Gerbang aktif</em></div>
                                    <p class="preview-desc">Hantar rekod ke gerbang Sysweb dan pantau statusnya.</p>
                                    <div class="preview-stats">
                                        <div><small>Jumlah</small><b>1,596</b></div>
                                        <div><small>Pelajar</small><b>1,284</b></div>
                                        <div><small>Staf</small><b>312</b></div>
                                    </div>
                                    <div class="preview-transfer">
                                        <div class="preview-transfer-head"><span>Penghantaran rekod</span><span>00000000+UID</span></div>
                                        <div class="preview-progress is-ok"><span></span></div>
                                        <div class="preview-note"><i class="fa-solid fa-circle-check"></i>Gerbang Sysweb menerima rekod</div>
                                    </div>
                                </div>
                                <!-- Direktori kad -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-id-card-clip"></i>Direktori Kad</span><em>Pelajar &amp; Staf</em></div>
                                    <p class="preview-desc">Cari pemegang kad pelajar dan staf dengan status terkini.</p>
                                    <div class="preview-person"><span class="preview-avatar">NA</span><span class="preview-person-name">Nur Aisyah<small>2024123456</small></span><span class="preview-chip is-ok">Kad aktif</span></div>
                                    <div class="preview-person"><span class="preview-avatar">MD</span><span class="preview-person-name">Muhammad Danish<small>2024123457</small></span><span class="preview-chip is-ok">Kad aktif</span></div>
                                    <div class="preview-person"><span class="preview-avatar">TW</span><span class="preview-person-name">Tan Wei Ling<small>2024123458</small></span><span class="preview-chip is-ok">Kad aktif</span></div>
                                </div>
                                <!-- Log audit -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-clock-rotate-left"></i>Sejarah &amp; Log Audit</span><em>Hari ini</em></div>
                                    <p class="preview-desc">Jejak tarikan, eksport dan perubahan akaun mengikut masa.</p>
                                    <div class="preview-log"><span class="preview-log-icon"><i class="fa-solid fa-cloud-arrow-down"></i></span><span class="preview-log-main">Tarik rekod<small>128 rekod pelajar</small></span><time>10:42</time></div>
                                    <div class="preview-log"><span class="preview-log-icon"><i class="fa-solid fa-file-export"></i></span><span class="preview-log-main">Eksport UDA<small>42 UID dihantar</small></span><time>10:45</time></div>
                                    <div class="preview-log"><span class="preview-log-icon"><i class="fa-solid fa-user-plus"></i></span><span class="preview-log-main">Akaun baharu<small>Peranan viewer</small></span><time>10:48</time></div>
                                </div>
                                <!-- Pengurusan akaun -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-users-gear"></i>Pengurusan Akaun</span><em>Peranan</em></div>
                                    <p class="preview-desc">Urus pengguna serta peranan admin dan viewer dengan selamat.</p>
                                    <div class="preview-person"><span class="preview-avatar">AM</span><span class="preview-person-name">aminah<small>Akses penuh</small></span><span class="preview-chip is-violet">Admin</span></div>
                                    <div class="preview-person"><span class="preview-avatar">FR</span><span class="preview-person-name">farid<small>Baca sahaja</small></span><span class="preview-chip">Viewer</span></div>
                                    <div class="preview-person"><span class="preview-avatar">VW</span><span class="preview-person-name">viewer01<small>Baca sahaja</small></span><span class="preview-chip">Viewer</span></div>
                                </div>
                                <!-- Kualiti data -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-triangle-exclamation"></i>Kualiti Data</span><em>Integriti rekod</em></div>
                                    <p class="preview-desc">Kesan rekod tidak lengkap sebelum ia merebak ke sistem.</p>
                                    <div class="preview-stats">
                                        <div><small>Lengkap</small><b>1,142</b></div>
                                        <div class="is-amber"><small>Semak</small><b>86</b></div>
                                        <div class="is-rose"><small>Tidak lengkap</small><b>56</b></div>
                                    </div>
                                    <div class="preview-issues">
                                        <div class="preview-issue"><span><i class="fa-solid fa-user-slash"></i>Nama kosong</span><b>12</b></div>
                                        <div class="preview-issue"><span><i class="fa-solid fa-link-slash"></i>UID belum dikaitkan</span><b>74</b></div>
                                    </div>
                                </div>
                                <!-- Ulangan slaid pertama: menjadikan gelung tidak kelihatan. -->
                                <div class="preview-scene">
                                    <div class="preview-scene-head"><span><i class="fa-solid fa-cloud-arrow-down"></i>Tarik Rekod</span><em>Transporter</em></div>
                                    <p class="preview-desc">Tarik rekod pelajar dan staf terus dari pangkalan data kampus.</p>
                                    <div class="preview-toolbar">
                                        <span class="preview-chip"><i class="fa-solid fa-filter"></i>StatusKad 02</span>
                                        <span class="preview-chip"><i class="fa-solid fa-graduation-cap"></i>Pelajar</span>
                                        <span class="preview-action"><i class="fa-solid fa-cloud-arrow-down"></i>Tarik rekod</span>
                                    </div>
                                    <div class="preview-panel">
                                        <div class="preview-panel-head"><span>Rekod diterima</span><b>128</b></div>
                                        <div class="preview-progress"><span></span></div>
                                        <div class="preview-note"><i class="fa-solid fa-circle-check"></i>Sedia untuk semakan UID</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="preview-foot">
                            <span class="preview-foot-label">
                                <span>&ldquo;Setiap rekod tepat memudahkan urusan.&rdquo;</span>
                                <span>&ldquo;Kerja hari ini, kejayaan esok.&rdquo;</span>
                                <span>&ldquo;Mulakan yakin, teruskan tekun.&rdquo;</span>
                            </span>
                            <span class="preview-cycle"><i></i></span>
                        </div>
                    </div>
                </div>

                <!-- Live counters: the same public numbers the sign-in page shows, refreshed by a 30s poll. -->
                <div class="hm-stats" id="hm-stats" role="group" aria-label="Statistik penggunaan portal">
                    <div class="hm-stat reveal reveal-from-left"><span class="hm-stat-value" data-stat="total"><?php echo number_format((int)$usmHomeStats['total']); ?></span><span class="hm-stat-label">Pengguna berdaftar</span></div>
                    <div class="hm-stat reveal reveal-from-below"><span class="hm-stat-value" data-stat="online"><?php echo number_format((int)$usmHomeStats['online']); ?></span><span class="hm-stat-label"><i class="fa-solid fa-circle" aria-hidden="true"></i>Dalam talian</span></div>
                    <div class="hm-stat reveal reveal-from-below"><span class="hm-stat-value" data-stat="today"><?php echo number_format((int)$usmHomeStats['today']); ?></span><span class="hm-stat-label">Log masuk hari ini</span></div>
                    <div class="hm-stat reveal reveal-from-right"><span class="hm-stat-value">8</span><span class="hm-stat-label">Modul teras</span></div>
                </div>
            </div>
            <a class="hm-scroll-cue" href="#ciri" aria-label="Tatal ke ciri portal"><span>Tatal untuk terokai</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></a>
        </section>

        <!-- FEATURES -->
        <section class="hm-section" id="ciri" aria-labelledby="hm-ciri-title">
            <div class="hm-container">
                <div class="hm-section-head reveal reveal-from-below">
                    <span class="hm-eyebrow">MODUL TERAS</span>
                    <h2 id="hm-ciri-title">Satu portal. <span>Lapan modul teras.</span></h2>
                    <p>Setiap modul dibina untuk satu tugas dan disiapkan sepenuhnya — daripada tarikan data pertama sehingga rekod bersih dihantar keluar.</p>
                </div>
                <div class="hm-feature-grid">
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#6d28d9;--feat-soft:#f2efff">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i></span><span class="hm-feature-chip">Transporter</span></div>
                        <h3>Tarik Rekod</h3>
                        <p>Tarik rekod pelajar dan staf terus daripada pangkalan data kampus, dengan penapis StatusKad dan jenis pemegang.</p>
                    </article>
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#059669;--feat-soft:#ecfdf5">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><span class="hm-feature-chip">Integriti</span></div>
                        <h3>Semakan UID</h3>
                        <p>Sahkan UID lapan aksara, kesan pendua dan asingkan rekod layak sebelum apa-apa dihantar keluar.</p>
                    </article>
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#4f46e5;--feat-soft:#eef2ff">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-chart-column" aria-hidden="true"></i></span><span class="hm-feature-chip">Analitik</span></div>
                        <h3>Analisis Grafik</h3>
                        <p>KPI, carta taburan sekolah dan trend tarikh luput — ringkasan yang boleh dibaca dalam satu pandangan.</p>
                    </article>
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#0284c7;--feat-soft:#f0f9ff">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-id-card-clip" aria-hidden="true"></i></span><span class="hm-feature-chip">Direktori</span></div>
                        <h3>Direktori Kad</h3>
                        <p>Cari, tapis dan isih pemegang kad pelajar serta staf dengan status kad yang sentiasa terkini.</p>
                    </article>
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#d97706;--feat-soft:#fffbeb">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></span><span class="hm-feature-chip">Gerbang</span></div>
                        <h3>Integrasi Sysweb</h3>
                        <p>Hantar rekod ke gerbang Sysweb dan pantau status penghantaran tanpa meninggalkan portal.</p>
                    </article>
                    <article class="hm-feature reveal reveal-from-below" style="--feat:#e11d48;--feat-soft:#fff1f2">
                        <div class="hm-feature-top"><span class="hm-feature-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></span><span class="hm-feature-chip">Keselamatan</span></div>
                        <h3>Akaun &amp; Peranan</h3>
                        <p>Peranan Admin dan Viewer, permintaan perubahan untuk kelulusan, serta log audit bagi setiap tindakan.</p>
                    </article>
                </div>
            </div>
        </section>

        <!-- WORKFLOW -->
        <section class="hm-section hm-section-tint" id="aliran" aria-labelledby="hm-aliran-title">
            <div class="hm-container">
                <div class="hm-section-head reveal reveal-from-below">
                    <span class="hm-eyebrow">ALIRAN KERJA</span>
                    <h2 id="hm-aliran-title">Empat langkah. <span>Siap.</span></h2>
                    <p>Aliran yang sama setiap kali — pendek, jelas dan boleh dijejak dari awal hingga akhir.</p>
                </div>
                <ol class="hm-steps">
                    <li class="hm-step reveal reveal-from-below">
                        <span class="hm-step-num">01</span>
                        <span class="hm-step-icon"><i class="fa-solid fa-arrow-right-to-bracket" aria-hidden="true"></i></span>
                        <h3>Log masuk</h3>
                        <p>Guna kata laluan, passkey atau akaun Google yang telah dipautkan pada profil anda.</p>
                    </li>
                    <li class="hm-step reveal reveal-from-below">
                        <span class="hm-step-num">02</span>
                        <span class="hm-step-icon"><i class="fa-solid fa-cloud-arrow-down" aria-hidden="true"></i></span>
                        <h3>Tarik rekod</h3>
                        <p>Pilih jenis pemegang dan status, kemudian tarik rekod terkini daripada sumber kampus.</p>
                    </li>
                    <li class="hm-step reveal reveal-from-below">
                        <span class="hm-step-num">03</span>
                        <span class="hm-step-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span>
                        <h3>Semak &amp; sahkan</h3>
                        <p>Semakan UID mengasingkan rekod layak daripada pendua dan rekod tidak lengkap.</p>
                    </li>
                    <li class="hm-step reveal reveal-from-below">
                        <span class="hm-step-num">04</span>
                        <span class="hm-step-icon"><i class="fa-solid fa-file-export" aria-hidden="true"></i></span>
                        <h3>Eksport / hantar</h3>
                        <p>Muat turun CSV bersih atau hantar terus ke Sysweb; semuanya tercatat dalam log audit.</p>
                    </li>
                </ol>
            </div>
        </section>

        <?php if ($usmHomeVideo): ?>
        <!-- DEMO: the real guide clip, recorded from the live dashboard with sample data. -->
        <section class="hm-section hm-demo" id="demo" aria-labelledby="hm-demo-title">
            <div class="hm-container">
                <div class="hm-demo-grid">
                    <div class="hm-demo-copy reveal reveal-from-left">
                        <span class="hm-eyebrow hm-eyebrow-glass">DEMO</span>
                        <h2 id="hm-demo-title">Lihat portal <span>sebenar.</span></h2>
                        <p>Rakaman skrin aliran kerja penuh — tarik rekod, semak UID dan eksport — terus daripada dashboard USMSmart. Semua data dalam rakaman ialah contoh; tiada rekod sebenar dipaparkan.</p>
                        <div class="hm-demo-chips">
                            <span><i class="fa-solid fa-clock" aria-hidden="true"></i>34 saat</span>
                            <span><i class="fa-solid fa-database" aria-hidden="true"></i>Data contoh</span>
                            <span><i class="fa-solid fa-closed-captioning" aria-hidden="true"></i>Kapsyen BM</span>
                        </div>
                        <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-light"><span><?php echo $usmHomeCta; ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </div>
                    <figure class="hm-demo-frame reveal reveal-from-right">
                        <video controls preload="none" playsinline <?php if ($usmHomeVideo['poster']): ?>poster="media/manual/<?php echo $usmHomeVideo['slug']; ?>.jpg?v=<?php echo $usmHomeVideo['poster']; ?>"<?php endif; ?> aria-label="Demo USMSmart: tarik rekod, semak UID dan eksport">
                            <source src="media/manual/<?php echo $usmHomeVideo['slug']; ?>.mp4?v=<?php echo $usmHomeVideo['v']; ?>" type="video/mp4">
                            <?php if ($usmHomeVideo['vtt']): ?><track kind="captions" src="media/manual/<?php echo $usmHomeVideo['slug']; ?>.vtt?v=<?php echo $usmHomeVideo['vtt']; ?>" srclang="ms" label="Bahasa Melayu" default><?php endif; ?>
                            <p>Pelayar anda tidak menyokong video. <a href="media/manual/<?php echo $usmHomeVideo['slug']; ?>.mp4?v=<?php echo $usmHomeVideo['v']; ?>">Muat turun video (MP4)</a>.</p>
                        </video>
                        <figcaption><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Direkod daripada dashboard sebenar dengan data contoh.</figcaption>
                    </figure>
                </div>
                <div class="hm-trust reveal reveal-from-below">
                    <div class="hm-trust-item"><span class="hm-trust-icon"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i></span><span><strong>Passkey</strong><small>Log masuk tanpa kata laluan</small></span></div>
                    <div class="hm-trust-item"><span class="hm-trust-icon"><svg style="width:18px;height:18px" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg></span><span><strong>Akaun Google</strong><small>Pautan sekali, log masuk satu klik</small></span></div>
                    <div class="hm-trust-item"><span class="hm-trust-icon"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><span><strong>Log audit</strong><small>Setiap tindakan tercatat</small></span></div>
                    <div class="hm-trust-item"><span class="hm-trust-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></span><span><strong>Sandaran automatik</strong><small>Backup harian tanpa tindakan manual</small></span></div>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- FAQ -->
        <section class="hm-section hm-section-tint" id="faq" aria-labelledby="hm-faq-title">
            <div class="hm-container hm-faq-grid">
                <div class="hm-faq-intro">
                    <div class="hm-section-head reveal reveal-from-below">
                        <span class="hm-eyebrow">FAQ</span>
                        <h2 id="hm-faq-title">Soalan <span>lazim.</span></h2>
                        <p>Jawapan ringkas sebelum anda log masuk. Untuk bantuan lanjut, hubungi pentadbir portal.</p>
                    </div>
                    <aside class="hm-faq-help reveal reveal-from-below">
                        <span class="hm-faq-help-icon"><i class="fa-solid fa-circle-info" aria-hidden="true"></i></span>
                        <strong>Masih ada soalan?</strong>
                        <p>Panduan penuh dan sokongan pentadbir tersedia di dalam portal — log masuk untuk meneruskan.</p>
                        <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-primary"><span><?php echo $usmHomeCta; ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                    </aside>
                </div>
                <div class="hm-faq-list reveal reveal-from-below">
                    <details class="hm-faq" open>
                        <summary><span>Siapa yang boleh menggunakan USMSmart?</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="hm-faq-body"><p>Portal ini terhad kepada pengguna yang dibenarkan oleh pentadbir kampus. Akaun dicipta oleh Admin — tiada pendaftaran terbuka, dan setiap sesi disahkan semula pada setiap permintaan.</p></div>
                    </details>
                    <details class="hm-faq">
                        <summary><span>Apakah beza Admin dan Viewer?</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="hm-faq-body"><p>Admin mengurus akaun, eksport, sandaran dan tetapan sistem. Viewer boleh melihat dan menyemak data, tetapi perubahan besar dihantar sebagai permintaan perubahan untuk kelulusan Admin.</p></div>
                    </details>
                    <details class="hm-faq">
                        <summary><span>Bolehkah saya log masuk dengan akaun Google?</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="hm-faq-body"><p>Boleh, setelah pentadbir mengaktifkan integrasi Google dan akaun anda dipautkan. Pautkan sekali melalui <strong>Profil Saya</strong> dengan memilih akaun Google sebenar anda — selepas itu log masuk hanya satu klik.</p></div>
                    </details>
                    <details class="hm-faq">
                        <summary><span>Saya terlupa kata laluan.</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="hm-faq-body"><p>Gunakan pautan <strong>Lupa kata laluan</strong> di halaman log masuk untuk menerima kod OTP melalui e-mel dan menetapkan kata laluan baharu, atau hubungi pentadbir untuk set semula secara manual.</p></div>
                    </details>
                    <details class="hm-faq">
                        <summary><span>Adakah data portal selamat?</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                        <div class="hm-faq-body"><p>Kata laluan disimpan sebagai cincangan (hash), sesi tamat automatik selepas 30 minit tidak aktif, tindakan sensitif memerlukan kebenaran Admin, dan semua perubahan direkodkan dalam log audit yang boleh diperiksa.</p></div>
                    </details>
                </div>
            </div>
        </section>

        <!-- FINAL CTA -->
        <section class="hm-cta" aria-labelledby="hm-cta-title">
            <div class="hm-container hm-cta-inner reveal reveal-from-below">
                <span class="hm-eyebrow hm-eyebrow-glass"><?php echo $setupMode ? 'PERSEDIAAN' : 'MULA SEKARANG'; ?></span>
                <h2 id="hm-cta-title"><?php echo $setupMode ? 'Sediakan portal anda.' : 'Sedia untuk mula?'; ?></h2>
                <p><?php echo $setupMode ? 'Cipta akaun pentadbir pertama untuk mengaktifkan USMSmart. Proses mengambil masa kurang seminit.' : 'Log masuk untuk meneruskan ke dashboard, atau teruskan meneroka ciri portal di atas.'; ?></p>
                <a href="<?php echo $usmHomeLoginUrl; ?>" class="hm-btn hm-btn-light hm-btn-lg"><span><?php echo $usmHomeCta; ?></span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        </section>
    </main>

    <footer class="hm-footer">
        <div class="hm-container hm-footer-grid">
            <div class="hm-footer-brand">
                <a class="hm-brand" href="#top" aria-label="USMSmart — kembali ke atas">
                    <span class="hm-brand-mark">USM</span>
                    <span class="hm-brand-text"><span class="hm-brand-name">USMSmart</span><span class="hm-brand-sub">Kampus Kejuruteraan UDA</span></span>
                </a>
                <p>Portal pengurusan kad yang teratur — daripada tarikan rekod sehingga eksport dan penghantaran.</p>
            </div>
            <nav class="hm-footer-links" aria-label="Pautan footer">
                <a href="#ciri">Ciri</a>
                <a href="#aliran">Aliran Kerja</a>
                <?php if ($usmHomeVideo): ?><a href="#demo">Demo</a><?php endif; ?>
                <a href="#faq">FAQ</a>
                <a href="<?php echo $usmHomeLoginUrl; ?>">Log masuk</a>
            </nav>
        </div>
        <div class="hm-container hm-footer-base">
            <span>&copy; <?php echo date('Y'); ?> USMSmart &middot; Kampus Kejuruteraan UDA</span>
            <span>PORTAL PENGURUSAN KAD</span>
        </div>
    </footer>

    <script id="usmsmart-home-ui">
    (function () {
        'use strict';
        const root = document.documentElement;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* Theme toggle: same localStorage key as the sign-in page and dashboard, so the
           choice carries across all three surfaces. */
        const themeBtn = document.getElementById('hm-theme-toggle');
        const syncTheme = function () {
            const dark = root.classList.contains('dark');
            themeBtn.innerHTML = dark ? '<i class="fa-solid fa-sun" aria-hidden="true"></i>' : '<i class="fa-solid fa-moon" aria-hidden="true"></i>';
            themeBtn.setAttribute('aria-label', dark ? 'Tukar kepada mod cerah' : 'Tukar kepada mod gelap');
            themeBtn.setAttribute('title', dark ? 'Mod cerah' : 'Mod gelap');
        };
        themeBtn.addEventListener('click', function () {
            root.classList.toggle('dark');
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
            syncTheme();
        });
        syncTheme();

        /* Nav: frosted surface after leaving the hero top; mobile menu; active-section spy. */
        const nav = document.getElementById('hm-nav');
        const menuBtn = document.getElementById('hm-menu-btn');
        const navLinks = document.getElementById('hm-nav-links');
        const setMenu = function (open) {
            navLinks.classList.toggle('is-open', open);
            menuBtn.setAttribute('aria-expanded', String(open));
            menuBtn.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
            menuBtn.innerHTML = open ? '<i class="fa-solid fa-xmark" aria-hidden="true"></i>' : '<i class="fa-solid fa-bars" aria-hidden="true"></i>';
        };
        menuBtn.addEventListener('click', function () { setMenu(!navLinks.classList.contains('is-open')); });
        navLinks.addEventListener('click', function (event) { if (event.target.closest('a')) setMenu(false); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape') setMenu(false); });

        const photo = document.getElementById('hm-hero-photo');
        let scrollTick = false;
        const onScroll = function () {
            if (scrollTick) return;
            scrollTick = true;
            requestAnimationFrame(function () {
                scrollTick = false;
                nav.classList.toggle('is-scrolled', window.scrollY > 16);
                if (!reduceMotion && window.innerWidth > 900 && window.scrollY < window.innerHeight * 1.2) {
                    photo.style.transform = 'translate3d(0,' + (window.scrollY * 0.16) + 'px,0)';
                }
            });
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        const spyLinks = Array.from(navLinks.querySelectorAll('.hm-nav-link'));
        const spyTargets = spyLinks.map(function (link) { return document.querySelector(link.getAttribute('href')); }).filter(Boolean);
        if ('IntersectionObserver' in window && spyTargets.length) {
            const spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    spyLinks.forEach(function (link) {
                        const active = link.getAttribute('href') === '#' + entry.target.id;
                        if (active) link.setAttribute('aria-current', 'true'); else link.removeAttribute('aria-current');
                    });
                });
            }, { rootMargin: '-40% 0px -55% 0px', threshold: 0 });
            spyTargets.forEach(function (target) { spy.observe(target); });
        }

        /* Staged entrance reveals. Without JS everything is simply visible. */
        const revealTargets = document.querySelectorAll('.reveal');
        const showAll = function () { revealTargets.forEach(function (el) { el.classList.add('is-in'); }); };
        if (!('IntersectionObserver' in window) || reduceMotion) { showAll(); }
        else {
            const revealObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-in');
                    revealObserver.unobserve(entry.target);
                });
            }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
            revealTargets.forEach(function (el) { revealObserver.observe(el); });
        }

        /* Live counters: count up once when the band first appears, then a quiet 30s poll. */
        const statsBand = document.getElementById('hm-stats');
        const format = new Intl.NumberFormat('en-MY');
        const cells = statsBand.querySelectorAll('.hm-stat-value[data-stat]');
        const paint = function (data) {
            if (!data || !data.ok) return;
            cells.forEach(function (cell) {
                const value = data[cell.getAttribute('data-stat')];
                if (typeof value === 'number') cell.textContent = format.format(value);
            });
        };
        const countUp = function () {
            if (reduceMotion) return;
            cells.forEach(function (cell) {
                const target = parseInt(cell.textContent.replace(/[^0-9]/g, ''), 10) || 0;
                if (target <= 0) return;
                const start = performance.now();
                const duration = 900;
                const step = function (now) {
                    const t = Math.min(1, (now - start) / duration);
                    const eased = 1 - Math.pow(1 - t, 3);
                    cell.textContent = format.format(Math.round(target * eased));
                    if (t < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });
        };
        if ('IntersectionObserver' in window && !reduceMotion) {
            const statsObserver = new IntersectionObserver(function (entries) {
                if (entries.some(function (entry) { return entry.isIntersecting; })) {
                    countUp();
                    statsObserver.disconnect();
                }
            }, { threshold: 0.3 });
            statsObserver.observe(statsBand);
        }
        let statsBusy = false;
        const pollStats = function () {
            if (statsBusy || document.hidden) return;
            statsBusy = true;
            fetch('index.php?action=login_stats', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(paint)
                .catch(function () {})
                .finally(function () { statsBusy = false; });
        };
        window.setInterval(pollStats, 30000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) pollStats(); });

        /* FAQ: one answer open at a time keeps the column compact. */
        const faqItems = Array.from(document.querySelectorAll('.hm-faq'));
        faqItems.forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (!item.open) return;
                faqItems.forEach(function (other) { if (other !== item) other.open = false; });
            });
        });
    })();
    </script>
    <script src="assets/usmsmart-i18n.js?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.js'); ?>"></script>
</body>
</html>