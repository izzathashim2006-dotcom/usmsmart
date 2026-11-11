<!doctype html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">

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

    <title>USMSmart — <?php echo $setupMode ? 'Persediaan Pentadbir' : 'Log Masuk'; ?></title>
    <meta name="description" content="Log masuk ke USMSmart — portal pengurusan kad pintar Kampus Kejuruteraan USM. Sokongan log masuk Google, passkey dan kata laluan.">
    <link rel="preload" as="image" href="usmsmart_login_bg.jpg" fetchpriority="high">
    <link rel="preload" as="font" type="font/woff2" href="assets/vendor/fonts/plus-jakarta-sans-latin.woff2" crossorigin>
    <link rel="stylesheet" href="assets/vendor/fonts.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/fonts.css'); ?>">
    <link rel="stylesheet" href="assets/vendor/font-awesome.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/font-awesome.css'); ?>">
        <link rel="stylesheet" href="assets/usmsmart-login.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-login.css'); ?>">
    <link rel="stylesheet" href="assets/usmsmart-i18n.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.css'); ?>">

    <script id="usmsmart-login-theme">
    /* Read and applied before the body parses, so a stored dark choice never arrives on the far side
       of a painted white frame. The button it belongs to is in the body, beside its handler. */
    (function () {
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (saved ? saved === 'dark' : prefersDark) document.documentElement.classList.add('dark');
    })();
    </script>





    <script>document.documentElement.classList.add('js-reveal');</script>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <script>window.USMSMART_GOOGLE_CLIENT_ID = <?php echo json_encode($usmGoogleClientId ?? ''); ?>;</script>
</head>
<body>
    <a href="#authCard" class="login-skip">Langkau ke borang log masuk</a>
    <div class="usm-login-bg" aria-hidden="true"></div>
    <button id="themeToggle" aria-label="Tukar kepada mod gelap" title="Mod gelap"
        style="position:fixed;top:1rem;right:1rem;z-index:2;width:38px;height:38px;display:grid;place-items:center;border-radius:12px;background:rgba(15,23,42,.45);border:1px solid rgba(226,232,240,.32);color:#e2e8f0;cursor:pointer;font-size:1.15rem;line-height:1">
    ☾
</button>
<script id="usmsmart-login-theme-toggle">
  (function () {
    const btn = document.getElementById('themeToggle');
    const root = document.documentElement;
    btn.addEventListener('click', () => {
      root.classList.toggle('dark');
      localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
    });
  })();
</script>
    <div class="topbar" aria-hidden="true"></div>
    <a href="index.php" aria-label="Kembali ke laman utama" title="Laman utama"
        style="position:fixed;top:10px;left:12px;z-index:2;display:grid;place-items:center;width:38px;height:38px;border-radius:12px;background:rgba(15,23,42,.45);border:1px solid rgba(226,232,240,.32);color:#e2e8f0;text-decoration:none;-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)">
        <i class="fa-solid fa-house" aria-hidden="true" style="font-size:15px"></i>
    </a>
    <main class="shell">
        <section class="intro" aria-label="Pengenalan USMSmart">
            <div class="brand">
                <div class="brand-mark">USM</div>
                <div><div class="brand-name">USMSmart</div><div class="brand-sub">Kampus Kejuruteraan UDA</div></div>
            </div>
            <div class="eyebrow"><i class="fa-solid fa-shield-halved"></i> PORTAL PENGURUSAN KAD</div>
            <h1>Pengurusan yang lebih <span>pintar.</span></h1>
            <p class="intro-copy">Satu ruang untuk mengurus rekod pelajar, pemindahan data dan aktiviti sistem dengan lebih teratur.</p>
            <div class="feature-list">
                <div class="feature"><i class="fa-solid fa-circle-check"></i><span>Rekod pelajar dan kad dalam satu tempat</span></div>
                <div class="feature"><i class="fa-solid fa-chart-line"></i><span>Analitik dan sejarah aktiviti yang jelas</span></div>
                <div class="feature"><i class="fa-solid fa-lock"></i><span>Akses mengikut peranan pengguna</span></div>
            </div>
            <div class="preview-intro">
                <h2>Kenali portal sebelum log masuk</h2>
                <ul class="preview-points">
                    <li>Rekod kad pelajar dan staf dalam satu portal</li>
                    <li>Semakan UID, analitik dan kualiti data</li>
                    <li>Penghantaran rekod ke Sysweb secara langsung</li>
                </ul>
                <p class="preview-hint">Tatal ke bawah untuk pratonton modul dan statistik langsung.</p>
            </div>
            <div class="preview reveal reveal-from-left" aria-hidden="true">
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
        </section>
        <section class="card" id="authCard" tabindex="-1" aria-labelledby="authTitle">
            <div class="card-icon"><i class="fa-solid <?php echo $setupMode ? 'fa-user-shield' : 'fa-arrow-right-to-bracket'; ?>"></i></div>
            <h2 id="authTitle"><?php echo $setupMode ? 'Sediakan pentadbir' : 'Selamat kembali'; ?></h2>
            <p class="card-desc"><?php echo $setupMode ? 'Cipta akaun pentadbir pertama untuk mengaktifkan portal. Gunakan kata laluan unik sekurang-kurangnya 12 aksara.' : 'Log masuk untuk meneruskan ke dashboard USMSmart. Akaun Viewer mempunyai akses baca sahaja.'; ?></p>
            <form id="authForm" autocomplete="on">
                <label class="field">Nama pengguna atau Gmail
                    <input name="username" required minlength="3" maxlength="190" autocomplete="username" placeholder="Masukkan nama pengguna atau Gmail" autofocus>
                </label>
                <label class="field">Kata laluan
                    <div class="password-wrap"><input id="authPassword" type="password" name="password" required <?php echo $setupMode ? 'minlength="12"' : ''; ?> autocomplete="<?php echo $setupMode ? 'new-password' : 'current-password'; ?>" placeholder="<?php echo $setupMode ? 'Sekurang-kurangnya 12 aksara' : 'Masukkan kata laluan'; ?>"><button class="toggle-password" type="button" id="togglePassword" aria-label="Papar kata laluan" title="Papar kata laluan"><i class="fa-regular fa-eye"></i></button></div>
                    <p class="caps-hint" id="capsHint" role="status" hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Caps Lock aktif — kata laluan sensitif huruf besar/kecil.</p>
                </label>
                <?php if (!$setupMode): ?>
                <div class="forgot-row"><a class="forgot-link" id="forgotPasswordLink" href="index.php?action=forgot_password">Lupa kata laluan?</a></div>
                <?php endif; ?>
                <button class="submit" id="submitBtn" type="submit"><i class="fa-solid <?php echo $setupMode ? 'fa-user-plus' : 'fa-arrow-right-to-bracket'; ?>"></i><span><?php echo $setupMode ? 'Cipta akaun pentadbir' : 'Log masuk'; ?></span></button>
            </form>
            <?php if (!$setupMode): ?>
            <div class="passkey-block">
                <div class="passkey-divider"><span>atau log masuk pantas</span></div>
                <button type="button" id="googleLoginBtn" class="passkey-btn" style="border-color:#e2e8f0;background:#ffffff;color:#1e293b;margin-bottom:8px">
                    <svg class="w-4 h-4 inline-block" style="width:16px;height:16px;vertical-align:-2px;margin-right:8px" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    <span>Log masuk dengan Akaun Google</span>
                </button>
                <?php if ($usmGoogleClientId !== ''): ?>
                <p class="passkey-hint google-admin-hint"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Akses Admin melalui Google: e-mel USM sahaja (@<?php echo htmlspecialchars(implode(', @', $usmGoogleAdminDomains), ENT_QUOTES, 'UTF-8'); ?>).</p>
                <?php endif; ?>
                <?php if ($usmPasskeyAvailable): ?>
                <button type="button" id="passkeyLoginBtn" class="passkey-btn">
                    <i class="fa-solid fa-fingerprint" aria-hidden="true"></i><span>Log masuk dengan passkey</span>
                </button>
                <p class="passkey-hint">Guna Face ID, Windows Hello atau cap jari peranti ini. Kata laluan masih boleh digunakan jika akaun belum mempunyai passkey.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div id="msg" role="status" aria-live="polite"></div>
            <div class="security-note"><i class="fa-solid fa-shield-halved"></i><span>Akses terhad kepada pengguna yang dibenarkan. Jangan kongsi kata laluan anda.</span></div>
        </section>

        <?php $usmStats = isset($usmLoginStats) && is_array($usmLoginStats) ? $usmLoginStats : array('ok' => false, 'total' => 0, 'online' => 0, 'today' => 0); ?>
        <div class="auth-stats" id="loginStats" role="group" aria-label="Statistik penggunaan"<?php echo empty($usmStats['ok']) ? ' hidden' : ''; ?>>
            <div class="auth-stat reveal reveal-from-left"><span class="auth-stat-value" data-stat="total"><?php echo number_format((int)$usmStats['total']); ?></span><span class="auth-stat-label" title="Jumlah akaun berdaftar">Pengguna</span></div>
            <div class="auth-stat reveal reveal-from-below"><span class="auth-stat-value" data-stat="online"><?php echo number_format((int)$usmStats['online']); ?></span><span class="auth-stat-label" title="Aktif dalam 5 minit terakhir"><i class="fa-solid fa-circle" aria-hidden="true"></i>Dalam talian</span></div>
            <div class="auth-stat reveal reveal-from-right"><span class="auth-stat-value" data-stat="today"><?php echo number_format((int)$usmStats['today']); ?></span><span class="auth-stat-label" title="Log masuk berjaya hari ini">Log masuk hari ini</span></div>
        </div>
        <script id="usmsmart-login-stats-poll">
        (function () {
            const strip = document.getElementById('loginStats');
            if (!strip) return;
            const cells = strip.querySelectorAll('.auth-stat-value');
            const format = new Intl.NumberFormat('en-MY');
            let busy = false;
            const paint = function (data) {
                if (!data || !data.ok) return;
                strip.hidden = false;
                cells.forEach(function (cell) {
                    const value = data[cell.getAttribute('data-stat')];
                    if (typeof value === 'number') cell.textContent = format.format(value);
                });
            };
            const tick = function () {
                if (busy || document.hidden) return;
                busy = true;
                fetch('index.php?action=login_stats', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(paint)
                    .catch(function () {})
                    .finally(function () { busy = false; });
            };
            window.setInterval(tick, 30000);
            document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
        })();
        </script>

        <script id="usmsmart-login-reveal">
        (function () {
            const targets = document.querySelectorAll('.reveal');
            if (!targets.length) return;
            const showAll = function () { targets.forEach(function (el) { el.classList.add('is-in'); }); };
            if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { showAll(); return; }
            const observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('is-in');
                    observer.unobserve(entry.target);
                });
            }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });
            targets.forEach(function (el) { observer.observe(el); });
        })();
        </script>

    </main>
    <footer>USMSMART <span aria-hidden="true">•</span> PORTAL PENGURUSAN KAD</footer>
    <script>
        const form = document.getElementById('authForm');
        const msg = document.getElementById('msg');
        const submitBtn = document.getElementById('submitBtn');
        const passwordInput = document.getElementById('authPassword');
        document.getElementById('togglePassword').addEventListener('click', () => {
            const show = passwordInput.type === 'password';
            passwordInput.type = show ? 'text' : 'password';
            document.querySelector('#togglePassword i').className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            document.getElementById('togglePassword').setAttribute('aria-label', show ? 'Sembunyikan kata laluan' : 'Papar kata laluan');
        });
        // Warn while Caps Lock is on, so a failed login is not mistaken for a wrong password.
        const capsHint = document.getElementById('capsHint');
        const paintCaps = (e) => { capsHint.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); };
        passwordInput.addEventListener('keydown', paintCaps);
        passwordInput.addEventListener('keyup', paintCaps);
        passwordInput.addEventListener('focus', paintCaps);
        passwordInput.addEventListener('blur', () => { capsHint.hidden = true; });
        // Carry an already-typed username into the reset request, so the user never types it twice.
        const forgotLink = document.getElementById('forgotPasswordLink');
        if (forgotLink) {
            forgotLink.addEventListener('click', () => {
                const username = (form.querySelector('input[name="username"]').value || '').trim();
                if (/^[A-Za-z0-9_.\-]{3,80}$/.test(username)) {
                    forgotLink.href = 'index.php?action=forgot_password&username=' + encodeURIComponent(username);
                }
            });
        }
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            msg.classList.remove('visible');
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Sila tunggu…';
            try {
                const data = new FormData(form);
                const r = await fetch('index.php?action=<?php echo $setupMode ? 'setup_admin' : 'login'; ?>', {
                    method: 'POST',
                    headers: {'X-CSRF-Token': '<?php echo $csrfForAuth; ?>'},
                    body: data
                });
                const j = await r.json();
                if (!r.ok || !j.success) throw new Error(j.message || 'Permintaan gagal. Sila cuba lagi.');
                location.href = 'index.php?usm_tab=' + encodeURIComponent(sessionStorage.getItem('usmsmart_tab_token') || '');
            } catch (err) {
                msg.textContent = err.message || 'Tidak dapat menghubungi pelayan. Sila cuba lagi.';
                msg.classList.add('visible');
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = '<?php echo $setupMode ? 'Cipta akaun pentadbir' : 'Log masuk'; ?>';
            }
        });
    </script>
    <?php if (!$setupMode && $usmPasskeyAvailable): ?>
    <script>
    /* Passkey sign-in. The username must be typed first: the server then returns only that
       account's credential ids, so the browser never offers an unrelated passkey. */
    (function () {
        const btn = document.getElementById('passkeyLoginBtn');
        if (!btn) return;
        const usernameInput = document.querySelector('#authForm input[name="username"]');
        const csrf = <?php echo json_encode((string)$_SESSION['usmsmart_csrf_token']); ?>;
        const defaultLabel = btn.querySelector('span').textContent;

        function bytesFromB64url(value) {
            const text = String(value || '').replace(/-/g, '+').replace(/_/g, '/');
            const padded = text + '='.repeat((4 - (text.length % 4)) % 4);
            const raw = atob(padded);
            const bytes = new Uint8Array(raw.length);
            for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
            return bytes.buffer;
        }
        function b64urlFromBytes(buffer) {
            const bytes = new Uint8Array(buffer);
            let raw = '';
            for (let i = 0; i < bytes.length; i++) raw += String.fromCharCode(bytes[i]);
            return btoa(raw).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
        }
        function fail(message) {
            msg.textContent = message;
            msg.classList.add('visible');
        }
        function busy(on) {
            btn.disabled = on;
            btn.querySelector('span').textContent = on ? 'Menunggu peranti…' : defaultLabel;
        }

        btn.addEventListener('click', async function () {
            msg.classList.remove('visible');
            const username = (usernameInput.value || '').trim();
            if (!username) { fail('Masukkan nama pengguna dahulu, kemudian tekan passkey.'); usernameInput.focus(); return; }
            if (!window.PublicKeyCredential || !navigator.credentials || !window.isSecureContext) {
                fail('Pelayar atau sambungan ini tidak menyokong passkey. Sila guna kata laluan.');
                return;
            }
            busy(true);
            try {
                const optionsResponse = await fetch('index.php?action=webauthn_login_options', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
                    body: JSON.stringify({username: username})
                });
                const options = await optionsResponse.json();
                if (!optionsResponse.ok || !options.success) throw new Error(options.message || 'Passkey tidak tersedia.');
                const publicKey = options.publicKey;
                publicKey.challenge = bytesFromB64url(publicKey.challenge);
                publicKey.allowCredentials = (publicKey.allowCredentials || []).map(function (item) {
                    return Object.assign({}, item, {id: bytesFromB64url(item.id)});
                });
                const assertion = await navigator.credentials.get({publicKey: publicKey});
                const verifyResponse = await fetch('index.php?action=webauthn_login_verify', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
                    body: JSON.stringify({
                        username: username,
                        credentialId: b64urlFromBytes(assertion.rawId),
                        clientDataJSON: b64urlFromBytes(assertion.response.clientDataJSON),
                        authenticatorData: b64urlFromBytes(assertion.response.authenticatorData),
                        signature: b64urlFromBytes(assertion.response.signature)
                    })
                });
                const result = await verifyResponse.json();
                if (!verifyResponse.ok || !result.success) throw new Error(result.message || 'Pengesahan passkey gagal.');
                location.href = 'index.php?usm_tab=' + encodeURIComponent(sessionStorage.getItem('usmsmart_tab_token') || '');
            } catch (error) {
                if (error && error.name === 'NotAllowedError') fail('Passkey dibatalkan atau tamat masa. Cuba lagi.');
                else if (error && error.name === 'SecurityError') fail('Passkey memerlukan sambungan HTTPS atau localhost.');
                else fail(error && error.message ? error.message : 'Passkey tidak dapat digunakan. Sila guna kata laluan.');
            } finally {
                busy(false);
            }
        });
    })();
    </script>
    <?php endif; ?>

<script>
/* Small quality-of-life enhancements; existing filters and data logic remain authoritative. */
(function () {
    function initUSMSmartEnhancements() {
        const search = document.getElementById('master-search');
        const school = document.getElementById('master-school-filter');
        const status = document.getElementById('master-status-filter');
        const sort = document.getElementById('master-sort');
        const filterGrid = search && search.closest('.grid');

        if (filterGrid && search && !document.getElementById('master-clear-filters')) {
            const actions = document.createElement('div');
            actions.className = 'flex items-end gap-2 flex-wrap';
            actions.style.gridColumn = '1 / -1';
            actions.style.justifyContent = 'space-between';
            const hint = document.createElement('span');
            hint.className = 'usm-shortcut-hint self-center';
            hint.textContent = 'Petua: tekan / untuk mencari';
            const clear = document.createElement('button');
            clear.type = 'button';
            clear.id = 'master-clear-filters';
            clear.className = 'px-3 py-2 border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 text-slate-600 dark:text-zinc-300 hover:bg-slate-50 dark:hover:bg-zinc-800 text-xs font-medium inline-flex items-center gap-2';
            clear.innerHTML = '<i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Kosongkan tapisan</span>';
            clear.addEventListener('click', function () {
                if (search) search.value = '';
                if (school) school.value = '';
                if (status) status.value = '';
                if (sort) sort.value = 'name_asc';
                if (typeof viewStudentsByExpiryStatus === 'function') viewStudentsByExpiryStatus('');
                if (typeof filterMasterTable === 'function') filterMasterTable();
                if (search) search.focus();
            });
            actions.append(hint, clear);
            filterGrid.appendChild(actions);
        }

        document.addEventListener('keydown', function (event) {
            const target = event.target;
            const typing = target && (target.matches('input, textarea, select') || target.isContentEditable);
            if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey && !event.altKey && search) {
                event.preventDefault();
                search.focus();
            }
            if (event.key === 'Escape' && target === search && search.value) {
                search.value = '';
                if (typeof filterMasterTable === 'function') filterMasterTable();
            }
        });

        const themeToggle = document.getElementById('themeToggle');
        if (themeToggle) {
            const syncThemeIcon = function () {
                const dark = document.documentElement.classList.contains('dark');
                themeToggle.textContent = dark ? '☀' : '☾';
                themeToggle.setAttribute('aria-label', dark ? 'Tukar kepada mod cerah' : 'Tukar kepada mod gelap');
                themeToggle.setAttribute('title', dark ? 'Mod cerah' : 'Mod gelap');
            };
            syncThemeIcon();
            const originalClick = themeToggle.getAttribute('data-usm-theme-bound');
            if (!originalClick) {
                themeToggle.addEventListener('click', function () { window.setTimeout(syncThemeIcon, 0); });
                themeToggle.setAttribute('data-usm-theme-bound', '1');
            }
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initUSMSmartEnhancements);
    else initUSMSmartEnhancements();
})();
</script>




<!-- USMSmart navigation polish: presentation-only overrides; existing actions and handlers remain intact. -->

<script>
(function(){
  // Give icon-only controls descriptive tooltips and accessible names where absent.
  const hints=[
    [/profile|account|user/i,'Profile and account'],[/audit|history|log/i,'Audit history'],
    [/camera|scan/i,'Camera / scanner'],[/print/i,'Print page'],[/theme|dark|moon/i,'Toggle appearance'],
    [/logout|log out|sign out/i,'Log out'],[/notification/i,'Notifications'],[/message/i,'Messages'],
    [/health|diagnostic/i,'System health'],[/backup/i,'Backup and recovery'],[/analytics|chart/i,'Analytics']
  ];
  document.querySelectorAll('button,a,[role="button"]').forEach(el=>{
    if(el.closest('#usm-health-panel,#usm-notifications-panel,#usm-requests-panel,#usm-messages-page,#usmsmart-ai-panel'))return;
    const text=(el.innerText||el.textContent||'').trim();
    const existing=(el.getAttribute('aria-label')||el.getAttribute('title')||'').trim();
    if(existing)return;
    const hay=(text+' '+Array.from(el.attributes).map(a=>a.name+' '+a.value).join(' ')).toLowerCase();
    if(text.length>0&&text.length<34&&!/[\u{1F300}-\u{1FAFF}]/u.test(text)&&!(/^[\W_]+$/.test(text)))return;
    const found=hints.find(([re])=>re.test(hay));
    if(found){el.setAttribute('title',found[1]);el.setAttribute('aria-label',found[1]);}
  });
})();
</script>


<?php if (!empty($_SESSION['user_id']) && in_array(($_SESSION['role'] ?? ''), array('admin','viewer'), true) && ($action ?? '') === ''): ?>
<?php endif; ?>























    <?php if (!$setupMode): ?>
    <script>
    (function () {
        const googleBtn = document.getElementById('googleLoginBtn');
        if (!googleBtn) return;
        const msg = document.getElementById('msg');
        const csrf = <?php echo json_encode((string)$csrfForAuth); ?>;
        const clientId = window.USMSMART_GOOGLE_CLIENT_ID || '';

        function fail(text) {
            msg.textContent = text;
            msg.classList.add('visible');
        }

        async function loginWithCredential(credential) {
            msg.classList.remove('visible');
            googleBtn.disabled = true;
            const label = googleBtn.querySelector('span');
            label.textContent = 'Mengesahkan akaun Google…';
            try {
                const fd = new FormData();
                fd.append('credential', credential);
                const res = await fetch('index.php?action=google_login', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: fd
                });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.message || 'Log masuk dengan Google gagal.');
                location.href = 'index.php?usm_tab=' + encodeURIComponent(sessionStorage.getItem('usmsmart_tab_token') || '');
            } catch (err) {
                fail(err.message || 'Gagal log masuk dengan akaun Google.');
                googleBtn.disabled = false;
                label.textContent = 'Log masuk dengan Akaun Google';
            }
        }

        if (!clientId) {
            googleBtn.addEventListener('click', function () {
                fail('Log masuk Google belum diaktifkan oleh pentadbir. Sila log masuk dengan nama pengguna dan kata laluan.');
            });
            return;
        }

        // Google Identity Services: the official button opens the device's Google
        // account chooser, so the user picks their real account instead of typing it.
        let initialized = false;
        function initGsi() {
            if (initialized) return true;
            if (!(window.google && window.google.accounts && window.google.accounts.id)) return false;
            window.google.accounts.id.initialize({
                client_id: clientId,
                callback: function (response) {
                    if (response && response.credential) loginWithCredential(response.credential);
                },
                auto_select: false,
                cancel_on_tap_outside: true,
                use_fedcm_for_prompt: true
            });
            initialized = true;
            return true;
        }

        function renderOfficialButton() {
            if (!initGsi()) return false;
            let slot = document.getElementById('google-gsi-slot');
            if (!slot) {
                slot = document.createElement('div');
                slot.id = 'google-gsi-slot';
                slot.style.cssText = 'display:flex;justify-content:center;margin-bottom:8px';
                googleBtn.parentNode.insertBefore(slot, googleBtn);
            }
            if (!slot.hasChildNodes()) {
                window.google.accounts.id.renderButton(slot, {
                    theme: 'outline', size: 'large', shape: 'pill',
                    text: 'continue_with', logo_alignment: 'left',
                    width: Math.min(400, Math.max(240, googleBtn.offsetWidth || 320))
                });
            }
            googleBtn.style.display = 'none';
            return true;
        }

        // The GSI script loads async; poll briefly, then keep the custom button as a fallback.
        if (!renderOfficialButton()) {
            let tries = 0;
            const timer = setInterval(function () {
                if (renderOfficialButton() || ++tries > 40) clearInterval(timer);
            }, 250);
        }
        googleBtn.addEventListener('click', function () {
            if (renderOfficialButton()) return;
            fail('Perkhidmatan Google tidak dapat dimuatkan. Semak sambungan internet, atau log masuk dengan kata laluan.');
        });
    })();
    </script>
    <?php endif; ?>
    <script src="assets/usmsmart-i18n.js?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.js'); ?>"></script>
</body>
</html>