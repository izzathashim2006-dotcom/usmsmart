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

    <title>USMSmart — Set Semula Kata Laluan</title>
    <link rel="preload" as="image" href="usmsmart_login_bg.jpg" fetchpriority="high">
    <link rel="preload" as="font" type="font/woff2" href="assets/vendor/fonts/plus-jakarta-sans-latin.woff2" crossorigin>
    <link rel="stylesheet" href="assets/vendor/fonts.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/fonts.css'); ?>">
    <link rel="stylesheet" href="assets/vendor/font-awesome.css?v=<?php echo @filemtime(__DIR__ . '/../assets/vendor/font-awesome.css'); ?>">
    <link rel="stylesheet" href="assets/usmsmart-login.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-login.css'); ?>">
    <link rel="stylesheet" href="assets/usmsmart-i18n.css?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.css'); ?>">

    <script id="usmsmart-reset-theme">
    /* Same pre-paint theme read as the sign-in page: a stored dark choice never flashes white. */
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
<script id="usmsmart-reset-theme-toggle">
  (function () {
    const btn = document.getElementById('themeToggle');
    const root = document.documentElement;
    const sync = function () {
      const dark = root.classList.contains('dark');
      btn.textContent = dark ? '☀' : '☾';
      btn.setAttribute('aria-label', dark ? 'Tukar kepada mod cerah' : 'Tukar kepada mod gelap');
      btn.setAttribute('title', dark ? 'Mod cerah' : 'Mod gelap');
    };
    sync();
    btn.addEventListener('click', () => {
      root.classList.toggle('dark');
      localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
      sync();
    });
  })();
</script>
    <div class="topbar" aria-hidden="true"></div>

    <main class="reset-shell">
        <a class="reset-brand" href="index.php?action=login_page" aria-label="Kembali ke halaman log masuk USMSmart">
            <span class="brand-mark">USM</span>
            <span><span class="brand-name">USMSmart</span><span class="brand-sub">Kampus Kejuruteraan UDA</span></span>
        </a>

        <?php if ($resetMode === 'request'): ?>
        <section class="card reset-card" aria-labelledby="resetTitle">
            <!-- Reset Method Tabs -->
            <div class="reset-method-tabs" style="display:flex;background:rgba(241,245,249,0.8);border-radius:12px;padding:4px;gap:4px;margin-bottom:1.5rem">
                <button type="button" id="tabOtpMethod" class="reset-method-tab is-active" style="flex:1;padding:8px 12px;border:none;background:#fff;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;box-shadow:0 1px 3px rgba(0,0,0,0.06)">
                    <svg style="width:14px;height:14px;flex:0 0 auto" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg> OTP Gmail
                </button>
                <button type="button" id="tabLinkMethod" class="reset-method-tab" style="flex:1;padding:8px 12px;border:none;background:transparent;border-radius:8px;font-weight:600;font-size:12px;color:#64748b;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px">
                    <i class="fa-solid fa-link"></i> Pautan Emel
                </button>
            </div>

            <!-- OTP Method Step 1: Request OTP -->
            <div id="otpStep1">
                <div class="card-icon" style="background:#fee2e2;color:#dc2626"><svg style="width:20px;height:20px" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg></div>
                <h2 id="resetTitle">Kod OTP Gmail</h2>
                <p class="card-desc">Masukkan <strong>nama pengguna</strong> atau <strong>alamat Gmail</strong> yang telah dipautkan ke akaun anda untuk menerima kod OTP 6-digit.</p>
                <form id="otpRequestForm" autocomplete="on">
                    <label class="field">Nama pengguna atau Alamat Gmail
                        <input name="username" id="otpUsernameInput" required maxlength="190" autocomplete="username" placeholder="cth: administrator atau nama@gmail.com" value="<?php echo htmlspecialchars((string)($resetPrefillUsername ?? ''), ENT_QUOTES, 'UTF-8'); ?>" autofocus>
                    </label>
                    <button class="submit" id="otpRequestBtn" type="submit" style="background:#dc2626"><i class="fa-solid fa-paper-plane"></i><span>Hantar Kod OTP</span></button>
                </form>
            </div>

            <!-- OTP Method Step 2: Verify OTP -->
            <div id="otpStep2" hidden>
                <div class="card-icon" style="background:#fef3c7;color:#d97706"><i class="fa-solid fa-shield-halved"></i></div>
                <h2>Masukkan Kod OTP</h2>
                <p id="otpSentDesc" class="card-desc">Kod pengesahan 6 digit telah dihantar ke Gmail anda. Sahkan sebelum kod tamat tempoh.</p>
                <form id="otpVerifyForm" autocomplete="off">
                    <input type="hidden" name="user_id" id="otpUserIdInput">
                    <label class="field">Kod OTP 6 Digit
                        <input name="otp" id="otpCodeInput" required minlength="6" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" style="font-size:1.4rem;letter-spacing:6px;text-align:center;font-weight:700">
                    </label>
                    <button class="submit" id="otpVerifyBtn" type="submit" style="background:#059669"><i class="fa-solid fa-check"></i><span>Sahkan & Tetapkan Kata Laluan</span></button>
                    <button type="button" id="otpResendBtn" style="margin-top:8px;background:none;border:none;color:#6366f1;font-size:12px;font-weight:600;cursor:pointer;width:100%;text-align:center">Hantar semula kod OTP</button>
                </form>
            </div>

            <!-- Link Method (Original) -->
            <div id="resetRequestIntro" hidden>
                <div class="card-icon"><i class="fa-solid fa-key"></i></div>
                <h2>Pautan Set Semula</h2>
                <p class="card-desc">Masukkan nama pengguna akaun anda. Pautan set semula sekali guna akan dihantar ke e-mel berdaftar, atau disediakan oleh pentadbir jika akaun tiada e-mel.</p>
                <form id="resetRequestForm" autocomplete="on">
                    <label class="field">Nama pengguna
                        <input name="username" required minlength="3" maxlength="80" pattern="[A-Za-z0-9_.\-]{3,80}" autocomplete="username" placeholder="Masukkan nama pengguna" value="<?php echo htmlspecialchars((string)($resetPrefillUsername ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    </label>
                    <button class="submit" id="resetRequestBtn" type="submit"><i class="fa-solid fa-paper-plane"></i><span>Hantar permintaan</span></button>
                </form>
            </div>

            <div id="resetRequestDone" class="reset-done" hidden>
                <div class="reset-done-icon"><i class="fa-solid fa-check"></i></div>
                <h2>Permintaan direkodkan</h2>
                <p id="resetRequestDoneMsg" class="card-desc"></p>
                <ul class="reset-next">
                    <li><i class="fa-solid fa-envelope"></i><span>Semak peti masuk e-mel berdaftar anda, termasuk folder spam.</span></li>
                    <li><i class="fa-solid fa-user-shield"></i><span>Tiada e-mel? Pentadbir boleh menjana pautan dari halaman Pengurusan Akaun.</span></li>
                    <li><i class="fa-solid fa-clock"></i><span>Pautan sah selama 30 minit dan hanya boleh digunakan sekali.</span></li>
                </ul>
                <a class="submit reset-done-btn" href="index.php?action=login_page"><i class="fa-solid fa-arrow-left"></i><span>Kembali ke log masuk</span></a>
            </div>

            <div id="msg" role="status" aria-live="polite"></div>
            <div class="reset-back-row"><a class="reset-back" href="index.php?action=login_page"><i class="fa-solid fa-arrow-left"></i>Kembali ke log masuk</a></div>
            <div class="security-note"><i class="fa-solid fa-shield-halved"></i><span>Atas sebab keselamatan, jangan kongsi pautan atau kod OTP anda dengan sesiapa.</span></div>
        </section>

        <?php elseif ($resetMode === 'reset'): ?>
        <section class="card reset-card" aria-labelledby="resetTitle">
            <div id="resetSetIntro">
                <div class="card-icon"><i class="fa-solid fa-lock-open"></i></div>
                <h2 id="resetTitle">Tetapkan kata laluan baharu</h2>
                <p class="card-desc">Pautan ini sah untuk akaun di bawah. Pilih kata laluan baharu yang unik dan tidak digunakan di tempat lain.</p>
                <div class="reset-account">
                    <span class="reset-account-avatar"><i class="fa-solid fa-user"></i></span>
                    <span class="reset-account-name"><?php echo htmlspecialchars((string)$resetUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="reset-expiry" id="resetExpiryChip" title="Masa tinggal sebelum pautan tamat"><i class="fa-solid fa-hourglass-half"></i><span id="resetCountdown">30:00</span></span>
                </div>
                <form id="resetSetForm" autocomplete="off">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars((string)$resetToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <label class="field">Kata laluan baharu
                        <div class="password-wrap"><input id="newPassword" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="Sekurang-kurangnya 12 aksara"><button class="toggle-password" type="button" data-toggle="newPassword" aria-label="Papar kata laluan" title="Papar kata laluan"><i class="fa-regular fa-eye"></i></button></div>
                    </label>
                    <div class="pw-meter" id="pwMeter" data-score="0" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <div class="pw-meter-label" id="pwMeterLabel">Kekuatan kata laluan</div>
                    <label class="field">Sahkan kata laluan baharu
                        <div class="password-wrap"><input id="confirmPassword" type="password" name="confirm_password" required minlength="12" maxlength="72" autocomplete="new-password" placeholder="Ulang kata laluan baharu"><button class="toggle-password" type="button" data-toggle="confirmPassword" aria-label="Papar kata laluan" title="Papar kata laluan"><i class="fa-regular fa-eye"></i></button></div>
                    </label>
                    <ul class="pw-rules" aria-live="polite">
                        <li id="ruleLength"><i class="fa-solid fa-circle"></i><span>12–72 aksara</span></li>
                        <li id="ruleMatch"><i class="fa-solid fa-circle"></i><span>Kedua-dua kata laluan sepadan</span></li>
                    </ul>
                    <button class="submit" id="resetSetBtn" type="submit" disabled><i class="fa-solid fa-floppy-disk"></i><span>Simpan kata laluan</span></button>
                </form>
            </div>
            <div id="resetSetDone" class="reset-done" hidden>
                <div class="reset-done-icon"><i class="fa-solid fa-check"></i></div>
                <h2>Kata laluan berjaya ditetapkan</h2>
                <p class="card-desc">Semua sesi log masuk sedia ada telah dinyahaktifkan demi keselamatan. Log masuk semula dengan kata laluan baharu anda.</p>
                <a class="submit reset-done-btn" href="index.php?action=login_page"><i class="fa-solid fa-arrow-right-to-bracket"></i><span>Log masuk</span></a>
            </div>
            <div id="msg" role="status" aria-live="polite"></div>
            <div class="security-note"><i class="fa-solid fa-shield-halved"></i><span>Pautan ini sekali guna. Selepas disimpan, pautan yang sama tidak boleh digunakan lagi.</span></div>
        </section>

        <?php else: ?>
        <section class="card reset-card" aria-labelledby="resetTitle">
            <div class="reset-done reset-invalid">
                <?php
                $invalidCopy = array(
                    'missing' => array('fa-link-slash', 'Pautan tidak sah', 'Pautan set semula ini tidak dikenali. Ia mungkin salah disalin atau telah ditukar dengan permintaan baharu.'),
                    'expired' => array('fa-hourglass-end', 'Pautan telah tamat', 'Pautan set semula hanya sah selama 30 minit. Minta pautan baharu untuk meneruskan.'),
                    'used' => array('fa-circle-check', 'Pautan telah digunakan', 'Kata laluan bagi pautan ini telah ditetapkan. Jika itu bukan anda, minta pautan baharu atau hubungi pentadbir dengan segera.'),
                    'unavailable' => array('fa-triangle-exclamation', 'Perkhidmatan tidak tersedia', 'Perkhidmatan set semula tidak dapat dicapai buat masa ini. Sila hubungi pentadbir USMSmart.'),
                );
                $copy = $invalidCopy[$resetInvalidReason ?? 'missing'] ?? $invalidCopy['missing'];
                ?>
                <div class="reset-invalid-icon"><i class="fa-solid <?php echo $copy[0]; ?>"></i></div>
                <h2 id="resetTitle"><?php echo htmlspecialchars($copy[1], ENT_QUOTES, 'UTF-8'); ?></h2>
                <p class="card-desc"><?php echo htmlspecialchars($copy[2], ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (($resetInvalidReason ?? '') !== 'unavailable'): ?>
                <a class="submit reset-done-btn" href="index.php?action=forgot_password"><i class="fa-solid fa-rotate-right"></i><span>Minta pautan baharu</span></a>
                <?php endif; ?>
                <div class="reset-back-row"><a class="reset-back" href="index.php?action=login_page"><i class="fa-solid fa-arrow-left"></i>Kembali ke log masuk</a></div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer>USMSMART <span aria-hidden="true">•</span> PORTAL PENGURUSAN KAD</footer>

    <?php if ($resetMode === 'request'): ?>
    <script>
    (function () {
        const csrf = <?php echo json_encode((string)($csrfForReset ?? '')); ?>;
        const msg = document.getElementById('msg');
        
        // Tab switching: OTP Gmail vs Link
        const tabOtp = document.getElementById('tabOtpMethod');
        const tabLink = document.getElementById('tabLinkMethod');
        const otpStep1 = document.getElementById('otpStep1');
        const otpStep2 = document.getElementById('otpStep2');
        const linkIntro = document.getElementById('resetRequestIntro');
        const done = document.getElementById('resetRequestDone');
        
        function switchMethod(method) {
            msg.classList.remove('visible');
            if (method === 'otp') {
                tabOtp.style.background = '#fff';
                tabOtp.style.color = '#0f172a';
                tabOtp.style.boxShadow = '0 1px 3px rgba(0,0,0,0.06)';
                tabLink.style.background = 'transparent';
                tabLink.style.color = '#64748b';
                tabLink.style.boxShadow = 'none';
                otpStep1.hidden = false;
                otpStep2.hidden = true;
                linkIntro.hidden = true;
            } else {
                tabLink.style.background = '#fff';
                tabLink.style.color = '#0f172a';
                tabLink.style.boxShadow = '0 1px 3px rgba(0,0,0,0.06)';
                tabOtp.style.background = 'transparent';
                tabOtp.style.color = '#64748b';
                tabOtp.style.boxShadow = 'none';
                linkIntro.hidden = false;
                otpStep1.hidden = true;
                otpStep2.hidden = true;
            }
        }
        tabOtp.addEventListener('click', () => switchMethod('otp'));
        tabLink.addEventListener('click', () => switchMethod('link'));

        // OTP Step 1 Form
        const otpReqForm = document.getElementById('otpRequestForm');
        const otpReqBtn = document.getElementById('otpRequestBtn');
        const otpUserIdInput = document.getElementById('otpUserIdInput');
        const otpSentDesc = document.getElementById('otpSentDesc');
        
        otpReqForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            msg.classList.remove('visible');
            otpReqBtn.disabled = true;
            otpReqBtn.querySelector('span').textContent = 'Menghantar OTP…';
            try {
                const res = await fetch('index.php?action=forgot_password_otp', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: new FormData(otpReqForm)
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Penghantaran OTP gagal.');
                otpUserIdInput.value = data.user_id;
                otpSentDesc.textContent = 'Kod OTP 6 digit telah dihantar ke akaun Gmail ' + (data.masked_email || '') + '. Sila semak peti masuk anda.';
                otpStep1.hidden = true;
                otpStep2.hidden = false;
                document.getElementById('otpCodeInput').focus();
            } catch (err) {
                msg.textContent = err.message || 'Ralat penghantaran OTP.';
                msg.classList.add('visible');
            } finally {
                otpReqBtn.disabled = false;
                otpReqBtn.querySelector('span').textContent = 'Hantar Kod OTP';
            }
        });

        // OTP Step 2 Form (Verify)
        const otpVerForm = document.getElementById('otpVerifyForm');
        const otpVerBtn = document.getElementById('otpVerifyBtn');
        otpVerForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            msg.classList.remove('visible');
            otpVerBtn.disabled = true;
            otpVerBtn.querySelector('span').textContent = 'Mengesahkan…';
            try {
                const res = await fetch('index.php?action=verify_otp', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: new FormData(otpVerForm)
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Pengesahan OTP gagal.');
                // Redirect directly to the reset password form using the issued reset token
                location.href = 'index.php?action=reset_password&token=' + encodeURIComponent(data.reset_token);
            } catch (err) {
                msg.textContent = err.message || 'Kod OTP tidak sah.';
                msg.classList.add('visible');
                otpVerBtn.disabled = false;
                otpVerBtn.querySelector('span').textContent = 'Sahkan & Tetapkan Kata Laluan';
            }
        });

        document.getElementById('otpResendBtn').addEventListener('click', function () {
            otpStep2.hidden = true;
            otpStep1.hidden = false;
            document.getElementById('otpCodeInput').value = '';
        });

        // Link Form (Original)
        const linkForm = document.getElementById('resetRequestForm');
        const linkBtn = document.getElementById('resetRequestBtn');
        const doneMsg = document.getElementById('resetRequestDoneMsg');
        linkForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            msg.classList.remove('visible');
            linkBtn.disabled = true;
            linkBtn.querySelector('span').textContent = 'Menghantar…';
            try {
                const response = await fetch('index.php?action=forgot_password', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: new FormData(linkForm)
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Permintaan gagal. Sila cuba lagi.');
                doneMsg.textContent = result.message || 'Permintaan set semula telah direkodkan.';
                linkIntro.hidden = true;
                tabOtp.parentElement.hidden = true;
                done.hidden = false;
            } catch (error) {
                msg.textContent = error.message || 'Tidak dapat menghubungi pelayan. Sila cuba lagi.';
                msg.classList.add('visible');
                linkBtn.disabled = false;
                linkBtn.querySelector('span').textContent = 'Hantar permintaan';
            }
        });
    })();
    </script>
    <?php endif; ?>

    <?php if ($resetMode === 'reset'): ?>
    <script>
    (function () {
        const form = document.getElementById('resetSetForm');
        const intro = document.getElementById('resetSetIntro');
        const done = document.getElementById('resetSetDone');
        const msg = document.getElementById('msg');
        const btn = document.getElementById('resetSetBtn');
        const pass = document.getElementById('newPassword');
        const confirm = document.getElementById('confirmPassword');
        const meter = document.getElementById('pwMeter');
        const meterLabel = document.getElementById('pwMeterLabel');
        const ruleLength = document.getElementById('ruleLength');
        const ruleMatch = document.getElementById('ruleMatch');
        const csrf = <?php echo json_encode((string)($csrfForReset ?? '')); ?>;
        // Absolute epoch ms: the stored DATETIME is in the PHP server timezone, so it must
        // not be handed to new Date() as a bare string (the browser would read it as local time).
        const expiresAt = new Date(<?php echo (int)(strtotime((string)$resetExpiresAt) * 1000); ?>);
        let expired = false;

        document.querySelectorAll('.toggle-password').forEach(function (toggle) {
            toggle.addEventListener('click', function () {
                const input = document.getElementById(toggle.getAttribute('data-toggle'));
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                toggle.querySelector('i').className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
                toggle.setAttribute('aria-label', show ? 'Sembunyikan kata laluan' : 'Papar kata laluan');
            });
        });

        function strength(value) {
            if (!value) return 0;
            let score = 0;
            if (value.length >= 12) score++;
            if (value.length >= 16) score++;
            if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
            if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) score++;
            return Math.max(1, Math.min(4, score));
        }
        const labels = ['Kekuatan kata laluan', 'Lemah — tambah panjang dan variasi', 'Sederhana — tambah variasi aksara', 'Kuat', 'Cemerlang — kata laluan yang kukuh'];
        function paint() {
            const value = pass.value;
            const okLength = value.length >= 12 && value.length <= 72;
            const okMatch = confirm.value.length > 0 && value === confirm.value;
            ruleLength.classList.toggle('ok', okLength);
            ruleMatch.classList.toggle('ok', okMatch);
            const score = strength(value);
            meter.setAttribute('data-score', String(score));
            meterLabel.textContent = labels[score];
            meterLabel.setAttribute('data-score', String(score));
            btn.disabled = expired || !okLength || !okMatch;
        }
        pass.addEventListener('input', paint);
        confirm.addEventListener('input', paint);
        paint();

        // The countdown mirrors the server's 30-minute window; when it reaches zero the
        // form locks client-side and the server independently rejects the late submit.
        const countdown = document.getElementById('resetCountdown');
        const chip = document.getElementById('resetExpiryChip');
        function tick() {
            const remaining = Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000));
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            countdown.textContent = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
            if (remaining <= 300) chip.classList.add('is-low');
            if (remaining <= 0 && !expired) {
                expired = true;
                chip.classList.add('is-low');
                msg.innerHTML = 'Pautan telah tamat. <a href="index.php?action=forgot_password">Minta pautan baharu</a> untuk meneruskan.';
                msg.classList.add('visible');
                paint();
            }
        }
        tick();
        window.setInterval(tick, 1000);

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (btn.disabled) return;
            msg.classList.remove('visible');
            btn.disabled = true;
            btn.querySelector('span').textContent = 'Menyimpan…';
            try {
                const response = await fetch('index.php?action=reset_password', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    body: new FormData(form)
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    if (result.invalid_token) {
                        msg.innerHTML = escapeText(result.message || 'Pautan tidak sah.') + ' <a href="index.php?action=forgot_password">Minta pautan baharu</a>.';
                        msg.classList.add('visible');
                        expired = true;
                        paint();
                        btn.querySelector('span').textContent = 'Simpan kata laluan';
                        return;
                    }
                    throw new Error(result.message || 'Permintaan gagal. Sila cuba lagi.');
                }
                intro.hidden = true;
                done.hidden = false;
            } catch (error) {
                msg.textContent = error.message || 'Tidak dapat menghubungi pelayan. Sila cuba lagi.';
                msg.classList.add('visible');
                btn.disabled = false;
                btn.querySelector('span').textContent = 'Simpan kata laluan';
            }
        });
        function escapeText(value) {
            const node = document.createElement('span');
            node.textContent = String(value || '');
            return node.innerHTML;
        }
    })();
    </script>
    <?php endif; ?>
    <script src="assets/usmsmart-i18n.js?v=<?php echo @filemtime(__DIR__ . '/../assets/usmsmart-i18n.js'); ?>"></script>
</body>
</html>