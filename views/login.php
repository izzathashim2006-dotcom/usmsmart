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
    <link rel="preload" as="image" href="usmsmart_login_bg.jpg" fetchpriority="high">
    <link rel="preload" as="font" type="font/woff2" href="assets/vendor/fonts/plus-jakarta-sans-latin.woff2" crossorigin>
    <link rel="stylesheet" href="assets/vendor/fonts.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/fonts.css'); ?>">
    <link rel="stylesheet" href="assets/vendor/font-awesome.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/font-awesome.css'); ?>">
        <link rel="stylesheet" href="assets/usmsmart-login.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-login.css'); ?>">

    <script id="usmsmart-login-theme">
    /* Read and applied before the body parses, so a stored dark choice never arrives on the far side
       of a painted white frame. The button it belongs to is in the body, beside its handler. */
    (function () {
        const saved = localStorage.getItem('theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (saved ? saved === 'dark' : prefersDark) document.documentElement.classList.add('dark');
    })();
    </script>





</head>
<body>
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
                    <span class="preview-foot-label">8 modul · slaid automatik</span>
                    <span class="preview-cycle"><i></i></span>
                </div>
            </div>
        </section>
        <section class="card" aria-labelledby="authTitle">
            <div class="card-icon"><i class="fa-solid <?php echo $setupMode ? 'fa-user-shield' : 'fa-arrow-right-to-bracket'; ?>"></i></div>
            <h2 id="authTitle"><?php echo $setupMode ? 'Sediakan pentadbir' : 'Selamat kembali'; ?></h2>
            <p class="card-desc"><?php echo $setupMode ? 'Cipta akaun pentadbir pertama untuk mengaktifkan portal. Gunakan kata laluan unik sekurang-kurangnya 12 aksara.' : 'Log masuk untuk meneruskan ke dashboard USMSmart. Akaun Viewer mempunyai akses baca sahaja.'; ?></p>
            <form id="authForm" autocomplete="on">
                <label class="field">Nama pengguna
                    <input name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9_.\-]{3,80}" autocomplete="username" placeholder="Masukkan nama pengguna" autofocus>
                </label>
                <label class="field">Kata laluan
                    <div class="password-wrap"><input id="authPassword" type="password" name="password" required <?php echo $setupMode ? 'minlength="12"' : ''; ?> autocomplete="<?php echo $setupMode ? 'new-password' : 'current-password'; ?>" placeholder="<?php echo $setupMode ? 'Sekurang-kurangnya 12 aksara' : 'Masukkan kata laluan'; ?>"><button class="toggle-password" type="button" id="togglePassword" aria-label="Papar kata laluan" title="Papar kata laluan"><i class="fa-regular fa-eye"></i></button></div>
                </label>
                <button class="submit" id="submitBtn" type="submit"><i class="fa-solid <?php echo $setupMode ? 'fa-user-plus' : 'fa-arrow-right-to-bracket'; ?>"></i><span><?php echo $setupMode ? 'Cipta akaun pentadbir' : 'Log masuk'; ?></span></button>
            </form>
            <?php if (!$setupMode && $usmPasskeyAvailable): ?>
            <div class="passkey-block">
                <div class="passkey-divider"><span>atau</span></div>
                <button type="button" id="passkeyLoginBtn" class="passkey-btn">
                    <i class="fa-solid fa-fingerprint" aria-hidden="true"></i><span>Log masuk dengan passkey</span>
                </button>
                <p class="passkey-hint">Guna Face ID, Windows Hello atau cap jari peranti ini. Kata laluan masih boleh digunakan jika akaun belum mempunyai passkey.</p>
            </div>
            <?php endif; ?>
            <div id="msg" role="status" aria-live="polite"></div>
            <div class="security-note"><i class="fa-solid fa-shield-halved"></i><span>Akses terhad kepada pengguna yang dibenarkan. Jangan kongsi kata laluan anda.</span></div>
        </section>
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























</body>
</html>