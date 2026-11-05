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
<script id="usmsmart-first-paint-gate">
/* A frame of this portal is not one thing that appears; it is the markup, then the utility stylesheet
   Tailwind writes while it reads that markup, then the icon font, then the style blocks this file
   appends after the content they restyle. Shown as those land, the page stacks or jumps for a few
   dozen milliseconds before settling. So the document is held hidden until parsing is done and given
   back two frames later, once the styles have had their turn. The timer releases it whatever else
   happens: a stalled subresource must never leave an operator staring at a blank page. */
(function () {
    var root = document.documentElement;
    var released = false;
    function release() {
        if (released) return;
        released = true;
        root.style.removeProperty('visibility');
    }
    function twoFrames() {
        var next = window.requestAnimationFrame || function (fn) { setTimeout(fn, 16); };
        next(function () { next(release); });
    }
    root.style.setProperty('visibility', 'hidden', 'important');
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', twoFrames);
    else twoFrames();
    setTimeout(release, 1200);
})();
</script>
    <title>USMSmart — <?php echo $setupMode ? 'Persediaan Pentadbir' : 'Log Masuk'; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preload" as="image" href="usmsmart_login_bg.jpg" fetchpriority="high">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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