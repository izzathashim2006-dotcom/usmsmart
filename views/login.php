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