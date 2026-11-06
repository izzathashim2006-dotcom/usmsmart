<!DOCTYPE html>
<html lang="ms" id="html-root">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USMSmart / UDA Transporter Studio</title>

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

    <link rel="manifest" href="index.php?action=manifest">
    <meta name="theme-color" content="#6366f1">
    <link rel="preload" as="font" type="font/woff2" href="assets/vendor/fonts/plus-jakarta-sans-latin.woff2" crossorigin>
    <script>
        // Prevent dark-mode flash before paint (also re-applied in JS at bottom)
        if (localStorage.getItem('theme') === 'dark') document.documentElement.classList.add('dark');
    </script>
    <script src="assets/vendor/chart-4.6.1.umd.min.js"></script>
    <link rel="stylesheet" href="assets/vendor/fonts.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/fonts.css'); ?>">
    <link rel="stylesheet" href="assets/vendor/font-awesome.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/font-awesome.css'); ?>">
    <link rel="stylesheet" href="assets/tailwind.css?v=<?php echo @filemtime(__DIR__ . '/../assets/tailwind.css'); ?>">
        <link rel="stylesheet" href="assets/usmsmart-dashboard.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-dashboard.css'); ?>">








</head>
<body class="font-sans antialiased min-h-screen flex flex-col bg-[#F8FAFC] text-[#0F172A] dark:bg-[#09090B] dark:text-[#F4F4F5] transition-colors duration-200">
    <a href="#usm-main" class="usm-skip">Langkau ke kandungan utama</a>

    <!-- LEFT RAIL: brand plus every segment entry. Moved out of the top row (see usmsmart-ui-tokens-v1). -->
    <aside id="usm-rail" class="usm-rail" aria-label="Navigasi utama">
        <div class="usm-rail-brand">
            <div class="usm-rail-mark" aria-hidden="true">USM</div>
            <div class="usm-rail-brand-text">
                <h1 class="usm-rail-name usm-rail-label">USMSmart</h1>
                <p class="usm-rail-sub usm-rail-label">Kampus Kejuruteraan UDA</p>
            </div>
        </div>

        <div class="usm-rail-group">
            <p class="usm-rail-eyebrow">Bahagian</p>
            <div id="nav-tabs" class="usm-tabbar" aria-label="Bahagian utama">
                <button type="button" id="btn-tab-transporter" onclick="switchMainTab('transporter')" class="usm-tab is-active seg-transporter" aria-current="page"><i class="fa-solid fa-bus" aria-hidden="true"></i><span class="usm-rail-label">Transporter Utama</span></button>
                <button type="button" id="btn-tab-analytics" onclick="switchMainTab('analytics')" class="usm-tab seg-analytics"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span class="usm-rail-label">Analisis Grafik</span></button>
                <button type="button" id="btn-tab-sysweb" onclick="navigateToSyswebSegment('header')" class="usm-tab seg-sysweb"><i class="fa-solid fa-network-wired" aria-hidden="true"></i><span class="usm-rail-label">Sysweb</span></button>
            </div>
        </div>

        <div class="usm-rail-group">
            <p class="usm-rail-eyebrow">Data &amp; Rekod</p>
            <button type="button" class="usm-rail-link seg-directory" data-usm-view="view-directory-page" onclick="navigateToDirectoryPage()" title="Direktori Kad Pelajar &amp; Staf"><i class="fa-solid fa-id-card-clip" aria-hidden="true"></i><span class="usm-rail-label">Direktori Kad</span></button>
            <button type="button" class="usm-rail-link seg-audit" data-usm-view="view-audit-history" onclick="navigateToAuditHistory('directory')" title="Sejarah Rekod &amp; Log Audit"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i><span class="usm-rail-label">Sejarah &amp; Log Audit</span></button>
            <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <button type="button" class="usm-rail-link seg-users" data-usm-view="view-user-management" onclick="navigateToUserManagement()" title="Pengurusan Akaun"><i class="fa-solid fa-users-gear" aria-hidden="true"></i><span class="usm-rail-label">Pengurusan Akaun</span></button>
            <?php } ?>
            <button type="button" class="usm-rail-link seg-quality" data-usm-view="view-data-quality" onclick="navigateToDataQuality()" title="Kualiti Data"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span class="usm-rail-label">Kualiti Data</span></button>
        </div>

        <div class="usm-rail-group usm-rail-tools">
            <p class="usm-rail-eyebrow"><i class="fa-solid fa-sliders" aria-hidden="true"></i>Alat</p>
            <button type="button" class="usm-rail-link" onclick="openUserManual()" title="Panduan Pengguna"><i class="fa-solid fa-book-open" aria-hidden="true"></i><span class="usm-rail-label">Panduan</span></button>
            <button type="button" class="usm-rail-link" onclick="openMyProfile()" title="Profil Saya"><?php echo usmsmartAvatarChipHtml('usm-rail-avatar', (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['avatar_updated_at'] ?? ''), (string)($_SESSION['username'] ?? ''), 'usm-avatar-20', $usmTabToken); ?><span class="usm-rail-label">Profil Saya</span></button>
            <button type="button" class="usm-rail-link" onclick="openScannerModal()" title="Imbas Kad"><i class="fa-solid fa-camera" aria-hidden="true"></i><span class="usm-rail-label">Imbas Kad</span></button>
            <button type="button" class="usm-rail-link" onclick="window.print()" title="Cetak"><i class="fa-solid fa-print" aria-hidden="true"></i><span class="usm-rail-label">Cetak</span></button>
        </div>

        <div class="usm-rail-spacer"></div>
    </aside>

    <!-- HEADER -->
    <header class="bg-white/95 dark:bg-[#0f0f11]/95 backdrop-blur-lg border-b border-slate-100 dark:border-zinc-800/60 sticky top-0 z-40">
        <div class="max-w-[1100px] mx-auto px-5 flex items-center justify-between h-14">
            <!-- Left: who is signed in. The brand and all navigation now live in #usm-rail. -->
            <div class="flex items-center gap-2 min-w-0">
                <button type="button" id="usm-header-profile" class="usm-header-profile" onclick="openMyProfile()" title="Profil Saya" aria-haspopup="dialog" aria-controls="my-profile-modal"><span class="usm-header-profile-mark" aria-hidden="true"><?php echo usmsmartAvatarChipHtml('usm-header-avatar', (int)($_SESSION['user_id'] ?? 0), (string)($_SESSION['avatar_updated_at'] ?? ''), (string)($_SESSION['username'] ?? ''), 'usm-avatar-32', $usmTabToken); ?></span><span class="truncate text-[12px] font-medium font-mono tracking-wide text-slate-400 dark:text-zinc-400"><?php echo htmlspecialchars((string)($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></button>
            </div>
            <!-- Right: information panels, theme and session. Everything is in flow: a floating dock landed on top of page buttons. -->
            <div class="flex items-center gap-1.5 text-[11px]">
                <?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && $action === ''): ?>
                <div class="usm-header-tools" role="group" aria-label="Panel maklumat">
                    <button id="usm-db-status" type="button" class="usm-db-status is-checking" aria-controls="usm-health-panel" aria-expanded="false" aria-label="Data source status" title="Data sources: checking"><span class="usm-db-dot" aria-hidden="true"></span><span class="usm-db-label">Checking</span></button>
                    <button id="usm-requests-launch" type="button" aria-label="<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Pending Approvals' : 'My Requests'; ?>" title="<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Pending Approvals' : 'My Requests'; ?>"><i class="fa-solid fa-inbox" aria-hidden="true"></i></button>
                    <button id="usm-notifications-launch" type="button" aria-label="Notifications" title="Notifications"><i class="fa-solid fa-bell" aria-hidden="true"></i><span id="usm-notifications-badge" hidden>0</span></button>
                    <button id="usm-messages-launch" type="button" aria-label="Messages" title="Messages"><i class="fa-solid fa-envelope" aria-hidden="true"></i><span id="usm-messages-unread" hidden></span></button>
                    <button id="usm-health-launch" type="button" aria-expanded="false" aria-label="System health" title="System health"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i></button>
                </div>
                <div class="usm-header-sep" aria-hidden="true"></div>
                <?php endif; ?>
            <div class="flex items-center gap-1.5 text-[11px]">

                <button onclick="toggleDarkMode()" class="p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-zinc-800/60 text-slate-500 dark:text-zinc-400 transition" title="Toggle dark mode">
                    <i id="theme-icon" class="fa-solid fa-moon text-sm"></i>
                </button>

                <div class="w-px h-5 bg-slate-200 dark:bg-zinc-700 mx-1"></div>

                <button type="button" onclick="logoutUSMSmart()" class="px-2.5 py-1.5 rounded-lg text-slate-500 dark:text-zinc-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400 transition text-[11px] font-medium">Log out</button>
            </div>
        </div>
    </header>

    <main id="usm-main" tabindex="-1" class="flex-grow max-w-[1100px] w-full mx-auto px-5 py-7 space-y-5">
        
        <?php
        // Who the hero greets. The script fills in the clock and the date so they follow the
        // browser's time zone instead of the server's; the role picks the subline.
        $usmHeroName = trim((string)($_SESSION['display_name'] ?? ''));
        if ($usmHeroName === '') { $usmHeroName = (string)($_SESSION['username'] ?? 'pengguna'); }
        $usmHeroAdmin = (($_SESSION['role'] ?? '') === 'admin');
        ?>

        <!-- HALAMAN UTAMA: TRANSPORTER -->
        <div id="view-transporter" class="space-y-5">
            
            <section class="usm-hero" aria-labelledby="usm-dash-hero-title">
                <span class="usm-hero-blob usm-hero-blob-a" aria-hidden="true"></span>
                <span class="usm-hero-blob usm-hero-blob-b" aria-hidden="true"></span>
                <div class="usm-hero-inner">
                    <span class="usm-hero-mark" aria-hidden="true"><i id="usm-dash-icon" class="fa-solid fa-sun"></i></span>
                    <p class="usm-hero-eyebrow">Kampus Kejuruteraan USM</p>
                    <h2 id="usm-dash-hero-title" class="usm-hero-title"><span id="usm-dash-greeting">Selamat datang</span>, <span class="usm-hero-name"><?php echo htmlspecialchars($usmHeroName, ENT_QUOTES, 'UTF-8'); ?></span></h2>
                    <p class="usm-hero-sub"><?php echo $usmHeroAdmin
                        ? 'Semoga hari anda tenang dan produktif. Akses penuh — tarikan rekod, kemas kini pukal, log audit dan eksport tersedia di rel kiri.'
                        : 'Semoga hari anda tenang dan produktif. Akses baca — direktori kad, statistik dan eksport tersedia di rel kiri.'; ?></p>
                    <div class="usm-hero-meta">
                        <span class="usm-hero-pill"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i><span id="usm-dash-date">Hari ini</span></span>
                        <span class="usm-hero-pill"><i class="fa-solid fa-clock" aria-hidden="true"></i><time id="usm-dash-time" datetime="">--:--</time></span>
                    </div>
                </div>
            </section>

            <!-- BANNER PERINGATAN KAD HAMPIR LUPUT (1-CLICK ACCESS) -->
            <div id="transporter-expiry-alert-banner" class="hidden bg-amber-50/90 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/50 rounded-2xl p-4 shadow-clean flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-amber-100 dark:bg-amber-900/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-semibold text-xs text-amber-900 dark:text-amber-200 uppercase tracking-wide">Pemberitahuan Status Kad Pelajar</h4>
                            <span id="transporter-expiring-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100">0</span>
                        </div>
                        <p id="transporter-expiry-alert-text" class="text-[11px] text-amber-800/80 dark:text-amber-300/80 mt-0.5">Terdapat pelajar yang kad akses mereka akan tamat tempoh dalam masa terdekat.</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                    <button type="button" onclick="viewStudentsByExpiryStatus('30')" class="bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <i class="fa-solid fa-user-clock text-xs"></i> <span>Lihat Pelajar Luput (30 Hari)</span>
                    </button>
                    <button type="button" id="transporter-expired-btn" onclick="viewStudentsByExpiryStatus('expired')" class="hidden bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition items-center gap-1.5 cursor-pointer whitespace-nowrap">
                        <i class="fa-solid fa-circle-exclamation text-xs"></i> <span>Tamat Tempoh</span>
                    </button>
                </div>
            </div>

            <section id="uda-live-pull-panel" class="bg-white dark:bg-[#111113] border border-slate-200 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-5 sm:px-6 border-b border-slate-100 dark:border-zinc-800 bg-gradient-to-r from-slate-50 to-white dark:from-zinc-900 dark:to-[#111113]">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300"><i class="fa-solid fa-database text-lg"></i></div>
                            <div>
                                <h2 class="text-base sm:text-lg font-semibold tracking-tight text-slate-900 dark:text-white">Sumber Kad & Eksport UDA</h2>
                                <p class="mt-1 max-w-2xl text-xs leading-5 text-slate-500 dark:text-zinc-400">Tarik rekod pemegang kad daripada pangkalan data USM dan tapis keputusan sebelum dihantar ke Transporter UDA.</p>
                            </div>
                        </div>
                        <span id="uda-source-badge" role="status" aria-live="polite" class="usm-src-badge inline-flex items-center gap-2 self-start rounded-full border px-3 py-1.5 text-[10px] font-semibold is-checking" title="Menyemak sambungan pangkalan data sumber..."><span class="usm-src-dot h-1.5 w-1.5 rounded-full"></span><span class="usm-src-text">Menyemak sumber…</span></span>
                    </div>
                </div>

                <div class="p-5 sm:p-6 space-y-5">
                    <div>
                        <div class="mb-3 flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-sliders text-[10px]"></i></span><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-zinc-200">Tetapan tarikan</h3></div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-12 gap-x-4 gap-y-4 items-start">
                            <div class="min-w-0 xl:col-span-3">
                                <label for="uda-cardholder-type" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Jenis pemegang kad</label>
                                <select id="uda-cardholder-type" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="student">Pelajar</option><option value="staff">Staf</option></select>
                            </div>
                            <div class="min-w-0 xl:col-span-2">
                                <label for="uda-live-record-limit" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Bilangan rekod</label>
                                <input type="number" id="uda-live-record-limit" min="1" step="1" value="100" inputmode="numeric" required aria-describedby="uda-live-record-limit-help" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                                <p id="uda-live-record-limit-help" class="mt-1.5 text-[10px] leading-4 text-slate-500 dark:text-zinc-400">Anda tentukan jumlah; tiada had maksimum aplikasi.</p>
                            </div>
                            <div class="min-w-0 xl:col-span-2">
                                <label for="start-date" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Tarikh mula</label>
                                <input type="date" id="start-date" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div class="min-w-0 xl:col-span-2">
                                <label for="end-date" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Tarikh akhir</label>
                                <input type="date" id="end-date" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                            </div>
                            <div class="min-w-0 sm:col-span-2 xl:col-span-3 xl:self-end">
                                <button type="button" onclick="readFromUSMSmart()" class="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-500/25"><i class="fa-solid fa-cloud-arrow-down"></i><span>Tarik rekod</span></button>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-5 dark:border-zinc-800">
                        <div class="mb-3 flex items-center gap-2"><span class="flex h-6 w-6 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-filter text-[10px]"></i></span><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-zinc-200">Penapis rekod</h3><span class="text-[10px] text-slate-500 dark:text-slate-400">Pilihan</span></div>
                        <div class="grid min-w-0 grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                            <div class="min-w-0"><label for="uda-live-search" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Carian pantas</label><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-slate-400"></i><input id="uda-live-search" type="search" maxlength="120" placeholder="Nama, matrik, no. staf atau UID" class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-9 pr-3.5 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"></div></div>
                            <div class="min-w-0"><label for="uda-live-status" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Status kad</label><select id="uda-live-status" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Semua status</option><option value="Aktif">Aktif</option><option value="Tidak Aktif">Tidak Aktif</option><option value="Hilang">Hilang</option></select></div>
                            <div class="min-w-0"><label for="uda-live-school" class="mb-1.5 block text-xs font-medium text-slate-700 dark:text-zinc-300">Sekolah / program / jabatan</label><select id="uda-live-school" class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white"><option value="">Semua sekolah / program</option></select></div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-slate-100 pt-4 dark:border-zinc-800 sm:flex-row sm:items-center sm:justify-between">
                        <p class="flex max-w-2xl items-start gap-2 text-[11px] leading-5 text-slate-500 dark:text-zinc-400"><i class="fa-solid fa-circle-info mt-0.5 text-indigo-500"></i><span>Rekod yang sudah wujud dalam UDA tidak dipaparkan semula. Polisi palang dan nombor siri akan disemak sebelum eksport.</span></p>
                        <div class="flex min-w-0 flex-wrap gap-2 sm:justify-end">
                            <button type="button" onclick="setLiveDateRange('month')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">Bulan ini</button>
                            <button type="button" onclick="setLiveDateRange('year')" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-700 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">Tahun ini</button>
                            <button type="button" onclick="resetLiveFilters()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-rotate-left mr-1"></i>Reset</button>
                        </div>
                    </div>

                    <div id="live-pull-status" class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/70" aria-live="polite">
                        <div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300"><i class="fa-solid fa-circle-info"></i></span><div><p id="live-pull-status-title" class="text-sm font-semibold text-slate-800 dark:text-zinc-100">Belum menarik data</p><p id="live-pull-status-detail" class="mt-1 text-xs leading-5 text-slate-500 dark:text-zinc-400">Pilih kategori, bilangan rekod dan julat tarikh, kemudian tekan “Tarik rekod”.</p></div></div>
                    </div>
                    <div id="live-pull-summary" class="hidden grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-[11px] text-slate-500 dark:text-zinc-400">Rekod baharu</p><p id="live-summary-total" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">0</p></div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20"><p class="text-[11px] text-emerald-700 dark:text-emerald-300">Sedia disemak</p><p id="live-summary-ready" class="mt-1 text-2xl font-semibold tracking-tight text-emerald-700 dark:text-emerald-300">0</p></div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20"><p class="text-[11px] text-amber-700 dark:text-amber-300">Tiada polisi</p><p id="live-summary-policy" class="mt-1 text-2xl font-semibold tracking-tight text-amber-700 dark:text-amber-300">0</p></div>
                        <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 dark:border-rose-900/60 dark:bg-rose-950/20"><p class="text-[11px] text-rose-700 dark:text-rose-300">Semak UID / siri</p><p id="live-summary-invalid" class="mt-1 text-2xl font-semibold tracking-tight text-rose-700 dark:text-rose-300">0</p></div>
                    </div>

                    <!-- SEMAKAN UID LAYAK EKSPORT (PELAJAR + STAF, StatusKad 02) — dalam panel tarikan yang sama -->
                    <div id="uid-eligibility-panel" class="border-t border-slate-100 pt-5 dark:border-zinc-800">
                        <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex items-start gap-2">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"><i class="fa-solid fa-shield-halved text-[10px]"></i></span>
                                <div>
                                    <h3 id="uid-eligibility-title" class="text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-zinc-200">Semakan UID layak eksport</h3>
                                    <p class="mt-1 max-w-2xl text-[11px] leading-5 text-slate-500 dark:text-zinc-400">Baca dbo.Pelajar dan dbo.Staf, StatusKad 02 sahaja. UID mesti 8 aksara hexadecimal dan unik merentas pelajar serta staf, termasuk rekod yang telah tamat tempoh. Semakan berjalan automatik setiap kali “Tarik rekod”.</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 self-start">
                                <button type="button" id="uid-eligibility-csv" onclick="downloadUidEligibilityCsv()" disabled class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-40 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-csv"></i> Eksport CSV</button>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900/70" aria-live="polite">
                                <p id="uid-eligibility-status" class="text-xs leading-5 text-slate-600 dark:text-zinc-300"><i class="fa-solid fa-circle-info mr-1.5 text-emerald-600 dark:text-emerald-400"></i>Belum disemak. Semakan akan berjalan automatik selepas anda menekan “Tarik rekod”.</p>
                            </div>
                            <div id="uid-eligibility-summary" class="hidden grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900"><p class="text-[11px] text-slate-500 dark:text-zinc-400">Rekod StatusKad 02</p><p id="uid-summary-scanned" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">0</p></div>
                                <div class="rounded-xl border border-emerald-200 bg-emerald-50/60 p-4 dark:border-emerald-900/60 dark:bg-emerald-950/20"><p class="text-[11px] text-emerald-700 dark:text-emerald-300">Layak dieksport</p><p id="uid-summary-eligible" class="mt-1 text-2xl font-semibold tracking-tight text-emerald-700 dark:text-emerald-300">0</p></div>
                                <div class="rounded-xl border border-rose-200 bg-rose-50/60 p-4 dark:border-rose-900/60 dark:bg-rose-950/20"><p class="text-[11px] text-rose-700 dark:text-rose-300">Disekat</p><p id="uid-summary-blocked" class="mt-1 text-2xl font-semibold tracking-tight text-rose-700 dark:text-rose-300">0</p></div>
                                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20"><p class="text-[11px] text-amber-700 dark:text-amber-300">Rekod UID berulang</p><p id="uid-summary-duplicate" class="mt-1 text-2xl font-semibold tracking-tight text-amber-700 dark:text-amber-300">0</p></div>
                            </div>
                            <div id="uid-eligibility-eligible-wrap" class="hidden space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-300"><i class="fa-solid fa-circle-check mr-1.5"></i>Layak dieksport</h4><span id="uid-eligible-count" class="text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">0 rekod</span></div>
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase tracking-wider">
                                            <tr><th class="p-3">Nama</th><th class="p-3">Jenis</th><th class="p-3">ID</th><th class="p-3">Pusat / Jabatan</th><th class="p-3">UID</th><th class="p-3">Tamat</th></tr>
                                        </thead>
                                        <tbody id="uid-eligible-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-600 dark:text-zinc-300"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div id="uid-eligibility-blocked-wrap" class="hidden space-y-3">
                                <div class="flex flex-wrap items-center justify-between gap-2"><h4 class="text-xs font-semibold uppercase tracking-wider text-rose-700 dark:text-rose-300"><i class="fa-solid fa-circle-xmark mr-1.5"></i>Disekat</h4><span id="uid-blocked-count" class="text-[11px] font-semibold text-rose-700 dark:text-rose-300">0 rekod</span></div>
                                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase tracking-wider">
                                            <tr><th class="p-3">Nama</th><th class="p-3">Jenis</th><th class="p-3">ID</th><th class="p-3">Pusat / Jabatan</th><th class="p-3">UID</th><th class="p-3">Tamat</th><th class="p-3">Sebab</th></tr>
                                        </thead>
                                        <tbody id="uid-blocked-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-600 dark:text-zinc-300"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="dashboard-metrics" class="grid grid-cols-1 sm:grid-cols-3 gap-4 font-mono hidden page-transition">
                <div class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 p-5 rounded-2xl shadow-clean">
                    <div class="flex items-center justify-between text-slate-400 dark:text-zinc-400 mb-2">
                        <span class="text-[11px] uppercase tracking-wider font-semibold">Jumlah Rekod Sesi</span>
                        <i class="fa-solid fa-folder-open text-indigo-500 text-sm"></i>
                    </div>
                    <div id="metric-total-records" class="text-2xl font-bold text-slate-900 dark:text-white">0</div>
                </div>
                <div class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 p-5 rounded-2xl shadow-clean">
                    <div class="flex items-center justify-between text-slate-400 dark:text-zinc-400 mb-2">
                        <span class="text-[11px] uppercase tracking-wider font-semibold">Pusat Pengajian</span>
                        <i class="fa-solid fa-building-columns text-indigo-500 text-sm"></i>
                    </div>
                    <div id="metric-total-schools" class="text-2xl font-bold text-slate-900 dark:text-white">0</div>
                </div>
                <div class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 p-5 rounded-2xl shadow-clean">
                    <div class="flex items-center justify-between text-slate-400 dark:text-zinc-400 mb-2">
                        <span class="text-[11px] uppercase tracking-wider font-semibold">Status Enjin</span>
                        <i class="fa-solid fa-microchip text-emerald-500 text-sm"></i>
                    </div>
                    <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-2 mt-1">
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Ready
                    </div>
                </div>
            </section>

            <section id="section-stats" class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl p-6 space-y-4 hidden page-transition shadow-clean">
                <div class="border-b border-slate-100 dark:border-zinc-800 pb-3 flex items-center justify-between">
                    <h2 class="font-medium text-xs uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-mono"><i class="fa-solid fa-chart-pie mr-1.5 text-indigo-500"></i> Taburan Mengikut Pusat Pengajian</h2>
                    <span id="total-student-badge" class="text-[11px] font-mono bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/40 px-2.5 py-1 rounded-lg font-bold">0 Pelajar</span>
                </div>
                <div id="school-stats-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 font-mono"></div>
            </section>

            <section id="section-step-2" class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden hidden page-transition shadow-clean">
                <div class="p-4 bg-slate-50/70 dark:bg-zinc-900/40 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row justify-between items-center gap-3 font-mono">
                    <h3 class="font-semibold text-xs text-slate-800 dark:text-white uppercase tracking-wide"><i class="fa-solid fa-list mr-1.5 text-indigo-500"></i> Senarai Rekod untuk Transporter UDA</h3>
                    <div class="flex items-center gap-2.5 w-full sm:w-auto">
                        <div class="relative flex-grow sm:w-64">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs"><i class="fa-solid fa-search"></i></span>
                            <input type="text" id="table-search" placeholder="Cari..." oninput="filterTableRecords()" class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                        </div>
                        <button onclick="downloadCSV()" class="bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 hover:bg-slate-50 px-3 py-1.5 rounded-xl text-xs">CSV</button>
                        <button onclick="exportToUDA()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-1.5 rounded-xl text-xs font-medium shadow-sm">Export UDA</button>

                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-50/80 dark:bg-zinc-900 text-slate-400 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase tracking-wider">
                            <tr>
                                <th class="p-3.5 text-center"><input type="checkbox" checked id="select-all" onclick="toggleSelectAll(this)" class="rounded accent-indigo-600"></th>
                                <th class="p-3.5">Bil</th>
                                <th class="p-3.5">Nama Penuh</th>
                                <th class="p-3.5">Matrik</th>
                                <th class="p-3.5">Jabatan / Pusat</th>
                                <th class="p-3.5">Jenis</th>
                                <th class="p-3.5">UID Asal</th>
                                <th class="p-3.5">Semakan Data</th>
                                <th class="p-3.5">Polisi Palang</th>
                            </tr>
                        </thead>
                        <tbody id="usm-raw-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-600 dark:text-zinc-300"></tbody>
                    </table>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 border-t border-slate-200 dark:border-zinc-800 p-4 text-xs">
                    <div class="flex items-center gap-2"><span id="uda-pagination-summary" class="text-slate-500 dark:text-zinc-400">0 rekod</span><label for="uda-page-size" class="text-slate-500 dark:text-zinc-400">Setiap halaman</label><select id="uda-page-size" onchange="changeUdaPageSize(this.value)" class="border border-slate-200 dark:border-zinc-700 rounded-lg px-2 py-1.5 bg-white dark:bg-zinc-900"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select></div>
                    <div class="flex items-center gap-2"><button type="button" onclick="changeUdaPage(-1)" id="uda-page-prev" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 disabled:opacity-40">Sebelumnya</button><span id="uda-page-indicator" class="text-slate-600 dark:text-zinc-300 min-w-[90px] text-center">Halaman 1 / 1</span><button type="button" onclick="changeUdaPage(1)" id="uda-page-next" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 disabled:opacity-40">Seterusnya</button></div>
                </div>
            </section>

            <section id="section-step-3" class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden hidden page-transition shadow-clean">
                <div class="p-4 border-b border-slate-200/80 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-900/40 flex flex-col sm:flex-row justify-between items-center gap-3 font-mono">
                    <h3 class="font-semibold text-xs text-slate-800 dark:text-white uppercase tracking-wide"><i class="fa-solid fa-code-merge mr-1.5 text-indigo-500"></i> Konversi Little-Endian & Status UDA</h3>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <select id="status-filter" onchange="filterUdaTable()" class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-slate-900 dark:text-white outline-none">
                            <option value="ALL">Semua Status</option>
                            <option value="READY">Ready</option>
                            <option value="PENDING">Pending</option>
                        </select>
                        <input type="text" id="uda-search" placeholder="Cari..." oninput="filterUdaTable()" class="bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-1.5 text-xs text-slate-900 dark:text-white outline-none w-32">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-50/80 dark:bg-zinc-900 text-slate-400 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase tracking-wider">
                            <tr>
                                <th class="p-3.5">Bil</th>
                                <th class="p-3.5">Nama & Matrik</th>
                                <th class="p-3.5">UID Asal</th>
                                <th class="p-3.5">Converted UDA UID</th>
                                <th class="p-3.5">No Siri & Status</th>
                            </tr>
                        </thead>
                        <tbody id="uda-converted-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-600 dark:text-zinc-300"></tbody>
                    </table>
                </div>
                <div class="p-4 bg-slate-50/70 dark:bg-zinc-900/40 border-t border-slate-200/80 dark:border-zinc-800 flex justify-between items-center font-mono">
                    <span class="text-xs text-slate-500 dark:text-zinc-400">Pastikan semua maklumat betul sebelum simpan.</span>
                    <button onclick="openConfirmModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-xs font-medium shadow-sm cursor-pointer">Sahkan & Simpan ke Live DB UDA</button>
                </div>
            </section>

            <section id="section-session-history" <?php echo (($_SESSION['role'] ?? '') === 'admin') ? '' : 'hidden'; ?> class="bg-white dark:bg-[#111113] border border-slate-200/60 dark:border-zinc-800/50 rounded-2xl p-5 space-y-4 shadow-clean">
                <div class="flex justify-between items-center border-b border-slate-100 dark:border-zinc-800/60 pb-3">
                    <h3 class="section-heading font-mono flex items-center gap-1.5"><i class="fa-solid fa-clock-rotate-left text-indigo-500"></i> Sejarah Sesi Penghantaran UDA</h3>
                    <button onclick="downloadReportExcel()" class="text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 px-2.5 py-1.5 rounded-lg text-[11px] font-medium transition"><i class="fa-solid fa-file-excel mr-1"></i> Eksport Excel</button>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Namakan sesi mengikut tujuan, tarikh atau kumpulan untuk memudahkan carian.</p>
                    <input id="session-history-filter" type="search" oninput="renderSessionHistory()" placeholder="Tapis nama / ID / tarikh..." class="w-full sm:max-w-xs rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2 text-xs outline-none focus:ring-2 focus:ring-indigo-500" aria-label="Tapis sejarah sesi" />
                </div>
                <section id="uda-session-stats-panel" hidden class="usm-stats-entrant usm-chart-card p-5 sm:p-6 rounded-2xl border border-slate-200/60 dark:border-zinc-800 bg-white dark:bg-[#121214] shadow-clean">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Statistik sesi penghantaran</h3>
                                <p id="session-history-stats-caption" role="status" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Memuatkan sejarah sesi...</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                            <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Eksport statistik sesi">
                                <button type="button" onclick="downloadSessionStatsPdf()" title="Muat turun ringkasan PDF" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-pdf"></i>PDF</button>
                                <button type="button" onclick="downloadSessionStatsPng()" title="Muat turun PNG semua carta" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-download"></i>PNG</button>
                                <button type="button" onclick="downloadSessionStatsExcel()" title="Muat turun laporan Excel" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-excel"></i>Excel</button>
                            </div>
                            <button type="button" id="session-history-drill-reset" hidden onclick="clearSessionHistoryDrill()" class="px-2.5 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-800 text-[11px] font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"></button>
                            <div class="usm-stats-ranges" role="group" aria-label="Julat hari statistik sesi">
                                <button type="button" class="usm-stats-range" onclick="setSessionHistoryRange(7, this)">7 hari</button>
                                <button type="button" class="usm-stats-range is-on" onclick="setSessionHistoryRange(30, this)">30 hari</button>
                                <button type="button" class="usm-stats-range" onclick="setSessionHistoryRange(90, this)">90 hari</button>
                                <button type="button" class="usm-stats-range" onclick="setSessionHistoryRange(3660, this)">Semua</button>
                            </div>
                        </div>
                    </div>

                    <div class="usm-stats-strip">
                        <div><span class="usm-stats-value" id="session-stats-sessions">0</span><span class="usm-stats-label">Sesi dalam julat</span></div>
                        <div><span class="usm-stats-value" id="session-stats-records">0</span><span class="usm-stats-label">Pelajar dihantar</span></div>
                        <div><span class="usm-stats-value" id="session-stats-days">0</span><span class="usm-stats-label">Hari berbeza</span></div>
                        <div><span class="usm-stats-value" id="session-stats-unnamed">0</span><span class="usm-stats-label">Sesi tanpa nama</span></div>
                    </div>

                    <div class="relative h-64 w-full mt-1"><canvas id="sessionHistoryTrendChart"></canvas></div>

                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                        <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Campuran saiz sesi</h3>
                                    <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu hiris untuk menapis jadual</p>
                                </div>
                                <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                            </div>
                            <div class="relative h-64 w-full"><canvas id="sessionSizeChart"></canvas></div>
                        </div>
                        <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                            <div class="flex items-start justify-between gap-3 mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Sesi paling besar</h3>
                                    <p id="session-top-note" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu bar untuk menapis mengikut sesi</p>
                                </div>
                                <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                            </div>
                            <div class="relative h-64 w-full"><canvas id="sessionTopChart"></canvas></div>
                        </div>
                    </div>
                </section>

                <div class="overflow-x-auto rounded-lg border border-slate-200/60 dark:border-zinc-800/50">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50/80 dark:bg-zinc-900/60 text-slate-400 dark:text-zinc-400 border-b border-slate-200/60 dark:border-zinc-800/50 text-[10px] uppercase tracking-wider">
                            <tr>
                                <th>ID / Nama Sesi</th>
                                <th>Tarikh & Masa</th>
                                <th class="text-center">Jumlah Pelajar</th>
                                <th class="text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="session-history-tbody" class="divide-y divide-slate-100/80 dark:divide-zinc-800/50 text-slate-600 dark:text-zinc-300"></tbody>
                    </table>
                </div>
            </section>

            <section id="section-success" class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl p-6 sm:p-8 space-y-6 hidden page-transition shadow-clean text-center">
                <div class="w-14 h-14 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/40 rounded-2xl flex items-center justify-center mx-auto shadow-sm">
                    <i class="fa-solid fa-circle-check text-2xl"></i>
                </div>
                <div class="space-y-1">
                    <h3 class="text-base font-bold text-slate-900 dark:text-white font-mono">Data Berjaya Disimpan ke Live Database UDA!</h3>
                    <p class="text-xs text-slate-500 dark:text-zinc-400 max-w-md mx-auto">Semua rekod telah berjaya dieksport dan diverifikasi ke dalam pangkalan data UDA.</p>
                </div>

                <!-- Next step card: Sysweb -->
                <div class="max-w-lg mx-auto p-5 rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-br from-emerald-50/90 via-teal-50/50 to-white dark:from-emerald-950/40 dark:via-zinc-900 dark:to-zinc-950 dark:border-emerald-700/60 text-left space-y-3 shadow-md">
                    <div class="flex items-center justify-between">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-900/70 text-emerald-800 dark:text-emerald-200 text-[10px] font-bold uppercase tracking-wider">
                            <i class="fa-solid fa-forward-step"></i> Langkah Seterusnya (Langkah 4)
                        </span>
                        <span class="text-[11px] font-mono text-emerald-700 dark:text-emerald-300 font-semibold flex items-center gap-1" id="usm-sysweb-pending-badge">
                            <i class="fa-solid fa-circle-check text-[10px]"></i> Sedia Dihantar
                        </span>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <span>Integrasi Sistem Sysweb</span>
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-zinc-300 mt-1 leading-relaxed">
                            Formatkan rekod yang baru dieksport dengan UID Sysweb (8 sifar + Little-Endian UID) dan hantar terus ke sistem Sysweb.
                        </p>
                    </div>
                    <div class="pt-2">
                        <button id="usm-sysweb-after-uda-btn" type="button" onclick="openSyswebDemo()" class="w-full bg-emerald-700 hover:bg-emerald-800 active:scale-[0.99] text-white px-5 py-3 rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <i class="fa-solid fa-network-wired"></i>
                            <span>Teruskan ke Sysweb (Langkah Seterusnya)</span>
                            <i class="fa-solid fa-arrow-right ml-1"></i>
                        </button>
                    </div>
                    <p id="usm-sysweb-after-uda-note" class="text-[11px] text-emerald-700 dark:text-emerald-300 flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i> <span>Eksport UDA selesai. Klik butang di atas untuk memulakan integrasi Sysweb.</span>
                    </p>
                </div>

                <div class="flex flex-wrap justify-center gap-3 pt-2">
                    <button onclick="location.reload()" class="bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 px-4 py-2 rounded-xl text-xs font-mono text-slate-700 dark:text-zinc-200 transition">
                        <i class="fa-solid fa-rotate-right mr-1.5"></i> Lakukan Transporter Baharu
                    </button>
                </div>
            </section>
        </div>

        <!-- HALAMAN KHAS 1: DIREKTORI KAD (PELAJAR + STAF, ONE PAGE) -->
        <div id="view-directory-page" class="hidden page-transition space-y-6">
            <!-- One directory, two record types: the switch keeps a single entry point. -->
            <div class="usm-dir-head">
                <div class="usm-dir-head-lead">
                    <button type="button" onclick="backToMainTransporter()" class="usm-dir-back" title="Kembali ke Utama" aria-label="Kembali ke Utama"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
                    <div>
                        <p class="usm-dir-eyebrow">DIREKTORI KAD</p>
                        <h2 class="usm-dir-title">Pelajar &amp; Staf</h2>
                    </div>
                </div>
                <div class="usm-dir-switch" role="tablist" aria-label="Jenis rekod">
                    <button type="button" id="dir-tab-student" role="tab" aria-selected="true" aria-controls="dir-panel-student" onclick="switchDirectoryPanel('student')" onkeydown="handleDirectoryTabKey(event)" class="usm-dir-switch-btn is-active"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><span>Pelajar</span></button>
                    <button type="button" id="dir-tab-staff" role="tab" aria-selected="false" aria-controls="view-staff-directory" onclick="switchDirectoryPanel('staff')" onkeydown="handleDirectoryTabKey(event)" class="usm-dir-switch-btn"><i class="fa-solid fa-user-tie" aria-hidden="true"></i><span>Staf</span></button>
                </div>
            </div>

            <div id="dir-panel-student" role="tabpanel" aria-labelledby="dir-tab-student">
            <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-clean">
                <div class="p-5 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col gap-4 font-mono">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center gap-3">
                            <div>
                                <p class="text-[10px] uppercase tracking-[.18em] text-indigo-600 dark:text-indigo-400 font-bold mb-1">REKOD PELAJAR</p><h2 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">Direktori & Pengurusan Pelajar</h2>
                                <p class="text-[10px] text-slate-500 dark:text-zinc-400">Master CRUD + Sandaran / Restore DB & Cetak Kad.</p>
                            </div>
                        </div>
                        <div id="student-directory-actions" class="flex items-center gap-2 flex-wrap w-full sm:w-auto justify-end">
                            <button onclick="viewAuditHistory()" class="bg-violet-600 hover:bg-violet-700 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer" title="Lihat sejarah bila rekod pelajar dikemas kini">
                                <i class="fa-solid fa-clock-rotate-left"></i> <span>Sejarah Rekod</span>
                            </button>
                            <?php if (($_SESSION['role'] ?? 'viewer') === 'admin') { ?>
                            <button onclick="triggerDatabaseBackup()" class="bg-amber-600 hover:bg-amber-700 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer" title="Muat turun backup penuh pangkalan data">
                                <i class="fa-solid fa-database"></i> <span>Sandaran DB</span>
                            </button>
                            <button onclick="manageServerBackups()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer" title="Lihat dan urus sandaran pelayan automatik">
                                <i class="fa-solid fa-clock"></i> <span>Backup Automatik</span>
                            </button>
                            <button onclick="document.getElementById('restore-file-input').click()" class="bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer" title="Pulihkan pangkalan data dari fail JSON">
                                <i class="fa-solid fa-rotate-left"></i> <span>Pulihkan DB</span>
                            </button>
                            <input type="file" id="restore-file-input" class="hidden" accept=".json,application/json" onchange="uploadRestoreDatabase(this)">
                            <button onclick="downloadMasterCSV()" class="bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 border border-slate-200 dark:border-zinc-700 px-3.5 py-2 rounded-xl text-xs font-medium transition flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-file-arrow-down"></i> <span>Eksport CSV</span>
                            </button>
                            <button type="button" onclick="exportFilteredStudentsToSysweb()" class="bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer" title="Eksport senarai pelajar yang ditapis ke Sysweb">
                                <i class="fa-solid fa-network-wired"></i> <span>Eksport Sysweb</span>
                            </button>
                            <button onclick="document.getElementById('csv-file-input').click()" class="bg-emerald-700 hover:bg-emerald-800 text-white px-3.5 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-file-arrow-up"></i> <span>Import CSV</span>
                            </button>
                            <input type="file" id="csv-file-input" class="hidden" accept=".csv" onchange="uploadCsvStudents(this)">
                            <div id="csv-import-modal" class="hidden fixed inset-0 z-[100] bg-slate-950/50 p-3 sm:p-6 flex items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="csv-import-title" data-modal-close="closeCsvImportModal">
                                <div class="w-full max-w-5xl max-h-[92vh] overflow-hidden rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xl flex flex-col">
                                    <div class="p-4 sm:p-5 border-b border-slate-200 dark:border-zinc-700 flex items-start justify-between gap-3"><div><h3 id="csv-import-title" class="font-semibold text-slate-900 dark:text-white">Pratonton Import CSV</h3><p id="csv-import-subtitle" class="text-xs text-slate-500 mt-1">Semak data sebelum disimpan.</p></div><button type="button" onclick="closeCsvImportModal()" class="rounded-lg border px-3 py-1.5 text-sm">Tutup</button></div>
                                    <div class="p-4 flex flex-wrap gap-2 text-xs"><span id="csv-stat-total" class="rounded-lg bg-slate-100 dark:bg-zinc-800 px-3 py-2">Jumlah: 0</span><span id="csv-stat-valid" class="rounded-lg bg-emerald-50 text-emerald-800 px-3 py-2">Sedia: 0</span><span id="csv-stat-errors" class="rounded-lg bg-rose-50 text-rose-800 px-3 py-2">Ralat: 0</span><button id="csv-download-errors" type="button" onclick="downloadCsvImportErrors()" class="ml-auto rounded-lg border px-3 py-2" disabled>Muat turun laporan ralat</button></div>
                                    <div id="csv-import-preview" class="px-4 pb-4 overflow-auto flex-1"><p class="text-sm text-slate-500">Pilih fail CSV untuk pratonton.</p></div>
                                    <div class="p-4 border-t border-slate-200 dark:border-zinc-700 flex flex-wrap justify-end gap-2"><button type="button" onclick="closeCsvImportModal()" class="rounded-lg border px-4 py-2 text-sm">Batal</button><button id="csv-commit-button" type="button" onclick="commitCsvImport()" class="rounded-lg bg-indigo-600 text-white px-4 py-2 text-sm font-medium" disabled>Import baris sah</button></div>
                                </div>
                            </div>
                            <button onclick="navigateToEditPage()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer whitespace-nowrap">
                                <i class="fa-solid fa-user-plus text-[10px]"></i> <span>+ Tambah Pelajar</span>
                            </button>
                            <?php } ?>
                            <?php if (($_SESSION['role'] ?? 'viewer') !== 'admin') { ?>
                            <button onclick="navigateToEditPage()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-medium transition shadow-sm flex items-center gap-1.5 cursor-pointer whitespace-nowrap"><i class="fa-solid fa-paper-plane text-[10px]"></i><span>+ Minta Tambah Pelajar</span></button>
                            <?php } ?>
                        </div>
                    </div>

                    <section id="expiry-alerts" class="hidden rounded-xl border border-amber-300 bg-amber-50 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100 dark:border-amber-800 px-4 py-4 space-y-3" role="region" aria-label="Amaran tamat tempoh kad">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-sm"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i>Amaran Tamat Tempoh Kad</h3>
                                <p id="expiry-alert-summary" class="text-xs mt-1">Semakan tarikh luput kad.</p>
                            </div>
                            <label class="text-xs flex items-center gap-2">Tempoh amaran
                                <select id="expiry-alert-window" onchange="renderExpiryAlerts()" class="rounded-lg border border-amber-300 dark:border-amber-700 bg-white dark:bg-zinc-900 px-2 py-1.5 text-xs">
                                    <option value="7">7 hari</option><option value="14">14 hari</option><option value="30" selected>30 hari</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div onclick="viewStudentsByExpiryStatus('expired')" class="cursor-pointer group hover:bg-white dark:hover:bg-zinc-800 transition rounded-xl bg-white/70 dark:bg-zinc-900/60 p-3.5 border border-transparent hover:border-rose-300 dark:hover:border-rose-800 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-semibold text-rose-800 dark:text-rose-300 flex items-center gap-1.5"><i class="fa-solid fa-circle-xmark text-rose-500"></i> Kad tamat tempoh</div>
                                    <span class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold group-hover:translate-x-0.5 transition-transform">Tapis ➔</span>
                                </div>
                                <div id="expiry-alert-expired-count" class="text-2xl font-bold text-rose-600 mt-1">0</div>
                                <div class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Klik untuk tapis senarai pelajar ini</div>
                            </div>
                            <div onclick="viewStudentsByExpiryStatus('7')" class="cursor-pointer group hover:bg-white dark:hover:bg-zinc-800 transition rounded-xl bg-white/70 dark:bg-zinc-900/60 p-3.5 border border-transparent hover:border-orange-300 dark:hover:border-orange-800 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-semibold text-orange-800 dark:text-orange-300 flex items-center gap-1.5"><i class="fa-solid fa-triangle-exclamation text-orange-500"></i> Tamat dalam 7 hari</div>
                                    <span class="text-[10px] text-orange-600 dark:text-orange-400 font-semibold group-hover:translate-x-0.5 transition-transform">Tapis ➔</span>
                                </div>
                                <div id="expiry-alert-week-count" class="text-2xl font-bold text-orange-600 mt-1">0</div>
                                <div class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Tindakan segera diperlukan</div>
                            </div>
                            <div onclick="viewStudentsByExpiryStatus('30')" class="cursor-pointer group hover:bg-white dark:hover:bg-zinc-800 transition rounded-xl bg-white/70 dark:bg-zinc-900/60 p-3.5 border border-transparent hover:border-amber-300 dark:hover:border-amber-700 shadow-sm">
                                <div class="flex items-center justify-between">
                                    <div id="expiry-alert-window-label" class="text-xs font-semibold text-amber-900 dark:text-amber-200 flex items-center gap-1.5"><i class="fa-solid fa-clock text-amber-500"></i> Tamat dalam 30 hari</div>
                                    <span class="text-[10px] text-amber-700 dark:text-amber-400 font-semibold group-hover:translate-x-0.5 transition-transform">Tapis ➔</span>
                                </div>
                                <div id="expiry-alert-soon-count" class="text-2xl font-bold text-amber-600 mt-1">0</div>
                                <div class="text-[10px] text-slate-500 dark:text-zinc-400 mt-0.5">Klik untuk lihat & perbaharui</div>
                            </div>
                        </div>
                        <div class="overflow-x-auto rounded-lg border border-amber-200 dark:border-amber-800">
                            <table class="w-full text-xs text-left"><thead class="bg-amber-100/70 dark:bg-amber-900/40"><tr><th class="p-2.5">Pelajar</th><th class="p-2.5">Matrik</th><th class="p-2.5">Tarikh luput</th><th class="p-2.5">Status</th></tr></thead><tbody id="expiry-alert-tbody"><tr><td colspan="4" class="p-3">Memuatkan...</td></tr></tbody></table>
                        </div>
                        <p class="text-[11px] opacity-75">Senarai menunjukkan sehingga 10 rekod paling mendesak. Klik mana-mana baris atau kad statistik untuk melihat senarai penuh dalam jadual di bawah.</p>
                    </section>

                    <!-- QUICK FILTER PILLS BAR -->
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-b border-slate-100 dark:border-zinc-800/60 pb-3" id="quick-expiry-pills">
                        <span class="text-[10px] font-bold text-slate-400 dark:text-zinc-400 uppercase tracking-wider mr-1">Tapis Pantas:</span>
                        <button type="button" id="pill-filter-all" onclick="viewStudentsByExpiryStatus('')" class="pill-filter-btn active px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer flex items-center gap-1.5 bg-slate-900 text-white dark:bg-zinc-100 dark:text-zinc-900 shadow-sm">
                            <span>Semua Pelajar</span> <span id="pill-count-all" class="text-[10px] opacity-80">(0)</span>
                        </button>
                        <button type="button" id="pill-filter-expiring30" onclick="viewStudentsByExpiryStatus('30')" class="pill-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 bg-amber-50 text-amber-900 border border-amber-300/80 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-200 dark:border-amber-800 shadow-sm">
                            <i class="fa-solid fa-clock text-amber-500"></i>
                            <span>Hampir Luput (≤ 30 Hari)</span>
                            <span id="pill-count-expiring30" class="px-1.5 py-0.5 rounded-full bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100 text-[10px] font-bold">0</span>
                        </button>
                        <button type="button" id="pill-filter-expiring7" onclick="viewStudentsByExpiryStatus('7')" class="pill-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 bg-orange-50 text-orange-900 border border-orange-300/80 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-200 dark:border-orange-800 shadow-sm">
                            <i class="fa-solid fa-triangle-exclamation text-orange-500"></i>
                            <span>Kritikal (≤ 7 Hari)</span>
                            <span id="pill-count-expiring7" class="px-1.5 py-0.5 rounded-full bg-orange-200 text-orange-900 dark:bg-orange-900 dark:text-orange-100 text-[10px] font-bold">0</span>
                        </button>
                        <button type="button" id="pill-filter-expired" onclick="viewStudentsByExpiryStatus('expired')" class="pill-filter-btn px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5 bg-rose-50 text-rose-900 border border-rose-300/80 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-200 dark:border-rose-800 shadow-sm">
                            <i class="fa-solid fa-circle-xmark text-rose-500"></i>
                            <span>Tamat Tempoh</span>
                            <span id="pill-count-expired" class="px-1.5 py-0.5 rounded-full bg-rose-200 text-rose-900 dark:bg-rose-900 dark:text-rose-100 text-[10px] font-bold">0</span>
                        </button>
                        <button type="button" id="pill-filter-active" onclick="viewStudentsByExpiryStatus('active')" class="pill-filter-btn px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer flex items-center gap-1.5 bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 dark:bg-zinc-800 dark:text-zinc-200 dark:border-zinc-700 shadow-sm">
                            <i class="fa-solid fa-circle-check text-emerald-500"></i>
                            <span>Kad Aktif</span>
                            <span id="pill-count-active" class="text-[10px] opacity-75">(0)</span>
                        </button>
                    </div>

                    <!-- ACTIVE FILTER BANNER -->
                    <div id="active-filter-alert" class="hidden bg-indigo-50/90 dark:bg-indigo-950/40 border border-indigo-200/80 dark:border-indigo-800/60 rounded-xl px-3.5 py-2 flex items-center justify-between text-xs text-indigo-950 dark:text-indigo-200">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-filter text-indigo-600 dark:text-indigo-400"></i>
                            <span id="active-filter-text" class="font-medium">Menapis: Pelajar Hampir Luput</span>
                        </div>
                        <button type="button" onclick="viewStudentsByExpiryStatus('')" class="text-indigo-700 hover:text-indigo-900 dark:text-indigo-300 dark:hover:text-white font-semibold flex items-center gap-1 text-[11px] cursor-pointer">
                            <i class="fa-solid fa-xmark"></i> Padam Tapisan
                        </button>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-2">
                        <div class="relative">
                            <label for="master-search" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Carian menyeluruh</label>
                            <span class="absolute left-3 top-[34px] flex items-center pointer-events-none text-slate-400 text-xs"><i class="fa-solid fa-search"></i></span>
                            <input type="search" id="master-search" aria-label="Cari nama, matrik, USM UID, nombor kad atau pusat pengajian" placeholder="Cari nama, matrik, UID, kad atau sekolah…" oninput="filterMasterTable()" class="w-full pl-8 pr-3 py-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="master-school-filter" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Pusat pengajian</label>
                            <select id="master-school-filter" onchange="filterMasterTable()" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                                <option value="">Semua Pusat Pengajian</option>
                            </select>
                        </div>
                        <div>
                            <label for="master-education-filter" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Tahap pendidikan</label>
                            <select id="master-education-filter" onchange="filterMasterTable()" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white"><option value="">Semua tahap</option></select>
                        </div>
                        <div>
                            <label for="master-category-filter" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Kategori pelajar</label>
                            <select id="master-category-filter" onchange="filterMasterTable()" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white"><option value="">Semua kategori</option><option value="Local">Tempatan</option><option value="International">Antarabangsa</option><option value="Unknown">Belum disahkan</option></select>
                        </div>
                        <div>
                            <label for="master-status-filter" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Status kad</label>
                            <select id="master-status-filter" onchange="filterMasterTable()" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                                <option value="">Semua status</option><option value="active">Aktif</option><option value="7">Tamat dalam 7 hari</option><option value="14">Tamat dalam 14 hari</option><option value="30">Tamat dalam 30 hari</option><option value="expired">Tamat tempoh</option>
                            </select>
                        </div>
                        <div>
                            <label for="master-sort" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Susun mengikut</label>
                            <select id="master-sort" onchange="filterMasterTable()" class="w-full bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                                <option value="name_asc">Nama (A–Z)</option><option value="name_desc">Nama (Z–A)</option><option value="school_asc">Pusat pengajian (A–Z)</option><option value="school_desc">Pusat pengajian (Z–A)</option><option value="date_joined_desc">Tarikh daftar (terbaharu)</option><option value="date_joined_asc">Tarikh daftar (terlama)</option><option value="expiry_date_asc">Tarikh luput (terdekat)</option><option value="expiry_date_desc">Tarikh luput (terjauh)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div id="student-duplicate-alert" class="hidden mx-5 mt-4 p-4 rounded-xl border border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100" role="status" aria-live="polite">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="flex items-start gap-3"><i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-600"></i><div><p class="font-semibold text-sm">Rekod berkemungkinan pendua dikesan</p><p id="student-duplicate-summary" class="text-xs mt-1 opacity-80">Semak matrik, UID dan nombor kad.</p></div></div>
                        <div class="flex gap-2"><button type="button" id="duplicate-filter-button" onclick="toggleDuplicateFilter()" class="px-3 py-2 rounded-lg border border-amber-300 dark:border-amber-800 text-xs font-semibold hover:bg-amber-100 dark:hover:bg-amber-900/40">Lihat rekod pendua</button><button type="button" onclick="dismissDuplicateAlert()" class="px-3 py-2 rounded-lg text-xs text-amber-800 dark:text-amber-200 hover:bg-amber-100 dark:hover:bg-amber-900/40">Tutup</button></div>
                    </div>
                </div>

                <!-- BULK ACTIONS FLOATING / BANNER TOOLBAR -->
                <div id="master-bulk-bar" class="hidden mx-5 my-3 p-3 bg-indigo-50/90 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-800 rounded-xl flex flex-wrap items-center justify-between gap-3 text-xs transition-all font-mono">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-md bg-indigo-600 text-white font-bold text-xs" id="bulk-selected-badge">0</span>
                        <span class="font-semibold text-slate-800 dark:text-zinc-200" id="bulk-selected-text">0 pelajar dipilih</span>
                        <button type="button" onclick="selectEntireFilteredList()" id="bulk-select-all-filtered-btn" class="hidden text-indigo-600 dark:text-indigo-400 hover:underline font-bold text-[11px] ml-1 cursor-pointer">
                            Pilih semua rekod yang ditapis
                        </button>
                        <span class="text-slate-300 dark:text-zinc-700">|</span>
                        <button type="button" onclick="clearMasterSelection()" class="text-slate-500 hover:text-slate-700 dark:text-zinc-400 dark:hover:text-zinc-200 text-[11px] cursor-pointer flex items-center gap-1 font-sans">
                            <i class="fa-solid fa-xmark text-slate-400"></i> Batal Pilihan
                        </button>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" onclick="bulkExportSelectedCSV()" class="bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                            <i class="fa-solid fa-file-arrow-down text-emerald-600 dark:text-emerald-400"></i>
                            <span>Eksport Terpilih (CSV)</span>
                        </button>
                        <button type="button" onclick="bulkExportSelectedSysweb()" class="bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5 cursor-pointer shadow-sm" title="Eksport rekod terpilih ke Sysweb">
                            <i class="fa-solid fa-network-wired text-indigo-600 dark:text-indigo-400"></i>
                            <span>Eksport Terpilih (Sysweb)</span>
                        </button>
                        <button type="button" onclick="navigateToDataQuality()" class="bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 border border-slate-200 dark:border-zinc-700 px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                            <i class="fa-solid fa-shield-heart text-amber-600 dark:text-amber-400"></i><span>Kualiti Data</span>
                        </button>
                        <?php if (($_SESSION['role'] ?? 'viewer') === 'admin') { ?>
                        <button type="button" onclick="navigateToBulkUpdatePage()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                            <i class="fa-solid fa-pen-to-square"></i>
                            <span>Kemaskini Pukal</span>
                        </button>
                        <button type="button" onclick="confirmBulkDelete()" class="bg-rose-600 hover:bg-rose-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 cursor-pointer shadow-sm">
                            <i class="fa-solid fa-trash"></i>
                            <span>Padam Terpilih</span>
                        </button>
                        <?php } ?>
                    </div>
                </div>

                <div class="overflow-x-auto min-h-[380px]">
                    <table class="w-full text-left text-xs font-mono">
                        <thead class="bg-slate-50/80 dark:bg-zinc-900 text-slate-400 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase tracking-wider sticky top-0">
                            <tr>
                                <th class="p-3.5 w-10 text-center">
                                    <input type="checkbox" id="master-select-all" onchange="toggleMasterSelectAll(this)" class="rounded accent-indigo-600 cursor-pointer" title="Pilih Semua di Muka Surat Ini">
                                </th>
                                <th class="p-3.5">Bil</th>
                                <th class="p-3.5"><button type="button" onclick="sortMasterByColumn('name')" class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-300" title="Klik untuk susun nama">Nama Penuh <i id="sort-icon-name" class="fa-solid fa-sort text-slate-300"></i></button></th>
                                <th class="p-3.5">Matrik</th>
                                <th class="p-3.5"><button type="button" onclick="sortMasterByColumn('school')" class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-300" title="Klik untuk susun pusat pengajian">Pusat Pengajian <i id="sort-icon-school" class="fa-solid fa-sort text-slate-300"></i></button></th>
                                <th class="p-3.5">UID Asal</th>
                                <th class="p-3.5"><button type="button" onclick="sortMasterByColumn('dateJoined')" class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-300" title="Klik untuk susun tarikh daftar">Tarikh Daftar <i id="sort-icon-dateJoined" class="fa-solid fa-sort text-slate-300"></i></button></th>
                                <th class="p-3.5"><button type="button" onclick="sortMasterByColumn('expiryDate')" class="inline-flex items-center gap-1 hover:text-indigo-600 dark:hover:text-indigo-300" title="Klik untuk susun tarikh luput">Tarikh Luput & Status <i id="sort-icon-expiryDate" class="fa-solid fa-sort text-slate-300"></i></button></th>
                                <th class="p-3.5 text-center">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody id="master-student-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-600 dark:text-zinc-300"></tbody>
                    </table>
                </div>

                <div class="p-4 bg-slate-50/80 dark:bg-zinc-900 border-t border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3 font-mono text-xs">
                    <div class="text-slate-500 dark:text-zinc-400" id="pagination-info">Menunjukkan 0 dari 0 rekod</div>
                    <div class="flex items-center gap-2 flex-wrap justify-center">
                        <label for="master-page-size" class="text-slate-500 dark:text-zinc-400">Baris:</label>
                        <select id="master-page-size" onchange="changeRowsPerPage()" class="px-2 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg">
                            <option value="10" selected>10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option>
                        </select>
                        <button onclick="changePage(-1)" id="prev-page-btn" class="px-3 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 cursor-pointer transition">Sebelumnya</button>
                        <span id="page-indicator" class="px-3 py-1.5 text-slate-700 dark:text-zinc-300 font-bold">1 / 1</span>
                        <button onclick="changePage(1)" id="next-page-btn" class="px-3 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 cursor-pointer transition">Seterusnya</button>
                    </div>
                </div>
            </section>
            </div>

            <div id="view-staff-directory" role="tabpanel" aria-labelledby="dir-tab-staff" class="hidden space-y-6">
                <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-clean">
                    <div class="p-5 sm:p-6 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-sm"><i class="fa-solid fa-id-badge"></i></div>
                            <div>
                                <p class="text-[10px] uppercase tracking-[.18em] text-indigo-600 dark:text-indigo-400 font-bold mb-1">REKOD STAF</p>
                                <h2 class="font-bold text-lg text-slate-900 dark:text-white">Pengurusan Rekod Staf</h2>
                                <p class="text-xs text-slate-600 dark:text-zinc-400 mt-1">Data ujian disimpan dalam MySQL tempatan, berasingan daripada rekod rasmi USM.</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" onclick="exportStaffDirectoryToSysweb()" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800 px-4 py-2.5 text-xs font-semibold transition cursor-pointer" title="Eksport senarai staf ke Sysweb"><i class="fa-solid fa-network-wired text-indigo-600 dark:text-indigo-400"></i>Eksport ke Sysweb</button>
                            <button type="button" onclick="openLocalStaffForm()" <?php echo (($_SESSION['role'] ?? '') !== 'admin') ? 'hidden' : ''; ?> class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 text-xs font-bold shadow-sm"><i class="fa-solid fa-user-plus"></i>Tambah Staf</button>
                            <button type="button" id="sync-live-staff-button" onclick="syncLiveStaffToLocal()" <?php echo (($_SESSION['role'] ?? '') !== 'admin') ? 'hidden' : ''; ?> class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800 px-4 py-2.5 text-xs font-semibold transition"><i class="fa-solid fa-cloud-arrow-down text-indigo-600 dark:text-indigo-400"></i>Import dari SQL Server</button>
                            <button type="button" onclick="loadStaffDirectory(true)" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-800 px-4 py-2.5 text-xs font-semibold transition"><i class="fa-solid fa-rotate"></i>Muat Semula</button>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-xl border border-slate-200 dark:border-zinc-800/60 bg-slate-50 dark:bg-zinc-900/60 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold">Jumlah rekod ujian</p><p id="staff-directory-count" class="text-3xl font-bold text-slate-900 dark:text-white mt-1">—</p></div>
                            <div class="sm:col-span-2"><label for="staff-directory-search" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-zinc-400 mb-1.5">Carian staf</label><input id="staff-directory-search" type="search" oninput="filterStaffDirectory()" placeholder="Cari nama, nombor staf, jabatan, UID kad, siri kad atau status…" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3.5 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500"></div>
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/20 dark:border-amber-900/70 px-4 py-3 text-xs text-amber-900 dark:text-amber-200"><i class="fa-solid fa-flask mr-2"></i><strong>Mod ujian tempatan:</strong> Tambah, kemas kini dan padam hanya mengubah jadual <code>usmsmart_staff_directory</code> dalam MySQL. Jadual rasmi SQL Server <code>dbo.Staf</code> kekal baca sahaja.</div>

                        <div id="local-staff-form-panel" class="hidden rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-900/40 p-4 sm:p-5">
                            <div class="flex items-center justify-between gap-3 mb-4"><div><h3 id="local-staff-form-title" class="font-bold text-sm text-slate-900 dark:text-white">Tambah rekod staf</h3><p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Semua medan disimpan ke pangkalan data MySQL tempatan.</p></div><button type="button" onclick="closeLocalStaffForm()" class="w-8 h-8 rounded-lg bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700" aria-label="Tutup borang"><i class="fa-solid fa-xmark"></i></button></div>
                            <form id="local-staff-form" onsubmit="submitLocalStaffForm(event)" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <input type="hidden" id="local-staff-id" value="0">
                                <div><label for="local-staff-no" class="block text-[11px] font-semibold mb-1">No. staf <span class="text-rose-600">*</span></label><input id="local-staff-no" maxlength="80" required class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div><label for="local-staff-name" class="block text-[11px] font-semibold mb-1">Nama penuh <span class="text-rose-600">*</span></label><input id="local-staff-name" maxlength="180" required class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div><label for="local-staff-department" class="block text-[11px] font-semibold mb-1">Jabatan</label><input id="local-staff-department" maxlength="180" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div><label for="local-staff-uid" class="block text-[11px] font-semibold mb-1">UID kad (heks)</label><input id="local-staff-uid" maxlength="50" placeholder="4A3B2C1D" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm font-mono"></div>
                                <div><label for="local-staff-card" class="block text-[11px] font-semibold mb-1">No. siri kad</label><input id="local-staff-card" maxlength="100" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div><label for="local-staff-status" class="block text-[11px] font-semibold mb-1">Status kad</label><select id="local-staff-status" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"><option>Aktif</option><option>Tidak Aktif</option><option>Luput</option><option>Dalam Proses</option><option>Tidak Dinyatakan</option></select></div>
                                <div><label for="local-staff-joined" class="block text-[11px] font-semibold mb-1">Tarikh mula / diberi</label><input id="local-staff-joined" type="date" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div><label for="local-staff-expiry" class="block text-[11px] font-semibold mb-1">Tarikh tamat kad</label><input id="local-staff-expiry" type="date" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm"></div>
                                <div class="sm:col-span-2"><label for="local-staff-notes" class="block text-[11px] font-semibold mb-1">Catatan</label><input id="local-staff-notes" maxlength="500" class="w-full rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-3 py-2.5 text-sm" placeholder="Catatan ringkas (pilihan)"></div>
                                <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap justify-end gap-2 pt-2"><button type="button" onclick="closeLocalStaffForm()" class="rounded-lg bg-slate-100 dark:bg-zinc-800 px-4 py-2.5 text-xs font-semibold">Batal</button><button type="submit" id="local-staff-submit" class="rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 text-xs font-bold"><i class="fa-solid fa-floppy-disk mr-1.5"></i>Simpan Rekod</button></div>
                            </form>
                        </div>

                        <div id="staff-directory-message" class="rounded-xl border border-slate-200 bg-slate-50 text-slate-700 dark:bg-zinc-900 dark:border-zinc-800 dark:text-zinc-300 px-4 py-3 text-xs" role="status">Memuatkan rekod ujian staf tempatan…</div>
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800">
                            <table class="min-w-[1160px] w-full text-xs text-left">
                                <thead><tr><th class="px-3 py-3 font-semibold">Nama staf</th><th class="px-3 py-3 font-semibold">No. staf</th><th class="px-3 py-3 font-semibold">Jabatan</th><th class="px-3 py-3 font-semibold">UID kad</th><th class="px-3 py-3 font-semibold">No. siri kad</th><th class="px-3 py-3 font-semibold">Status kad</th><th class="px-3 py-3 font-semibold">Tarikh tamat</th><th class="px-3 py-3 font-semibold">Tindakan</th></tr></thead>
                                <tbody id="staff-directory-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800 text-slate-700 dark:text-zinc-300"><tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">Belum memuatkan data staf.</td></tr></tbody>
                            </table>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-zinc-400">Rekod pelajar dan staf berkongsi satu halaman. Data staf ialah jadual ujian MySQL tempatan; jadual rasmi <code>dbo.Staf</code> kekal baca sahaja. Hanya Admin boleh membuat perubahan.</p>
                    </div>
                </section>
            </div>
        </div>

        <!-- HALAMAN KHAS: PENGURUSAN AKAUN PENGGUNA (ADMIN) -->
        <div id="view-user-management" class="hidden page-transition space-y-6">
            <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-clean">
                <div class="p-5 sm:p-6 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="backToMainTransporter()" class="w-9 h-9 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition" title="Kembali ke dashboard"><i class="fa-solid fa-arrow-left text-xs"></i></button>
                        <div>
                            <p class="text-[10px] uppercase tracking-[.18em] text-indigo-600 dark:text-indigo-400 font-bold font-mono">KAWALAN AKSES</p>
                            <h2 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white tracking-tight">Pengurusan Akaun Pengguna</h2>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Cipta akaun, semak status dan urus peranan pengguna.</p>
                        </div>
                    </div>
                    <button type="button" onclick="loadUserManagement()" class="px-3.5 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-700 text-xs font-medium transition"><i class="fa-solid fa-rotate mr-1.5"></i>Muat semula</button>
                </div>
                <div class="p-5 sm:p-6 space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/70 p-4"><div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Jumlah akaun</span><span class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 flex items-center justify-center"><i class="fa-solid fa-users"></i></span></div><div id="users-total-count" class="text-2xl font-bold mt-2 text-slate-900 dark:text-white">—</div></div>
                        <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/70 p-4"><div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Akaun aktif</span><span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center"><i class="fa-solid fa-user-check"></i></span></div><div id="users-active-count" class="text-2xl font-bold mt-2 text-slate-900 dark:text-white">—</div></div>
                        <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/70 p-4"><div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Pentadbir aktif</span><span class="w-8 h-8 rounded-xl bg-violet-100 dark:bg-violet-950/70 text-violet-600 dark:text-violet-400 flex items-center justify-center"><i class="fa-solid fa-shield-halved"></i></span></div><div id="users-admin-count" class="text-2xl font-bold mt-2 text-slate-900 dark:text-white">—</div></div>
                    </div>
                    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_minmax(280px,.72fr)] gap-5 items-start">
                        <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden min-w-0">
                            <div class="p-4 border-b border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between bg-white dark:bg-[#121214]">
                                <div><h3 class="font-semibold text-sm text-slate-900 dark:text-white">Senarai akaun</h3><p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Urus peranan, kata laluan, status dan pemadaman akaun.</p></div>
                                <div class="relative w-full sm:w-64"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i><input id="user-management-search" type="search" oninput="filterUserManagementTable()" placeholder="Cari nama atau peranan..." class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 text-xs text-slate-900 dark:text-white focus:border-indigo-500 outline-none"></div>
                            </div>
                            <div class="overflow-x-auto"><table class="w-full text-left text-xs min-w-[680px]"><thead class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 uppercase tracking-wider text-[10px]"><tr><th class="p-3.5">Pengguna</th><th class="p-3.5">Peranan</th><th class="p-3.5">Status</th><th class="p-3.5">Login terakhir</th><th class="p-3.5 text-right">Tindakan</th></tr></thead><tbody id="user-management-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800 text-slate-700 dark:text-zinc-300"><tr><td colspan="5" class="p-8 text-center text-slate-500">Memuatkan akaun...</td></tr></tbody></table></div>
                            <div id="user-management-count" class="px-4 py-3 bg-slate-50 dark:bg-zinc-900 border-t border-slate-200 dark:border-zinc-800 text-[11px] text-slate-500 dark:text-zinc-400">Memuatkan senarai akaun...</div>
                        </div>
                        <form id="create-user-form" onsubmit="submitCreateUser(event)" class="rounded-2xl border border-slate-200 dark:border-zinc-800 p-5 space-y-4 bg-white dark:bg-[#121214]">
                            <div class="flex items-start gap-3"><div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900 flex items-center justify-center"><i class="fa-solid fa-user-plus"></i></div><div><h3 class="font-semibold text-sm text-slate-900 dark:text-white">Cipta akaun baharu</h3><p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Kata laluan minimum 12 aksara.</p></div></div>
                            <div><label for="new-user-username" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Nama pengguna</label><input id="new-user-username" name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9_.\-]{3,80}" autocomplete="off" placeholder="contoh: staf_uda" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 text-xs text-slate-900 dark:text-white focus:border-indigo-500 outline-none"></div>
                            <div><label for="new-user-password" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Kata laluan sementara</label><input id="new-user-password" name="password" type="password" required minlength="12" autocomplete="new-password" placeholder="Sekurang-kurangnya 12 aksara" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 text-xs text-slate-900 dark:text-white focus:border-indigo-500 outline-none"></div>
                            <div><label for="new-user-role" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Peranan</label><select id="new-user-role" name="role" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-slate-50 dark:bg-zinc-900 text-xs text-slate-900 dark:text-white focus:border-indigo-500 outline-none"><option value="viewer">Viewer — akses baca</option><option value="admin">Admin — akses pentadbir</option></select></div>
                            <button id="create-user-submit" type="submit" class="w-full px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-xs font-semibold transition shadow-sm"><i class="fa-solid fa-plus mr-1.5"></i>Cipta akaun</button>
                            <p class="text-[10px] text-slate-500 dark:text-zinc-400 leading-relaxed">Akses Admin memberi kuasa mengurus pengguna dan fungsi pentadbiran. Berikan peranan ini hanya kepada staf yang dibenarkan.</p>
                        </form>
                    </div>
                </div>
            </section>
        </div>

        <!-- HALAMAN KHAS: KEMASKINI PUKAL -->
        <div id="view-bulk-update" class="hidden page-transition space-y-6">
            <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800 rounded-2xl overflow-hidden shadow-clean">
                <div class="p-5 sm:p-6 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="navigateToDirectoryPage()" class="w-9 h-9 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition cursor-pointer" title="Kembali ke direktori"><i class="fa-solid fa-arrow-left text-xs"></i></button>
                        <div>
                            <p class="text-[10px] uppercase tracking-[.18em] text-indigo-600 dark:text-indigo-400 font-bold font-mono">PENGURUSAN REKOD</p>
                            <h2 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white tracking-tight">Kemaskini Pukal</h2>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Kemas kini beberapa rekod pelajar dalam satu tindakan.</p>
                        </div>
                    </div>
                    <span id="bulk-page-selected-count" class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 text-xs font-semibold"><i class="fa-solid fa-users"></i> 0 pelajar dipilih</span>
                </div>
                <div class="p-5 sm:p-6 space-y-5">
                    <div class="rounded-xl border border-blue-200 dark:border-blue-900 bg-blue-50/70 dark:bg-blue-950/20 p-4 text-xs text-blue-900 dark:text-blue-200 flex gap-3"><i class="fa-solid fa-circle-info mt-0.5"></i><p>Pilih rekod di Direktori Pelajar dahulu. Pilihan rekod akan dikekalkan apabila anda membuka segmen ini. Had maksimum ialah 500 rekod bagi setiap kemas kini.</p></div>
                    <form id="bulk-update-page-form" onsubmit="submitBulkUpdatePage(event)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2"><label for="bulk-page-type" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-300 mb-2">Jenis kemas kini</label>
                            <select id="bulk-page-type" required onchange="onBulkUpdatePageTypeChange()" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                                <option value="">Pilih tindakan...</option><option value="extend_days">Lanjutkan tempoh sah (hari)</option><option value="set_expiry">Tetapkan tarikh luput</option><option value="set_policy">Tukar polisi</option><option value="set_school">Tukar pusat pengajian</option>
                            </select>
                        </div>
                        <div class="md:col-span-2"><label id="bulk-page-value-label" for="bulk-page-value" class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 dark:text-zinc-300 mb-2">Nilai baharu</label>
                            <input id="bulk-page-value" required disabled placeholder="Pilih jenis kemas kini dahulu" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-3 text-sm text-slate-900 dark:text-white outline-none focus:border-indigo-500 disabled:opacity-50">
                            <p id="bulk-page-help" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-2">Arahan dan format nilai akan dipaparkan selepas jenis kemas kini dipilih.</p>
                        </div>
                        <div class="md:col-span-2 flex flex-col sm:flex-row justify-end gap-2 pt-2">
                            <button type="button" onclick="navigateToDirectoryPage()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-200 text-xs font-semibold">Kembali ke direktori</button>
                            <button id="bulk-page-submit" type="submit" disabled class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs font-semibold shadow-sm"><i class="fa-solid fa-check mr-1.5"></i>Sahkan & Kemaskini</button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <!-- HALAMAN KHAS: DATA QUALITY CENTRE -->
        <div id="view-data-quality" class="hidden page-transition space-y-6">
            <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-clean">
                <div class="p-5 sm:p-6 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="navigateToDirectoryPage()" class="w-9 h-9 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition" title="Kembali ke direktori"><i class="fa-solid fa-arrow-left text-xs"></i></button>
                        <div><p class="text-[10px] uppercase tracking-[.18em] text-amber-600 dark:text-amber-400 font-bold font-mono">DATA QUALITY CENTRE</p><h2 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white tracking-tight">Pusat Kualiti Data</h2><p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Kenal pasti rekod pendua, maklumat tidak lengkap, format kad tidak sah dan masalah tarikh.</p></div>
                    </div>
                    <div class="flex flex-wrap gap-2"><button type="button" onclick="exportDataQualityIssues()" class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-slate-700 dark:text-zinc-200 text-xs font-semibold"><i class="fa-solid fa-file-csv mr-1.5 text-emerald-600"></i>Eksport Isu</button><button type="button" id="quality-scan-button" onclick="loadDataQuality(true)" class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold"><i class="fa-solid fa-rotate mr-1.5"></i>Imbas Semula</button></div>
                </div>
                <div class="p-5 sm:p-6 space-y-5">
                    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500">Rekod Diimbas</p><p id="quality-total-records" class="text-2xl font-bold text-slate-900 dark:text-white mt-2">—</p></div>
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500">Rekod Terjejas</p><p id="quality-affected" class="text-2xl font-bold text-amber-600 mt-2">—</p></div>
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500">Isu Pendua</p><p id="quality-duplicates" class="text-2xl font-bold text-rose-600 mt-2">—</p></div>
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500">Kad / UID Tidak Sah</p><p id="quality-invalid-cards" class="text-2xl font-bold text-orange-600 mt-2">—</p></div>
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-4"><p class="text-[10px] uppercase tracking-wide text-slate-500">Kad Luput</p><p id="quality-expired" class="text-2xl font-bold text-slate-600 dark:text-zinc-300 mt-2">—</p></div>
                    </div>
                    <div class="flex flex-col md:flex-row gap-3">
                        <div class="relative flex-1"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i><input id="quality-search" oninput="renderDataQualityIssues()" placeholder="Cari nama, matrik atau mesej isu..." class="w-full pl-9 pr-3 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-800 dark:text-zinc-100 outline-none focus:border-indigo-500"></div>
                        <select id="quality-filter" onchange="renderDataQualityIssues()" class="md:w-64 px-3 py-2.5 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-sm text-slate-800 dark:text-zinc-100"><option value="">Semua jenis isu</option><option value="matric_duplicate">Matrik pendua</option><option value="uid_duplicate">UID pendua</option><option value="serial_duplicate">Nombor kad pendua</option><option value="missing_fields">Maklumat tidak lengkap</option><option value="invalid_uid">UID tidak sah</option><option value="invalid_serial">Nombor kad tidak sah</option><option value="invalid_dates">Tarikh tidak sah</option><option value="expiry_before_joined">Julat tarikh tidak sah</option><option value="expired">Kad telah luput</option></select>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-[11px] text-slate-500 dark:text-zinc-400"><span id="quality-result-count">Tekan Imbas Semula untuk menganalisis rekod.</span><span id="quality-scanned-at"></span></div>
                    <!-- Statistics for the current filter. Hidden until a scan produces issues, and computed
                         from the same predicate as the table below, so the two can never disagree. -->
                    <section id="quality-stats-panel" hidden class="usm-stats-entrant usm-chart-card p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#121214] shadow-clean">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Statistik mengikut tapisan</h3>
                                    <p id="quality-stats-caption" role="status" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Jalankan imbasan untuk memaparkan statistik.</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Eksport statistik kualiti data">
                                    <button type="button" onclick="downloadQualityStatsPdf()" title="Muat turun ringkasan PDF" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-pdf"></i>PDF</button>
                                    <button type="button" onclick="downloadQualityStatsPng()" title="Muat turun PNG semua carta" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-download"></i>PNG</button>
                                    <button type="button" onclick="downloadQualityStatsExcel()" title="Muat turun laporan Excel" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-excel"></i>Excel</button>
                                </div>
                                <div class="usm-stats-ranges" role="group" aria-label="Julat bulan carta">
                                    <button type="button" class="usm-stats-range is-on" onclick="setQualityStatsRange(12, this)">12 bulan</button>
                                    <button type="button" class="usm-stats-range" onclick="setQualityStatsRange(24, this)">24 bulan</button>
                                    <button type="button" class="usm-stats-range" onclick="setQualityStatsRange(36, this)">36 bulan</button>
                                </div>
                            </div>
                        </div>

                        <div class="usm-stats-strip">
                            <div><span class="usm-stats-value" id="quality-stats-in-filter">0</span><span class="usm-stats-label">Isu dalam tapisan</span></div>
                            <div><span class="usm-stats-value" id="quality-stats-records">0</span><span class="usm-stats-label">Rekod terjejas</span></div>
                            <div><span class="usm-stats-value" id="quality-stats-schools">0</span><span class="usm-stats-label">Pusat terjejas</span></div>
                            <div><span class="usm-stats-value" id="quality-stats-types">0</span><span class="usm-stats-label">Jenis isu</span></div>
                        </div>

                        <div class="relative h-64 w-full mt-1"><canvas id="qualityTrendChart"></canvas></div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Campuran jenis isu</h3>
                                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu hiris untuk menapis senarai</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="qualityTypeChart"></canvas></div>
                            </div>
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pusat pengajian paling banyak isu</h3>
                                        <p id="quality-school-note" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu bar untuk menapis mengikut pusat pengajian</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="qualitySchoolChart"></canvas></div>
                            </div>
                        </div>
                    </section>

                    <div class="overflow-x-auto border border-slate-200 dark:border-zinc-800 rounded-xl">
                        <table class="w-full text-left text-xs min-w-[850px]"><thead class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 uppercase tracking-wide text-[10px]"><tr><th class="p-3">Pelajar</th><th class="p-3">Matrik</th><th class="p-3">Jenis Isu</th><th class="p-3">Perincian</th><th class="p-3">Tarikh Luput</th><th class="p-3 text-right">Tindakan</th></tr></thead><tbody id="quality-issues-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800"><tr><td colspan="6" class="p-8 text-center text-slate-500">Tekan “Imbas Semula” untuk memulakan semakan.</td></tr></tbody></table>
                    </div>
                    <p class="text-[11px] text-slate-500 dark:text-zinc-400"><i class="fa-solid fa-circle-info mr-1"></i>Imbasan ini hanya membaca data dan tidak mengubah rekod. Gunakan tindakan Edit untuk membetulkan rekod; pendua perlu disemak sebelum dipadam atau digabungkan.</p>
                </div>
            </section>
        </div>

        <!-- Modal pratonton rekod pendua: membandingkan rekod semasa dengan padanan -->
        <div id="duplicate-preview-modal" class="hidden fixed inset-0 z-[100] bg-slate-950/60 backdrop-blur-sm p-3 sm:p-6 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="duplicate-preview-title" data-modal-close="closeDuplicatePreview" onclick="if(event.target===this)closeDuplicatePreview()">
            <div class="max-w-5xl mx-auto my-6 bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-700 rounded-2xl shadow-2xl overflow-hidden">
                <div class="flex items-start justify-between gap-4 p-5 border-b border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900">
                    <div><p class="text-[10px] uppercase tracking-[.16em] font-bold text-rose-600">SEMAKAN REKOD PENDUA</p><h3 id="duplicate-preview-title" class="text-lg font-bold text-slate-900 dark:text-white">Perbandingan rekod</h3><p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Bandingkan rekod asal dengan rekod yang mempunyai nilai sepadan.</p></div>
                    <button type="button" onclick="closeDuplicatePreview()" class="w-9 h-9 rounded-lg border border-slate-200 dark:border-zinc-700 hover:bg-white dark:hover:bg-zinc-800" aria-label="Tutup pratonton"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div id="duplicate-preview-content" class="p-5 space-y-4"></div>
                <div class="p-4 border-t border-slate-200 dark:border-zinc-800 flex justify-end"><button type="button" onclick="closeDuplicatePreview()" class="px-4 py-2 rounded-xl bg-slate-900 text-white dark:bg-white dark:text-slate-900 text-sm font-semibold">Tutup pratonton</button></div>
            </div>
        </div>

        <!-- HALAMAN KHAS: SEJARAH REKOD & AUDIT LOG (DEDICATED SEGMENT) -->
        <div id="view-audit-history" class="hidden page-transition space-y-6">
            <section class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl overflow-hidden shadow-clean">
                <div class="p-5 sm:p-6 bg-slate-50/80 dark:bg-zinc-900 border-b border-slate-200/80 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="backFromAuditHistory()" class="w-9 h-9 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 transition cursor-pointer" title="Kembali"><i class="fa-solid fa-arrow-left text-xs"></i></button>
                        <div>
                            <p class="text-[10px] uppercase tracking-[.18em] text-violet-600 dark:text-violet-400 font-bold font-mono">AUDIT TRAIL & LOG AKTIVITI</p>
                            <h2 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white tracking-tight">Sejarah Rekod & Aktiviti Sistem</h2>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Jejak lengkap setiap kemas kini rekod pelajar, pendaftaran, pemadaman dan aktiviti keselamatan.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" onclick="loadAuditHistoryPage(1)" class="px-3.5 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-700 text-xs font-medium transition cursor-pointer"><i class="fa-solid fa-rotate mr-1.5"></i>Muat Semula</button>
                        <button type="button" onclick="exportAuditLogCSV()" class="px-3.5 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-medium transition shadow-sm cursor-pointer flex items-center gap-1.5"><i class="fa-solid fa-file-csv"></i>Eksport CSV</button>
                    </div>
                </div>

                <div class="p-5 sm:p-6 space-y-5">
                    <!-- Metric Cards -->
                    <div data-usm-reveal class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div onclick="quickFilterAuditAction('')" class="cursor-pointer group hover:border-violet-300 transition rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/70 p-4">
                            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Jumlah Peristiwa</span><span class="w-8 h-8 rounded-xl bg-violet-100 dark:bg-violet-950/70 text-violet-600 dark:text-violet-400 flex items-center justify-center text-xs"><i class="fa-solid fa-clock-rotate-left"></i></span></div>
                            <div id="audit-stat-total" class="text-2xl font-bold mt-2 text-slate-900 dark:text-white">—</div>
                            <div class="text-[10px] text-violet-600 dark:text-violet-400 mt-0.5 group-hover:underline">Papar semua aktiviti →</div>
                        </div>
                        <div onclick="quickFilterAuditAction('update')" class="cursor-pointer group hover:border-blue-300 transition rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/30 dark:bg-zinc-900/70 p-4">
                            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Kemas Kini Rekod</span><span class="w-8 h-8 rounded-xl bg-blue-100 dark:bg-blue-950/70 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs"><i class="fa-solid fa-pen-to-square"></i></span></div>
                            <div id="audit-stat-updates" class="text-2xl font-bold mt-2 text-blue-600 dark:text-blue-400">—</div>
                            <div class="text-[10px] text-blue-600 dark:text-blue-400 mt-0.5 group-hover:underline">Klik untuk tapis kemas kini →</div>
                        </div>
                        <div onclick="quickFilterAuditAction('create,save_student,import_csv')" class="cursor-pointer group hover:border-emerald-300 transition rounded-2xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/30 dark:bg-zinc-900/70 p-4">
                            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Pendaftaran / Tambah</span><span class="w-8 h-8 rounded-xl bg-emerald-100 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs"><i class="fa-solid fa-user-plus"></i></span></div>
                            <div id="audit-stat-inserts" class="text-2xl font-bold mt-2 text-emerald-600 dark:text-emerald-400">—</div>
                            <div class="text-[10px] text-emerald-600 dark:text-emerald-400 mt-0.5 group-hover:underline">Klik untuk tapis pendaftaran →</div>
                        </div>
                        <div onclick="quickFilterAuditAction('delete,delete_user,soft_delete_session')" class="cursor-pointer group hover:border-rose-300 transition rounded-2xl border border-rose-200 dark:border-rose-900/60 bg-rose-50/30 dark:bg-zinc-900/70 p-4">
                            <div class="flex items-center justify-between"><span class="text-[11px] text-slate-500 dark:text-zinc-400">Pemadaman Data</span><span class="w-8 h-8 rounded-xl bg-rose-100 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 flex items-center justify-center text-xs"><i class="fa-solid fa-trash-can"></i></span></div>
                            <div id="audit-stat-deletes" class="text-2xl font-bold mt-2 text-rose-600 dark:text-rose-400">—</div>
                            <div class="text-[10px] text-rose-600 dark:text-rose-400 mt-0.5 group-hover:underline">Klik untuk tapis pemadaman →</div>
                        </div>
                    </div>

                    <!-- Search & Filter Controls -->
                    <div data-usm-reveal class="p-4 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#121214] space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div class="relative">
                                <label for="audit-search-input" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Cari Rekod</label>
                                <span class="absolute left-3 top-[34px] flex items-center pointer-events-none text-slate-400 text-xs"><i class="fa-solid fa-search"></i></span>
                                <input type="search" id="audit-search-input" oninput="debounceAuditSearch()" placeholder="Cari nama, matrik, ID, staf..." class="w-full pl-8 pr-3 py-2 bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl text-xs text-slate-900 dark:text-white outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label for="audit-action-filter" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Jenis Tindakan</label>
                                <select id="audit-action-filter" onchange="loadAuditHistoryPage(1)" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-violet-500">
                                    <option value="">Semua Tindakan</option>
                                    <option value="create,save_student,import_csv">Pendaftaran / Tambah (semua)</option>
                                    <option value="delete,delete_user,soft_delete_session">Pemadaman (semua)</option>
                                    <option value="update">Kemas Kini Pelajar (Update)</option>
                                    <option value="create">Tambah Pelajar Baharu (Create)</option>
                                    <option value="delete">Padam Pelajar (Delete)</option>
                                    <option value="import_csv">Import Pelajar (CSV)</option>
                                    <option value="export_csv">Eksport Pelajar (CSV)</option>
                                    <option value="dump_uda">Eksport UDA Live DB</option>
                                    <option value="backup_database">Sandaran Pangkalan Data</option>
                                    <option value="restore_database">Pemulihan Pangkalan Data</option>
                                    <option value="login">Log Masuk Pengguna</option>
                                    <option value="logout">Log Keluar Pengguna</option>
                                </select>
                            </div>
                            <div>
                                <label for="audit-start-date" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Dari Tarikh</label>
                                <input type="date" id="audit-start-date" onchange="loadAuditHistoryPage(1)" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-violet-500">
                            </div>
                            <div>
                                <label for="audit-end-date" class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1.5">Hingga Tarikh</label>
                                <div class="flex gap-2">
                                    <input type="date" id="audit-end-date" onchange="loadAuditHistoryPage(1)" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-violet-500">
                                    <button type="button" onclick="resetAuditFilters()" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 text-slate-600 dark:text-zinc-300 text-xs font-medium cursor-pointer" title="Kosongkan Tapisan"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics. Hidden until the figures arrive, and hidden altogether for a role
                         that cannot read the log. -->
                    <section id="audit-stats-panel" hidden class="usm-stats-entrant usm-chart-card p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#121214] shadow-clean">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Statistik mengikut tapisan</h3>
                                    <p id="audit-stats-caption" role="status" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Memuatkan angka…</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Eksport statistik log audit">
                                    <button type="button" onclick="downloadAuditStatsPdf()" title="Muat turun ringkasan PDF" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-pdf"></i>PDF</button>
                                    <button type="button" onclick="downloadAuditStatsPng()" title="Muat turun PNG semua carta" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-download"></i>PNG</button>
                                    <button type="button" onclick="downloadAuditStatsExcel()" title="Muat turun laporan Excel" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-excel"></i>Excel</button>
                                </div>
                                <div class="usm-stats-ranges" role="group" aria-label="Julat hari carta">
                                    <button type="button" class="usm-stats-range" onclick="setAuditStatsRange(7, this)">7 hari</button>
                                    <button type="button" class="usm-stats-range is-on" onclick="setAuditStatsRange(30, this)">30 hari</button>
                                    <button type="button" class="usm-stats-range" onclick="setAuditStatsRange(90, this)">90 hari</button>
                                </div>
                            </div>
                        </div>

                        <div class="usm-stats-strip">
                            <div><span class="usm-stats-value" id="audit-stats-in-range">0</span><span class="usm-stats-label">Peristiwa dalam <span id="audit-stats-range-label">30</span> hari</span></div>
                            <div><span class="usm-stats-value" id="audit-stats-today">0</span><span class="usm-stats-label">Hari ini</span></div>
                            <div><span class="usm-stats-value" id="audit-stats-week">0</span><span class="usm-stats-label">7 hari lepas</span><span id="audit-stats-week-delta" class="usm-stats-delta is-flat">—</span></div>
                            <div><span class="usm-stats-value" id="audit-stats-actors">0</span><span class="usm-stats-label">Pengguna aktif</span></div>
                        </div>

                        <div class="relative h-64 w-full mt-1"><canvas id="auditDailyChart"></canvas></div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Campuran tindakan</h3>
                                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu hiris untuk menapis senarai</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="auditActionChart"></canvas></div>
                            </div>
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pengguna paling aktif</h3>
                                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu bar untuk menapis mengikut pengguna</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-user-check"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="auditActorChart"></canvas></div>
                            </div>
                        </div>
                    </section>

                    <!-- Table -->
                    <div data-usm-reveal class="rounded-2xl border border-slate-200 dark:border-zinc-800 overflow-hidden min-w-0 bg-white dark:bg-[#121214] shadow-sm">
                        <div class="overflow-x-auto min-h-[380px]">
                            <table class="w-full text-left text-xs min-w-[780px]">
                                <thead class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 uppercase tracking-wider text-[10px] border-b border-slate-200 dark:border-zinc-800">
                                    <tr>
                                        <th class="p-3.5 w-40">Masa & Tarikh</th>
                                        <th class="p-3.5 w-36">Tindakan</th>
                                        <th class="p-3.5 w-48">Entiti / Matrik</th>
                                        <th class="p-3.5 w-32">Pengguna (Actor)</th>
                                        <th class="p-3.5">Perincian Perubahan / Aktiviti</th>
                                    </tr>
                                </thead>
                                <tbody id="audit-log-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800/80 text-slate-700 dark:text-zinc-300">
                                    <tr><td colspan="5" class="p-8 text-center text-slate-400">Memuatkan sejarah rekod...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="p-4 bg-slate-50/80 dark:bg-zinc-900 border-t border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs font-mono">
                            <div id="audit-pagination-info" class="text-slate-500 dark:text-zinc-400">Menunjukkan 0 dari 0 rekod</div>
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <label for="audit-page-size" class="text-slate-500 dark:text-zinc-400">Baris:</label>
                                <select id="audit-page-size" onchange="changeAuditPageSize()" class="px-2 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg text-slate-900 dark:text-white">
                                    <option value="10">10</option>
                                    <option value="20" selected>20</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button type="button" onclick="changeAuditPage(-1)" id="audit-prev-page-btn" class="px-3 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 cursor-pointer transition">Sebelumnya</button>
                                <span id="audit-page-indicator" class="px-3 py-1.5 text-slate-700 dark:text-zinc-300 font-bold">1 / 1</span>
                                <button type="button" onclick="changeAuditPage(1)" id="audit-next-page-btn" class="px-3 py-1.5 bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 cursor-pointer transition">Seterusnya</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Audit event detail modal -->
        <div id="audit-detail-modal" class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="audit-detail-title" data-modal-close="closeAuditDetail" onclick="if(event.target===this) closeAuditDetail()">
            <div class="w-full max-w-2xl max-h-[85vh] overflow-y-auto rounded-2xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-[#121214] shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-slate-200 dark:border-zinc-800 p-5">
                    <div><p class="text-[10px] uppercase tracking-widest font-bold text-violet-600 dark:text-violet-400">AUDIT EVENT</p><h3 id="audit-detail-title" class="mt-1 text-base font-bold text-slate-900 dark:text-white">Butiran aktiviti</h3></div>
                    <button type="button" onclick="closeAuditDetail()" class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-zinc-800 text-slate-600 dark:text-zinc-300" aria-label="Tutup butiran"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div id="audit-detail-content" class="p-5 space-y-4"></div>
                <div class="flex justify-end border-t border-slate-200 dark:border-zinc-800 p-4"><button type="button" onclick="closeAuditDetail()" class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold">Tutup</button></div>
            </div>
        </div>


        <!-- HALAMAN KHAS 2: BORANG EDIT / TAMBAH PELAJAR -->

        <!-- HALAMAN KHAS: SEGMEN & PORTAL INTEGRASI SYSWEB -->
        <div id="view-sysweb" class="hidden page-transition space-y-6">
            <section class="bg-white dark:bg-[#121214] border border-emerald-200 dark:border-emerald-900/60 rounded-2xl overflow-hidden shadow-clean">
                <!-- Segment Top Header -->
                <div class="p-5 sm:p-6 bg-gradient-to-r from-emerald-50 via-teal-50 to-slate-50 dark:from-emerald-950/40 dark:via-zinc-900 dark:to-zinc-950 border-b border-emerald-200 dark:border-emerald-900/60 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="backFromSysweb()" class="w-10 h-10 rounded-xl bg-white dark:bg-zinc-800 border border-emerald-200 dark:border-emerald-900 flex items-center justify-center text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-zinc-700 transition cursor-pointer" title="Kembali"><i class="fa-solid fa-arrow-left"></i></button>
                        <div class="usm-hero-ico w-12 h-12 rounded-2xl bg-emerald-600 text-white items-center justify-center text-xl shadow-sm"><i class="fa-solid fa-network-wired"></i></div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-[10px] uppercase tracking-[.18em] text-emerald-700 dark:text-emerald-300 font-bold mb-0.5">MODUL INTEGRASI SYSWEB</p>
                                <span id="sysweb-segment-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">Simulasi Gerbang Aktif</span>
                            </div>
                            <h2 class="font-bold text-lg text-slate-900 dark:text-white">Portal & Rekod Integrasi Sysweb</h2>
                            <p class="text-xs text-slate-600 dark:text-zinc-400 mt-0.5">Penghantaran dan penjejakan rekod pelajar & staf ke gerbang Sysweb USM (format <code class="font-bold text-emerald-700 dark:text-emerald-400">00000000 + Converted_UID</code>).</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" onclick="loadSyswebSavedRecords(true)" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-zinc-700 text-xs font-semibold transition cursor-pointer">
                            <i class="fa-solid fa-rotate"></i><span>Muat Semula</span>
                        </button>
                        <button type="button" onclick="downloadSyswebCSV()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-700 dark:text-zinc-200 hover:bg-slate-50 dark:hover:bg-zinc-700 text-xs font-semibold transition cursor-pointer shadow-sm" title="Eksport tanpa kata laluan">
                            <i class="fa-solid fa-file-arrow-down text-emerald-600"></i><span>Eksport CSV</span>
                        </button>
                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                        <button type="button" onclick="confirmSyswebCredentialExport()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-900 text-xs font-semibold transition cursor-pointer shadow-sm" title="Eksport dengan kata laluan — direkodkan dalam audit log">
                            <i class="fa-solid fa-file-shield"></i><span>Eksport CSV + Kata Laluan</span>
                        </button>
                        <?php } ?>
                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                        <button type="button" onclick="confirmClearSyswebData()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900 text-xs font-semibold transition cursor-pointer shadow-sm">
                            <i class="fa-solid fa-trash-can"></i><span>Kosongkan Rekod</span>
                        </button>
                        <?php } ?>
                    </div>
                </div>

                <div class="p-5 sm:p-6 space-y-6">
                    <!-- Metric Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                        <div class="rounded-2xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/70 dark:bg-emerald-950/20 p-5 flex items-center justify-between hover:shadow-sm transition-shadow">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300 font-bold">Jumlah Rekod Sysweb</p>
                                <p id="sysweb-stat-total" class="text-3xl font-bold text-emerald-800 dark:text-emerald-200 mt-1">0</p>
                                <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">Tersimpan dalam fail integrasi</p>
                            </div>
                            <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl shadow-sm shrink-0">
                                <i class="fa-solid fa-database"></i>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-blue-200 dark:border-blue-900/60 bg-blue-50/70 dark:bg-blue-950/20 p-5 flex items-center justify-between hover:shadow-sm transition-shadow">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-blue-700 dark:text-blue-300 font-bold">Rekod Pelajar</p>
                                <p id="sysweb-stat-students" class="text-3xl font-bold text-blue-800 dark:text-blue-200 mt-1">0</p>
                                <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">Matrik &amp; Kad Pelajar</p>
                            </div>
                            <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center text-xl shadow-sm shrink-0">
                                <i class="fa-solid fa-graduation-cap"></i>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-purple-200 dark:border-purple-900/60 bg-purple-50/70 dark:bg-purple-950/20 p-5 flex items-center justify-between hover:shadow-sm transition-shadow">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-purple-700 dark:text-purple-300 font-bold">Rekod Staf</p>
                                <p id="sysweb-stat-staff" class="text-3xl font-bold text-purple-800 dark:text-purple-200 mt-1">0</p>
                                <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">No. Staf &amp; Kad USM</p>
                            </div>
                            <div class="w-12 h-12 rounded-2xl bg-purple-600 text-white flex items-center justify-center text-xl shadow-sm shrink-0">
                                <i class="fa-solid fa-id-badge"></i>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-5 flex items-center justify-between hover:shadow-sm transition-shadow">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold">Status Gerbang Sysweb</p>
                                <p class="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-1.5 flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    <span>Simulasi Aktif</span>
                                </p>
                                <p class="text-[11px] text-slate-400 dark:text-zinc-400 mt-0.5" id="sysweb-last-sync-time">Belum ada transaksi</p>
                            </div>
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-zinc-800 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xl shrink-0">
                                <i class="fa-solid fa-satellite-dish"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Sysweb data quality / validation centre -->
                    <section id="sysweb-quality-centre" class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 sm:p-5 space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div><h3 class="font-bold text-sm text-slate-900 dark:text-white"><i class="fa-solid fa-shield-halved text-emerald-600 mr-2"></i>Pusat Semakan Data Sysweb</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">Semakan asas sebelum rekod digunakan dalam integrasi.</p></div>
                            <span id="sysweb-quality-total" class="text-[11px] font-semibold text-slate-500">Menunggu data…</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/25 border border-emerald-100 dark:border-emerald-900/60 p-3"><p class="text-[10px] uppercase tracking-wide font-bold text-emerald-700 dark:text-emerald-300">Rekod lengkap</p><p id="sysweb-quality-valid" class="text-2xl font-bold text-emerald-800 dark:text-emerald-200">0</p></div>
                            <div class="rounded-xl bg-amber-50 dark:bg-amber-950/25 border border-amber-100 dark:border-amber-900/60 p-3"><p class="text-[10px] uppercase tracking-wide font-bold text-amber-700 dark:text-amber-300">Perlu semakan</p><p id="sysweb-quality-review" class="text-2xl font-bold text-amber-800 dark:text-amber-200">0</p></div>
                            <div class="rounded-xl bg-rose-50 dark:bg-rose-950/25 border border-rose-100 dark:border-rose-900/60 p-3"><p class="text-[10px] uppercase tracking-wide font-bold text-rose-700 dark:text-rose-300">Tidak lengkap</p><p id="sysweb-quality-invalid" class="text-2xl font-bold text-rose-800 dark:text-rose-200">0</p></div>
                        </div>
                        <div id="sysweb-quality-breakdown" class="flex flex-wrap items-center gap-1.5"></div>
                        <div class="rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-950/40 p-3 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p id="sysweb-reconcile-summary" class="text-[11px] text-slate-600 dark:text-zinc-400">Belum semak silang dengan rekod kad.</p>
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                <button type="button" onclick="previewSyswebReconcile()" class="px-3 py-1.5 rounded-lg border border-sky-200 dark:border-sky-900 bg-sky-50 hover:bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-300 text-[11px] font-bold transition cursor-pointer shrink-0"><i class="fa-solid fa-rotate mr-1"></i>Semak dengan rekod kad</button>
                                <?php } ?>
                            </div>
                            <div id="sysweb-reconcile-detail" class="space-y-2"></div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p id="sysweb-quality-hint" class="text-[11px] text-slate-500 dark:text-zinc-400">Semakan merangkumi nama, pengenalan, UID, susunan byte, prefiks Sysweb, no. siri, tarikh kad dan kata laluan.</p>
                            <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                            <button type="button" onclick="generateMissingSyswebPasswords()" class="px-3 py-1.5 rounded-lg border border-amber-200 dark:border-amber-900 bg-amber-50 hover:bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 text-[11px] font-bold transition cursor-pointer shrink-0"><i class="fa-solid fa-key mr-1"></i>Jana kata laluan yang tiada</button>
                            <?php } ?>
                        </div>
                    </section>

                    <!-- Active Batch Dispatch Card -->
                    <div id="sysweb-active-batch-card" class="rounded-2xl border-2 border-emerald-500/40 bg-gradient-to-br from-emerald-50/80 via-white to-teal-50/40 dark:from-emerald-950/30 dark:via-zinc-900 dark:to-zinc-950 p-5 space-y-4 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-base shrink-0 shadow-sm mt-0.5">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-bold text-sm text-slate-900 dark:text-white" id="sysweb-batch-title">Batch Penghantaran Aktif</h3>
                                        <span id="sysweb-batch-source-pill" class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Eksport UDA</span>
                                    </div>
                                    <p class="text-xs text-slate-600 dark:text-zinc-400 mt-0.5" id="sysweb-batch-desc">
                                        <span id="sysweb-batch-count" class="font-bold text-emerald-700 dark:text-emerald-300">0</span> rekod sedia untuk dihantar ke integrasi Sysweb.
                                    </p>
                                    <div id="sysweb-batch-split" class="flex flex-wrap items-center gap-1.5 mt-2"></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <button type="button" id="sysweb-batch-toggle-btn" onclick="toggleSyswebSource()" class="px-3.5 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 text-xs font-semibold text-slate-700 dark:text-zinc-300 transition cursor-pointer flex items-center gap-1.5 shadow-sm">
                                    <i class="fa-solid fa-shuffle text-indigo-500"></i><span>Tukar ke Sampel Demo</span>
                                </button>
                                <button type="button" id="sysweb-segment-send-btn" onclick="sendSyswebRecordsFromSegment()" class="px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 active:scale-[0.99] text-white text-xs font-bold transition shadow-sm hover:shadow-md cursor-pointer flex items-center gap-2">
                                    <i class="fa-solid fa-paper-plane"></i><span>Hantar Batch Ini Sekarang</span>
                                </button>
                            </div>
                        </div>

                        <!-- Mini preview table of active batch records -->
                        <div class="overflow-x-auto rounded-xl border border-emerald-200/80 dark:border-zinc-800 max-h-52 overflow-y-auto bg-white dark:bg-zinc-950">
                            <table class="w-full text-xs text-left font-mono">
                                <thead id="sysweb-batch-thead" class="bg-slate-50/90 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase sticky top-0">
                                    <tr>
                                        <th class="p-2.5">Bil</th>
                                        <th class="p-2.5 font-sans">Nama</th>
                                        <th class="p-2.5">No. Pengenalan</th>
                                        <th class="p-2.5 font-sans">Kategori</th>
                                        <th class="p-2.5 font-sans">Pusat Pengajian / Jabatan</th>
                                        <th class="p-2.5 font-sans">Tamat Kad</th>
                                        <th class="p-2.5">UID Converted (LE)</th>
                                        <th class="p-2.5">UID Sysweb (Final)</th>
                                        <th class="p-2.5 font-sans">Semakan Data</th>
                                    </tr>
                                </thead>
                                <tbody id="sysweb-batch-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800 text-slate-700 dark:text-zinc-300">
                                    <tr><td colspan="9" class="p-4 text-center text-slate-400">Tiada rekod dalam batch aktif.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <section id="sysweb-history-stats-panel" hidden class="usm-stats-entrant usm-chart-card p-5 sm:p-6 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#121214] shadow-clean">
                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                            <div class="flex items-start gap-3">
                                <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Statistik eksport Sysweb</h3>
                                    <p id="sysweb-history-stats-caption" role="status" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Memuatkan sejarah eksport...</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Eksport statistik eksport Sysweb">
                                    <button type="button" onclick="downloadSyswebHistoryStatsPdf()" title="Muat turun ringkasan PDF" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-pdf"></i>PDF</button>
                                    <button type="button" onclick="downloadSyswebHistoryStatsPng()" title="Muat turun PNG semua carta" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-download"></i>PNG</button>
                                    <button type="button" onclick="downloadSyswebHistoryStatsExcel()" title="Muat turun laporan Excel" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800"><i class="fa-solid fa-file-excel"></i>Excel</button>
                                </div>
                                <button type="button" id="sysweb-history-drill-reset" hidden onclick="clearSyswebHistoryDrill()" class="px-2.5 py-1.5 rounded-lg border border-indigo-200 dark:border-indigo-800 text-[11px] font-semibold text-indigo-700 dark:text-indigo-300 hover:bg-indigo-50 dark:hover:bg-indigo-950/40"></button>
                                <div class="usm-stats-ranges" role="group" aria-label="Julat hari statistik eksport">
                                    <button type="button" class="usm-stats-range" onclick="setSyswebHistoryRange(7, this)">7 hari</button>
                                    <button type="button" class="usm-stats-range is-on" onclick="setSyswebHistoryRange(30, this)">30 hari</button>
                                    <button type="button" class="usm-stats-range" onclick="setSyswebHistoryRange(90, this)">90 hari</button>
                                    <button type="button" class="usm-stats-range" onclick="setSyswebHistoryRange(3660, this)">Semua</button>
                                </div>
                            </div>
                        </div>

                        <div class="usm-stats-strip">
                            <div><span class="usm-stats-value" id="sysweb-history-stats-batches">0</span><span class="usm-stats-label">Batch dalam julat</span></div>
                            <div><span class="usm-stats-value" id="sysweb-history-stats-records">0</span><span class="usm-stats-label">Rekod diproses</span></div>
                            <div><span class="usm-stats-value" id="sysweb-history-stats-saved">0</span><span class="usm-stats-label">Disimpan baharu</span></div>
                            <div><span class="usm-stats-value" id="sysweb-history-stats-failed">0</span><span class="usm-stats-label">Rekod gagal</span></div>
                        </div>

                        <div class="relative h-64 w-full mt-1"><canvas id="syswebHistoryTrendChart"></canvas></div>

                        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 mt-5">
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Status batch</h3>
                                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu hiris untuk menapis senarai</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="syswebHistoryStatusChart"></canvas></div>
                            </div>
                            <div class="usm-chart-card min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 p-4">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Pengguna paling banyak eksport</h3>
                                        <p id="sysweb-history-user-note" class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">Klik satu bar untuk menapis mengikut pengguna</p>
                                    </div>
                                    <span class="usm-chart-ico" aria-hidden="true"><i class="fa-solid fa-user-gear"></i></span>
                                </div>
                                <div class="relative h-64 w-full"><canvas id="syswebHistoryUserChart"></canvas></div>
                            </div>
                        </div>
                    </section>

                    <!-- Sysweb export history and failed-record retry -->
                    <section class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-900 overflow-hidden">
                        <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-zinc-800">
                            <div class="flex items-center gap-2.5"><span class="w-8 h-8 rounded-xl bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left"></i></span><div><h3 class="text-sm font-bold text-slate-900 dark:text-white">Sejarah Eksport Sysweb</h3><p class="text-[11px] text-slate-500 dark:text-zinc-400">Jejak batch, keputusan penghantaran dan hantar semula rekod gagal</p></div></div>
                            <button type="button" onclick="loadSyswebExportHistory()" class="px-3 py-2 rounded-lg border border-slate-200 dark:border-zinc-700 text-xs font-semibold hover:bg-slate-50 dark:hover:bg-zinc-800"><i class="fa-solid fa-rotate mr-1"></i>Muat semula</button>
                        </div>
                        <div id="sysweb-export-history-list" class="divide-y divide-slate-100 dark:divide-zinc-800"><div class="p-4 text-xs text-slate-400">Memuatkan sejarah eksport...</div></div>
                    </section>

                    <!-- Search & Filter Bar for Saved Sysweb Records -->
                    <div class="rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-900/60 overflow-hidden">
                        <div class="px-4 pt-4 pb-3 border-b border-slate-200 dark:border-zinc-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-indigo-100 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 flex items-center justify-center text-sm shrink-0">
                                    <i class="fa-solid fa-table-list"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Pangkalan Rekod Integrasi Sysweb</h3>
                                    <p class="text-[11px] text-slate-500 dark:text-zinc-400">Semua rekod yang berjaya dihantar ke gerbang simulasi Sysweb</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 self-start sm:self-auto">
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                <button type="button" id="sysweb-add-record-btn" onclick="openSyswebEditDrawer(-1)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold transition cursor-pointer shadow-sm">
                                    <i class="fa-solid fa-plus text-[10px]"></i><span>Tambah Rekod</span>
                                </button>
                                <?php } ?>
                                <span id="sysweb-records-counter" class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-900">0 rekod dipaparkan</span>
                            </div>
                        </div>

                        <!-- Student / Staff segment control -->
                        <div class="px-4 pt-4">
                            <div class="inline-flex flex-wrap items-center gap-1 p-1 rounded-xl bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800" role="group" aria-label="Tapis mengkategori kategori">
                                <button type="button" id="sysweb-seg-all" data-type="" onclick="setSyswebTypeMode('')" class="sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-layer-group mr-1.5 opacity-70"></i>Semua<span id="sysweb-seg-all-count" class="ml-1.5 font-mono opacity-70">(0)</span>
                                </button>
                                <button type="button" id="sysweb-seg-student" data-type="student" onclick="setSyswebTypeMode('student')" class="sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-graduation-cap mr-1.5 text-blue-500"></i>Sysweb Pelajar<span id="sysweb-seg-student-count" class="ml-1.5 font-mono opacity-70">(0)</span>
                                </button>
                                <button type="button" id="sysweb-seg-staff" data-type="staff" onclick="setSyswebTypeMode('staff')" class="sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer">
                                    <i class="fa-solid fa-id-badge mr-1.5 text-purple-500"></i>Sysweb Staf<span id="sysweb-seg-staff-count" class="ml-1.5 font-mono opacity-70">(0)</span>
                                </button>
                                <button type="button" id="sysweb-seg-crud" onclick="openSyswebEditDrawer(-1)" class="px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer bg-emerald-600 text-white hover:bg-emerald-700" title="Buka pengurusan CRUD Sysweb">
                                    <i class="fa-solid fa-sliders mr-1.5"></i>Pengurusan CRUD
                                </button>
                            </div>
                        </div>

                        <div id="sysweb-workspace-context" class="mx-4 mt-4 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-4 py-3 flex items-center gap-3">
                            <div id="sysweb-workspace-icon" class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center"><i class="fa-solid fa-layer-group"></i></div>
                            <div class="min-w-0"><h4 id="sysweb-workspace-title" class="font-bold text-sm text-slate-900 dark:text-white">Semua Rekod Sysweb</h4><p id="sysweb-workspace-desc" class="text-[11px] text-slate-500 dark:text-zinc-400">Paparan gabungan rekod pelajar dan staf.</p></div>
                            <span id="sysweb-workspace-count" class="ml-auto shrink-0 rounded-full bg-slate-100 dark:bg-zinc-800 px-3 py-1 text-[11px] font-bold text-slate-600 dark:text-zinc-300">0 rekod</span>
                        </div>
                        <div class="p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                                <div class="xl:col-span-2">
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-search-input">Carian Semua Butiran</label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"><i class="fa-solid fa-search"></i></span>
                                        <input id="sysweb-search-input" type="search" oninput="syswebPage=1;applySyswebFilters()" placeholder="Nama, matrik/no staf, sekolah/jabatan, polisi, no. siri, tamat kad, UID, kata laluan, tarikh diterima&hellip;" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 pl-9 pr-4 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20 transition">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-school-filter"><span id="sysweb-school-filter-label">Pusat Pengajian / Jabatan</span></label>
                                    <select id="sysweb-school-filter" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                        <option value="">Semua</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-policy-filter"><span id="sysweb-policy-filter-label">Polisi / Status Kad</span></label>
                                    <select id="sysweb-policy-filter" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                        <option value="">Semua</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-3">
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-status-filter">Status Semakan</label>
                                    <select id="sysweb-status-filter" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                        <option value="">Semua Status</option>
                                        <option value="valid">Sah</option>
                                        <option value="review">Perlu semakan</option>
                                        <option value="invalid">Tidak sah</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-expiry-filter">Kekeslahan Kad</label>
                                    <select id="sysweb-expiry-filter" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                        <option value="">Semua Keadaan</option>
                                        <option value="valid">Aktif (lebih 30 hari)</option>
                                        <option value="soon">Sangat Tamat (30 hari ke bawah)</option>
                                        <option value="expired">Tamat / Luput</option>
                                        <option value="none">Tiada Rekod Tamat</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-received-from">Diterima Mulai</label>
                                    <input id="sysweb-received-from" type="date" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                </div>
                                <div>
                                    <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-received-to">Diterima Hingga</label>
                                    <input id="sysweb-received-to" type="date" onchange="syswebPage=1;applySyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3 py-2.5 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                </div>
                                <div class="flex items-end gap-2">
                                    <button type="button" onclick="clearSyswebFilters()" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-3 py-2.5 text-xs font-semibold text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-700 transition cursor-pointer">
                                        <i class="fa-solid fa-filter-circle-xmark mr-1.5 text-slate-400"></i>Kosongkan Penapis
                                    </button>
                                    <button type="button" onclick="applySyswebFilters();loadSyswebSavedRecords(true)" class="rounded-xl border border-emerald-200 dark:border-emerald-800 bg-white dark:bg-zinc-800 px-3 py-2.5 text-xs font-semibold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-50 dark:hover:bg-zinc-700 transition cursor-pointer" title="Muat semula rekod">
                                        <i class="fa-solid fa-rotate"></i>
                                    </button>
                                </div>
                            </div>
                            <p id="sysweb-active-filter-summary" class="text-[11px] text-slate-500 dark:text-zinc-400"></p>
                            <div id="sysweb-bulk-toolbar" class="hidden rounded-xl border border-emerald-200 dark:border-emerald-900 bg-emerald-50/70 dark:bg-emerald-950/20 px-3 py-2.5 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 text-xs"><span id="sysweb-bulk-count" class="font-bold text-emerald-800 dark:text-emerald-300">0 dipilih</span><button type="button" onclick="selectAllFilteredSysweb()" class="text-emerald-700 dark:text-emerald-300 underline underline-offset-2">Pilih semua hasil penapis</button><button type="button" onclick="clearSyswebSelection()" class="text-slate-500 dark:text-zinc-400 underline underline-offset-2">Nyahpilih</button></div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" onclick="validateSelectedSysweb()" class="px-3 py-1.5 rounded-lg bg-white dark:bg-zinc-800 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-300 text-[11px] font-bold"> <i class="fa-solid fa-shield-halved mr-1"></i>Semak semula</button>
                                    <button type="button" onclick="exportSelectedSyswebCSV()" class="px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold"><i class="fa-solid fa-file-csv mr-1"></i>Eksport pilihan</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Saved Sysweb Table -->
                    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-zinc-950">
                        <table class="w-full text-xs text-left">
                            <thead id="sysweb-saved-thead" class="bg-slate-50 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase font-mono">
                                <tr>
                                    <th class="p-3 text-center w-10">#</th>
                                    <th class="p-3 font-sans">Nama</th>
                                    <th class="p-3">Pengenalan (Matrik/Staf)</th>
                                    <th class="p-3 font-sans">Jenis</th>
                                    <th class="p-3 font-sans">Pusat Pengajian / Jabatan</th>
                                    <th class="p-3 font-sans">Polisi / Status Kad</th>
                                    <th class="p-3 font-mono">No. Siri Kad</th>
                                    <th class="p-3 font-sans">Tamat Kad</th>
                                    <th class="p-3 font-mono">UID Converted (LE)</th>
                                    <th class="p-3 font-mono">Sysweb UID (00000000+UID)</th>
                                    <th class="p-3 font-sans text-amber-600 dark:text-amber-400">Kata Laluan (12 Aksara)</th>
                                    <th class="p-3 font-sans">Tarikh &amp; Masa Integrasi</th>
                                    <th class="p-3 text-center">Tindakan</th>
                                </tr>
                            </thead>
                            <tbody id="sysweb-saved-tbody" class="divide-y divide-slate-100 dark:divide-zinc-800/80 font-mono text-slate-700 dark:text-zinc-300">
                                <tr><td colspan="14" class="p-8 text-center text-slate-400">Memuatkan rekod Sysweb...</td></tr>
                            </tbody>
                        </table>
                        <!-- Pagination Bar -->
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-slate-200 dark:border-zinc-800 bg-slate-50/60 dark:bg-zinc-900/60">
                            <span id="sysweb-pagination-info" class="text-[11px] text-slate-500 dark:text-zinc-400 font-mono">&#x2014;</span>
                            <div class="flex items-center gap-2 flex-wrap justify-center">
                                <label class="text-[11px] text-slate-500 dark:text-zinc-400">Baris/halaman:</label>
                                <select id="sysweb-page-size" onchange="syswebPageSize=+this.value;syswebPage=1;applySyswebFilters()" class="rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2 py-1 text-xs text-slate-900 dark:text-white outline-none focus:border-emerald-500">
                                    <option value="15">15</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button onclick="syswebPage=Math.max(1,syswebPage-1);applySyswebFilters()" id="sysweb-prev-btn" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer flex items-center gap-1"><i class="fa-solid fa-chevron-left text-[10px]"></i>Sebelum</button>
                                <span id="sysweb-page-indicator" class="text-xs font-bold font-mono text-slate-600 dark:text-zinc-300 min-w-16 text-center bg-slate-100 dark:bg-zinc-800 rounded-lg px-2 py-1">1 / 1</span>
                                <button onclick="syswebPage++;applySyswebFilters()" id="sysweb-next-btn" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-slate-700 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-700 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer flex items-center gap-1">Seterus<i class="fa-solid fa-chevron-right text-[10px]"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Collapsible Developer Drawer: JSON Log & Code Output -->
                    <details class="group rounded-2xl border border-slate-200 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-900/50 overflow-hidden">
                        <summary class="cursor-pointer p-4 font-bold text-xs flex items-center justify-between text-slate-700 dark:text-zinc-300 select-none hover:bg-slate-100/70 dark:hover:bg-zinc-800/50 transition">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-code text-indigo-500"></i>
                                <span>Papar Log Teknikal & Respons JSON Sysweb (Pilihan Pembangun)</span>
                            </span>
                            <span class="text-[11px] text-slate-400 group-open:rotate-180 transition-transform"><i class="fa-solid fa-chevron-down"></i></span>
                        </summary>
                        <div class="p-4 border-t border-slate-200 dark:border-zinc-800 space-y-3 bg-white dark:bg-zinc-950">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-mono text-slate-500">Endpoint: <code>index.php?action=sysweb_demo_receive</code></span>
                                <span id="sysweb-technical-status" class="text-[10px] font-mono px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">Sedia</span>
                            </div>
                            <pre id="sysweb-segment-json-result" class="whitespace-pre-wrap break-words rounded-xl bg-slate-950 text-emerald-400 p-4 text-xs font-mono max-h-56 overflow-auto border border-slate-800 shadow-inner">Tiada respons penghantaran terkini. Tekan "Hantar Batch Ini Sekarang" untuk menguji sambungan.</pre>
                        </div>
                    </details>
                </div>
            </section>
        </div>

        <!-- Sysweb Record CRUD Drawer (slide-over) -->
        <div id="sysweb-record-detail" class="hidden fixed inset-0 z-[2050] bg-slate-950/50 backdrop-blur-sm p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="sysweb-detail-title" data-modal-close="closeSyswebDetail" onclick="if(event.target===this)closeSyswebDetail()">
            <div class="mx-auto mt-6 max-w-2xl rounded-2xl bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 shadow-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-zinc-800 flex items-center justify-between"><div><p class="text-[10px] uppercase tracking-widest font-bold text-emerald-600">Butiran Rekod</p><h3 id="sysweb-detail-title" class="font-bold text-lg text-slate-900 dark:text-white">Maklumat Sysweb</h3></div><button type="button" onclick="closeSyswebDetail()" class="w-9 h-9 rounded-lg hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button></div>
                <div id="sysweb-detail-content" class="p-5 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs"></div>
                <div class="px-5 py-3 bg-slate-50 dark:bg-zinc-950 border-t border-slate-200 dark:border-zinc-800 flex justify-end"><button type="button" onclick="closeSyswebDetail()" class="px-4 py-2 rounded-lg bg-slate-900 text-white dark:bg-zinc-700 text-xs font-semibold">Tutup</button></div>
            </div>
        </div>

        <div id="sysweb-edit-drawer" class="hidden fixed inset-0 z-[2100]" role="dialog" aria-modal="true" aria-labelledby="sysweb-drawer-title" data-modal-close="closeSyswebEditDrawer">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm" onclick="closeSyswebEditDrawer()"></div>
            <div class="absolute right-0 top-0 h-full w-full sm:w-[540px] bg-white dark:bg-[#121214] shadow-2xl flex flex-col border-l border-slate-200 dark:border-zinc-800">
                <div class="px-5 py-4 border-b border-slate-200 dark:border-zinc-800 flex items-start justify-between gap-3 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-950/40 dark:to-zinc-900">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-base shrink-0 shadow-sm"><i class="fa-solid fa-id-card-clip"></i></div>
                        <div>
                            <h3 class="font-bold text-sm text-slate-900 dark:text-white" id="sysweb-drawer-title">Sunting Rekod Sysweb</h3>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5" id="sysweb-drawer-subtitle">Kemaskini butiran rekod yang telah dihantar ke gerbang Sysweb.</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeSyswebEditDrawer()" class="w-9 h-9 rounded-xl bg-white dark:bg-zinc-800 border border-slate-200 dark:border-zinc-700 text-slate-500 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-700 transition cursor-pointer shrink-0" title="Tutup (Esc)"><i class="fa-solid fa-xmark text-sm"></i></button>
                </div>

                <form id="sysweb-edit-form" class="flex-1 overflow-y-auto p-5 space-y-4 text-xs" onsubmit="saveSyswebDrawer(event)">
                    <input type="hidden" id="sysweb-f-index" value="-1">

                    <div class="flex items-center gap-2 p-3 rounded-xl bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800">
                        <span class="text-[10px] uppercase tracking-wide font-bold text-slate-500 dark:text-zinc-400">Kategori Rekod</span>
                        <div class="ml-auto inline-flex p-1 rounded-lg bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-700">
                            <button type="button" id="sysweb-kind-student" onclick="setSyswebDrawerType('student')" class="sysweb-kind-btn px-3 py-1.5 rounded-md text-[11px] font-bold transition cursor-pointer"><i class="fa-solid fa-graduation-cap mr-1.5"></i>Pelajar</button>
                            <button type="button" id="sysweb-kind-staff" onclick="setSyswebDrawerType('staff')" class="sysweb-kind-btn px-3 py-1.5 rounded-md text-[11px] font-bold transition cursor-pointer"><i class="fa-solid fa-id-badge mr-1.5"></i>Staf</button>
                        </div>
                        <input type="hidden" id="sysweb-f-type" value="student">
                    </div>

                    <div>
                        <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-name">Nama Penuh</label>
                        <input type="text" id="sysweb-f-name" maxlength="200" required placeholder="Contoh: WANG TIAN" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-identifier"><span id="sysweb-l-identifier">No. Matrik</span></label>
                            <input type="text" id="sysweb-f-identifier" maxlength="100" required oninput="syncSyswebUidPreview()" placeholder="Contoh: P-SD0003/21(R)" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-school"><span id="sysweb-l-school">Pusat Pengajian</span></label>
                            <input type="text" id="sysweb-f-school" maxlength="180" placeholder="Contoh: Pusat Pengajian Ilmu Kajian" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-policy"><span id="sysweb-l-policy">Polisi Kad</span></label>
                            <input type="text" id="sysweb-f-policy" maxlength="60" placeholder="Contoh: P-SD0003/21" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-serial">No. Siri Kad</label>
                            <input type="text" id="sysweb-f-serial" maxlength="100" placeholder="Kosong untuk nombor siri automatik" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                            <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Dijana automatik apabila kosong.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-joined">Tarikh Masuk / Dikeluarkan</label>
                            <input type="date" id="sysweb-f-joined" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-expiry">Tarikh Tamat Kad</label>
                            <input type="date" id="sysweb-f-expiry" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                        </div>
                    </div>

                    <div id="sysweb-nationality-field">
                        <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-nationality">Kewarganegaraan</label>
                        <input type="text" id="sysweb-f-nationality" maxlength="60" placeholder="Contoh: Malaysian / International" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                    </div>

                    <div id="sysweb-notes-field" class="hidden">
                        <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-notes">Nota Staf</label>
                        <textarea id="sysweb-f-notes" rows="2" maxlength="500" placeholder="Catatan dalaman staf&hellip;" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition"></textarea>
                    </div>

                    <div class="rounded-xl border border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/70 dark:bg-emerald-950/20 p-3.5 space-y-3">
                        <p class="text-[10px] uppercase tracking-wide font-bold text-emerald-700 dark:text-emerald-300">Kuid &amp; Kata Laluan Sysweb</p>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-uid">UID Converted (Hex, Little-Endian)</label>
                            <input type="text" id="sysweb-f-uid" oninput="syncSyswebUidPreview()" required placeholder="Contoh: B45A678F" class="w-full rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono font-bold text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                            <p id="sysweb-f-uid-hint" class="text-[10px] mt-1 text-slate-500 dark:text-zinc-400">Mesti hexadecimal dengan bilangan aksara genap.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1.5" for="sysweb-f-source-uid">UID Kad Mentah (Big-Endian, pilihan)</label>
                            <div class="flex gap-2">
                                <input type="text" id="sysweb-f-source-uid" oninput="syncSyswebUidPreview()" placeholder="Contoh: F8675AB4" class="flex-1 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-950 px-3.5 py-2.5 font-mono font-bold text-slate-900 dark:text-white outline-none focus:border-emerald-500 transition">
                                <button type="button" onclick="applySyswebRawUidByteOrder()" class="shrink-0 px-3 rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-bold uppercase tracking-wide hover:bg-emerald-100 dark:hover:bg-zinc-700 transition cursor-pointer" title="Songsangkan susunan byte UID mentah dan isikan UID converted"> kira UID </button>
                            </div>
                            <p id="sysweb-f-source-uid-hint" class="text-[10px] mt-1 text-slate-500 dark:text-zinc-400">Isi untuk membolehkan pelayan mengesahkan susunan byte UID converted.</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1">UID Sysweb (Final)</p>
                                <p id="sysweb-derived-uid" class="font-mono font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">-</p>
                            </div>
                            <div>
                                <p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1">Kata Laluan Sysweb</p>
                                <p id="sysweb-derived-password" class="font-mono font-bold text-amber-600 dark:text-amber-400 tracking-widest">-</p>
                            </div>
                        </div>
                        <p class="text-[10px] text-slate-500 dark:text-zinc-400">Kata laluan dijana oleh pelayan menggunakan penjana rawak kripto dan disimpan bersama rekod &mdash; ia tidak dipaparkan di sini. Selepas rekod disimpan, gunakan butang mata atau ikon salin dalam jadual untuk memaparkan, menyalin atau set semula kata laluan.</p>
                    </div>

                    <div id="sysweb-drawer-error" class="hidden rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 p-3 text-[11px] text-rose-700 dark:text-rose-300"></div>
                </form>

                <div class="px-5 py-4 border-t border-slate-200 dark:border-zinc-800 flex items-center gap-2 bg-slate-50 dark:bg-zinc-900">
                    <button type="button" id="sysweb-drawer-delete" onclick="deleteSyswebRecordFromDrawer()" class="hidden inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-900 text-xs font-semibold transition cursor-pointer">
                        <i class="fa-solid fa-trash-can"></i><span>Padam</span>
                    </button>
                    <button type="button" onclick="closeSyswebEditDrawer()" class="ml-auto px-4 py-2 rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-xs font-semibold text-slate-700 dark:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-700 transition cursor-pointer">Batal</button>
                    <button type="submit" form="sysweb-edit-form" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold transition cursor-pointer shadow-sm">
                        <i class="fa-solid fa-floppy-disk"></i><span id="sysweb-drawer-save-label">Simpan Perubahan</span>
                    </button>
                </div>
            </div>
        </div>

        <div id="view-edit-student" class="hidden page-transition">
            <div class="bg-white dark:bg-[#121214] border border-slate-200/80 dark:border-zinc-800/80 rounded-2xl p-6 sm:p-8 space-y-6 shadow-clean font-mono max-w-3xl mx-auto">
                
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-5">
                    <div class="flex items-center gap-3">
                        <button onclick="navigateToDirectoryPage()" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 flex items-center justify-center text-slate-700 dark:text-zinc-300 transition cursor-pointer" title="Kembali ke Direktori">
                            <i class="fa-solid fa-arrow-left text-xs"></i>
                        </button>
                        <div>
                            <h2 class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider" id="edit-page-title">Kemaskini Rekod Pelajar</h2>
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">Sila isi maklumat borang di bawah untuk disimpan ke pangkalan data.</p>
                        </div>
                    </div>
                    <span class="text-[10px] bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/40 px-2.5 py-1 rounded-lg font-bold uppercase">Form Editor</span>
                </div>

                <form id="edit-page-form" onsubmit="submitEditPageForm(event)" class="space-y-4 text-xs">
                    <input type="hidden" id="edit-page-id" value="0">
                    
                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Nama Penuh Pelajar</label>
                        <input type="text" id="edit-page-name" required placeholder="Contoh: Muhammad Izzat bin Hashim" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">No Matrik / ID</label>
                            <input type="text" id="edit-page-matric" required placeholder="Contoh: CDCS110/2026" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">UID Asal (Hexadecimal)</label>
                            <div class="flex gap-2">
                                <input type="text" id="edit-page-uid" required placeholder="Contoh: CC DD EE FF" oninput="updateLiveUidPreview(this.value)" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                            </div>
                            <div class="mt-1.5 text-[11px] text-slate-500 dark:text-zinc-400">
                                Pratonton Little-Endian: <span id="live-uid-preview" class="font-bold text-emerald-600 dark:text-emerald-400">-</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Pusat Pengajian (Kampus Kejuruteraan USM)</label>
                        <select id="edit-page-school" required onchange="usmsmartLinkSchoolPolicy()" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                            <option value="">Memuatkan sekolah daripada SQL Server…</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Kewarganegaraan</label><input type="text" id="edit-page-nationality" placeholder="Contoh: Malaysian, Indonesian" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white"><p class="text-[10px] text-slate-500 mt-1">Kategori paparan diutamakan daripada IC live: awalan USM = International; IC Malaysia 12 digit = Local.</p></div>
                        <div><label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Tahap Pendidikan</label><select id="edit-page-education" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white"><option value="">Belum ditentukan</option><option>Foundation</option><option>Diploma</option><option>Bachelor's Degree</option><option>Master's Degree</option><option>PhD</option><option>Other</option></select></div>
                    </div>
                    <div><label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Kategori Pelajar</label><select id="edit-page-category" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white"><option value="auto">Automatik berdasarkan kewarganegaraan</option><option value="Local">Tempatan</option><option value="International">Antarabangsa</option><option value="Unknown">Belum disahkan</option></select></div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">No Siri Fizikal Kad</label>
                                                        <input type="text" id="edit-page-serial" placeholder="Kosong untuk nombor siri automatik" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 font-mono text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                            <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Dijana automatik apabila kosong, dan tidak akan bertindih dengan rekod sedia ada.</p>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Dasar Akses (Policy)</label>
                            <input type="text" id="edit-page-policy" value="" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                            <p id="edit-page-policy-hint" class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1"></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Tarikh Pendaftaran</label>
                            <input type="date" id="edit-page-date" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Tarikh Luput Kad</label>
                            <input type="date" id="edit-page-expiry" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none transition">
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-4 border-t border-slate-100 dark:border-zinc-800">
                        <button type="button" onclick="navigateToDirectoryPage()" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-xl text-slate-700 dark:text-zinc-300 font-medium transition cursor-pointer">Batal</button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-medium shadow-sm cursor-pointer transition flex items-center gap-2">
                            <i class="fa-solid fa-floppy-disk text-xs"></i> <span>Simpan Perubahan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB ANALISIS GRAFIK -->
        <div id="view-analytics" class="space-y-6 hidden page-transition">
            <div id="analytics-export-wrapper" class="space-y-6">
                <section data-usm-reveal class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3" aria-label="Ringkasan status kad">
                    <div onclick="viewStudentsByExpiryStatus('')" class="analytics-stat analytics-stat-indigo cursor-pointer group hover:border-indigo-400 dark:hover:border-indigo-600 transition min-w-0 rounded-2xl border border-slate-200 dark:border-zinc-800 bg-white dark:bg-[#121214] p-4 shadow-clean hover:shadow-md" title="Klik untuk lihat semua rekod pelajar">
                        <div class="flex justify-between items-start">
                            <p class="text-xs text-slate-500 dark:text-zinc-400">Jumlah rekod kad</p>
                            <span class="text-[10px] font-medium text-indigo-500 opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                        </div>
                        <p id="card-stat-total" class="analytics-stat-value text-2xl font-bold mt-1">—</p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">Semua rekod pelajar</p>
                            <span class="text-[10px] text-indigo-600 dark:text-indigo-400 font-medium group-hover:underline">Papar semua →</span>
                        </div>
                    </div>
                    <div onclick="viewStudentsByExpiryStatus('active')" class="analytics-stat analytics-stat-emerald cursor-pointer group hover:border-emerald-400 dark:hover:border-emerald-600 transition min-w-0 rounded-2xl border border-emerald-200 dark:border-emerald-900 bg-white dark:bg-[#121214] p-4 shadow-clean hover:shadow-md" title="Klik untuk lihat kad pelajar aktif">
                        <div class="flex justify-between items-start">
                            <p class="text-xs text-slate-500 dark:text-zinc-400">Kad aktif</p>
                            <span class="text-[10px] font-medium text-emerald-500 opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                        </div>
                        <p id="card-stat-active" class="analytics-stat-value text-2xl font-bold text-emerald-600 mt-1">—</p>
                        <div class="flex items-center justify-between mt-1">
                            <p id="card-stat-active-rate" class="text-[11px] text-slate-500 dark:text-zinc-400">—% daripada rekod</p>
                            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium group-hover:underline">Papar aktif →</span>
                        </div>
                    </div>
                    <div onclick="viewStudentsByExpiryStatus('30')" class="analytics-stat analytics-stat-amber cursor-pointer group hover:border-amber-400 dark:hover:border-amber-500 transition min-w-0 rounded-2xl border-2 border-amber-300 dark:border-amber-700 bg-amber-50/30 dark:bg-amber-950/20 p-4 shadow-clean hover:shadow-md ring-2 ring-amber-400/20" title="Klik untuk lihat senarai pelajar yang kad mereka hampir luput (30 hari)">
                        <div class="flex justify-between items-start">
                            <p class="text-xs font-semibold text-amber-800 dark:text-amber-400 flex items-center gap-1.5"><i class="fa-solid fa-clock text-amber-500 animate-pulse"></i> Luput dalam 30 hari</p>
                            <span class="text-[10px] font-bold text-amber-700 bg-amber-100 dark:bg-amber-900/60 px-1.5 py-0.5 rounded flex items-center gap-1">Tindakan <i class="fa-solid fa-arrow-right text-[8px]"></i></span>
                        </div>
                        <p id="card-stat-expiring" class="analytics-stat-value text-2xl font-bold text-amber-600 mt-1">—</p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-amber-700/80 dark:text-zinc-400">Perlu perhatian / pembaharuan</p>
                            <span class="text-[10px] text-amber-700 dark:text-amber-400 font-bold group-hover:underline">Klik lihat senarai →</span>
                        </div>
                    </div>
                    <div onclick="viewStudentsByExpiryStatus('expired')" class="analytics-stat analytics-stat-rose cursor-pointer group hover:border-rose-400 dark:hover:border-rose-600 transition min-w-0 rounded-2xl border border-rose-200 dark:border-rose-900 bg-white dark:bg-[#121214] p-4 shadow-clean hover:shadow-md" title="Klik untuk lihat kad pelajar yang telah tamat tempoh">
                        <div class="flex justify-between items-start">
                            <p class="text-xs text-slate-500 dark:text-zinc-400">Kad tamat tempoh</p>
                            <span class="text-[10px] font-medium text-rose-500 opacity-0 group-hover:opacity-100 transition"><i class="fa-solid fa-arrow-up-right-from-square"></i></span>
                        </div>
                        <p id="card-stat-expired" class="analytics-stat-value text-2xl font-bold text-rose-600 mt-1">—</p>
                        <div class="flex items-center justify-between mt-1">
                            <p class="text-[11px] text-slate-500 dark:text-zinc-400">Tarikh luput sebelum hari ini</p>
                            <span class="text-[10px] text-rose-600 dark:text-rose-400 font-medium group-hover:underline">Papar tamat →</span>
                        </div>
                    </div>
                </section>
                <div data-usm-reveal class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 px-1">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400 mb-2">USMSmart / Statistik</p>
                        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Analisis Statistik</h2>
                        <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Ringkasan pendaftaran pelajar mengikut pusat pengajian dan bulan.</p>
                    </div>
                    <div id="analytics-actions" class="flex flex-wrap items-center gap-2 no-print">
                        <button type="button" onclick="downloadAnalyticsSummaryPdf()" class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                            <i class="fa-solid fa-file-pdf text-xs"></i> PDF ringkasan
                        </button>
                        <button type="button" onclick="downloadAnalyticsSummaryPng()" class="inline-flex items-center gap-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 px-3 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                            <i class="fa-solid fa-download text-xs"></i> PNG semua carta
                        </button>
                        <button type="button" onclick="downloadAnalyticsExcel()" class="inline-flex items-center gap-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 px-3 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                            <i class="fa-solid fa-file-excel text-xs"></i> Laporan Excel
                        </button>
                        <button type="button" onclick="switchMainTab('transporter')" class="inline-flex items-center gap-2 bg-white dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-800 text-slate-700 dark:text-zinc-200 px-3 py-2.5 rounded-xl text-sm font-medium transition shadow-sm">
                            <i class="fa-solid fa-arrow-left text-xs"></i> Kembali
                        </button>
                    </div>
                </div>

                <!-- Enhanced overview cards; values come from the existing student dataset. -->
                <section data-usm-reveal class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" aria-label="Sorotan analitik">
                    <article class="analytics-kpi analytics-kpi-indigo"><div class="analytics-kpi-top"><span>Jumlah pelajar</span><span class="analytics-kpi-icon"><i class="fa-solid fa-users"></i></span></div><div id="analytics-kpi-total" class="analytics-kpi-value">—</div><p class="analytics-kpi-caption">Jumlah rekod dalam senarai semasa</p></article>
                    <article class="analytics-kpi analytics-kpi-emerald"><div class="analytics-kpi-top"><span>Pusat pengajian</span><span class="analytics-kpi-icon"><i class="fa-solid fa-building-columns"></i></span></div><div id="analytics-kpi-schools" class="analytics-kpi-value">—</div><p class="analytics-kpi-caption">Pusat dengan rekod pelajar</p></article>
                    <article class="analytics-kpi analytics-kpi-violet"><div class="analytics-kpi-top"><span>Pusat terbesar</span><span class="analytics-kpi-icon"><i class="fa-solid fa-chart-column"></i></span></div><div id="analytics-kpi-top-school" class="analytics-kpi-value analytics-kpi-text">—</div><p id="analytics-kpi-top-school-count" class="analytics-kpi-caption">Belum ada data</p></article>
                    <article class="analytics-kpi analytics-kpi-amber"><div class="analytics-kpi-top"><span>Pendaftaran terkini</span><span class="analytics-kpi-icon"><i class="fa-regular fa-calendar-check"></i></span></div><div id="analytics-kpi-latest" class="analytics-kpi-value analytics-kpi-text">—</div><p class="analytics-kpi-caption">Tarikh terbaru yang tersedia</p></article>
                </section>
                <section data-usm-reveal class="analytics-filter-panel" aria-label="Penapis analitik">
                    <div class="analytics-filter-copy"><span class="analytics-filter-icon"><i class="fa-solid fa-sliders"></i></span><div><h3>Penapis analisis</h3><p>Gunakan penapis untuk mengemas kini kad ringkasan dan semua carta serentak.</p><p id="analytics-active-filter" class="analytics-active-filter"><i class="fa-solid fa-circle-check"></i> Paparan: semua pusat pengajian</p></div></div>
                    <div class="analytics-filter-controls">
                        <div class="analytics-filter-field"><label for="analytics-school-filter">Pusat pengajian</label>
                        <select id="analytics-school-filter" onchange="setAnalyticsSchoolFilter(this.value)" class="analytics-school-select">
                            <option value="">Semua pusat pengajian</option>
                        </select></div>
                        <button id="analytics-open-records" type="button" onclick="openAnalyticsStudentRecords(selectedAnalyticsSchool)" class="analytics-records-button" hidden><i class="fa-solid fa-list-ul"></i> Buka senarai pelajar</button>
                        <button id="analytics-school-reset" type="button" onclick="resetAnalyticsFilters()" class="analytics-reset-button" hidden><i class="fa-solid fa-rotate-left"></i> Reset semua</button>
                    </div>
                </section>
                <div data-usm-reveal class="analytics-section-heading"><div><h3>Analisis mengikut pusat pengajian</h3><p>Bandingkan jumlah dan perkadaran pelajar melalui carta di bawah.</p></div><span><i class="fa-solid fa-chart-simple"></i> Ringkasan visual</span></div>
                <div data-usm-reveal class="grid grid-cols-1 xl:grid-cols-2 gap-5">
                    <section class="analytics-chart-card min-w-0 bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-clean">
                        <div class="flex items-start justify-between gap-3 mb-5">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Taburan pelajar</h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Peratusan mengikut pusat pengajian</p>
                            </div>
                            <span class="analytics-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-pie"></i></span>
                        </div>
                        <div class="relative h-72 sm:h-80 w-full"><canvas id="pieChart"></canvas></div>
                    </section>

                    <section class="analytics-chart-card min-w-0 bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-clean">
                        <div class="flex items-start justify-between gap-3 mb-5">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Jumlah mengikut pusat</h3>
                                <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Perbandingan jumlah pelajar berdaftar</p>
                            </div>
                            <span class="analytics-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
                        </div>
                        <div class="relative h-72 sm:h-80 w-full"><canvas id="barChart"></canvas></div>
                    </section>
                </div>

                <section data-usm-reveal class="analytics-chart-card bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 sm:p-6 shadow-clean">
                    <div class="flex items-start justify-between gap-3 mb-5">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Trend pendaftaran bulanan</h3>
                            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Perbandingan pendaftaran bagi setiap pusat pengajian</p>
                        </div>
                        <span class="analytics-chart-ico" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>
                    </div>
                    <div class="analytics-month-filter">
                        <div><label for="analytics-start-month">Bulan mula</label><input type="month" id="analytics-start-month" onchange="renderMonthlyComparisonChart()" class="analytics-month-input"></div>
                        <div><label for="analytics-end-month">Bulan akhir</label><input type="month" id="analytics-end-month" onchange="renderMonthlyComparisonChart()" class="analytics-month-input"></div>
                        <button type="button" onclick="resetAnalyticsMonthFilter()" class="analytics-month-reset"><i class="fa-solid fa-rotate-left"></i> Kosongkan tarikh</button>
                    </div>
                    <p id="analytics-chart-status" role="status" class="text-xs text-slate-500 dark:text-zinc-400 mb-2">Memuatkan data carta…</p>
                    <div class="relative h-80 sm:h-96 w-full"><canvas id="monthlyComparisonChart"></canvas></div>
                </section>
            </div>
        </div>
    </main>

    <!-- MODAL SCANNER KAMERA -->
    <div id="scanner-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden" role="dialog" aria-modal="true" aria-labelledby="scanner-modal-title" data-modal-close="closeScannerModal">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 max-w-md w-full mx-4 space-y-4 font-mono shadow-2xl page-transition">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3">
                <h3 id="scanner-modal-title" class="font-bold text-xs text-slate-900 dark:text-white uppercase tracking-wider"><i class="fa-solid fa-camera text-indigo-500 mr-1.5" aria-hidden="true"></i> Imbas Kod QR / Barcode</h3>
                <button type="button" onclick="closeScannerModal()" aria-label="Tutup pengimbas" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="fa-solid fa-xmark text-base" aria-hidden="true"></i></button>
            </div>
            <div id="reader" class="w-full overflow-hidden rounded-xl"></div>
            <div id="scan-result-container" class="text-xs text-center text-slate-600 dark:text-zinc-300 font-mono">
                Sila halakan kamera pada kod QR kad pelajar...
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeScannerModal()" class="px-4 py-2 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-medium cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    <!-- PANEL PROFIL PELAJAR -->
    <div id="student-profile-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/40 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="student-profile-title" data-modal-close="closeStudentProfile" onclick="if(event.target===this)closeStudentProfile()">
        <section class="w-full max-w-5xl max-h-[90vh] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl dark:border-zinc-800 dark:bg-[#121214] sm:p-6">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 dark:border-zinc-800">
                <div><p class="text-[10px] font-bold uppercase tracking-[.18em] text-indigo-600 dark:text-indigo-400">PROFIL PELAJAR</p><h2 id="student-profile-title" class="mt-1 text-lg font-bold text-slate-900 dark:text-white">Butiran pelajar</h2><p id="profile-matric-subtitle" class="mt-1 text-xs font-mono text-slate-500"></p></div>
                <button type="button" onclick="closeStudentProfile()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Tutup profil"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <span id="student-profile-status" class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-bold"><span class="h-1.5 w-1.5 rounded-full bg-current"></span><span id="student-profile-status-label">Status kad</span></span>
                <span id="student-profile-expiry-hint" class="text-xs text-slate-500 dark:text-zinc-400"></span>
            </div>
            <div id="student-profile-duplicate-note" class="hidden mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"></div>
            <section id="student-profile-duplicate-comparison" class="hidden mt-4 rounded-xl border border-rose-200 p-3 dark:border-rose-900">
                <div class="mb-3"><h3 class="text-sm font-bold text-rose-700 dark:text-rose-300">Perbandingan rekod pendua</h3><p class="text-xs text-slate-500 dark:text-zinc-400">Rekod semasa dan rekod yang mempunyai nilai sepadan dipaparkan bersebelahan.</p></div>
                <div id="student-profile-duplicate-comparison-cards" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
            </section>
            <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2" id="student-profile-fields"></div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" id="student-profile-copy-uid" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><i class="fa-regular fa-copy mr-1.5"></i>Salin UID</button>
                <button type="button" id="student-profile-copy-card" class="rounded-lg border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800"><i class="fa-regular fa-copy mr-1.5"></i>Salin nombor kad</button>
            </div>
            <section class="mt-6 rounded-xl border border-slate-200 dark:border-zinc-800">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 dark:border-zinc-800"><div><h3 class="text-sm font-bold text-slate-900 dark:text-white">Sejarah Kad</h3><p class="mt-0.5 text-[11px] text-slate-500 dark:text-zinc-400">Rekod snapshot kad dan perubahan yang disimpan</p></div><button type="button" onclick="loadStudentCardHistory(window.usmsmartActiveProfileId)" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-[11px] font-semibold hover:bg-slate-50 dark:border-zinc-700 dark:hover:bg-zinc-800"><i class="fa-solid fa-rotate-right mr-1"></i>Muat semula</button></div>
                <div id="student-card-history-summary" class="grid grid-cols-2 gap-2 p-3 sm:grid-cols-4"><div class="col-span-2 text-xs text-slate-500 sm:col-span-4">Memuatkan sejarah kad...</div></div>
                <div id="student-card-history-list" class="max-h-64 space-y-2 overflow-y-auto px-3 pb-3"><p class="text-xs text-slate-500">Memuatkan sejarah...</p></div>
            </section>
            <div class="mt-6 flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4 dark:border-zinc-800">
                <button type="button" onclick="closeStudentProfile()" class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">Tutup</button>
                <button type="button" id="student-profile-card-btn" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700"><i class="fa-solid fa-id-card mr-1.5"></i>Kad</button>
                <button type="button" id="student-profile-edit-btn" class="hidden rounded-lg bg-amber-500 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-600"><i class="fa-solid fa-pen-to-square mr-1.5"></i>Edit rekod</button>
            </div>
        </section>
    </div>

    <!-- MODAL CETAK KAD PELAJAR -->
    <div id="student-card-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden" role="dialog" aria-modal="true" aria-labelledby="card-modal-title" data-modal-close="closeCardModal">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 max-w-md w-full mx-4 space-y-5 font-mono shadow-2xl page-transition">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3">
                <h3 id="card-modal-title" class="font-bold text-xs text-slate-900 dark:text-white uppercase tracking-wider"><i class="fa-solid fa-id-card text-indigo-500 mr-1.5" aria-hidden="true"></i> Kad Akses Pelajar</h3>
                <button type="button" onclick="closeCardModal()" aria-label="Tutup kad pelajar" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="fa-solid fa-xmark text-base" aria-hidden="true"></i></button>
            </div>
            
            <div class="print-card-box bg-gradient-to-br from-slate-900 to-indigo-950 text-white p-6 rounded-2xl space-y-4 shadow-lg border border-indigo-500/30 relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 text-white/5 text-8xl font-bold pointer-events-none"><i class="fa-solid fa-id-badge"></i></div>
                <div class="flex justify-between items-center border-b border-white/10 pb-3">
                    <div class="flex items-center space-x-2">
                        <div class="bg-indigo-600 text-white w-7 h-7 rounded-lg flex items-center justify-center font-bold text-[10px]">USM</div>
                        <span class="text-[11px] font-bold tracking-widest uppercase">Kampus Kejuruteraan Pass</span>
                    </div>
                    <span id="card-modal-policy" class="text-[9px] bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 px-2 py-0.5 rounded font-bold">LAB_ALPHA</span>
                </div>
                <div class="space-y-1">
                    <div id="card-modal-name" class="font-bold text-sm text-white">Nama Pelajar</div>
                    <div id="card-modal-matric" class="text-xs text-indigo-300 font-mono">CDCS110/0000</div>
                    <div id="card-modal-school" class="text-[10px] text-zinc-400 pt-1">Pusat Pengajian</div>
                </div>
                <div class="grid grid-cols-2 gap-2 pt-3 border-t border-white/10 text-[10px] font-mono">
                    <div>
                        <span class="text-zinc-400 block text-[9px]">UID Asal:</span>
                        <span id="card-modal-uid" class="text-indigo-200 font-bold">-</span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[9px]">UDA Little-Endian:</span>
                        <span id="card-modal-le" class="text-emerald-400 font-bold">-</span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[9px]">Tarikh Daftar:</span>
                        <span id="card-modal-joined" class="text-slate-200 font-bold">-</span>
                    </div>
                    <div>
                        <span class="text-zinc-400 block text-[9px]">Tarikh Luput Kad:</span>
                        <span id="card-modal-expiry" class="text-amber-300 font-bold">-</span>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2.5 pt-2">
                <button onclick="closeCardModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Tutup</button>
                <button onclick="window.print()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-medium shadow-sm cursor-pointer flex items-center gap-1.5"><i class="fa-solid fa-print"></i> Cetak Kad</button>
            </div>
        </div>
    </div>

    <!-- MODAL DETAILS SESI -->
    <div id="session-detail-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden" role="dialog" aria-modal="true" aria-labelledby="modal-session-title" data-modal-close="closeDetailModal">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 max-w-xl w-full mx-4 space-y-4 font-mono shadow-2xl page-transition">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 pb-3">
                <h3 class="font-bold text-xs text-slate-900 dark:text-white uppercase tracking-wider" id="modal-session-title">Senarai Pelajar</h3>
                <button type="button" onclick="closeDetailModal()" aria-label="Tutup senarai pelajar" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="fa-solid fa-xmark text-base" aria-hidden="true"></i></button>
            </div>
            <div class="max-h-72 overflow-y-auto rounded-xl border border-slate-200 dark:border-zinc-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-50 dark:bg-zinc-900 text-slate-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase sticky top-0">
                        <tr>
                            <th class="p-3">Bil</th>
                            <th class="p-3">Nama Penuh</th>
                            <th class="p-3">Matrik / ID</th>
                        </tr>
                    </thead>
                    <tbody id="modal-session-tbody" class="divide-y divide-slate-100 dark:divide-zinc-900 text-slate-700 dark:text-zinc-300"></tbody>
                </table>
            </div>
            <div class="flex justify-end pt-2">
                <button onclick="closeDetailModal()" class="px-4 py-2 bg-slate-900 dark:bg-white text-white dark:text-black rounded-xl text-xs font-medium cursor-pointer">Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL PENGESAHAN -->
    <div id="confirm-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden" role="dialog" aria-modal="true" aria-labelledby="confirm-modal-title" data-modal-close="closeConfirmModal">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl p-6 max-w-sm w-full mx-4 space-y-4 font-mono shadow-2xl page-transition">
            <div class="flex items-center space-x-3 text-amber-500">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200/50 flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-base" aria-hidden="true"></i>
                </div>
                <h3 id="confirm-modal-title" class="font-bold text-sm text-slate-900 dark:text-white">Pengesahan</h3>
            </div>
            <p class="text-xs text-slate-600 dark:text-zinc-300 leading-relaxed">Adakah anda pasti mahu menyimpan dan memindahkan rekod ini ke Live Database UDA?</p>
            <div class="flex justify-end space-x-2.5 pt-2">
                <button onclick="closeConfirmModal()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Batal</button>
                <button onclick="confirmDumpToUDA()" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-medium shadow-sm cursor-pointer">Teruskan</button>
            </div>
        </div>
    </div>

    <!-- MODAL KEMASKINI REKOD PUKAL -->
    <div id="bulk-update-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl max-w-lg w-full font-mono shadow-2xl page-transition overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 p-5 bg-white dark:bg-[#121214]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900 flex items-center justify-center">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white">Kemaskini Rekod Pukal</h3>
                        <p class="text-[11px] text-slate-500 dark:text-zinc-400" id="bulk-update-modal-subtitle">0 pelajar dipilih</p>
                    </div>
                </div>
                <button type="button" onclick="closeBulkUpdateModal()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800 cursor-pointer">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="bulk-update-form" onsubmit="submitBulkUpdate(event)" class="p-5 space-y-4 text-xs font-mono">
                <div>
                    <label for="bulk-update-type" class="block text-[11px] font-bold text-slate-700 dark:text-zinc-300 mb-1.5 uppercase tracking-wider">
                        Pilih Tindakan Kemaskini
                    </label>
                    <select id="bulk-update-type" onchange="onBulkUpdateTypeChange()" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                        <option value="extend_days">Lanjutkan Tarikh Luput (Tambah Tempoh)</option>
                        <option value="set_expiry">Tetapkan Tarikh Luput Baharu (Tarikh Khusus)</option>
                        <option value="set_policy">Tukar Pakej Polisi Kad</option>
                        <option value="set_school">Tukar Pusat Pengajian (Fakulti)</option>
                    </select>
                </div>

                <!-- FIELD 1: Extend Days -->
                <div id="bulk-field-extend-days">
                    <label for="bulk-extend-preset" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">
                        Pilih Tempoh Lanjutan
                    </label>
                    <select id="bulk-extend-preset" onchange="onBulkExtendPresetChange()" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 mb-2">
                        <option value="30">+30 Hari (1 Bulan)</option>
                        <option value="90">+90 Hari (3 Bulan)</option>
                        <option value="180">+180 Hari (6 Bulan)</option>
                        <option value="365" selected>+365 Hari (1 Tahun)</option>
                        <option value="730">+730 Hari (2 Tahun)</option>
                        <option value="custom">Pilihan Hari Khusus...</option>
                    </select>
                    <div id="bulk-extend-custom-wrap" class="hidden">
                        <label for="bulk-extend-custom-days" class="block text-[10px] text-slate-500 mb-1">Bilangan hari:</label>
                        <input type="number" id="bulk-extend-custom-days" min="1" max="1825" placeholder="Contoh: 45" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2 text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                    </div>
                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1.5 leading-relaxed">
                        <i class="fa-solid fa-circle-info mr-1 text-indigo-500"></i>
                        Jika kad sudah tamat tempoh, lanjutan dikira dari hari ini. Jika kad masih aktif, lanjutan ditambah ke tarikh luput sedia ada.
                    </p>
                </div>

                <!-- FIELD 2: Set Specific Expiry Date -->
                <div id="bulk-field-set-expiry" class="hidden">
                    <label for="bulk-set-expiry-date" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">
                        Tarikh Luput Baharu
                    </label>
                    <input type="date" id="bulk-set-expiry-date" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Semua pelajar yang dipilih akan dikemas kini ke tarikh luput ini.</p>
                </div>

                <!-- FIELD 3: Set Policy -->
                <div id="bulk-field-set-policy" class="hidden">
                    <label for="bulk-set-policy-select" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">
                        Pakej Polisi Kad
                    </label>
                    <select id="bulk-set-policy-select" onchange="onBulkPolicyChange()" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 mb-2">
                        <option value="LAB_ALPHA">LAB_ALPHA (Standard Makmal)</option>
                        <option value="LAB_BETA">LAB_BETA (Akses Makmal Khusus)</option>
                        <option value="FULL_CAMPUS">FULL_CAMPUS (Akses Menyeluruh Kampus)</option>
                        <option value="HOSTEL_ACCESS">HOSTEL_ACCESS (Akses Kolej Kediaman)</option>
                        <option value="custom">Pakej Polisi Lain (Tulis Sendiri)...</option>
                    </select>
                    <div id="bulk-policy-custom-wrap" class="hidden">
                        <input type="text" id="bulk-policy-custom-val" maxlength="100" placeholder="Masukkan nama polisi baharu..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2 text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- FIELD 4: Set School -->
                <div id="bulk-field-set-school" class="hidden">
                    <label for="bulk-set-school-select" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">
                        Pusat Pengajian Baharu
                    </label>
                    <select id="bulk-set-school-select" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500">
                    </select>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeBulkUpdateModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Batal</button>
                    <button type="submit" id="bulk-update-submit-btn" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-sm cursor-pointer flex items-center gap-1.5">
                        <i class="fa-solid fa-check"></i> <span>Sahkan & Kemaskini</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PENGESAHAN PADAM PUKAL -->
    <div id="bulk-delete-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl max-w-sm w-full font-mono shadow-2xl page-transition p-6 space-y-4">
            <div class="flex items-center space-x-3 text-rose-500">
                <div class="w-10 h-10 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200/50 flex items-center justify-center">
                    <i class="fa-solid fa-trash-can text-base"></i>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Padam Rekod Pukal</h3>
                    <p class="text-[11px] text-rose-600 dark:text-rose-400 font-bold" id="bulk-delete-count-text">0 rekod pelajar</p>
                </div>
            </div>
            <p class="text-xs text-slate-600 dark:text-zinc-300 leading-relaxed">
                Adakah anda pasti mahu memadam <strong id="bulk-delete-confirm-count" class="text-rose-600 dark:text-rose-400">0</strong> rekod pelajar yang dipilih? Tindakan ini adalah <span class="underline font-bold">kekal</span> dan tidak boleh dikembalikan.
            </p>
            <div class="flex justify-end space-x-2 pt-2">
                <button type="button" onclick="closeBulkDeleteModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-zinc-800 dark:hover:bg-zinc-700 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Batal</button>
                <button type="button" id="bulk-delete-submit-btn" onclick="submitBulkDelete()" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-semibold shadow-sm cursor-pointer flex items-center gap-1.5">
                    <i class="fa-solid fa-trash"></i> <span>Ya, Padam Semua</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===== NEW: MODAL PROFIL SAYA ===== -->
    <div id="my-profile-modal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4" role="dialog" aria-modal="true" aria-labelledby="my-profile-title" data-modal-close="closeMyProfile">
        <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800 rounded-2xl max-w-lg w-full font-mono shadow-2xl page-transition max-h-[92vh] overflow-y-auto">

            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-zinc-800 p-5 sticky top-0 bg-white dark:bg-[#121214] z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-200/60 dark:border-indigo-800/40 flex items-center justify-center">
                        <i class="fa-solid fa-user-circle" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h3 id="my-profile-title" class="font-bold text-sm text-slate-900 dark:text-white uppercase tracking-wider">Profil Saya</h3>
                        <p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-0.5">Urus maklumat akaun, kata laluan dan passkey anda.</p>
                    </div>
                </div>
                <button onclick="closeMyProfile()" class="text-slate-400 hover:text-slate-600 cursor-pointer p-1" aria-label="Tutup"><i class="fa-solid fa-xmark text-base"></i></button>
            </div>

            <!-- Tabs -->
            <div class="px-5 pt-4">
                <div class="flex bg-slate-100 dark:bg-zinc-900 rounded-xl p-1 gap-1 text-xs">
                    <button type="button" id="profile-tab-info" onclick="switchProfileTab('info')" class="flex-1 px-3 py-2 rounded-lg font-medium transition bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-clean">Maklumat</button>
                    <button type="button" id="profile-tab-password" onclick="switchProfileTab('password')" class="flex-1 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white transition">Kata Laluan</button>
                    <button type="button" id="profile-tab-passkey" onclick="switchProfileTab('passkey')" class="flex-1 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white transition">Passkey</button>
                </div>
            </div>

            <!-- Tab: Info -->
            <form id="profile-info-form" onsubmit="submitProfileInfo(event)" class="p-5 space-y-4 text-xs">
                <div class="usm-avatar-row">
                    <span class="usm-avatar usm-avatar-72" id="profile-avatar-wrap"><img id="profile-avatar" alt="Your profile picture" hidden><span id="profile-avatar-initials">--</span></span>
                    <div class="usm-avatar-side">
                        <div class="usm-avatar-tools">
                            <label class="usm-btn usm-btn-secondary usm-btn-sm usm-avatar-pick" for="profile-avatar-input"><i class="fa-solid fa-camera" aria-hidden="true"></i><span>Pilih gambar</span></label>
                            <input id="profile-avatar-input" type="file" accept="image/jpeg,image/png" class="usm-avatar-input">
                            <button type="button" id="profile-avatar-remove" class="usm-btn usm-btn-quiet usm-btn-xs">Buang gambar</button>
                        </div>
                        <p id="profile-avatar-status" class="usm-avatar-hint" role="status">JPG atau PNG. Gambar dipotong dan dikecilkan dalam pelayar anda, jadi foto asal tidak pernah dihantar ke pelayan.</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/60 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-bold">Nama pengguna</div>
                        <div id="profile-username-display" class="font-semibold text-slate-900 dark:text-white mt-1">—</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/60 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-bold">Peranan</div>
                        <div id="profile-role-display" class="font-semibold text-indigo-600 dark:text-indigo-400 mt-1 uppercase">—</div>
                    </div>
                </div>

                <div>
                    <label for="profile-display-name" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Nama paparan</label>
                    <input id="profile-display-name" name="display_name" type="text" maxlength="100" placeholder="Contoh: Ahmad bin Ali"
                           class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Nama mesra untuk paparan. Boleh dikosongkan.</p>
                </div>

                <div>
                    <label for="profile-email" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Alamat e-mel</label>
                    <input id="profile-email" name="email" type="email" maxlength="190" placeholder="nama@usm.my"
                           class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    <p class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Digunakan untuk notifikasi akaun (pilihan).</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-bold">Akaun dicipta</div>
                        <div id="profile-created-display" class="text-slate-700 dark:text-zinc-300 mt-1">—</div>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-3">
                        <div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-bold">Login terakhir</div>
                        <div id="profile-lastlogin-display" class="text-slate-700 dark:text-zinc-300 mt-1">—</div>
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeMyProfile()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Tutup</button>
                    <button type="submit" id="profile-info-submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-medium shadow-sm cursor-pointer flex items-center gap-1.5 disabled:opacity-60">
                        <i class="fa-solid fa-floppy-disk"></i> <span>Simpan Maklumat</span>
                    </button>
                </div>
            </form>

            <!-- Tab: Password -->
            <form id="profile-password-form" onsubmit="submitProfilePassword(event)" class="p-5 space-y-4 text-xs hidden">
                <div class="rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-3">
                    <p class="text-[11px] text-amber-900 dark:text-amber-200 leading-relaxed">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        Selepas menukar kata laluan, anda akan dilog keluar daripada semua peranti.
                    </p>
                </div>

                <div>
                    <label for="profile-current-password" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Kata laluan semasa</label>
                    <input id="profile-current-password" name="current_password" type="password" required autocomplete="current-password"
                           class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                </div>

                <div>
                    <label for="profile-new-password" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Kata laluan baharu</label>
                    <input id="profile-new-password" name="new_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="Minimum 12 aksara"
                           class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    <div class="mt-1.5 h-1.5 rounded-full bg-slate-200 dark:bg-zinc-800 overflow-hidden">
                        <div id="profile-password-strength" class="h-full w-0 bg-slate-400 transition-all"></div>
                    </div>
                    <p id="profile-password-strength-label" class="text-[10px] text-slate-500 dark:text-zinc-400 mt-1">Kekuatan kata laluan</p>
                </div>

                <div>
                    <label for="profile-confirm-password" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Sahkan kata laluan baharu</label>
                    <input id="profile-confirm-password" name="confirm_password" type="password" required minlength="12" maxlength="72" autocomplete="new-password"
                           class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    <p id="profile-password-match" class="text-[10px] mt-1 text-slate-500 dark:text-zinc-400"></p>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100 dark:border-zinc-800">
                    <button type="button" onclick="closeMyProfile()" class="px-4 py-2 bg-slate-100 dark:bg-zinc-800 rounded-xl text-xs text-slate-700 dark:text-zinc-300 cursor-pointer">Batal</button>
                    <button type="submit" id="profile-password-submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-medium shadow-sm cursor-pointer flex items-center gap-1.5 disabled:opacity-60">
                        <i class="fa-solid fa-key"></i> <span>Tukar Kata Laluan</span>
                    </button>
                </div>
            </form>

            <!-- Tab: Passkey -->
            <div id="profile-passkey-panel" class="p-5 space-y-4 text-xs hidden">
                <div class="rounded-xl border border-slate-200 dark:border-zinc-800 bg-slate-50 dark:bg-zinc-900/60 p-3">
                    <p class="text-[11px] text-slate-600 dark:text-zinc-300 leading-relaxed">
                        <i class="fa-solid fa-fingerprint mr-1 text-indigo-600 dark:text-indigo-400"></i>
                        Passkey membenarkan log masuk dengan Face ID, Windows Hello atau cap jari tanpa menaip kata laluan. Kunci peribadi kekal dalam peranti anda; pelayan hanya menyimpan kunci awam.
                    </p>
                </div>
                <div id="profile-passkey-unsupported" class="hidden rounded-xl border border-amber-200 dark:border-amber-900 bg-amber-50 dark:bg-amber-950/30 p-3">
                    <p class="text-[11px] text-amber-900 dark:text-amber-200 leading-relaxed">
                        <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                        Passkey memerlukan sambungan HTTPS (atau localhost) dan pelayar yang menyokong WebAuthn.
                    </p>
                </div>
                <div id="profile-passkey-list" class="space-y-2">
                    <p class="text-[11px] text-slate-500 dark:text-zinc-400">Memuatkan passkey…</p>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-end gap-3 pt-1">
                    <div class="flex-1">
                        <label for="profile-passkey-label" class="block text-[11px] font-medium text-slate-600 dark:text-zinc-400 mb-1.5">Nama peranti (pilihan)</label>
                        <input id="profile-passkey-label" type="text" maxlength="80" placeholder="Contoh: Laptop pejabat"
                               class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-700 rounded-xl px-3.5 py-2.5 text-slate-900 dark:text-white outline-none focus:border-indigo-500 transition">
                    </div>
                    <button type="button" id="profile-passkey-add" onclick="registerPasskey()" class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-medium shadow-sm cursor-pointer flex items-center justify-center gap-1.5 disabled:opacity-60">
                        <i class="fa-solid fa-plus"></i> <span>Tambah Passkey</span>
                    </button>
                </div>
                <p id="profile-passkey-status" role="status" aria-live="polite" class="text-[10px] text-slate-500 dark:text-zinc-400"></p>
            </div>
        </div>
    </div>
    <!-- ===== END NEW ===== -->

    <!-- ===== MODAL INTEGRASI SYSWEB (LANGKAH SETERUSNYA SELEPAS EKSPORT UDA) ===== -->
    <div id="usm-sysweb-demo-modal" class="hidden fixed inset-0 z-[2000] bg-slate-950/60 backdrop-blur-sm p-4 items-center justify-center" role="dialog" aria-modal="true" aria-labelledby="usm-sysweb-demo-title" data-modal-close="closeSyswebDemo">
        <div class="w-full max-w-4xl max-h-[92vh] overflow-hidden flex flex-col rounded-2xl bg-white dark:bg-zinc-950 border border-slate-200 dark:border-zinc-800 shadow-2xl">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 p-5 bg-white dark:bg-zinc-900">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-network-wired"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 id="usm-sysweb-demo-title" class="text-base font-bold text-slate-900 dark:text-white">Langkah Seterusnya: Integrasi Sysweb</h2>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">Langkah 4</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5" id="usm-sysweb-modal-subtitle">
                            Format UID Converted Little-Endian dengan format Sysweb (prefix <code>00000000</code>).
                        </p>
                    </div>
                </div>
                <button type="button" onclick="closeSyswebDemo()" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-zinc-200 hover:bg-slate-100 dark:hover:bg-zinc-800 text-lg cursor-pointer transition" aria-label="Tutup modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Body -->
            <div class="p-5 overflow-y-auto space-y-4 max-h-[calc(92vh-140px)]">
                <!-- Information Callout -->
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 text-emerald-900 dark:bg-emerald-950/30 dark:border-emerald-800 dark:text-emerald-200 p-3.5 text-xs flex items-start gap-2.5">
                    <i class="fa-solid fa-circle-info text-emerald-600 dark:text-emerald-400 mt-0.5 text-sm"></i>
                    <div class="space-y-1">
                        <div class="font-semibold">Format Penghantaran Sysweb:</div>
                        <p class="opacity-90">Setiap rekod menggunakan <strong>UID Converted (Little-Endian)</strong> yang didahului oleh lapan sifar (<code>00000000 + Converted_UID</code>). Data diproses dan disimpan ke fail <code>sysweb_demo_received.json</code>.</p>
                    </div>
                </div>

                <!-- Source Switcher Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 dark:border-zinc-800 pb-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-slate-700 dark:text-zinc-300" id="usm-sysweb-record-count-badge">0 rekod sedia dihantar</span>
                        <span id="usm-sysweb-source-indicator" class="text-[11px] text-slate-500 dark:text-zinc-400">(Daripada Eksport UDA Terkini)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="usm-sysweb-toggle-source-btn" onclick="toggleSyswebSource()" class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 text-xs font-semibold hover:underline flex items-center gap-1 cursor-pointer">
                            <i class="fa-solid fa-shuffle"></i> <span>Tukar ke Rekod Sampel (Demo)</span>
                        </button>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800 max-h-60 overflow-y-auto">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50/90 dark:bg-zinc-900 text-slate-500 dark:text-zinc-400 border-b border-slate-200 dark:border-zinc-800 text-[10px] uppercase font-mono sticky top-0">
                            <tr>
                                <th class="p-2.5">Bil</th>
                                <th class="p-2.5">Nama</th>
                                <th class="p-2.5">Pengenalan (Matrik/ID)</th>
                                <th class="p-2.5">Jenis</th>
                                <th class="p-2.5 font-mono">UID Converted</th>
                                <th class="p-2.5 font-mono">UID Sysweb (Final)</th>
                            </tr>
                        </thead>
                        <tbody id="usm-sysweb-demo-samples" class="divide-y divide-slate-100 dark:divide-zinc-800 font-mono text-slate-700 dark:text-zinc-300"></tbody>
                    </table>
                </div>

                <!-- Result Log / Output Box -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-[11px] font-bold text-slate-600 dark:text-zinc-400 uppercase tracking-wider">Log Tindak Balas Sysweb</label>
                        <span class="text-[10px] text-slate-400 font-mono" id="usm-sysweb-status-badge">Menunggu penghantaran...</span>
                    </div>
                    <pre id="usm-sysweb-demo-result" class="whitespace-pre-wrap break-words rounded-xl bg-slate-950 text-slate-100 p-3.5 text-xs font-mono max-h-48 overflow-auto border border-slate-800">Klik "Hantar ke Sysweb" untuk memulakan integrasi dengan penerima simulasi.</pre>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex flex-wrap justify-between items-center gap-2 border-t border-slate-200 dark:border-zinc-800 p-4 bg-slate-50/80 dark:bg-zinc-900/60">
                <div class="text-xs text-slate-500 dark:text-zinc-400">
                    Status: <span id="usm-sysweb-footer-status" class="font-semibold text-emerald-600 dark:text-emerald-400">Sedia untuk dihantar</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="closeSyswebDemo()" class="rounded-xl border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:bg-slate-50 dark:hover:bg-zinc-700 px-4 py-2 text-xs font-semibold text-slate-700 dark:text-zinc-300 transition cursor-pointer">
                        Tutup
                    </button>
                    <button type="button" id="usm-sysweb-send-btn" onclick="sendSyswebRecords()" class="rounded-xl bg-emerald-700 hover:bg-emerald-800 text-white px-5 py-2 text-xs font-bold shadow-md hover:shadow-lg transition flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-paper-plane"></i> <span>Hantar ke Sysweb</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- ===== END MODAL SYSWEB ===== -->

    <div id="toast-container" class="fixed inset-0 z-[100] flex flex-col items-center justify-center gap-3 p-4 pointer-events-none" aria-live="assertive" aria-atomic="false"></div>

        <script>
        const USMSMART_CSRF_TOKEN = <?php echo json_encode((string)$_SESSION['usmsmart_csrf_token'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const USMSMART_ROLE = <?php echo json_encode((string)($_SESSION['role'] ?? 'viewer'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const USMSMART_IS_ADMIN = USMSMART_ROLE === 'admin';
        const USMSMART_USER_ID = <?php echo json_encode((int)($_SESSION['user_id'] ?? 0)); ?>;
        const USM_SMART_IS_ADMIN = <?php echo json_encode(($_SESSION['role'] ?? '') === 'admin'); ?>;
    </script>
    <script src="assets/usmsmart-dashboard.js?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-dashboard.js'); ?>"></script>
    <button id="themeToggle" aria-label="Toggle dark mode"
        style="position:fixed;top:1rem;right:1rem;background:none;border:none;color:var(--muted);cursor:pointer;font-size:1.2rem;">
    🌙
</button>

<script>
  // Tiny helper – runs after the page loads
  (function () {
    const btn = document.getElementById('themeToggle');
    const root = document.documentElement;

    // Load saved preference or fallback to system
    const saved = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const active = saved ? saved === 'dark' : prefersDark;
    if (active) root.classList.add('dark');

    btn.addEventListener('click', () => {
      root.classList.toggle('dark');
      const isDark = root.classList.contains('dark');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
      btn.textContent = isDark ? '☀️' : '🌙';
    });
  })();
</script>

<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && $action === ''): ?>

<section id="usm-notifications-panel" aria-label="Notifications" aria-hidden="true"><header><i class="fa-solid fa-bell" aria-hidden="true"></i><div class="usm-panel-title"><strong>Notifications</strong></div><span id="usm-notifications-count" class="usm-panel-count" hidden></span><button id="usm-notifications-close" type="button" aria-label="Close notifications"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></header><div id="usm-notifications-body">Loading notifications…</div><footer><i class="fa-regular fa-circle-check" aria-hidden="true"></i><span id="usm-notifications-foot">Checking…</span></footer></section>
<section id="usm-requests-panel" aria-label="Change requests" aria-hidden="true"><header class="usm-requests-header"><i class="fa-solid fa-inbox" aria-hidden="true"></i><div class="usm-panel-title"><strong><?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Pending Approvals' : 'My Requests'; ?></strong><div class="usm-panel-sub"><?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'Review and decide on Viewer change requests.' : 'Your changes stay pending until an Admin approves them.'; ?></div></div><button id="usm-requests-close" type="button">Close</button></header><div id="usm-requests-body" class="usm-requests-body">Loading…</div></section>
<script>
(function(){const launch=document.getElementById('usm-notifications-launch'),panel=document.getElementById('usm-notifications-panel'),close=document.getElementById('usm-notifications-close'),body=document.getElementById('usm-notifications-body'),badge=document.getElementById('usm-notifications-badge'),pill=document.getElementById('usm-notifications-count'),foot=document.getElementById('usm-notifications-foot');if(!launch||!panel||!body||!badge)return;const MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],stamp=v=>{const d=String(v||'').split(' ')[0].split('-'),mo=d.length===3?MON[Number(d[1])-1]:'';return mo?d[2]+' '+mo+' '+d[0]:String(v||'');},ago=v=>{const t=Date.parse(String(v||'').replace(' ','T'));if(!isFinite(t))return stamp(v);const s=(Date.now()-t)/1000;if(s<0)return stamp(v);if(s<60)return 'just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';if(s<604800)return Math.floor(s/86400)+'d ago';return stamp(String(v||'').split(' ')[0]);};const csrf=<?php echo json_encode((string)($_SESSION['usmsmart_csrf_token']??'')); ?>;const isAdmin=<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'true' : 'false'; ?>;const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));async function refresh(){try{const r=await fetch('index.php?action=get_notifications',{credentials:'same-origin',headers:{'Accept':'application/json'}});const j=await r.json();if(!r.ok||!j.success)throw new Error();const count=(Number(j.unread)||0)+(isAdmin?(Number(j.pending_count)||0):0);badge.textContent=count>99?'99+':String(count);badge.hidden=count===0;if(isAdmin)launch.title=(Number(j.pending_count)||0)+' pending approval(s)';const unreadNow=Number(j.unread)||0,shown=(j.notifications||[]).length;if(pill){pill.textContent=unreadNow>0?(unreadNow>99?'99+':String(unreadNow))+' new':'';pill.hidden=unreadNow===0;}if(foot){foot.textContent=unreadNow>0?String(unreadNow)+' unread':'All caught up · '+shown+' recent';}if(!j.notifications.length){body.innerHTML='<div class="usm-notification-empty"><i class="fa-solid fa-bell-slash usm-empty-ico" aria-hidden="true"></i><strong>No notifications yet</strong><span>Approval and message activity lands here.</span></div>';if(foot)foot.textContent='All caught up';return;}body.innerHTML=j.notifications.map(n=>'<article class="usm-notification-item'+(Number(n.is_read)===0?' unread':'')+'"><span class="usm-nt-mark" aria-hidden="true"></span><div class="usm-nt-main"><div class="usm-nt-top"><strong>'+esc(n.title)+'</strong><time class="usm-notification-time" title="'+esc(n.created_at)+'">'+esc(ago(n.created_at))+'</time></div><p class="usm-nt-text">'+esc(n.message)+'</p>'+(n.request_id?'<div class="usm-nt-tags"><span class="usm-chip is-link">Request #'+esc(n.request_id)+'</span></div>':'')+'</div></article>').join('');}catch(e){body.innerHTML='<div class="usm-notification-empty is-error"><i class="fa-solid fa-triangle-exclamation usm-empty-ico" aria-hidden="true"></i><strong>Could not load notifications</strong><span>Refresh the page to try again.</span></div>';if(pill)pill.hidden=true;if(foot)foot.textContent='Notifications are not up to date';}}launch.addEventListener('click',async()=>{panel.classList.toggle('open');panel.setAttribute('aria-hidden',panel.classList.contains('open')?'false':'true');if(panel.classList.contains('open')){await refresh();try{await fetch('index.php?action=mark_notifications_read',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'},body:'{}'});await refresh();}catch(e){}}});close.addEventListener('click',()=>{panel.classList.remove('open');panel.setAttribute('aria-hidden','true');});document.addEventListener('click',e=>{if(panel.classList.contains('open')&&!panel.contains(e.target)&&!launch.contains(e.target)){panel.classList.remove('open');panel.setAttribute('aria-hidden','true');}});refresh();setInterval(refresh,30000);})();
(function(){const launch=document.getElementById('usm-requests-launch'),panel=document.getElementById('usm-requests-panel'),close=document.getElementById('usm-requests-close'),body=document.getElementById('usm-requests-body');if(!launch||!panel||!body)return;const isAdmin=<?php echo (($_SESSION['role'] ?? '') === 'admin') ? 'true' : 'false'; ?>;const csrf=<?php echo json_encode((string)($_SESSION['usmsmart_csrf_token']??'')); ?>;function esc(s){return String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
const fieldLabels={id:'Record ID',name:'Name',matric:'Identifier',cardholderType:'Cardholder type',school:'School / faculty',usmUid:'Card UID',serialNo:'Card serial number',policy:'Policy',dateJoined:'Date joined',expiryDate:'Expiry date',records:'UDA records',total_records:'Total records',details:'Session details',status:'Status'};
function prettyValue(v){if(v===null||v===undefined||v==='')return '<span style="opacity:.55">— Not provided</span>';if(typeof v==='boolean')return v?'Yes':'No';if(Array.isArray(v))return '<span>'+v.length+' item(s)</span>';if(typeof v==='object')return '<pre class="usm-proposal-json">'+esc(JSON.stringify(v,null,2))+'</pre>';return esc(String(v));}
function proposalMarkup(x){let d={};try{d=JSON.parse(x.details||'{}')}catch(e){return '<pre class="usm-proposal-json">'+esc(x.details||'No proposal details were saved.')+'</pre>'}const type=x.request_type;const actionLabels={save_student:(Number(d.id)>0?'Update student record':'Add student record'),delete_student:'Delete student record',dump_uda:'Submit records to UDA',soft_delete_session:'Delete transport session'};const action=actionLabels[type]||type.replace(/_/g,' ');let html='<div class="usm-proposal-action"><strong>Requested action</strong><span>'+esc(action)+'</span></div>';
if(type==='dump_uda'&&Array.isArray(d.records)){html+='<div class="usm-proposal-subtitle">Proposed UDA records ('+d.records.length+')</div><div class="usm-proposal-table-wrap"><table class="usm-proposal-table"><thead><tr><th>Name</th><th>Matric</th><th>UID</th><th>Card serial</th><th>Policy</th></tr></thead><tbody>'+d.records.map(r=>'<tr><td>'+prettyValue(r.name)+'</td><td>'+prettyValue(r.matric)+'</td><td>'+prettyValue(r.usmUid)+'</td><td>'+prettyValue(r.serialNo)+'</td><td>'+prettyValue(r.policy)+'</td></tr>').join('')+'</tbody></table></div>';}
else {const fields=Object.keys(d).filter(k=>k!=='_summary');if(fields.length){html+='<div class="usm-proposal-grid">'+fields.map(k=>'<div class="usm-proposal-field"><span>'+esc(fieldLabels[k]||k.replace(/([A-Z])/g,' $1').replace(/_/g,' ').replace(/^./,c=>c.toUpperCase()))+'</span><strong>'+prettyValue(d[k])+'</strong></div>').join('')+'</div>';}else{html+='<p>No proposed field values were saved.</p>';}}
html+='<p class="usm-proposal-note">This is the information submitted by the requester. The live record is not changed unless an Admin approves the request.</p>';return html;}
let allRequests=[];let filtersReady=false;
function setupFilters(){if(filtersReady)return;filtersReady=true;body.innerHTML='<div class="usm-approval-tools"><input id="usm-approval-search" type="search" placeholder="Search request, student, or requester…" aria-label="Search approval requests"><select id="usm-approval-status" aria-label="Filter by status"><option value="all">All statuses</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select><select id="usm-approval-sort" aria-label="Sort requests"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="status">Pending first</option></select></div><div id="usm-approval-summary" class="usm-approval-summary" aria-live="polite"></div><div id="usm-approval-results" aria-live="polite"></div>';['usm-approval-search','usm-approval-status','usm-approval-sort'].forEach(id=>document.getElementById(id).addEventListener(id==='usm-approval-search'?'input':'change',renderRequests));}
function renderRequests(){const result=document.getElementById('usm-approval-results'),summary=document.getElementById('usm-approval-summary');if(!result||!summary)return;const q=(document.getElementById('usm-approval-search').value||'').trim().toLowerCase(),status=document.getElementById('usm-approval-status').value,sort=document.getElementById('usm-approval-sort').value;const counts={pending:0,approved:0,rejected:0};allRequests.forEach(x=>{if(counts[x.status]!==undefined)counts[x.status]++;});summary.innerHTML='<span class="usm-approval-count">Total <b>'+allRequests.length+'</b></span><span class="usm-approval-count">Pending <b>'+counts.pending+'</b></span><span class="usm-approval-count">Approved <b>'+counts.approved+'</b></span><span class="usm-approval-count">Rejected <b>'+counts.rejected+'</b></span>';let list=allRequests.filter(x=>(status==='all'||x.status===status)&&(!q||[x.summary,x.requested_by_username,x.reviewed_by_username,x.id,x.status,x.review_note].some(v=>String(v??'').toLowerCase().includes(q))));list.sort((a,b)=>{if(sort==='status'){const rank={pending:0,rejected:1,approved:2};return (rank[a.status]??3)-(rank[b.status]??3)||Number(b.id)-Number(a.id);}const av=Date.parse(a.created_at)||Number(a.id)||0,bv=Date.parse(b.created_at)||Number(b.id)||0;return sort==='oldest'?av-bv:bv-av;});if(!list.length){result.innerHTML='<div class="usm-approval-empty"><strong>No matching requests</strong><br>Try another search term or choose a different status filter.</div>';return;}result.innerHTML=list.map(x=>'<article class="usm-request-row"><div style="min-width:0;flex:1"><div style="font-weight:750">'+esc(x.summary)+'</div><div class="usm-request-meta">Request #'+esc(x.id)+' · '+esc(x.requested_by_username)+' · Submitted '+esc(x.created_at)+'</div><div class="usm-request-status usm-status-'+esc(x.status)+'">'+esc(x.status.toUpperCase())+(x.reviewed_by_username?' · Reviewed by '+esc(x.reviewed_by_username):'')+'</div>'+'<details class="usm-proposal-details"><summary>View proposed changes</summary><div class="usm-proposal-content">'+proposalMarkup(x)+'</div></details>'+(x.status==='rejected'?'<div class="usm-rejection-reason"><strong>Reason for rejection</strong><div>'+esc(x.review_note||'No reason was recorded for this older request. Please contact the Admin for clarification.')+'</div></div>':(x.review_note?'<div class="usm-request-meta">Admin note: '+esc(x.review_note)+'</div>':''))+'</div>'+(isAdmin&&x.status==='pending'?'<div class="usm-request-actions"><button class="usm-approve" data-id="'+esc(x.id)+'" data-decision="approve">Approve</button><button class="usm-reject" data-id="'+esc(x.id)+'" data-decision="reject">Reject</button></div>':(!isAdmin&&x.status==='rejected'?'<div class="usm-request-actions"><button class="usm-resubmit" data-id="'+esc(x.id)+'" data-resubmit="1" data-payload="'+esc(x.details)+'">Edit &amp; Resubmit</button></div>':''))+'</article>').join('');}
async function load(){body.textContent='Memuatkan permintaan…';try{const r=await fetch('index.php?action=get_change_requests',{credentials:'same-origin',headers:{'Accept':'application/json'}});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||'Permintaan tidak dapat dimuatkan.');allRequests=Array.isArray(j.requests)?j.requests:[];setupFilters();renderRequests();}catch(e){body.textContent=e.message||'Permintaan tidak dapat dimuatkan.';}}
launch.addEventListener('click',()=>{panel.classList.add('open');panel.setAttribute('aria-hidden','false');load();});close.addEventListener('click',()=>{panel.classList.remove('open');panel.setAttribute('aria-hidden','true');});body.addEventListener('click',async e=>{const b=e.target.closest('button[data-id]');if(!b)return;if(b.dataset.resubmit==='1'){let initial={};try{initial=JSON.parse(b.dataset.payload||'{}');}catch(err){alert('Could not read the saved request data.');return;}if(!initial||typeof initial!=='object'||Array.isArray(initial)){alert('The saved request data is not editable.');return;}const editedText=prompt('Edit the request data as JSON, then click OK to resubmit. Keep the field names unchanged.\n\nExample fields for a student: name, matric, school, usmUid, serialNo, policy, dateJoined, expiryDate.',JSON.stringify(initial,null,2));if(editedText===null)return;let edited;try{edited=JSON.parse(editedText);if(!edited||typeof edited!=='object'||Array.isArray(edited))throw new Error();}catch(err){alert('Invalid JSON. Please correct the data and try again.');return;}if(!confirm('Resubmit this edited request to Admin for approval? The live database will not change until approval.'))return;b.disabled=true;try{const r=await fetch('index.php?action=edit_rejected_request',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'},body:JSON.stringify({id:Number(b.dataset.id),payload:edited})});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||'Resubmission failed');alert(j.message);load();}catch(err){alert(err.message||'Resubmission failed');b.disabled=false;}return;}let note='';if(b.dataset.decision==='reject'){note=prompt('Please explain why this request is being rejected (required, max 500 characters):');if(note===null)return;note=note.trim();if(!note){alert('A rejection reason is required. The request has not been rejected.');return;}if(note.length>500){alert('Please keep the rejection reason within 500 characters.');return;}}if(!confirm(b.dataset.decision==='approve'?'Approve and apply this change to the live database?':'Reject this request and send the reason to the requester?'))return;b.disabled=true;try{const r=await fetch('index.php?action=review_change_request',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,'Accept':'application/json'},body:JSON.stringify({id:Number(b.dataset.id),decision:b.dataset.decision,note})});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||'Review failed');alert(j.message);load();}catch(err){alert(err.message||'Review failed');b.disabled=false;}});
})();
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && $action === ''): ?>

<section id="usm-messages-page" aria-label="Messages" aria-hidden="true">
<header class="usm-msg-head"><i class="fa-solid fa-envelope" aria-hidden="true"></i><div class="usm-panel-title"><strong>USMSmart Messages</strong><div class="usm-panel-sub">Private conversations between Admin and Viewer</div></div><button id="usm-messages-close" type="button">Close</button></header>
 <div class="usm-msg-layout"><aside class="usm-msg-sidebar">
  <details class="usm-msg-new"><summary><i class="fa-solid fa-plus" aria-hidden="true"></i>New conversation</summary><form id="usm-msg-start-form" class="usm-msg-start"><label for="usm-msg-contact">Recipient</label><select id="usm-msg-contact" required><option value="">Loading users…</option></select><label for="usm-msg-subject">Subject</label><input id="usm-msg-subject" maxlength="160" required placeholder="e.g. Request clarification"><label for="usm-msg-request">Link to an approval request (optional)</label><select id="usm-msg-request"><option value="0">No linked request</option></select><label for="usm-msg-first">Message</label><textarea id="usm-msg-first" rows="3" maxlength="4000" required placeholder="Write your message…"></textarea><button type="submit">Start conversation</button></form></details>
  <div class="usm-msg-eyebrow"><span>Your conversations</span><span id="usm-msg-count" class="usm-panel-count">0</span></div><div id="usm-msg-list"><div class="usm-msg-empty">Loading conversations…</div></div>
 </aside><div class="usm-msg-thread"><div id="usm-msg-thread-title" class="usm-msg-thread-title">Select a conversation<small>Choose a conversation or start a new one.</small></div><div id="usm-msg-feed" class="usm-msg-feed"><div class="usm-msg-empty">Your messages will appear here.</div></div><form id="usm-msg-compose" class="usm-msg-compose"><div class="usm-msg-compose-field"><textarea id="usm-msg-reply" maxlength="4000" rows="2" placeholder="Write a reply…" aria-label="Write a reply" disabled></textarea><span class="usm-msg-hint">Enter sends · Shift+Enter adds a line</span></div><button type="submit" id="usm-msg-send" disabled><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span>Send</span></button></form></div></div>
</section>
<script>
(function(){
 const page=document.getElementById('usm-messages-page'),launch=document.getElementById('usm-messages-launch'),close=document.getElementById('usm-messages-close'),list=document.getElementById('usm-msg-list'),feed=document.getElementById('usm-msg-feed'),threadTitle=document.getElementById('usm-msg-thread-title'),contactSelect=document.getElementById('usm-msg-contact'),requestSelect=document.getElementById('usm-msg-request'),startForm=document.getElementById('usm-msg-start-form'),compose=document.getElementById('usm-msg-compose'),reply=document.getElementById('usm-msg-reply'),send=document.getElementById('usm-msg-send'),badge=document.getElementById('usm-messages-unread');
 if(!page||!launch)return;const csrf=<?php echo json_encode((string)($_SESSION['usmsmart_csrf_token']??'')); ?>;const me=Number(<?php echo (int)($_SESSION['user_id']??0); ?>);const isAdmin=<?php echo (($_SESSION['role']??'')==='admin')?'true':'false'; ?>;let activeId=0,contacts=[],requests=[],conversations=[];
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const avatarChip=(id,version,name,extra)=>{const two=(String(name||'').trim().slice(0,2)||'--').toUpperCase();const url=(typeof avatarUrl==='function')?avatarUrl(Number(id)||0,String(version||'')):'';return '<span class="usm-avatar '+esc(extra)+(url?' has-picture':'')+'">'+(url?'<img alt="" src="'+esc(url)+'">':'<img alt="" hidden>')+'<span>'+esc(two)+'</span></span>';};
 async function api(action,opts={}){const r=await fetch('index.php?action='+action+(opts.query||''),{credentials:'same-origin',headers:{'Accept':'application/json',...(opts.body?{'Content-Type':'application/json','X-CSRF-Token':csrf}:{})},...(opts.body?{method:'POST',body:JSON.stringify(opts.body)}:{})});const j=await r.json();if(!r.ok||!j.success)throw new Error(j.message||'Messaging request failed.');return j;}
 const MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],stamp=v=>{const d=String(v||'').split(' ')[0].split('-'),mo=d.length===3?MON[Number(d[1])-1]:'';return mo?d[2]+' '+mo+' '+d[0]:String(v||'');},ago=v=>{const t=Date.parse(String(v||'').replace(' ','T'));if(!isFinite(t))return stamp(v);const s=(Date.now()-t)/1000;if(s<0)return stamp(v);if(s<60)return 'just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';if(s<604800)return Math.floor(s/86400)+'d ago';return stamp(String(v||'').split(' ')[0]);};
 function updateUnread(){const n=conversations.reduce((a,c)=>a+(Number(c.unread)||0),0);badge.hidden=n===0;badge.textContent=n>99?'99+':String(n);}
 async function loadContacts(){const j=await api('get_message_contacts');contacts=j.contacts||[];requests=j.requests||[];contactSelect.innerHTML='<option value="">Choose a recipient…</option>'+contacts.map(u=>'<option value="'+esc(u.id)+'">'+esc(u.username)+'</option>').join('');requestSelect.innerHTML='<option value="0">No linked request</option>'+requests.map(r=>'<option value="'+esc(r.id)+'" data-viewer="'+esc(r.viewer_user_id)+'">Request #'+esc(r.id)+' · '+esc(r.request_type.replace(/_/g,' '))+' · '+esc(r.status)+'</option>').join('');}
 async function loadConversations(){const j=await api('get_message_conversations');conversations=j.conversations||[];updateUnread();const cnt=document.getElementById('usm-msg-count');if(cnt)cnt.textContent=String(conversations.length);if(!conversations.length){list.innerHTML='<div class="usm-msg-empty"><i class="fa-regular fa-comments usm-empty-ico" aria-hidden="true"></i><strong>No conversations yet</strong><span>Open New conversation above to write the first one.</span></div>';return;}list.innerHTML=conversations.map(c=>{const who=String((isAdmin?c.viewer_username:c.admin_username)||''),un=Number(c.unread)||0;return '<button type="button" class="usm-msg-list-item'+(Number(c.id)===activeId?' active':'')+(un>0?' unread':'')+'" data-conversation="'+esc(c.id)+'" title="'+esc(c.updated_at)+'">' + avatarChip(isAdmin?c.viewer_user_id:c.admin_user_id,isAdmin?c.viewer_avatar:c.admin_avatar,who,'usm-msg-avatar usm-avatar-28') + '<span class="usm-msg-li-main"><strong>'+esc(c.subject)+'</strong><span class="usm-msg-prev">'+esc(c.last_message||'No messages yet')+'</span><span class="usm-msg-meta"><time>'+esc(ago(c.updated_at))+'</time>'+(c.request_id?'<span class="usm-chip">#'+esc(c.request_id)+'</span>':'')+(un>0?'<em class="usm-msg-unread">'+esc(un)+'</em>':'')+'</span></span></button>';}).join('');}
 async function openConversation(id){activeId=Number(id);const j=await api('get_conversation_messages',{query:'&id='+encodeURIComponent(activeId)});const c=j.conversation;threadTitle.innerHTML=esc(c.subject)+'<small>Conversation #'+esc(c.id)+(c.request_id?' · Linked to approval request #'+esc(c.request_id):'')+'</small>';let lastDay='';feed.innerHTML=(j.messages||[]).map(m=>{const when=String(m.created_at||'').split(' '),day=when[0]||'',mine=Number(m.sender_id)===me;let sep='';if(day&&day!==lastDay){lastDay=day;sep='<div class="usm-msg-day"><span>'+esc(stamp(day))+'</span></div>';}return sep+'<div class="usm-msg-row'+(mine?' mine':'')+'">'+(mine?'':avatarChip(m.sender_id,m.sender_avatar,m.sender_username,'usm-msg-avatar usm-avatar-24'))+'<div class="usm-msg-bubble'+(mine?' mine':'')+'">'+esc(m.message_body)+'<small>'+esc(m.sender_username)+(when[1]?' · '+esc(when[1].slice(0,5)):'')+'</small></div></div>';}).join('')||'<div class="usm-msg-empty"><i class="fa-regular fa-comment-dots usm-empty-ico" aria-hidden="true"></i><strong>No messages in this conversation</strong><span>Write the first reply below.</span></div>';feed.scrollTop=feed.scrollHeight;reply.disabled=false;send.disabled=false;await loadConversations();}
 function toggle(open){page.classList.toggle('open',open);page.setAttribute('aria-hidden',open?'false':'true');if(open){loadContacts().catch(e=>{contactSelect.innerHTML='<option value="">'+esc(e.message)+'</option>';});loadConversations().catch(e=>{list.textContent=e.message;});}}
 launch.addEventListener('click',()=>toggle(true));close.addEventListener('click',()=>toggle(false));list.addEventListener('click',e=>{const b=e.target.closest('[data-conversation]');if(b)openConversation(b.dataset.conversation).catch(err=>alert(err.message));});
 requestSelect.addEventListener('change',()=>{const opt=requestSelect.selectedOptions[0];if(!opt||requestSelect.value==='0')return;const viewerId=Number(opt.dataset.viewer);if(isAdmin){contactSelect.value=String(viewerId);}else{const own=Number(<?php echo (int)($_SESSION['user_id']??0); ?>);if(Number(opt.dataset.viewer)!==own){requestSelect.value='0';alert('You can only link your own requests.');}}});
 startForm.addEventListener('submit',async e=>{e.preventDefault();const contactId=Number(contactSelect.value),subject=document.getElementById('usm-msg-subject').value.trim(),message=document.getElementById('usm-msg-first').value.trim(),requestId=Number(requestSelect.value||0);if(!contactId||!subject||!message)return;const opt=requestSelect.selectedOptions[0];if(requestId&&isAdmin&&opt&&Number(opt.dataset.viewer)!==contactId){alert('The linked request must belong to the selected Viewer.');return;}const btn=startForm.querySelector('button[type="submit"]');btn.disabled=true;try{const j=await api('create_message_conversation',{body:{contact_id:contactId,subject,message,request_id:requestId}});startForm.reset();const sheet=startForm.closest('details');if(sheet)sheet.open=false;await loadConversations();await openConversation(j.conversation_id);}catch(err){alert(err.message);}finally{btn.disabled=false;}});
 compose.addEventListener('submit',async e=>{e.preventDefault();const message=reply.value.trim();if(!activeId||!message||send.disabled)return;send.disabled=true;try{await api('send_message',{body:{conversation_id:activeId,message}});reply.value='';await openConversation(activeId);}catch(err){alert(err.message);}finally{send.disabled=!activeId;}});
  reply.addEventListener('keydown',e=>{if(e.key!=='Enter'||e.shiftKey||e.isComposing||send.disabled)return;e.preventDefault();if(compose.requestSubmit)compose.requestSubmit();else compose.dispatchEvent(new Event('submit',{cancelable:true}));});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&page.classList.contains('open'))toggle(false);});
 // Refresh the list periodically while open so unread counts and new replies stay current.
 setInterval(()=>{if(page.classList.contains('open'))loadConversations().catch(()=>{});},20000);
})();
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && $action === ''): ?>

<section id="usm-health-panel" aria-label="System health diagnostics" aria-hidden="true"><header class="usm-panel-head"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i><div class="usm-panel-title"><strong>System health</strong></div><button id="usm-health-refresh" type="button"><i class="fa-solid fa-rotate" aria-hidden="true"></i><span>Refresh</span></button></header><div class="usm-panel-body"><span id="usm-health-summary">Checks have not run yet.</span><div id="usm-health-items" aria-live="polite"></div><div id="usm-health-checked" class="usm-health-detail"></div></div></section>
<script>
(function(){
 const launch=document.getElementById('usm-health-launch'),panel=document.getElementById('usm-health-panel'),refresh=document.getElementById('usm-health-refresh'),items=document.getElementById('usm-health-items'),summary=document.getElementById('usm-health-summary'),checked=document.getElementById('usm-health-checked');
 if(!launch||!panel||!refresh||!items)return;
 const labels={ok:'OK',warning:'Warning',error:'Error'};
 async function runChecks(){summary.textContent='Checking services…';items.replaceChildren();refresh.disabled=true;try{const response=await fetch('index.php?action=system_health',{credentials:'same-origin',headers:{'Accept':'application/json'}});const data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'Health check failed.');summary.textContent=data.overall==='ok'?'All checked services look healthy.':data.overall==='warning'?'Some checks need attention.':'One or more checks failed.';(data.checks||[]).forEach(check=>{const card=document.createElement('div');card.className='usm-health-item';const top=document.createElement('div');top.className='usm-health-top';const name=document.createElement('span');name.textContent=check.name;const state=document.createElement('span');state.className='usm-health-state '+(check.status||'warning');state.textContent=labels[check.status]||'Unknown';top.append(name,state);const detail=document.createElement('div');detail.className='usm-health-detail';detail.textContent=check.detail||'';card.append(top,detail);items.appendChild(card);});checked.textContent='Checked at '+(data.checked_at||'unknown');}catch(e){summary.textContent=e.message||'Unable to run checks.';checked.textContent='';}finally{refresh.disabled=false;}}
 launch.addEventListener('click',()=>{const open=!panel.classList.contains('open');panel.classList.toggle('open',open);panel.setAttribute('aria-hidden',open?'false':'true');launch.setAttribute('aria-expanded',open?'true':'false');if(open)runChecks();});refresh.addEventListener('click',runChecks);document.addEventListener('keydown',e=>{if(e.key==='Escape'){panel.classList.remove('open');panel.setAttribute('aria-hidden','true');launch.setAttribute('aria-expanded','false');}});
})();
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && $action === ''): ?>

<button id="usmsmart-ai-launcher" type="button" aria-label="Open USMSmart AI Assistant" title="USMSmart AI Assistant"><span aria-hidden="true">✦</span></button>
<section id="usmsmart-ai-panel" aria-label="USMSmart AI Assistant" aria-hidden="true">
  <header class="usm-ai-head"><div style="width:38px;height:38px;border-radius:12px;background:rgba(255,255,255,.16);display:flex;align-items:center;justify-content:center;font-size:20px">✦</div><div><div style="font-weight:700;font-size:14px">USMSmart AI</div><div class="sub">Read-only card &amp; Sysweb assistant</div><div class="usm-ai-status">Local Ollama model</div></div><button id="usmsmart-ai-clear" class="usm-ai-close" type="button" aria-label="Clear conversation" title="Clear conversation" style="margin-left:auto;font-size:13px;width:auto;padding:0 9px">Clear</button><button id="usmsmart-ai-close" class="usm-ai-close" type="button" aria-label="Close assistant">×</button></header>
  <div id="usmsmart-ai-messages" role="log" aria-live="polite"><div class="usm-ai-msg bot">Hi! I can help explain USMSmart statistics, upcoming card expiries, and data-quality summaries. I only use aggregate data and cannot change student records.</div></div>
  <div class="usm-ai-suggestions"><button type="button" data-question="How many student records are there?">Student totals</button><button type="button" data-question="How many cards are expired or expiring in the next 30 days?">Card expiry</button><button type="button" data-question="Summarize the data quality issues.">Data quality</button><button type="button" data-question="What is the student registration trend over the last six months?">6-month trend</button><button type="button" data-question="How many cards will expire in the next 90 days, grouped by time range?">90-day outlook</button><button type="button" data-question="How many Sysweb records are valid, need review, or are invalid, and why?">Sysweb readiness</button><button type="button" data-question="Summarise the recent Sysweb export batches and their results.">Export history</button></div>
  <form id="usmsmart-ai-form" class="usm-ai-compose"><textarea id="usmsmart-ai-input" rows="1" maxlength="2000" placeholder="Ask about USMSmart…" aria-label="Ask USMSmart AI"></textarea><button id="usmsmart-ai-send" type="submit">Send</button></form>
  <div class="usm-ai-footnote">AI responses may be inaccurate. Student-level identifiers are not sent to the AI.</div>
</section>
<script>
(function(){
  const launcher=document.getElementById('usmsmart-ai-launcher');
  const panel=document.getElementById('usmsmart-ai-panel');
  const closeBtn=document.getElementById('usmsmart-ai-close');
  const clearBtn=document.getElementById('usmsmart-ai-clear');
  const form=document.getElementById('usmsmart-ai-form');
  const input=document.getElementById('usmsmart-ai-input');
  const sendBtn=document.getElementById('usmsmart-ai-send');
  const messages=document.getElementById('usmsmart-ai-messages');
  if(!launcher||!panel||!form)return;
  function toggle(open){panel.classList.toggle('open',open);panel.setAttribute('aria-hidden',open?'false':'true');launcher.setAttribute('aria-expanded',open?'true':'false');if(open)input.focus();}
  function addMessage(text,role){const node=document.createElement('div');node.className='usm-ai-msg '+role;node.textContent=String(text||'');messages.appendChild(node);messages.scrollTop=messages.scrollHeight;return node;}
  if(clearBtn)clearBtn.addEventListener('click',()=>{if(sendBtn.disabled)return;messages.innerHTML='';addMessage('Hi! I can help explain USMSmart statistics, upcoming card expiries, and data-quality summaries. I only use aggregate data and cannot change student records.','bot');input.focus();});
  launcher.setAttribute('aria-expanded','false');launcher.addEventListener('click',()=>toggle(!panel.classList.contains('open')));closeBtn.addEventListener('click',()=>toggle(false));
  document.querySelectorAll('.usm-ai-suggestions button').forEach(btn=>btn.addEventListener('click',()=>{input.value=btn.getAttribute('data-question')||'';form.requestSubmit();}));
  input.addEventListener('keydown',e=>{if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();form.requestSubmit();}});
  form.addEventListener('submit',async e=>{e.preventDefault();const message=input.value.trim();if(!message||sendBtn.disabled)return;addMessage(message,'user');input.value='';sendBtn.disabled=true;sendBtn.textContent='…';const pending=addMessage('','bot');pending.setAttribute('aria-label','Assistant is thinking');pending.innerHTML='<span class="usm-ai-typing" aria-hidden="true"><span></span><span></span><span></span></span>'; 
    try{const response=await fetch('index.php?action=ai_chat',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN,'Accept':'application/json'},credentials:'same-origin',body:JSON.stringify({message})});const result=await response.json();if(!response.ok||!result.success)throw new Error(result.message||result.error||('AI request failed (HTTP '+response.status+').'));pending.remove();const answer=typeof result.answer==='string'?result.answer.trim():'';if(!answer){addMessage('The AI server returned an empty answer. Check that Ollama is running and the selected local model is installed.','bot');return;}addMessage(answer,'bot');}
    catch(err){pending.textContent=err&&err.message?err.message:'Could not reach the AI assistant. Please try again.';}
    finally{sendBtn.disabled=false;sendBtn.textContent='Send';input.focus();}
  });
})();
</script>

<!-- User manual: self-contained help dialog; no database changes required. -->
<div id="usmsmart-manual-backdrop" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/60 p-3 sm:p-6" role="presentation" onclick="if(event.target===this)closeUserManual()">
  <section id="usmsmart-manual-dialog" class="w-full max-w-3xl max-h-[90vh] overflow-hidden rounded-2xl bg-white dark:bg-[#111318] shadow-2xl border border-slate-200 dark:border-zinc-700 flex flex-col" role="dialog" aria-modal="true" aria-labelledby="usmsmart-manual-title" aria-describedby="usmsmart-manual-intro">
    <div class="flex items-start gap-3 p-5 border-b border-slate-200 dark:border-zinc-700">
      <div class="w-10 h-10 shrink-0 rounded-xl bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 flex items-center justify-center"><i class="fa-solid fa-book-open" aria-hidden="true"></i></div>
      <div class="flex-1"><h2 id="usmsmart-manual-title" class="text-lg font-bold text-slate-900 dark:text-white">Panduan Pengguna USMSmart</h2><p id="usmsmart-manual-intro" class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Rujukan ringkas untuk menggunakan sistem dengan selamat.</p></div>
      <button type="button" onclick="closeUserManual()" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Tutup panduan" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div role="region" aria-label="Kandungan panduan pengguna" tabindex="0" class="overflow-y-auto p-5 space-y-5 text-sm text-slate-700 dark:text-zinc-300">
      <div class="rounded-xl border border-sky-200 bg-sky-50 dark:bg-sky-950/30 dark:border-sky-900 p-4"><h3 class="font-bold text-sky-900 dark:text-sky-200 mb-1"><i class="fa-solid fa-circle-info mr-1" aria-hidden="true"></i> Mula di sini</h3><p>Gunakan tab <strong>Transporter Utama</strong> untuk modul pengurusan dan <strong>Analisis Grafik</strong> untuk ringkasan serta carta. Butang <strong>Panduan</strong> di bahagian atas boleh dibuka bila-bila masa.</p></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">1. Cari dan tapis rekod pelajar</h3><ol class="list-decimal pl-5 space-y-1"><li>Buka <strong>Direktori Pelajar</strong>.</li><li>Taip kata kunci dalam kotak carian; gunakan penapis yang tersedia untuk mengecilkan hasil.</li><li>Pilih cip penapis untuk melihat keadaan tertentu. Kosongkan carian atau penapis jika hasil tiada.</li><li>Klik tajuk lajur yang boleh diisih untuk menukar susunan. Gunakan kawalan halaman untuk bergerak antara halaman.</li><li>Pilih saiz halaman yang sesuai; pilihan mungkin diingati oleh pelayar pada peranti yang sama.</li></ol></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">2. Tambah atau kemas kini rekod</h3><ul class="list-disc pl-5 space-y-1"><li>Gunakan tindakan tambah atau edit yang tersedia dalam modul berkenaan.</li><li>Semak nombor matrik/kad dan butiran pelajar sebelum menyimpan untuk mengelakkan rekod pendua atau kesilapan.</li><li>Jika akaun anda berperanan Viewer, perubahan mungkin perlu dihantar sebagai permintaan dan diluluskan oleh Admin.</li><li>Semak mesej pengesahan atau ralat selepas setiap tindakan; jangan anggap perubahan berjaya jika tiada pengesahan.</li></ul></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">3. Pilihan pukal dan eksport</h3><ul class="list-disc pl-5 space-y-1"><li>Gunakan kotak pilihan rekod untuk memilih beberapa baris.</li><li>Semak bilangan dan rekod terpilih sebelum menjalankan tindakan pukal.</li><li>Eksport CSV untuk analisis luar talian; simpan fail yang mengandungi maklumat pelajar di lokasi yang diluluskan sahaja.</li><li>Tindakan padam atau perubahan pukal boleh memberi kesan kepada banyak rekod. Baca dialog pengesahan dengan teliti.</li></ul></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">4. Analisis, tarikh luput dan sejarah</h3><ul class="list-disc pl-5 space-y-1"><li>Gunakan <strong>Analisis Grafik</strong> untuk memahami ringkasan data dan carta yang dipaparkan.</li><li>Semak amaran kad tamat tempoh atau bakal tamat tempoh dan sahkan butiran rekod sebelum mengambil tindakan.</li><li>Buka <strong>Sejarah Rekod &amp; Log Audit</strong> untuk menyemak aktiviti atau perubahan yang direkodkan.</li></ul></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">5. Akaun dan peranan</h3><ul class="list-disc pl-5 space-y-1"><li><strong>Admin</strong> mempunyai akses pengurusan tambahan seperti pengurusan akaun dan tindakan pentadbiran.</li><li><strong>Viewer</strong> mempunyai akses yang lebih terhad; gunakan aliran permintaan perubahan jika disediakan.</li><li>Gunakan <strong>Profil Saya</strong> untuk pilihan profil yang tersedia, dan log keluar apabila selesai terutama pada komputer kongsi.</li><li>Pemadaman akaun adalah kekal: taip nama pengguna untuk mengesahkan. Akaun sendiri dan Admin aktif terakhir tidak boleh dipadam.</li><li>Jangan kongsi kata laluan atau biarkan sesi log masuk tanpa pengawasan.</li></ul></div>
      <div><h3 class="font-bold text-base text-slate-900 dark:text-white mb-2">6. Pembantu AI dan penyelesaian masalah</h3><ul class="list-disc pl-5 space-y-1"><li>Pembantu AI tempatan boleh membantu menerangkan statistik agregat; semak jawapan penting dengan data sebenar kerana AI boleh tersilap.</li><li>Jika AI tidak memberi respons, pastikan Ollama berjalan dan model tempatan telah dipasang.</li><li>Jika halaman tidak dikemas kini, cuba muat semula dengan <strong>Ctrl + F5</strong>. Jika masalah berterusan, catat mesej ralat dan hubungi pentadbir.</li><li>Jangan lakukan pemulihan sandaran pada pangkalan data sebenar sebagai ujian. Uji pemulihan pada pangkalan data berasingan terlebih dahulu.</li></ul></div>
      <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/25 dark:border-amber-900 p-4"><h3 class="font-bold text-amber-900 dark:text-amber-200 mb-1"><i class="fa-solid fa-shield-halved mr-1" aria-hidden="true"></i> Amalan selamat</h3><p>Pastikan sandaran terkini sebelum perubahan besar. Hadkan akses kepada data pelajar, sahkan rekod sebelum menyimpan atau memadam, dan laporkan sebarang data yang tidak tepat kepada Admin.</p></div>
    </div>
    <div class="flex justify-end gap-2 p-4 border-t border-slate-200 dark:border-zinc-700"><button type="button" onclick="closeUserManual()" class="px-4 py-2 rounded-lg bg-sky-700 hover:bg-sky-800 text-white font-semibold">Saya faham</button></div>
  </section>
</div>
<script>
(function(){
  const backdrop=document.getElementById('usmsmart-manual-backdrop');
  const dialog=document.getElementById('usmsmart-manual-dialog');
  let previousFocus=null;
  window.openUserManual=function(){if(!backdrop)return;previousFocus=document.activeElement;backdrop.classList.remove('hidden');backdrop.classList.add('flex');document.body.style.overflow='hidden';const close=backdrop.querySelector('button[aria-label="Tutup panduan"]');if(close)close.focus();};
  window.closeUserManual=function(){if(!backdrop)return;backdrop.classList.add('hidden');backdrop.classList.remove('flex');document.body.style.overflow='';if(previousFocus&&typeof previousFocus.focus==='function')previousFocus.focus();};
  document.addEventListener('keydown',function(e){if(e.key==='Escape'&&backdrop&&!backdrop.classList.contains('hidden'))window.closeUserManual();});
  if(dialog)dialog.addEventListener('keydown',function(e){if(e.key!=='Tab')return;const items=Array.from(dialog.querySelectorAll('button,a,input,select,textarea,[tabindex]:not([tabindex="-1"])')).filter(el=>!el.disabled);if(!items.length)return;const first=items[0],last=items[items.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}});
})();
</script>
<?php endif; ?>

<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && ($action ?? '') === ''): ?>
<?php endif; ?>
















<script id="usmsmart-source-badge-v1">
(function(){
 const badge=document.getElementById('uda-source-badge');
 if(!badge)return;
 const text=badge.querySelector('.usm-src-text');
 /* kind comes from PHP, which is the only place that knows whether the failure was the connection,
    the tables, or the missing connection file. The words are Malay because the panel is. */
 const STATES={
  connected:['is-ok','Bersambung'],
  unreadable:['is-warn','Sambungan terhad'],
  unreachable:['is-bad','Tidak bersambung'],
  not_installed:['is-bad','Tiada fail sambungan']
 };
 const FALLBACK=['is-warn','Status sumber tak dikenali'];
 const CLASSES=['is-checking','is-ok','is-warn','is-bad'];
 const NAME_FALLBACK='Pangkalan data direktori';
 function show(pair,name,detail){
  const label=(name||NAME_FALLBACK)+' · '+pair[1];
  CLASSES.forEach(function(c){badge.classList.remove(c);});
  badge.classList.add(pair[0]);
  text.textContent=label;
  badge.title=(detail?detail+' · ':'')+label;
  badge.setAttribute('aria-label','Pangkalan data sumber: '+label);
 }
 document.addEventListener('usmsmart:db-status',function(e){
  const data=(e&&e.detail)||{};
  const src=(data.sources||[]).filter(function(s){return s&&s.key==='directory';})[0]||{};
  if(src.kind&&STATES[src.kind])return show(STATES[src.kind],src.dbname,src.detail);
  /* A session cached from before this build has sources but no kind, and a failed status request has
     neither: fall back to the overall verdict the header pill already trusts. */
  const state=data.overall==='ok'?'connected':(data.overall==='error'?'unreachable':'unreadable');
  show(STATES[state],src.dbname,src.detail);
 });
})();
</script>
<script id="usm-db-status-probe">
(function(){
 const pill=document.getElementById('usm-db-status'),health=document.getElementById('usm-health-launch'),
   panel=document.getElementById('usm-health-panel');
 if(!pill||!health||!panel)return;
 const label=pill.querySelector('.usm-db-label');
 const states=['ok','warning','error'];
 /* NL rather than an escape so this file stays free of backslashes the builder cannot carry. */
 const NL=String.fromCharCode(10);
 let busy=false;
 function paint(data){
  states.forEach(s=>pill.classList.remove(s));
  pill.classList.remove('is-checking');
  const state=states.includes(data.overall)?data.overall:'warning';
  pill.classList.add(state);
  label.textContent=data.label||'Status unknown';
  document.dispatchEvent(new CustomEvent('usmsmart:db-status',{detail:data}));
  const lines=(data.sources||[]).map(s=>s.name+': '+(s.status||'')+(s.detail?' - '+s.detail:''));
  lines.push('Checked '+(data.checked_at||'unknown')+(data.cached?' (cached)':''));
  pill.title=lines.join(NL);
  pill.setAttribute('aria-label','Data source status: '+(data.label||'unknown'));
 }
 async function check(force){
  if(busy)return; busy=true;
  try{
   const r=await fetch('index.php?action=db_status'+(force?'&force=1':''),{credentials:'same-origin',headers:{'Accept':'application/json'}});
   const j=await r.json();
   if(!r.ok||!j.success)throw new Error(j&&j.message?j.message:'Status request failed.');
   paint(j);
  }catch(e){
   states.forEach(s=>pill.classList.remove(s));
   pill.classList.remove('is-checking');
   pill.classList.add('error');
   label.textContent='No status';
   document.dispatchEvent(new CustomEvent('usmsmart:db-status',{detail:{overall:'error',sources:[]}}));
   pill.setAttribute('aria-label','Data source status: could not be read');
   pill.title=(e.message||'Could not read the data source status.');
  }finally{busy=false;}
 }
 /* One request per open: the pill routes through the health launcher so panel exclusivity,
    aria-expanded and the panel runChecks all still come from the launcher's own handler. */
 pill.addEventListener('click',()=>{health.click();});
 /* The pill opens a panel it does not own, so it borrows the panel's own state for its
    aria-expanded - Escape and the other launchers close it too, and only the class is trusted. */
 new MutationObserver(()=>{pill.setAttribute('aria-expanded',String(panel.classList.contains('open')));})
   .observe(panel,{attributes:true,attributeFilter:['class']});
 /* Capture phase, because the launcher adds .open before a bubble listener can see the closed panel -
    a bubble listener asked whether it was already open and so refreshed on every close instead. */
 health.addEventListener('click',()=>{if(!panel.classList.contains('open'))check(true);},true);
 check(false);
 setInterval(()=>check(false),60000);
})();
</script>
<script id="usm-panel-exclusivity">
(function(){
 const tools=[['usm-requests-launch','usm-requests-panel'],['usm-notifications-launch','usm-notifications-panel'],
   ['usm-messages-launch','usm-messages-page'],['usm-health-launch','usm-health-panel']];
 const all=tools.concat([['usmsmart-ai-launcher','usmsmart-ai-panel']]);
 const el=id=>document.getElementById(id);
 /* One floating surface at a time: two panels in the same slot overlap each other's close buttons,
    and the AI assistant sits in the bottom-right corner the Requests and Messages sheets fill. */
 document.addEventListener('click',e=>{
   const b=e.target.closest?e.target.closest('.usm-header-tools>button,#usmsmart-ai-launcher'):null;
   if(!b)return;
   all.forEach(pair=>{
     if(pair[0]===b.id)return;
     const panel=el(pair[1]);
     if(!panel||!panel.classList.contains('open'))return;
     panel.classList.remove('open');panel.setAttribute('aria-hidden','true');
     const other=el(pair[0]);
     if(other&&other.hasAttribute('aria-expanded'))other.setAttribute('aria-expanded','false');
   });
 },true);
 const sync=()=>document.body.classList.toggle('usm-panel-open',
   tools.some(pair=>{const panel=el(pair[1]);return !!panel&&panel.classList.contains('open');}));
 tools.forEach(pair=>{const panel=el(pair[1]);if(panel)new MutationObserver(sync).observe(panel,{attributes:true,attributeFilter:['class']});});
 sync();
 document.addEventListener('keydown',e=>{
   if(e.key!=='Escape')return;
   all.forEach(pair=>{
     const panel=el(pair[1]);
     if(!panel||!panel.classList.contains('open'))return;
     panel.classList.remove('open');panel.setAttribute('aria-hidden','true');
     const b=el(pair[0]);
     if(b&&b.hasAttribute('aria-expanded'))b.setAttribute('aria-expanded','false');
   });
 });
})();
</script>
<script id="usmsmart-stats-gate-v1">
(function () {
  /* Stage VF. The panels are already hidden in the markup; what made them appear was the render function
     running as soon as the screen had data. Each of those four globals is wrapped here: while the gate is shut
     the wrapper hides the panel and drops its charts instead of calling through, so nothing is fetched and
     nothing is painted. Opening a gate calls the same global, now un-wrapped by its own state, so the panel
     fills through the code that already knows how to do it - this block never computes a figure itself.
     The order matters: the button is built before the wrapper is installed, because a wrapper with no button
     would leave a panel nobody can open. If either step fails for a panel, that panel keeps the behaviour it
     shipped with. */
  'use strict';

  const PANELS = [
    { key: 'audit', panel: 'audit-stats-panel', render: 'loadAuditStats', destroy: 'destroyAuditStatsCharts',
      name: 'statistik log', hint: 'Angka log dibaca dari pelayan hanya apabila anda bukakan.' },
    { key: 'session', panel: 'uda-session-stats-panel', render: 'renderSessionHistoryStats', destroy: 'destroySessionHistoryCharts',
      name: 'statistik sesi', hint: 'Carta sesi dihantar hanya apabila anda bukakan.' },
    { key: 'sysweb', panel: 'sysweb-history-stats-panel', render: 'renderSyswebHistoryStats', destroy: 'destroySyswebHistoryCharts',
      name: 'statistik eksport', hint: 'Carta eksport Sysweb dilukis hanya apabila anda bukakan.' },
    { key: 'quality', panel: 'quality-stats-panel', render: 'renderQualityStats', destroy: 'destroyQualityStatsCharts',
      name: 'statistik kualiti data', hint: 'Carta kualiti data dilukis hanya apabila anda bukakan.' }
  ];

  const STORE = 'usmsmart_stats_gate_';
  const installed = [];

  const readState = key => {
    try { return localStorage.getItem(STORE + key) === 'open'; } catch (e) { return false; }
  };
  const writeState = (key, open) => {
    try { localStorage.setItem(STORE + key, open ? 'open' : 'closed'); } catch (e) { /* private mode: keep the gate, lose the memory */ }
  };

  // A top-level const in another classic script is a global binding, not a window property, so it has to be
  // read by name and guarded; failing open means a real admin always keeps the control.
  const isAdmin = (() => {
    try { return typeof USMSMART_IS_ADMIN === 'undefined' ? true : !!USMSMART_IS_ADMIN; } catch (e) { return true; }
  })();

  const panelOf = gate => document.getElementById(gate.panel);

  function sync(gate) {
    if (!gate.btn) return;
    const panel = panelOf(gate);
    // "Tiada" is a claim about the data, so it waits until the app has actually looked: a gate that is open but
    // whose screen has never been visited, or whose fetch is still in flight, is simply open.
    const empty = gate.open && gate.seen && !gate.loading && !!panel && panel.hidden;
    gate.btn.setAttribute('aria-expanded', gate.open ? 'true' : 'false');
    gate.btn.classList.toggle('is-empty', !!empty);
    gate.label.textContent = !gate.open ? 'Tunjuk ' + gate.name
      : empty ? 'Tiada ' + gate.name : 'Sembunyikan ' + gate.name;
  }

  function collapse(gate) {
    const panel = panelOf(gate);
    if (!panel || panel.hidden) return;
    if (typeof window[gate.destroy] === 'function') window[gate.destroy]();
    panel.hidden = true;
  }

  function wrap(gate) {
    const original = window[gate.render];
    if (typeof original !== 'function') return false;
    gate.original = original;
    const wrapped = function () {
      if (!gate.open) {
        collapse(gate); sync(gate);
        // The audit caller awaits; a resolved promise keeps that path honest when the gate is shut.
        return gate.key === 'audit' ? Promise.resolve(null) : undefined;
      }
      const out = gate.original.apply(this, arguments);
      const done = () => { gate.loading = false; gate.seen = true; sync(gate); };
      gate.loading = !!out && typeof out.then === 'function';
      if (gate.loading) out.then(done, done); else done();
      return out;
    };
    wrapped.usmStatsGate = gate.key;
    window[gate.render] = wrapped;
    return true;
  }

  function build(gate) {
    const panel = panelOf(gate);
    if (!panel || !panel.parentNode) return false;
    const bar = document.createElement('div');
    bar.className = 'usm-stats-gate';
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'usm-stats-gate-' + gate.key;
    btn.className = 'usm-stats-gate-btn';
    btn.setAttribute('aria-controls', gate.panel);
    btn.setAttribute('aria-expanded', 'false');
    btn.innerHTML = '<i class="fa-solid fa-chart-column" aria-hidden="true"></i>'
      + '<span class="usm-stats-gate-label"></span>'
      + '<i class="fa-solid fa-chevron-down usm-stats-gate-chev" aria-hidden="true"></i>';
    btn.addEventListener('click', () => set(gate, !gate.open));
    const hint = document.createElement('span');
    hint.className = 'usm-stats-gate-hint';
    hint.textContent = gate.hint;
    bar.appendChild(btn);
    bar.appendChild(hint);
    panel.parentNode.insertBefore(bar, panel);
    gate.bar = bar;
    gate.btn = btn;
    gate.label = btn.querySelector('.usm-stats-gate-label');
    new MutationObserver(() => sync(gate)).observe(panel, { attributes: true, attributeFilter: ['hidden'] });
    sync(gate);
    return true;
  }

  function set(gate, open) {
    gate.open = open;
    writeState(gate.key, open);
    if (!open) { collapse(gate); sync(gate); return; }
    // Going through the global keeps the label logic in one place: the wrapper sees the render start and, for
    // the audit panel, sees the promise settle.
    if (typeof window[gate.render] === 'function') window[gate.render]();
    sync(gate);
    if (typeof usmRevealCheck === 'function') usmRevealCheck();
  }

  PANELS.forEach(gate => {
    // The log figures are admin-only on the server, so a viewer would be offered a button that cannot work.
    if (gate.key === 'audit' && !isAdmin) return;
    gate.open = readState(gate.key);
    gate.seen = false;
    gate.loading = false;
    if (!build(gate)) return;
    if (!wrap(gate)) { gate.bar.remove(); gate.btn = null; return; }
    installed.push(gate.key);
  });

  window.usmStatsGateKeys = installed.slice();
  window.usmStatsGateVersion = 'vf1';
})();
</script>
<script id="usmsmart-cheerful-charts-v1">
(function () {
    'use strict';
    /* Stage VG. Twelve hues, one per campus, with a matching set for dark mode; the bars and doughnut segments
       are washed from a translucent base up to a lighter top instead of sitting flat. Everything is derived from
       the colour each dataset already carries, so no chart builder is edited and a chart that does not use a
       plain hex is left exactly as it is. */
    if (typeof Chart === 'undefined' || !Chart.register) return;

    var LIGHT = ['#7C3AED', '#2563EB', '#06B6D4', '#14B8A6', '#22C55E', '#84CC16', '#EAB308', '#F59E0B', '#F97316', '#EF4444', '#EC4899', '#D946EF'];
    var DARK = ['#A78BFA', '#60A5FA', '#22D3EE', '#2DD4BF', '#4ADE80', '#A3E635', '#FACC15', '#FBBF24', '#FB923C', '#F87171', '#F472B6', '#E879F9'];
    var SIBLING = {};
    for (var s = 0; s < LIGHT.length; s++) { SIBLING[LIGHT[s]] = DARK[s]; SIBLING[DARK[s]] = LIGHT[s]; }
    var HEX = /^#[0-9a-fA-F]{6}([0-9a-fA-F]{2})?$/;

    function isDark() { return document.documentElement.classList.contains('dark'); }
    function seedOf(hex) { return hex.slice(0, 7).toUpperCase(); }
    // A hue stays itself in its own theme and becomes its brighter sibling in the other one, so a chart built
    // before a theme switch still reads correctly after it.
    function pairOf(hex) {
        var seed = seedOf(hex);
        var sibling = SIBLING[seed];
        if (!sibling) return seed;
        var isLightSeed = LIGHT.indexOf(seed) !== -1;
        if (isDark()) return isLightSeed ? sibling : seed;
        return isLightSeed ? seed : sibling;
    }
    function parts(hex) { var n = parseInt(hex.slice(1), 16); return [(n >> 16) & 255, (n >> 8) & 255, n & 255]; }
    function soften(hex, amount) {
        var p = parts(hex);
        return 'rgb(' + Math.round(p[0] + (255 - p[0]) * amount) + ', ' + Math.round(p[1] + (255 - p[1]) * amount) + ', ' + Math.round(p[2] + (255 - p[2]) * amount) + ')';
    }
    function fade(hex, alpha) { var p = parts(hex); return 'rgba(' + p[0] + ', ' + p[1] + ', ' + p[2] + ', ' + alpha + ')'; }
    // Returns the flat colour while the plot box is unknown - which is every resolve before the first layout.
    function wash(chart, hex) {
        var area = chart.chartArea;
        if (!area || !(area.bottom > area.top)) return hex;
        var g = chart.ctx.createLinearGradient(0, area.bottom, 0, area.top);
        g.addColorStop(0, fade(hex, .62));
        g.addColorStop(1, soften(hex, isDark() ? .12 : .06));
        return g;
    }
    function pick(seeds, ctx) {
        var at = ctx && typeof ctx.dataIndex === 'number' ? ctx.dataIndex : 0;
        return seeds[at % seeds.length];
    }
    function bgFor(chart, seeds) {
        return function (ctx) { return wash(chart, pairOf(pick(seeds, ctx))); };
    }
    function borderFor(seeds) {
        return function (ctx) { return pairOf(pick(seeds, ctx)); };
    }
    function hexList(value) {
        var list = Array.isArray(value) ? value : [value];
        if (!list.length) return null;
        for (var i = 0; i < list.length; i++) if (typeof list[i] !== 'string' || !HEX.test(list[i])) return null;
        return list;
    }

    var PLUGIN = {
        id: 'usmsmart-cheerful-charts-v1',
        beforeUpdate: function (chart) {
            var type = chart.config && chart.config.type;
            if (type !== 'bar' && type !== 'doughnut') return;
            var sets = (chart.data && chart.data.datasets) || [];
            for (var d = 0; d < sets.length; d++) {
                var ds = sets[d];
                if (ds.__usmCheer) continue;
                var seeds = hexList(ds.backgroundColor);
                if (!seeds) { ds.__usmCheer = 1; continue; }
                var flat = seeds.map(seedOf);
                ds.__usmCheer = flat;
                ds.backgroundColor = bgFor(chart, flat);
                // Only outlines that already carry a palette hue follow the theme; a card-coloured ring stays.
                var borders = hexList(ds.borderColor);
                if (borders && borders.every(function (b) { return !!SIBLING[seedOf(b)]; })) {
                    ds.borderColor = borderFor(borders.map(seedOf));
                }
            }
        },
        // The legend resolves the same scriptable option, and a wash anchored to the plot box clamps to its
        // translucent stop down there, so every swatch would be the paler end of its own hue.
        beforeDraw: function (chart) {
            var items = chart.legend && chart.legend.legendItems;
            if (!items || !items.length) return;
            var sets = (chart.data && chart.data.datasets) || [];
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                var ds = sets[typeof it.datasetIndex === 'number' ? it.datasetIndex : 0];
                var seeds = ds && ds.__usmCheer;
                if (!seeds || seeds === 1) continue;
                it.fillStyle = pairOf(seeds[(typeof it.index === 'number' ? it.index : 0) % seeds.length]);
            }
        }
    };
    Chart.register(PLUGIN);

    // ANALYTICS_COLORS is a top-level const, so it cannot be reassigned, but the array itself is open.
    function applyPalette() {
        try {
            if (typeof ANALYTICS_COLORS === 'undefined' || !Array.isArray(ANALYTICS_COLORS)) return;
            var list = isDark() ? DARK : LIGHT;
            ANALYTICS_COLORS.splice(0, ANALYTICS_COLORS.length);
            for (var i = 0; i < list.length; i++) ANALYTICS_COLORS.push(list[i]);
        } catch (e) { }
    }
    function refresh() {
        applyPalette();
        var inst = Chart.instances || {};
        Object.keys(inst).forEach(function (key) { try { inst[key].update('none'); } catch (e) { } });
    }
    refresh();
    // The theme switch only flips a class, it never re-renders a chart, so without this the colours would keep
    // the palette of whichever theme the page was drawn in.
    new MutationObserver(refresh).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    window.usmCheerfulChartsVersion = 'vg1';
})();
</script>
</body>
</html>
