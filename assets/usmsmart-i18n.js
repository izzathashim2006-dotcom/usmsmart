/* USMSmart system-wide interface language (Malay default / English).
   Loaded before the dashboard bundle and on the public home, login and password-reset pages.
   The choice persists in localStorage only — nothing is sent to the server or the database.

   How it works:
   - Feature modules in usmsmart-dashboard.js render through window.__usmT(ms, en) and listen
     for the usm:langchange event; chrome they build carries data-usm-i18n tags (TAGS below).
   - Everything else is translated in the DOM from a captured Malay baseline: exact-match
     dictionary for labels and sentences, anchored patterns for numeric strings, and
     longest-first distinctive fragments for interpolated copy. School names, personal names,
     matric numbers, notes and other record data never match the dictionary and pass through
     untouched. Switching back to Malay restores the exact original wording.
   - Chart.js labels/tooltips and native alert/confirm/prompt dialogs are translated through
     defensive wrappers, so charts and confirmation dialogs follow the chosen language too.
   - A debounced MutationObserver re-applies English when the app rebuilds DOM from data.
   Custom usm-lang-* classes only (no Tailwind delta); styles live in usmsmart-i18n.css. */
(function () {
    'use strict';
    var KEY = 'usmsmart_lang_v1';
    var lang = 'ms';
    try { var saved = window.localStorage.getItem(KEY); if (saved === 'en' || saved === 'ms') lang = saved; } catch (e) { /* private mode */ }
    window.__usmLang = lang;
    window.__usmT = function (ms, en) { return (window.__usmLang === 'en' && en != null) ? en : ms; };

    /* Chrome built by the dashboard feature modules (saved views, recent searches, undo,
       expiry digest). Values are the English render; the Malay baseline is captured live. */
    var TAGS = {
        'expiry.title': 'Expiry digest', 'expiry.sub': 'Cards that need renewal or replacement.',
        'expiry.d7': '≤7 days', 'expiry.d30': '≤30 days', 'expiry.expired': 'Expired', 'expiry.dismiss': 'Hide summary',
        'views.label': 'Views', 'views.saved': 'Saved Views', 'views.clearRecent': 'Clear recent', 'views.save': 'Save view',
        'views.nameLabel': 'View name', 'views.cancel': 'Cancel', 'views.confirm': 'Save',
        'views.panelAria': 'Saved views and recent searches', 'views.saveAria': 'Save view', 'views.namePlaceholder': 'e.g. Expiring ≤30 days',
        'views.previewLabel': 'Will save', 'views.saveHint': 'Enter saves · Esc cancels',
        'cal.eyebrow': 'EXPIRY CALENDAR', 'cal.title': 'Student Card Expiries',
        'cal.back': 'Back to main', 'cal.prev': 'Previous month', 'cal.next': 'Next month', 'cal.today': 'Today',
        'cal.gridAria': 'Card expiry calendar',
        'cal.legPast': 'Past expiry', 'cal.legSoon': '≤ 7 days', 'cal.legFuture': 'Upcoming', 'cal.legNone': 'No records',
        'cal.statTotal': 'Expiring this month', 'cal.statExpired': 'Already expired', 'cal.statSoon': '≤ 7 days',
        'cal.hint': 'Click a marked day to filter the directory by that expiry date.',
        'cal.empty': 'No student records loaded yet.', 'cal.loading': 'Loading student records…', 'cal.emptyBtn': 'Open Directory',
        'cal.d0': 'Sun', 'cal.d1': 'Mon', 'cal.d2': 'Tue', 'cal.d3': 'Wed', 'cal.d4': 'Thu', 'cal.d5': 'Fri', 'cal.d6': 'Sat',
        'undo.sub': 'Not sent to the server yet — undo now.', 'undo.btn': 'Undo', 'undo.dismiss': 'Send now'
    };

    /* Malay → English pairs. First occurrence wins, so preferred wordings are listed first.
       Keys match the trimmed rendered text (HTML entities already resolved to glyphs). */
    var PAIRS = [
        /* ---- Shared / common labels ---- */
        ['Nama', 'Name'], ['Pelajar', 'Students'], ['Staf', 'Staff'], ['Jumlah', 'Total'],
        ['Aktif', 'Active'], ['Luput', 'Expired'], ['Berjaya', 'Success'], ['Gagal', 'Failed'],
        ['Ralat', 'Error'], ['Padam', 'Delete'], ['Simpan', 'Save'], ['Tutup', 'Close'],
        ['Batal', 'Cancel'], ['Salin', 'Copy'], ['Disalin', 'Copied'], ['Tindakan', 'Actions'],
        ['Tarikh', 'Date'], ['Pengguna', 'Users'], ['Sesi', 'Session'], ['Rekod', 'Records'],
        ['rekod', 'records'], ['isu', 'issues'], ['pelajar', 'students'], ['hari', 'days'],
        ['minggu', 'weeks'], ['bulan', 'months'], ['Tiada tarikh', 'No date'],
        ['(tanpa nama)', '(no name)'], ['Semua pusat pengajian', 'All schools'],
        ['Semua Pusat Pengajian', 'All Schools'], ['Tiada hasil.', 'No results.'],
        ['Tiada rekod', 'No records'], ['Tiada rekod sepadan', 'No matching records'],
        ['Selamat pagi', 'Good morning'], ['Selamat tengah hari', 'Good afternoon'],
        ['Selamat petang', 'Good evening'], ['Selamat malam', 'Good night'],
        ['Bahagian', 'Sections'], ['Transporter Utama', 'Main Transporter'],
        ['Analisis Grafik', 'Graphical Analysis'], ['Direktori Kad', 'Card Directory'],
        ['Kalendar Luput', 'Expiry Calendar'], ['Kalendar Luput Kad', 'Card Expiry Calendar'],
        ['Peta haba tarikh luput kad', 'Heatmap of card expiry dates'],
        ['Direktori Staf', 'Staff Directory'], ['Sejarah & Log Audit', 'History & Audit Log'],
        ['Kualiti Data', 'Data Quality'], ['Pengurusan Akaun', 'Account Management'],
        ['Profil Saya', 'My Profile'], ['Imbas Kad', 'Scan Card'],
        ['Panduan Pengguna', 'User Guide'], ['Panduan', 'Guide'], ['Tukar Tema', 'Toggle Theme'],
        ['Cetak Halaman', 'Print Page'], ['Cetak', 'Print'], ['Papan pemuka', 'Dashboard'],
        ['Statistik dan eksport', 'Statistics and exports'], ['Integrasi dan batch', 'Integration and batches'],
        ['Rekod staf tempatan', 'Local staff records'], ['Jejak rekod', 'Record trail'],
        ['Isu dan pendua', 'Issues and duplicates'], ['Pengguna dan peranan', 'Users and roles'],
        ['Nama, avatar, kata laluan, passkey', 'Name, avatar, password, passkey'],
        ['Kamera atau pengimbas', 'Camera or scanner'], ['Buku panduan dalam aplikasi', 'In-app manual'],
        ['Terang atau gelap', 'Light or dark'], ['Pencetak pelayar', 'Browser printer'],
        ['Pelajar tanpa nama', 'Unnamed student'], ['Staf tanpa nama', 'Unnamed staff member'],
        ['Tiada padanan untuk “', 'No matches for “'],
        ['Taip nama pelajar, matrik atau nama modul.', 'Type a student name, matric number or module name.'],
        ['Arahan tidak dapat dijalankan.', 'The command could not be run.'],
        ['Sah', 'Valid'], ['Perlu semakan', 'Needs review'], ['Tidak sah', 'Invalid'],
        ['Disekat', 'Blocked'], ['Sedia', 'Ready'], ['Selesai', 'Done'], ['Teruskan', 'Continue'],
        ['Kembali', 'Back'], ['Sebelumnya', 'Previous'], ['Seterusnya', 'Next'],
        ['Sebelum', 'Previous'], ['Seterus', 'Next'], ['Muat Semula', 'Reload'], ['Muat semula', 'Reload'],
        ['Eksport CSV', 'Export CSV'], ['Eksport Excel', 'Export Excel'], ['Cari...', 'Search...'],
        ['Semua Status', 'All Statuses'], ['Semua status', 'All statuses'], ['Semua', 'All'],
        ['7 hari', '7 days'], ['14 hari', '14 days'], ['30 hari', '30 days'], ['90 hari', '90 days'],
        ['0 rekod', '0 records'], ['Setiap halaman', 'Per page'],
        ['Statistik mengikut tapisan', 'Statistics by filter'],
        ['Klik satu hiris untuk menapis senarai', 'Click a slice to filter the list'],
        ['Muat turun ringkasan PDF', 'Download PDF summary'],
        ['Muat turun PNG semua carta', 'Download PNG of all charts'],
        ['Muat turun laporan Excel', 'Download Excel report'],
        ['Tamat tempoh', 'Expired'], ['Tamat Tempoh', 'Expired'], ['Tidak Aktif', 'Inactive'],
        ['Hilang', 'Lost'], ['Nyahaktif', 'Inactive'], ['Tempatan', 'Local'],
        ['Antarabangsa', 'International'], ['Belum disahkan', 'Not yet verified'],
        ['Tidak dinyatakan', 'Not specified'], ['Tidak Dinyatakan', 'Not Specified'],
        ['Dalam Proses', 'In Process'], ['Kewarganegaraan', 'Nationality'], ['Catatan', 'Notes'],
        ['Jabatan', 'Department'], ['Kategori', 'Category'], ['Jenis', 'Type'], ['Sebab', 'Reason'],
        ['Bil', 'No.'], ['Perincian', 'Details'], ['Maklumat', 'Information'],
        ['Kata Laluan', 'Password'], ['Kata laluan', 'Password'], ['Nama pengguna', 'Username'],
        ['Alamat e-mel', 'Email address'], ['Nama paparan', 'Display name'],
        ['Pautan Google', 'Google link'], ['Belum dipaut', 'Not linked yet'], ['Putuskan', 'Unlink'],
        ['Akaun dicipta', 'Account created'], ['Login terakhir', 'Last login'],
        ['Kata laluan semasa', 'Current password'], ['Kata laluan baharu', 'New password'],
        ['Minimum 12 aksara', 'Minimum 12 characters'], ['Kekuatan kata laluan', 'Password strength'],
        ['Sahkan kata laluan baharu', 'Confirm new password'],
        ['Nama peranti (pilihan)', 'Device name (optional)'],
        ['Contoh: Laptop pejabat', 'Example: Office laptop'], ['Contoh:', 'Example:'],
        ['Pilih gambar', 'Choose picture'], ['Buang gambar', 'Remove picture'],
        ['Simpan Maklumat', 'Save Information'], ['Tukar Kata Laluan', 'Change Password'],
        ['Tambah Passkey', 'Add Passkey'], ['Buang', 'Remove'], ['Mula', 'Get started'],
        ['Saya faham', 'I understand'], ['Pengesahan', 'Confirmation'],
        ['Ya, Padam Semua', 'Yes, Delete All'], ['Simpan Perubahan', 'Save Changes'],
        ['Simpan Rekod', 'Save Record'], ['Tambah Rekod', 'Add Record'],
        ['Cipta akaun', 'Create account'], ['Cipta akaun baharu', 'Create new account'],
        ['Hari ini', 'Today'], ['Bulan ini', 'This month'], ['Tahun ini', 'This year'],
        ['Kemaskini', 'Update'], ['Kemas kini', 'Update'], ['Disunting', 'Edited'],
        ['Dijana', 'Generated'], ['Baharu', 'New'], ['Lengkap', 'Complete'],
        ['Semakan', 'Check'], ['Semak', 'Review'], ['Layak', 'Eligible'], ['Berulang', 'Duplicate'],
        ['Pendua', 'Duplicate'], ['Matrik', 'Matric'], ['matrik', 'matric'],
        ['nombor kad', 'card number'], ['Nombor kad', 'Card number'], ['Kad', 'Card'],
        ['Status kad', 'Card status'], ['Status Kad', 'Card Status'],
        ['Pusat Pengajian', 'School'], ['Pusat pengajian', 'School'],
        ['Polisi Kad', 'Card Policy'], ['Polisi', 'Policy'], ['Polisi Palang', 'Turnstile Policy'],
        ['Polisi palang', 'Turnstile policy'], ['UID kad', 'Card UID'], ['UID Asal', 'Original UID'],
        ['UID asal', 'Original UID'], ['Tarikh Daftar', 'Registration Date'],
        ['Tarikh daftar', 'Registration date'], ['Tarikh Luput', 'Expiry Date'],
        ['Tarikh luput', 'Expiry date'], ['Tarikh Tamat', 'Expiry Date'],
        ['Tarikh tamat', 'Expiry date'], ['Tarikh Masuk', 'Entry Date'],
        ['No. Matrik', 'Matric No.'], ['No. Staf', 'Staff No.'], ['No. staf', 'Staff no.'],
        ['No Siri', 'Serial No.'], ['No. Siri Kad', 'Card Serial No.'],
        ['No. siri kad', 'Card serial no.'], ['Nombor siri kad', 'Card serial number'],
        ['Nama Penuh', 'Full Name'], ['Nama penuh', 'Full name'],
        ['Jabatan / Pusat', 'Department / Centre'], ['Pusat / Jabatan', 'School / Department'],
        ['Sekolah/Jabatan', 'School/Department'], ['Pusat Pengajian / Jabatan', 'School / Department'],
        ['Polisi / Status Kad', 'Policy / Card Status'], ['Status Semakan', 'Review Status'],
        ['Tarikh & Masa', 'Date & Time'], ['Tarikh & Masa Integrasi', 'Integration Date & Time'],
        ['Tamat Kad', 'Card Expiry'], ['Maklumat Sysweb', 'Sysweb Details'],
        ['Kata Laluan Sysweb', 'Sysweb Password'], ['Sysweb UID', 'Sysweb UID'],
        ['Pengenalan', 'Identifier'], ['Pengenalan (Matrik/Staf)', 'Identifier (Matric/Staff)'],
        ['Pengenalan (Matrik/ID)', 'Identifier (matric/ID)'], ['No. Pengenalan', 'ID Number'],
        ['Jenis Isu', 'Issue Type'], ['Jenis isu', 'Issue types'], ['Isu', 'Issue'],
        ['Semua jenis isu', 'All issue types'], ['Semua tindakan', 'All actions'],
        ['Semua Tindakan', 'All Actions'], ['Jenis tindakan', 'Action types'],
        ['Jenis Tindakan', 'Action Type'], ['Peranan', 'Role'], ['Tamat', 'Expiry'],
        ['Nama kosong', 'Name missing'], ['UID belum dikaitkan', 'UID not yet linked'],
        ['Tidak lengkap', 'Incomplete'], ['Integriti rekod', 'Record integrity'],
        ['Akses penuh', 'Full access'], ['Baca sahaja', 'Read only'],
        ['Akaun baharu', 'New account'], ['Peranan viewer', 'Viewer role'],
        ['Gerbang aktif', 'Gateway active'], ['Penghantaran rekod', 'Record delivery'],
        ['Gerbang Sysweb menerima rekod', 'Sysweb gateway accepted the records'],
        ['Kad aktif', 'Active cards'], ['Pelajar & Staf', 'Students & Staff'],
        ['Data & Rekod', 'Data & Records'], ['Matrik & Kad Pelajar', 'Matric Nos. & Student Cards'],
        ['No. Staf & Kad USM', 'Staff Nos. & USM Cards'],
        ['Direktori Kad Pelajar & Staf', 'Student & Staff Card Directory'],
        ['Sejarah Rekod & Log Audit', 'Record History & Audit Log'],
        ['Nama & Matrik', 'Name & Matric No.'], ['No Siri & Status', 'Serial No. & Status'],
        ['Konversi Little-Endian & Status UDA', 'Little-Endian Conversion & UDA Status'],
        ['Tarikh Luput & Status', 'Expiry Date & Status'], ['Matrik / ID', 'Matric / ID'],
        ['Kuid & Kata Laluan Sysweb', 'Sysweb KUID & Password'],
        ['Log masuk satu klik & OTP pemulihan kata laluan', 'One-click sign-in & password-recovery OTP'],
        ['Setiap rekod tepat memudahkan urusan.', 'Every accurate record makes the work easier.'],
        ['Kerja hari ini, kejayaan esok.', "Today's work, tomorrow's success."],
        ['Mulakan yakin, teruskan tekun.', 'Start with confidence, carry on with diligence.'],
        ['2 layak · 1 disekat', '2 eligible · 1 blocked'], ['128 rekod pelajar', '128 student records'],
        ['42 UID dihantar', '42 UIDs sent'], ['Eksport UDA', 'UDA Export'],
        ['Bahasa Melayu', 'Malay'], ['Kapsyen BM', 'Malay captions'], ['Data contoh', 'Sample data'],
        ['PRATONTON', 'PREVIEW'], ['Modul USMSmart', 'USMSmart Modules'],
        ['Tarik Rekod', 'Pull Records'], ['Tarik rekod', 'Pull records'],
        ['Tarik rekod pelajar dan staf terus dari pangkalan data kampus.', 'Pull student and staff records directly from the campus database.'],
        ['Rekod diterima', 'Records received'], ['Sedia untuk semakan UID', 'Ready for UID check'],
        ['Semakan UID', 'UID Check'],
        ['Sahkan UID layak, kesan pendua dan eksport senarai bersih.', 'Validate eligible UIDs, detect duplicates and export a clean list.'],
        ['Analisis Grafik', 'Graphical Analysis'],
        ['Pantau jumlah rekod, kadar layak dan taburan sekolah.', 'Monitor record counts, eligibility rate and school distribution.'],
        ['layak', 'eligible'], ['sekolah', 'schools'], ['Integrasi Sysweb', 'Sysweb Integration'],
        ['Hantar rekod ke gerbang Sysweb dan pantau statusnya.', 'Send records to the Sysweb gateway and monitor their status.'],
        ['Cari pemegang kad pelajar dan staf dengan status terkini.', 'Find student and staff cardholders with up-to-date status.'],
        ['Jejak tarikan, eksport dan perubahan akaun mengikut masa.', 'Trace pulls, exports and account changes over time.'],
        ['Urus pengguna serta peranan admin dan viewer dengan selamat.', 'Manage users and the admin and viewer roles securely.'],
        ['Kesan rekod tidak lengkap sebelum ia merebak ke sistem.', 'Detect incomplete records before they spread through the system.'],
        ['Tamat hari ini', 'Expires today'], ['Sangat tamat', 'Long expired'],
        ['Tiada rekod tamat', 'No records with expiry'], ['Diterima mulai', 'Received from'],
        ['Diterima hingga', 'Received until'], ['Penapis aktif:', 'Active filters:'],
        ['Tiada penapis — semua', 'No filters — all'], ['Kemaskini:', 'Updated:'],
        ['Tiada isu', 'No issues'], ['Tiada isu data', 'No data issues'],
        ['Sembunyi semula', 'Hide again'], ['belum dijana', 'not yet generated'],
        ['Paparkan (direkod dalam audit log)', 'Show (recorded in the audit log)'],
        ['Salin kata laluan', 'Copy password'], ['Set semula kata laluan', 'Reset password'],
        ['Sebelum ini', 'Previously'], ['Sunting butiran penuh', 'Edit full details'],
        ['Padam rekod', 'Delete record'], ['Disimpan', 'Saved'], ['Salin Sysweb UID', 'Copy Sysweb UID'],
        ['Hantar semula', 'Resend'], ['Sedang Menghantar...', 'Sending...'],
        ['Hantar Batch Ini Sekarang', 'Send This Batch Now'], ['Batch Aktif', 'Active Batch'],
        ['Tukar ke Sampel Demo', 'Switch to Demo Sample'], ['Batch Penghantaran Aktif:', 'Active Submission Batch:'],
        ['Tiada rekod dalam batch ini.', 'No records in this batch.'],
        ['Batch ini belum membezakan kategori.', 'This batch does not distinguish categories yet.'],
        ['Gagal memuatkan rekod Sysweb:', 'Failed to load Sysweb records:'],
        ['Pangkalan rekod Sysweb dikemas kini (', 'The Sysweb record database was updated ('],
        ['Eksport UDA Terkini', 'Latest UDA Export'], ['Eksport Terkini', 'Latest Export'],
        ['Rekod Pilihan', 'Selected Records'], ['Rekod Aktif', 'Active Records'],
        ['Tukar ke Rekod Contoh (Demo)', 'Switch to Sample Records (Demo)'],
        ['Tukar ke Rekod Sampel (Demo)', 'Switch to Sample Records (Demo)'],
        ['(Rekod Contoh / Demo: 2 rekod)', '(Sample / Demo Records: 2 records)'],
        ['(Daripada Eksport UDA Terkini)', '(From the Latest UDA Export)'],
        ['Kembali ke', 'Back to'], ['Tiada rekod untuk dipaparkan.', 'No records to display.'],
        ['Rekod pelajar tidak ditemui.', 'Student record not found.'],
        ['USMSmart Direktori Pelajar', 'USMSmart Student Directory'],
        ['USMSmart Direktori Pelajar (Pukal)', 'USMSmart Student Directory (Bulk)'],
        ['USMSmart Direktori Staf', 'USMSmart Staff Directory'],
        ['Memuatkan rekod pelajar ke Segmen Sysweb...', 'Loading student records into the Sysweb segment...'],
        ['Sila pilih sekurang-kurangnya satu rekod pelajar.', 'Please select at least one student record.'],
        ['Tiada rekod pelajar dipilih.', 'No student records selected.'],
        ['Tiada rekod pelajar untuk dieksport.', 'No student records to export.'],
        ['Rekod staf tidak ditemui.', 'Staff record not found.'],
        ['Memuatkan rekod staf ke Segmen Sysweb...', 'Loading staff records into the Sysweb segment...'],
        ['Tiada rekod staf untuk dieksport.', 'No staff records to export.'],
        ['Tiada rekod untuk dihantar.', 'No records to send.'],
        ['Menghantar', 'Sending'], ['Sedang diproses...', 'Processing...'],
        ['Memproses...', 'Processing...'], ['Menghubungkan ke Sysweb...', 'Connecting to Sysweb...'],
        ['Integrasi Sysweb gagal.', 'Sysweb integration failed.'], ['Penghantaran gagal', 'Submission failed'],
        ['Sebahagian selesai:', 'Partially complete:'], ['Sebahagian (200 OK)', 'Partial (200 OK)'],
        ['200 OK Sebahagian', '200 OK Partial'], ['Integrasi Sysweb selesai!', 'Sysweb integration complete!'],
        ['Selesai (200 OK)', 'Complete (200 OK)'], ['200 OK Selesai', '200 OK Complete'],
        ['Ralat Rangkaian', 'Network Error'], ['Ralat sambungan', 'Connection error'],
        ['Ralat rangkaian.', 'Network error.'], ['Ralat rangkaian:', 'Network error:'],
        ['Ralat rangkaian menghubungi Sysweb.', 'Network error contacting Sysweb.'],
        ['Ruang Kerja Pelajar', 'Student Workspace'],
        ['Rekod pelajar sahaja — carian matrik, pusat pengajian, polisi dan kad.', 'Student records only — search by matric, school, policy and card.'],
        ['Ruang Kerja Staf', 'Staff Workspace'],
        ['Rekod staf sahaja — carian nombor staf, jabatan, status dan kad.', 'Staff records only — search by staff number, department, status and card.'],
        ['Semua Rekod Sysweb', 'All Sysweb Records'],
        ['Paparan gabungan rekod pelajar dan staf.', 'Combined view of student and staff records.'],
        ['Pilih sekurang-kurangnya satu rekod.', 'Select at least one record.'],
        ['Semakan selesai:', 'Check complete:'], ['Tiada rekod dipilih.', 'No records selected.'],
        ['Pilih', 'Select'],
        ['Tiada rekod integrasi Sysweb yang sepadan dengan penapis ini.', 'No Sysweb integration records match these filters.'],
        ['Belum ada rekod disimpan dalam pangkalan integrasi Sysweb.', 'No records saved in the Sysweb integration database yet.'],
        ['Pangkalan rekod masih kosong — hantar satu batch daripada Pengangkut UDA atau tambah rekod manual.', 'The record database is still empty — send a batch from the UDA Transporter or add records manually.'],
        /* ---- dashboard.php: rail, header, hero ---- */
        ['Langkau ke kandungan utama', 'Skip to main content'],
        ['Navigasi utama', 'Main navigation'], ['Bahagian utama', 'Main sections'],
        ['Kampus Kejuruteraan UDA', 'UDA Engineering Campus'], ['Alat', 'Tools'],
        ['Panel maklumat', 'Information panel'],
        ['Cari atau jalankan arahan (Ctrl+K)', 'Search or run a command (Ctrl+K)'],
        ['Cari (Ctrl+K)', 'Search (Ctrl+K)'], ['Jenis rekod', 'Record type'],
        ['Kampus Kejuruteraan USM', 'USM Engineering Campus'], ['Selamat datang', 'Welcome'],
        ['Semoga hari anda tenang dan produktif. Akses penuh — tarikan rekod, kemas kini pukal, log audit dan eksport tersedia di rel kiri.', 'May your day be calm and productive. Full access — record pulls, bulk updates, audit logs and exports are available in the left rail.'],
        ['Semoga hari anda tenang dan produktif. Akses baca — direktori kad, statistik dan eksport tersedia di rel kiri.', 'May your day be calm and productive. Read access — the card directory, statistics and exports are available in the left rail.'],
        /* ---- Banners & alerts ---- */
        ['Pemberitahuan Status Kad Pelajar', 'Student Card Status Notification'],
        ['Terdapat pelajar yang kad akses mereka akan tamat tempoh dalam masa terdekat.', 'There are students whose access cards will expire soon.'],
        ['Lihat Pelajar Luput (30 Hari)', 'View Expiring Students (30 Days)'],
        ['Menapis: Pelajar Hampir Luput', 'Filtering: Students Expiring Soon'],
        ['Padam Tapisan', 'Clear Filter'],
        ['Amaran tamat tempoh kad', 'Card expiry warning'], ['Amaran Tamat Tempoh Kad', 'Card Expiry Warning'],
        ['Semakan tarikh luput kad.', 'Card expiry date check.'], ['Tempoh amaran', 'Warning window'],
        ['Kad tamat tempoh', 'Expired cards'], ['Tapis ➔', 'Filter ➔'],
        ['Klik untuk tapis senarai pelajar ini', 'Click to filter this student list'],
        ['Tamat dalam 7 hari', 'Expires in 7 days'], ['Tindakan segera diperlukan', 'Immediate action required'],
        ['Tamat dalam 30 hari', 'Expires in 30 days'], ['Klik untuk lihat & perbaharui', 'Click to view & renew'],
        ['Senarai menunjukkan sehingga 10 rekod paling mendesak. Klik mana-mana baris atau kad statistik untuk melihat senarai penuh dalam jadual di bawah.', 'The list shows up to the 10 most urgent records. Click any row or stat card to see the full list in the table below.'],
        ['Rekod berkemungkinan pendua dikesan', 'Possible duplicate records detected'],
        ['Semak matrik, UID dan nombor kad.', 'Check the matric no., UID and card number.'],
        ['Lihat rekod pendua', 'View duplicate records'], ['Mod ujian tempatan:', 'Local test mode:'],
        ['Tambah, kemas kini dan padam hanya mengubah jadual', 'Adding, updating and deleting only modify the table'],
        ['dalam MySQL. Jadual rasmi SQL Server', 'in MySQL. The official SQL Server table'],
        ['kekal baca sahaja.', 'remains read-only.'],
        ['Pilih rekod di Direktori Pelajar dahulu. Pilihan rekod akan dikekalkan apabila anda membuka segmen ini. Had maksimum ialah 500 rekod bagi setiap kemas kini.', 'Select records in the Student Directory first. Your selection is kept when you open this segment. The maximum is 500 records per update.'],
        ['Pautan ini dipaparkan sekali sahaja, sah selama 30 minit dan hanya boleh digunakan sekali. Serahkan kepada pengguna melalui saluran selamat; menjana pautan baharu membatalkan pautan ini.', 'This link is shown only once, is valid for 30 minutes and can be used only once. Deliver it to the user through a secure channel; generating a new link invalidates this one.'],
        ['Imbasan ini hanya membaca data dan tidak mengubah rekod. Gunakan tindakan Edit untuk membetulkan rekod; pendua perlu disemak sebelum dipadam atau digabungkan.', 'This scan only reads data and does not modify records. Use the Edit action to correct records; duplicates must be reviewed before being deleted or merged.'],
        /* ---- Home: live pull panel ---- */
        ['Sumber Kad & Eksport UDA', 'UDA Card Source & Export'],
        ['Tarik rekod pemegang kad daripada pangkalan data USM dan tapis keputusan sebelum dihantar ke Transporter UDA.', 'Pull cardholder records from the USM database and filter the results before sending them to the UDA Transporter.'],
        ['Menyemak sambungan pangkalan data sumber...', 'Checking the source database connection...'],
        ['Menyemak sumber…', 'Checking source…'], ['Tetapan tarikan', 'Pull settings'],
        ['Jenis pemegang kad', 'Cardholder type'], ['Bilangan rekod', 'Number of records'],
        ['Anda tentukan jumlah; tiada had maksimum aplikasi.', 'You decide the amount; there is no application maximum.'],
        ['Tarikh mula', 'Start date'], ['Tarikh akhir', 'End date'],
        ['Penapis rekod', 'Record filters'], ['Pilihan', 'Optional'], ['Carian pantas', 'Quick search'],
        ['Nama, matrik, no. staf atau UID', 'Name, matric no., staff no. or UID'],
        ['Sekolah / program / jabatan', 'School / programme / department'],
        ['Semua sekolah / program', 'All schools / programmes'],
        ['Rekod yang sudah wujud dalam UDA tidak dipaparkan semula. Polisi palang dan nombor siri akan disemak sebelum eksport.', 'Records that already exist in UDA are not shown again. Barrier policies and serial numbers will be checked before export.'],
        ['Belum menarik data', 'No data pulled yet'],
        ['Pilih kategori, bilangan rekod dan julat tarikh, kemudian tekan “Tarik rekod”.', 'Choose a category, record count and date range, then press “Pull records”.'],
        ['Rekod baharu', 'New records'], ['Sedia disemak', 'Ready for review'],
        ['Tiada polisi', 'No policy'], ['Semak UID / siri', 'Check UID / serial'],
        ['Semakan UID layak eksport', 'Export-eligible UID check'],
        ['Semak hanya rekod yang anda tarik dengan “Tarik rekod”, dan hanya baris StatusKad 02 daripadanya. UID mesti 8 aksara hexadecimal dan unik merentas pelajar serta staf dalam dbo.Pelajar dan dbo.Staf, termasuk rekod yang telah tamat tempoh. Baris yang disekat kerana UID berulang boleh dibuka untuk melihat kad lain yang memegang UID tersebut.', 'Only checks records you pulled with “Pull records”, and only their StatusKad 02 rows. The UID must be 8 hexadecimal characters and unique across students and staff in dbo.Pelajar and dbo.Staf, including expired records. Rows blocked for a duplicate UID can be opened to see the other cards holding that UID.'],
        ['Belum disemak. Semakan akan dijalankan pada rekod yang ditarik sebaik anda menekan “Tarik rekod”.', 'Not checked yet. The check will run on the pulled records as soon as you press “Pull records”.'],
        ['StatusKad 02 disemak', 'StatusKad 02 rows checked'], ['Layak dieksport', 'Eligible for export'],
        ['Rekod UID berulang', 'Duplicate-UID records'],
        /* ---- Home: metrics & step tables ---- */
        ['Jumlah Rekod Sesi', 'Total Session Records'], ['Status Enjin', 'Engine Status'],
        ['Taburan Mengikut Pusat Pengajian', 'Distribution by School'], ['0 Pelajar', '0 Students'],
        ['Senarai Rekod untuk Transporter UDA', 'Record List for the UDA Transporter'],
        ['Semakan Data', 'Data Check'],
        ['Pastikan semua maklumat betul sebelum simpan.', 'Make sure all information is correct before saving.'],
        ['Sahkan & Simpan ke Live DB UDA', 'Confirm & Save to UDA Live DB'],
        /* ---- Home: session history & stats ---- */
        ['Sejarah Sesi Penghantaran UDA', 'UDA Submission Session History'],
        ['Namakan sesi mengikut tujuan, tarikh atau kumpulan untuk memudahkan carian.', 'Name sessions by purpose, date or group to make searching easier.'],
        ['Tapis nama / ID / tarikh...', 'Filter by name / ID / date...'],
        ['Tapis sejarah sesi', 'Filter session history'],
        ['Statistik sesi penghantaran', 'Submission session statistics'],
        ['Memuatkan sejarah sesi...', 'Loading session history...'],
        ['Eksport statistik sesi', 'Export session statistics'],
        ['Julat hari statistik sesi', 'Session statistics day range'],
        ['Sesi dalam julat', 'Sessions in range'], ['Pelajar dihantar', 'Students submitted'],
        ['Hari berbeza', 'Distinct days'], ['Sesi tanpa nama', 'Unnamed sessions'],
        ['Campuran saiz sesi', 'Session size mix'],
        ['Klik satu hiris untuk menapis jadual', 'Click a slice to filter the table'],
        ['Sesi paling besar', 'Largest sessions'],
        ['Klik satu bar untuk menapis mengikut sesi', 'Click a bar to filter by session'],
        ['ID / Nama Sesi', 'Session ID / Name'], ['Jumlah Pelajar', 'Total Students'],
        /* ---- Home: success section ---- */
        ['Data Berjaya Disimpan ke Live Database UDA!', 'Data Successfully Saved to the UDA Live Database!'],
        ['Semua rekod telah berjaya dieksport dan diverifikasi ke dalam pangkalan data UDA.', 'All records have been successfully exported and verified into the UDA database.'],
        ['Langkah Seterusnya (Langkah 4)', 'Next Step (Step 4)'], ['Sedia Dihantar', 'Ready to Send'],
        ['Integrasi Sistem Sysweb', 'Sysweb System Integration'],
        ['Formatkan rekod yang baru dieksport dengan UID Sysweb (8 sifar + Little-Endian UID) dan hantar terus ke sistem Sysweb.', 'Format the newly exported records with the Sysweb UID (8 zeros + Little-Endian UID) and send them straight to the Sysweb system.'],
        ['Teruskan ke Sysweb (Langkah Seterusnya)', 'Continue to Sysweb (Next Step)'],
        ['Eksport UDA selesai. Klik butang di atas untuk memulakan integrasi Sysweb.', 'UDA export complete. Click the button above to start the Sysweb integration.'],
        ['Lakukan Transporter Baharu', 'Start a New Transporter'],
        /* ---- Directory: page head & toolbar ---- */
        ['Kembali ke Utama', 'Back to Home'], ['DIREKTORI KAD', 'CARD DIRECTORY'],
        ['REKOD PELAJAR', 'STUDENT RECORDS'],
        ['Direktori & Pengurusan Pelajar', 'Student Directory & Management'],
        ['Master CRUD + Sandaran / Restore DB & Cetak Kad.', 'Master CRUD + DB Backup / Restore & Card Printing.'],
        ['Lihat sejarah bila rekod pelajar dikemas kini', 'See when student records were updated'],
        ['Sejarah Rekod', 'Record History'],
        ['Muat turun backup penuh pangkalan data', 'Download a full database backup'],
        ['Sandaran DB', 'DB Backup'],
        ['Lihat dan urus sandaran pelayan automatik', 'View and manage automatic server backups'],
        ['Backup Automatik', 'Automatic Backup'],
        ['Pulihkan pangkalan data dari fail JSON', 'Restore the database from a JSON file'],
        ['Pulihkan DB', 'Restore DB'],
        ['Eksport senarai pelajar yang ditapis ke Sysweb', 'Export the filtered student list to Sysweb'],
        ['Eksport Sysweb', 'Export Sysweb'], ['Pratonton Import CSV', 'CSV Import Preview'],
        ['Semak data sebelum disimpan.', 'Review the data before saving.'],
        ['Muat turun laporan ralat', 'Download error report'],
        ['Pilih fail CSV untuk pratonton.', 'Select a CSV file to preview.'],
        ['Import baris sah', 'Import valid rows'],
        ['+ Tambah Pelajar', '+ Add Student'], ['+ Minta Tambah Pelajar', '+ Request to Add Student'],
        /* ---- Directory: quick filters, filters & sort ---- */
        ['Tapis Pantas:', 'Quick Filter:'], ['Semua Pelajar', 'All Students'],
        ['Hampir Luput (≤ 30 Hari)', 'Expiring Soon (≤ 30 Days)'],
        ['Kritikal (≤ 7 Hari)', 'Critical (≤ 7 Days)'], ['Kad Aktif', 'Active Cards'],
        ['Carian menyeluruh', 'Global search'],
        ['Cari nama, matrik, USM UID, nombor kad atau pusat pengajian', 'Search name, matric no., USM UID, card number or school'],
        ['Cari nama, matrik, UID, kad atau sekolah…', 'Search name, matric, UID, card or school…'],
        ['Tahap pendidikan', 'Education level'], ['Semua tahap', 'All levels'],
        ['Kategori pelajar', 'Student category'], ['Semua kategori', 'All categories'],
        ['Tamat dalam 14 hari', 'Expires in 14 days'], ['Susun mengikut', 'Sort by'],
        ['Nama (A–Z)', 'Name (A–Z)'], ['Nama (Z–A)', 'Name (Z–A)'],
        ['Pusat pengajian (A–Z)', 'School (A–Z)'], ['Pusat pengajian (Z–A)', 'School (Z–A)'],
        ['Tarikh daftar (terbaharu)', 'Registration date (newest)'],
        ['Tarikh daftar (terlama)', 'Registration date (oldest)'],
        ['Tarikh luput (terdekat)', 'Expiry date (soonest)'],
        ['Tarikh luput (terjauh)', 'Expiry date (latest)'],
        /* ---- Bulk toolbar & master table ---- */
        ['Pilih semua rekod yang ditapis', 'Select all filtered records'],
        ['Batal Pilihan', 'Clear Selection'], ['Eksport Terpilih (CSV)', 'Export Selected (CSV)'],
        ['Eksport rekod terpilih ke Sysweb', 'Export selected records to Sysweb'],
        ['Eksport Terpilih (Sysweb)', 'Export Selected (Sysweb)'],
        ['Kemaskini Pukal', 'Bulk Update'], ['Padam Terpilih', 'Delete Selected'],
        ['Pilih Semua di Muka Surat Ini', 'Select All on This Page'],
        ['Klik untuk susun nama', 'Click to sort by name'],
        ['Klik untuk susun pusat pengajian', 'Click to sort by school'],
        ['Klik untuk susun tarikh daftar', 'Click to sort by registration date'],
        ['Klik untuk susun tarikh luput', 'Click to sort by expiry date'],
        ['Baris:', 'Rows:'], ['Memuatkan...', 'Loading...'],
        /* ---- Staff directory ---- */
        ['REKOD STAF', 'STAFF RECORDS'], ['Pengurusan Rekod Staf', 'Staff Record Management'],
        ['Data ujian disimpan dalam MySQL tempatan, berasingan daripada rekod rasmi USM.', 'Test data is stored in local MySQL, separate from the official USM records.'],
        ['Eksport senarai staf ke Sysweb', 'Export the staff list to Sysweb'],
        ['Eksport ke Sysweb', 'Export to Sysweb'], ['Tambah Staf', 'Add Staff'],
        ['Import dari SQL Server', 'Import from SQL Server'], ['Jumlah rekod ujian', 'Total test records'],
        ['Carian staf', 'Staff search'],
        ['Cari nama, nombor staf, jabatan, UID kad, siri kad atau status…', 'Search name, staff number, department, card UID, card serial or status…'],
        ['Tambah rekod staf', 'Add staff record'],
        ['Semua medan disimpan ke pangkalan data MySQL tempatan.', 'All fields are saved to the local MySQL database.'],
        ['Tutup borang', 'Close form'], ['UID kad (heks)', 'Card UID (hex)'],
        ['Tarikh mula / diberi', 'Start / issued date'], ['Tarikh tamat kad', 'Card expiry date'],
        ['Catatan ringkas (pilihan)', 'Brief notes (optional)'],
        ['Memuatkan rekod ujian staf tempatan…', 'Loading local staff test records…'],
        ['Nama staf', 'Staff name'], ['Tarikh tamat', 'Expiry date'],
        ['Belum memuatkan data staf.', 'Staff data not loaded yet.'],
        ['Rekod pelajar dan staf berkongsi satu halaman. Data staf ialah jadual ujian MySQL tempatan; jadual rasmi', 'Student and staff records share one page. Staff data is a local MySQL test table; the official table'],
        ['kekal baca sahaja. Hanya Admin boleh membuat perubahan.', 'remains read-only. Only Admins can make changes.'],
        /* ---- User management ---- */
        ['Kembali ke dashboard', 'Back to dashboard'], ['KAWALAN AKSES', 'ACCESS CONTROL'],
        ['Pengurusan Akaun Pengguna', 'User Account Management'],
        ['Cipta akaun, semak status dan urus peranan pengguna.', 'Create accounts, check status and manage user roles.'],
        ['Jumlah akaun', 'Total accounts'], ['Akaun aktif', 'Active accounts'],
        ['Pentadbir aktif', 'Active administrators'],
        ['Permintaan set semula kata laluan', 'Password reset requests'],
        ['Pengguna yang terlupa kata laluan. Jana pautan sekali guna dan serahkan melalui saluran selamat.', 'Users who forgot their password. Generate a one-time link and deliver it through a secure channel.'],
        ['Senarai akaun', 'Account list'],
        ['Urus peranan, kata laluan, status dan pemadaman akaun.', 'Manage roles, passwords, status and account deletion.'],
        ['Cari nama atau peranan...', 'Search name or role...'],
        ['Memuatkan akaun...', 'Loading accounts...'],
        ['Memuatkan senarai akaun...', 'Loading account list...'],
        ['Kata laluan minimum 12 aksara.', 'Password must be at least 12 characters.'],
        ['contoh: staf_uda', 'e.g. staf_uda'], ['Kata laluan sementara', 'Temporary password'],
        ['Sekurang-kurangnya 12 aksara', 'At least 12 characters'],
        ['Viewer — akses baca', 'Viewer — read access'], ['Admin — akses pentadbir', 'Admin — administrator access'],
        ['Akses Admin memberi kuasa mengurus pengguna dan fungsi pentadbiran. Berikan peranan ini hanya kepada staf yang dibenarkan.', 'Admin access grants the power to manage users and administrative functions. Give this role only to authorised staff.'],
        ['Pautan set semula dijana', 'Reset link generated'], ['Pautan set semula', 'Reset link'],
        /* ---- Bulk update page ---- */
        ['Kembali ke direktori', 'Back to directory'], ['PENGURUSAN REKOD', 'RECORD MANAGEMENT'],
        ['Kemas kini beberapa rekod pelajar dalam satu tindakan.', 'Update several student records in one action.'],
        ['Jenis kemas kini', 'Update type'], ['Pilih tindakan...', 'Select an action...'],
        ['Lanjutkan tempoh sah (hari)', 'Extend validity period (days)'],
        ['Tetapkan tarikh luput', 'Set expiry date'], ['Tukar polisi', 'Change policy'],
        ['Tukar pusat pengajian', 'Change school'], ['Nilai baharu', 'New value'],
        ['Pilih jenis kemas kini dahulu', 'Choose an update type first'],
        ['Arahan dan format nilai akan dipaparkan selepas jenis kemas kini dipilih.', 'Instructions and the value format will appear once an update type is selected.'],
        ['Sahkan & Kemaskini', 'Confirm & Update'],
        /* ---- Data quality centre ---- */
        ['Pusat Kualiti Data', 'Data Quality Centre'],
        ['Kenal pasti rekod pendua, maklumat tidak lengkap, format kad tidak sah dan masalah tarikh.', 'Identify duplicate records, incomplete information, invalid card formats and date problems.'],
        ['Eksport Isu', 'Export Issues'], ['Imbas Semula', 'Rescan'],
        ['Rekod Diimbas', 'Records Scanned'], ['Rekod Terjejas', 'Affected Records'],
        ['Isu Pendua', 'Duplicate Issues'], ['Kad / UID Tidak Sah', 'Invalid Cards / UIDs'],
        ['Kad Luput', 'Expired Cards'],
        ['Cari nama, matrik atau mesej isu...', 'Search name, matric no. or issue message...'],
        ['Matrik pendua', 'Duplicate matric no.'], ['UID pendua', 'Duplicate UID'],
        ['Nombor kad pendua', 'Duplicate card number'], ['Maklumat tidak lengkap', 'Incomplete information'],
        ['UID tidak sah', 'Invalid UID'], ['Nombor kad tidak sah', 'Invalid card number'],
        ['Tarikh tidak sah', 'Invalid date'], ['Julat tarikh tidak sah', 'Invalid date range'],
        ['Kad telah luput', 'Card has expired'],
        ['Tekan Imbas Semula untuk menganalisis rekod.', 'Press Rescan to analyse the records.'],
        ['Jalankan imbasan untuk memaparkan statistik.', 'Run a scan to display statistics.'],
        ['Eksport statistik kualiti data', 'Export data quality statistics'],
        ['Julat bulan carta', 'Chart month range'],
        ['12 bulan', '12 months'], ['24 bulan', '24 months'], ['36 bulan', '36 months'],
        ['Isu dalam tapisan', 'Issues in filter'], ['Rekod terjejas', 'Affected records'],
        ['Pusat terjejas', 'Affected schools'], ['Campuran jenis isu', 'Issue type mix'],
        ['Pusat pengajian paling banyak isu', 'Schools with the most issues'],
        ['Klik satu bar untuk menapis mengikut pusat pengajian', 'Click a bar to filter by school'],
        ['Tekan “Imbas Semula” untuk memulakan semakan.', 'Press “Rescan” to start the check.'],
        ['SEMAKAN REKOD PENDUA', 'DUPLICATE RECORD CHECK'], ['Perbandingan rekod', 'Record comparison'],
        ['Bandingkan rekod asal dengan rekod yang mempunyai nilai sepadan.', 'Compare the original record with records that have matching values.'],
        ['Tutup pratonton', 'Close preview'],
        /* ---- Audit history & log ---- */
        ['AUDIT TRAIL & LOG AKTIVITI', 'AUDIT TRAIL & ACTIVITY LOG'],
        ['Sejarah Rekod & Aktiviti Sistem', 'Record History & System Activity'],
        ['Jejak lengkap setiap kemas kini rekod pelajar, pendaftaran, pemadaman dan aktiviti keselamatan.', 'A complete trail of every student record update, registration, deletion and security activity.'],
        ['Jumlah Peristiwa', 'Total Events'], ['Papar semua aktiviti →', 'Show all activity →'],
        ['Kemas Kini Rekod', 'Record Updates'], ['Klik untuk tapis kemas kini →', 'Click to filter updates →'],
        ['Pendaftaran / Tambah', 'Registrations / Additions'],
        ['Klik untuk tapis pendaftaran →', 'Click to filter registrations →'],
        ['Pemadaman Data', 'Data Deletions'], ['Klik untuk tapis pemadaman →', 'Click to filter deletions →'],
        ['Cari Rekod', 'Search Records'], ['Cari nama, matrik, ID, staf...', 'Search name, matric no., ID, staff...'],
        ['Pendaftaran / Tambah (semua)', 'Registration / Addition (all)'],
        ['Pemadaman (semua)', 'Deletion (all)'],
        ['Kemas Kini Pelajar (Update)', 'Update Student (Update)'],
        ['Tambah Pelajar Baharu (Create)', 'Add New Student (Create)'],
        ['Padam Pelajar (Delete)', 'Delete Student (Delete)'],
        ['Import Pelajar (CSV)', 'Import Students (CSV)'], ['Eksport Pelajar (CSV)', 'Export Students (CSV)'],
        ['Eksport UDA Live DB', 'UDA Live DB Export'], ['Sandaran Pangkalan Data', 'Database Backup'],
        ['Pemulihan Pangkalan Data', 'Database Restore'],
        ['Log Masuk Pengguna', 'User Login'], ['Log Keluar Pengguna', 'User Logout'],
        ['Dari Tarikh', 'From Date'], ['Hingga Tarikh', 'To Date'],
        ['Kosongkan Tapisan', 'Clear Filters'], ['Memuatkan angka…', 'Loading figures…'],
        ['Eksport statistik log audit', 'Export audit log statistics'],
        ['Julat hari carta', 'Chart day range'], ['Peristiwa dalam', 'Events in'],
        ['7 hari lepas', 'Past 7 days'], ['Pengguna aktif', 'Active users'],
        ['Campuran tindakan', 'Action mix'], ['Pengguna paling aktif', 'Most active users'],
        ['Klik satu bar untuk menapis mengikut pengguna', 'Click a bar to filter by user'],
        ['Masa & Tarikh', 'Time & Date'], ['Entiti / Matrik', 'Entity / Matric No.'],
        ['Pengguna (Actor)', 'User (Actor)'],
        ['Perincian Perubahan / Aktiviti', 'Change / Activity Details'],
        ['Memuatkan sejarah rekod...', 'Loading record history...'],
        ['Butiran aktiviti', 'Activity details'], ['Tutup butiran', 'Close details'],
        /* ---- Sysweb segment ---- */
        ['MODUL INTEGRASI SYSWEB', 'SYSWEB INTEGRATION MODULE'],
        ['Simulasi Gerbang Aktif', 'Gateway Simulation Active'],
        ['Portal & Rekod Integrasi Sysweb', 'Sysweb Integration Portal & Records'],
        ['Penghantaran dan penjejakan rekod pelajar & staf ke gerbang Sysweb USM (format', 'Submission and tracking of student & staff records to the USM Sysweb gateway (format'],
        ['Eksport tanpa kata laluan', 'Export without passwords'],
        ['Eksport dengan kata laluan — direkodkan dalam audit log', 'Export with passwords — recorded in the audit log'],
        ['Eksport CSV + Kata Laluan', 'Export CSV + Passwords'], ['Kosongkan Rekod', 'Clear Records'],
        ['Jumlah Rekod Sysweb', 'Total Sysweb Records'],
        ['Tersimpan dalam fail integrasi', 'Stored in the integration file'],
        ['Rekod Pelajar', 'Student Records'], ['Rekod Staf', 'Staff Records'],
        ['Status Gerbang Sysweb', 'Sysweb Gateway Status'], ['Simulasi Aktif', 'Simulation Active'],
        ['Belum ada transaksi', 'No transactions yet'],
        ['Pusat Semakan Data Sysweb', 'Sysweb Data Check Centre'],
        ['Semakan asas sebelum rekod digunakan dalam integrasi.', 'Basic checks before records are used in the integration.'],
        ['Menunggu data…', 'Waiting for data…'], ['Rekod lengkap', 'Complete records'],
        ['Belum semak silang dengan rekod kad.', 'Not yet cross-checked against card records.'],
        ['Semak dengan rekod kad', 'Check against card records'],
        ['Semakan merangkumi nama, pengenalan, UID, susunan byte, prefiks Sysweb, no. siri, tarikh kad dan kata laluan.', 'The check covers name, identification, UID, byte order, Sysweb prefix, serial no., card dates and password.'],
        ['Jana kata laluan yang tiada', 'Generate missing passwords'],
        ['Batch Penghantaran Aktif', 'Active Submission Batch'],
        ['rekod sedia untuk dihantar ke integrasi Sysweb.', 'records ready to be sent to the Sysweb integration.'],
        ['Tiada rekod dalam batch aktif.', 'No records in the active batch.'],
        ['Statistik eksport Sysweb', 'Sysweb export statistics'],
        ['Memuatkan sejarah eksport...', 'Loading export history...'],
        ['Eksport statistik eksport Sysweb', 'Export Sysweb export statistics'],
        ['Julat hari statistik eksport', 'Export statistics day range'],
        ['Batch dalam julat', 'Batches in range'], ['Rekod diproses', 'Records processed'],
        ['Disimpan baharu', 'Newly saved'], ['Rekod gagal', 'Failed records'],
        ['Status batch', 'Batch status'], ['Pengguna paling banyak eksport', 'Users with the most exports'],
        ['Sejarah Eksport Sysweb', 'Sysweb Export History'],
        ['Jejak batch, keputusan penghantaran dan hantar semula rekod gagal', 'Batch trail, submission results and resending of failed records'],
        ['Pangkalan Rekod Integrasi Sysweb', 'Sysweb Integration Record Database'],
        ['Semua rekod yang berjaya dihantar ke gerbang simulasi Sysweb', 'All records successfully sent to the Sysweb simulation gateway'],
        ['Tapis mengkategori kategori', 'Filter by category'],
        ['Sysweb Pelajar', 'Student Sysweb'], ['Sysweb Staf', 'Staff Sysweb'],
        ['Buka pengurusan CRUD Sysweb', 'Open Sysweb CRUD management'],
        ['Pengurusan CRUD', 'CRUD Management'], ['Carian Semua Butiran', 'Search All Details'],
        ['Nama, matrik/no staf, sekolah/jabatan, polisi, no. siri, tamat kad, UID, kata laluan, tarikh diterima…', 'Name, matric/staff no., school/department, policy, serial no., card expiry, UID, password, date received…'],
        ['Kekeslahan Kad', 'Card Validity'], ['Semua Keadaan', 'All Conditions'],
        ['Aktif (lebih 30 hari)', 'Active (more than 30 days)'],
        ['Sangat Tamat (30 hari ke bawah)', 'Expiring Very Soon (30 days or less)'],
        ['Tamat / Luput', 'Expired'], ['Tiada Rekod Tamat', 'No Expiry Record'],
        ['Diterima Mulai', 'Received From'], ['Diterima Hingga', 'Received Until'],
        ['Kosongkan Penapis', 'Clear Filters'], ['Muat semula rekod', 'Reload records'],
        ['Pilih semua hasil penapis', 'Select all filter results'], ['Nyahpilih', 'Deselect'],
        ['Semak semula', 'Re-check'], ['Eksport pilihan', 'Export selection'],
        ['Kata Laluan (12 Aksara)', 'Password (12 Characters)'],
        ['Memuatkan rekod Sysweb...', 'Loading Sysweb records...'], ['Baris/halaman:', 'Rows/page:'],
        ['Papar Log Teknikal & Respons JSON Sysweb (Pilihan Pembangun)', 'Show Technical Log & Sysweb JSON Response (Developer Option)'],
        ['Tiada respons penghantaran terkini. Tekan "Hantar Batch Ini Sekarang" untuk menguji sambungan.', 'No recent submission response. Press "Send This Batch Now" to test the connection.'],
        ['Butiran Rekod', 'Record Details'], ['Sunting Rekod Sysweb', 'Edit Sysweb Record'],
        ['Kemaskini butiran rekod yang telah dihantar ke gerbang Sysweb.', 'Update the details of a record already sent to the Sysweb gateway.'],
        ['Tutup (Esc)', 'Close (Esc)'],
        /* ---- Modals & drawers ---- */
        ['Kembali ke Direktori', 'Back to Directory'], ['Kemaskini Rekod Pelajar', 'Update Student Record'],
        ['Sila isi maklumat borang di bawah untuk disimpan ke pangkalan data.', 'Please fill in the form below to save to the database.'],
        ['Imbas Kod QR / Barcode', 'Scan QR Code / Barcode'], ['Tutup pengimbas', 'Close scanner'],
        ['Sila halakan kamera pada kod QR kad pelajar...', 'Please point the camera at the QR code on the student card...'],
        ['PROFIL PELAJAR', 'STUDENT PROFILE'], ['Butiran pelajar', 'Student details'],
        ['Tutup profil', 'Close profile'], ['Perbandingan rekod pendua', 'Duplicate record comparison'],
        ['Rekod semasa dan rekod yang mempunyai nilai sepadan dipaparkan bersebelahan.', 'The current record and records with matching values are shown side by side.'],
        ['Salin UID', 'Copy UID'], ['Salin nombor kad', 'Copy card number'],
        ['Sejarah Kad', 'Card History'],
        ['Rekod snapshot kad dan perubahan yang disimpan', 'Saved card snapshots and changes'],
        ['Memuatkan sejarah kad...', 'Loading card history...'], ['Memuatkan sejarah...', 'Loading history...'],
        ['Edit rekod', 'Edit record'],
        ['Kad Akses Pelajar', 'Student Access Card'], ['Tutup kad pelajar', 'Close student card'],
        ['Kampus Kejuruteraan Pass', 'Engineering Campus Pass'], ['Nama Pelajar', 'Student name'],
        ['UID Asal:', 'Original UID:'], ['Tarikh Daftar:', 'Registration date:'],
        ['Tarikh Luput Kad:', 'Card expiry date:'], ['Cetak Kad', 'Print Card'],
        ['Senarai Pelajar', 'Student List'], ['Tutup senarai pelajar', 'Close student list'],
        ['Adakah anda pasti mahu menyimpan dan memindahkan rekod ini ke Live Database UDA?', 'Are you sure you want to save and move this record to the UDA Live Database?'],
        ['Kemaskini Rekod Pukal', 'Bulk Update Records'], ['Padam Rekod Pukal', 'Bulk Delete Records'],
        ['Adakah anda pasti mahu memadam', 'Are you sure you want to delete'],
        ['rekod pelajar yang dipilih? Tindakan ini adalah', 'selected student records? This action is'],
        ['kekal', 'permanent'], ['dan tidak boleh dikembalikan.', 'and cannot be undone.'],
        ['Urus maklumat akaun, kata laluan dan passkey anda.', 'Manage your account information, password and passkeys.'],
        ['JPG atau PNG. Gambar dipotong dan dikecilkan dalam pelayar anda, jadi foto asal tidak pernah dihantar ke pelayan.', 'JPG or PNG. The image is cropped and resized in your browser, so the original photo is never sent to the server.'],
        ['Selepas menukar kata laluan, anda akan dilog keluar daripada semua peranti.', 'After changing your password, you will be logged out of all devices.'],
        ['Passkey membenarkan log masuk dengan Face ID, Windows Hello atau cap jari tanpa menaip kata laluan. Kunci peribadi kekal dalam peranti anda; pelayan hanya menyimpan kunci awam.', 'Passkeys let you sign in with Face ID, Windows Hello or a fingerprint without typing a password. The private key stays on your device; the server only stores the public key.'],
        ['Passkey memerlukan sambungan HTTPS (atau localhost) dan pelayar yang menyokong WebAuthn.', 'Passkeys require an HTTPS connection (or localhost) and a browser that supports WebAuthn.'],
        ['Memuatkan passkey…', 'Loading passkeys…'],
        ['Tutup alu-aluan', 'Close welcome'],
        ['Semua modul ada di rel kiri — Direktori Kad, Sysweb, laporan dan tetapan. Butang profil di atas membuka maklumat akaun, kata laluan dan passkey anda.', 'All modules are in the left rail — Card Directory, Sysweb, reports and settings. The profile button at the top opens your account information, password and passkeys.'],
        ["Jangan tunjukkan alu-aluan ini lagi pada pelayar ini.", "Don't show this welcome again on this browser."],
        ['Langkah Seterusnya: Integrasi Sysweb', 'Next Step: Sysweb Integration'], ['Langkah 4', 'Step 4'],
        ['Format UID Converted Little-Endian dengan format Sysweb (prefix 00000000).', 'Little-Endian Converted UID format matching the Sysweb format (prefix 00000000).'],
        ['Tutup modal', 'Close modal'], ['Format Penghantaran Sysweb:', 'Sysweb Submission Format:'],
        ['Setiap rekod menggunakan UID Converted (Little-Endian) yang didahului oleh lapan sifar (00000000 + Converted_UID). Data diproses dan disimpan ke fail sysweb_demo_received.json.', 'Each record uses the UID Converted (Little-Endian) preceded by eight zeros (00000000 + Converted_UID). Data is processed and saved to the file sysweb_demo_received.json.'],
        ['Log Tindak Balas Sysweb', 'Sysweb Response Log'],
        ['Menunggu penghantaran...', 'Waiting for submission...'],
        ['Klik "Hantar ke Sysweb" untuk memulakan integrasi dengan penerima simulasi.', 'Click "Send to Sysweb" to start the integration with the simulated receiver.'],
        ['Sedia untuk dihantar', 'Ready to send'], ['Hantar ke Sysweb', 'Send to Sysweb'],
        /* ---- User manual dialog ---- */
        ['Panduan Pengguna USMSmart', 'USMSmart User Guide'],
        ['Rujukan ringkas untuk menggunakan sistem dengan selamat.', 'A quick reference for using the system safely.'],
        ['Tutup panduan', 'Close guide'], ['Kandungan panduan pengguna', 'User guide content'],
        ['Video ringkas', 'Short video'],
        ['Rakaman skrin pendek untuk aliran kerja yang paling kerap digunakan.', 'A short screen recording of the most frequently used workflow.'],
        ['Video belum tersedia', 'Video not yet available'],
        ['Panduan teks di bawah merangkumi semua langkah. Rakaman pendek akan dimuatkan di ruang ini.', 'The text guide below covers all the steps. A short recording will be loaded in this space.'],
        ['Pelayar anda tidak menyokong video.', 'Your browser does not support video.'],
        ['Muat turun video (MP4)', 'Download video (MP4)'], ['Buka dalam tab baharu', 'Open in a new tab'],
        ['Transkrip video', 'Video transcript'],
        ['Tarik rekod, semak UID dan eksport', 'Pull records, check UIDs and export'],
        ['Aliran kerja penuh daripada tarikan rekod sehingga fail eksport dimuat turun. Data dalam rakaman ialah contoh.', 'The full workflow from pulling records to downloading the export file. The data in the recording is sample data.'],
        ['Mula di sini', 'Start here'],
        ['Gunakan tab Transporter Utama untuk modul pengurusan dan Analisis Grafik untuk ringkasan serta carta. Butang Panduan di bahagian atas boleh dibuka bila-bila masa.', 'Use the Main Transporter tab for management modules and Graph Analytics for summaries and charts. The Guide button at the top can be opened at any time.'],
        ['1. Cari dan tapis rekod pelajar', '1. Search and filter student records'],
        ['Direktori Pelajar', 'Student Directory'], ['Buka Direktori Pelajar.', 'Open the Student Directory.'],
        ['Taip kata kunci dalam kotak carian; gunakan penapis yang tersedia untuk mengecilkan hasil.', 'Type keywords in the search box; use the available filters to narrow the results.'],
        ['Pilih cip penapis untuk melihat keadaan tertentu. Kosongkan carian atau penapis jika hasil tiada.', 'Select a filter chip to view a specific state. Clear the search or filters if there are no results.'],
        ['Klik tajuk lajur yang boleh diisih untuk menukar susunan. Gunakan kawalan halaman untuk bergerak antara halaman.', 'Click a sortable column header to change the order. Use the pagination controls to move between pages.'],
        ['Pilih saiz halaman yang sesuai; pilihan mungkin diingati oleh pelayar pada peranti yang sama.', 'Choose a suitable page size; the choice may be remembered by the browser on the same device.'],
        ['2. Tambah atau kemas kini rekod', '2. Add or update records'],
        ['Gunakan tindakan tambah atau edit yang tersedia dalam modul berkenaan.', 'Use the add or edit actions available in the relevant module.'],
        ['Semak nombor matrik/kad dan butiran pelajar sebelum menyimpan untuk mengelakkan rekod pendua atau kesilapan.', 'Check the matric/card number and student details before saving to avoid duplicate records or mistakes.'],
        ['Jika akaun anda berperanan Viewer, perubahan mungkin perlu dihantar sebagai permintaan dan diluluskan oleh Admin.', 'If your account has the Viewer role, changes may need to be submitted as a request and approved by an Admin.'],
        ['Semak mesej pengesahan atau ralat selepas setiap tindakan; jangan anggap perubahan berjaya jika tiada pengesahan.', 'Check the confirmation or error message after every action; do not assume a change succeeded without confirmation.'],
        ['3. Pilihan pukal dan eksport', '3. Bulk selection and export'],
        ['Gunakan kotak pilihan rekod untuk memilih beberapa baris.', 'Use the record checkboxes to select multiple rows.'],
        ['Semak bilangan dan rekod terpilih sebelum menjalankan tindakan pukal.', 'Check the count and selected records before running a bulk action.'],
        ['Eksport CSV untuk analisis luar talian; simpan fail yang mengandungi maklumat pelajar di lokasi yang diluluskan sahaja.', 'Export CSV for offline analysis; store files containing student information only in approved locations.'],
        ['Tindakan padam atau perubahan pukal boleh memberi kesan kepada banyak rekod. Baca dialog pengesahan dengan teliti.', 'Delete or bulk-change actions can affect many records. Read the confirmation dialog carefully.'],
        ['4. Analisis, tarikh luput dan sejarah', '4. Analytics, expiry dates and history'],
        ['Gunakan Analisis Grafik untuk memahami ringkasan data dan carta yang dipaparkan.', 'Use Graph Analytics to understand the data summaries and charts shown.'],
        ['Semak amaran kad tamat tempoh atau bakal tamat tempoh dan sahkan butiran rekod sebelum mengambil tindakan.', 'Check warnings for expired or soon-to-expire cards and verify record details before taking action.'],
        ['Buka Sejarah Rekod & Log Audit untuk menyemak aktiviti atau perubahan yang direkodkan.', 'Open Record History & Audit Log to review recorded activity or changes.'],
        ['5. Akaun dan peranan', '5. Accounts and roles'],
        ['Admin mempunyai akses pengurusan tambahan seperti pengurusan akaun dan tindakan pentadbiran.', 'Admin has additional management access such as account management and administrative actions.'],
        ['Viewer mempunyai akses yang lebih terhad; gunakan aliran permintaan perubahan jika disediakan.', 'Viewer has more limited access; use the change-request flow if provided.'],
        ['Gunakan Profil Saya untuk pilihan profil yang tersedia, dan log keluar apabila selesai terutama pada komputer kongsi.', 'Use My Profile for the available profile options, and log out when finished, especially on shared computers.'],
        ['Pemadaman akaun adalah kekal: taip nama pengguna untuk mengesahkan. Akaun sendiri dan Admin aktif terakhir tidak boleh dipadam.', 'Account deletion is permanent: type the username to confirm. Your own account and the last active Admin cannot be deleted.'],
        ['Jangan kongsi kata laluan atau biarkan sesi log masuk tanpa pengawasan.', 'Do not share passwords or leave a logged-in session unattended.'],
        ['6. Pembantu AI dan penyelesaian masalah', '6. AI assistant and troubleshooting'],
        ['Pembantu AI tempatan boleh membantu menerangkan statistik agregat; semak jawapan penting dengan data sebenar kerana AI boleh tersilap.', 'The local AI assistant can help explain aggregate statistics; verify important answers against real data because the AI can be wrong.'],
        ['Jika AI tidak memberi respons, pastikan Ollama berjalan dan model tempatan telah dipasang.', 'If the AI does not respond, make sure Ollama is running and the local model is installed.'],
        ['Jika halaman tidak dikemas kini, cuba muat semula dengan Ctrl + F5. Jika masalah berterusan, catat mesej ralat dan hubungi pentadbir.', 'If the page does not update, try reloading with Ctrl + F5. If the problem persists, note the error message and contact the administrator.'],
        ['Jangan lakukan pemulihan sandaran pada pangkalan data sebenar sebagai ujian. Uji pemulihan pada pangkalan data berasingan terlebih dahulu.', 'Do not perform a backup restore on the real database as a test. Test restoration on a separate database first.'],
        ['Amalan selamat', 'Safe practices'],
        ['Pastikan sandaran terkini sebelum perubahan besar. Hadkan akses kepada data pelajar, sahkan rekod sebelum menyimpan atau memadam, dan laporkan sebarang data yang tidak tepat kepada Admin.', 'Ensure backups are current before major changes. Restrict access to student data, verify records before saving or deleting, and report any inaccurate data to the Admin.'],
        /* ---- Forms: Sysweb drawer, edit student, bulk update, profile ---- */
        ['Kategori Rekod', 'Record category'],
        ['Kosong untuk nombor siri automatik', 'Leave blank for an automatic serial number'],
        ['Dijana automatik apabila kosong.', 'Generated automatically when left blank.'],
        ['Tarikh Masuk / Dikeluarkan', 'Date joined / issued'], ['Tarikh Tamat Kad', 'Card expiry date'],
        ['Nota Staf', 'Staff notes'], ['Catatan dalaman staf…', 'Internal staff notes…'],
        ['Mesti hexadecimal dengan bilangan aksara genap.', 'Must be hexadecimal with an even number of characters.'],
        ['UID Kad Mentah (Big-Endian, pilihan)', 'Raw card UID (Big-Endian, optional)'],
        ['kira UID', 'compute UID'],
        ['Songsangkan susunan byte UID mentah dan isikan UID converted', 'Reverse the raw UID byte order and fill in the converted UID'],
        ['Isi untuk membolehkan pelayan mengesahkan susunan byte UID converted.', 'Fill this in so the server can verify the byte order of the converted UID.'],
        ['Kata laluan dijana oleh pelayan menggunakan penjana rawak kripto dan disimpan bersama rekod — ia tidak dipaparkan di sini. Selepas rekod disimpan, gunakan butang mata atau ikon salin dalam jadual untuk memaparkan, menyalin atau set semula kata laluan.', 'The password is generated by the server using a cryptographic random generator and stored with the record — it is not displayed here. After the record is saved, use the eye button or copy icon in the table to view, copy or reset the password.'],
        ['Nama Penuh Pelajar', "Student's full name"], ['No Matrik / ID', 'Matric No. / ID'],
        ['UID Asal (Hexadecimal)', 'Original UID (hexadecimal)'],
        ['Pratonton Little-Endian:', 'Little-Endian preview:'],
        ['Pusat Pengajian (Kampus Kejuruteraan USM)', 'School (USM Engineering Campus)'],
        ['Memuatkan sekolah daripada SQL Server…', 'Loading schools from SQL Server…'],
        ['Kategori paparan diutamakan daripada IC live: awalan USM = International; IC Malaysia 12 digit = Local.', 'Display category is taken from the live IC: USM prefix = International; 12-digit Malaysian IC = Local.'],
        ['Tahap Pendidikan', 'Education level'], ['Belum ditentukan', 'Not yet specified'],
        ['Kategori Pelajar', 'Student category'],
        ['Automatik berdasarkan kewarganegaraan', 'Automatic based on nationality'],
        ['No Siri Fizikal Kad', 'Physical card serial no.'],
        ['Dijana automatik apabila kosong, dan tidak akan bertindih dengan rekod sedia ada.', 'Generated automatically when blank, and will not overlap with existing records.'],
        ['Dasar Akses (Policy)', 'Access policy'], ['Tarikh Pendaftaran', 'Registration date'],
        ['Tarikh Luput Kad', 'Card expiry date'],
        ['Pilih Tindakan Kemaskini', 'Select update action'],
        ['Lanjutkan Tarikh Luput (Tambah Tempoh)', 'Extend expiry date (add duration)'],
        ['Tetapkan Tarikh Luput Baharu (Tarikh Khusus)', 'Set new expiry date (specific date)'],
        ['Tukar Pakej Polisi Kad', 'Change card policy package'],
        ['Tukar Pusat Pengajian (Fakulti)', 'Change school (faculty)'],
        ['Pilih Tempoh Lanjutan', 'Select extension period'],
        ['+30 Hari (1 Bulan)', '+30 days (1 month)'], ['+90 Hari (3 Bulan)', '+90 days (3 months)'],
        ['+180 Hari (6 Bulan)', '+180 days (6 months)'], ['+365 Hari (1 Tahun)', '+365 days (1 year)'],
        ['+730 Hari (2 Tahun)', '+730 days (2 years)'],
        ['Pilihan Hari Khusus...', 'Custom number of days...'], ['Bilangan hari:', 'Number of days:'],
        ['Jika kad sudah tamat tempoh, lanjutan dikira dari hari ini. Jika kad masih aktif, lanjutan ditambah ke tarikh luput sedia ada.', 'If the card has already expired, the extension is counted from today. If the card is still active, the extension is added to the existing expiry date.'],
        ['Tarikh Luput Baharu', 'New expiry date'],
        ['Semua pelajar yang dipilih akan dikemas kini ke tarikh luput ini.', 'All selected students will be updated to this expiry date.'],
        ['Pakej Polisi Kad', 'Card policy package'],
        ['LAB_ALPHA (Standard Makmal)', 'LAB_ALPHA (Lab Standard)'],
        ['LAB_BETA (Akses Makmal Khusus)', 'LAB_BETA (Special Lab Access)'],
        ['FULL_CAMPUS (Akses Menyeluruh Kampus)', 'FULL_CAMPUS (Full Campus Access)'],
        ['HOSTEL_ACCESS (Akses Kolej Kediaman)', 'HOSTEL_ACCESS (Residential College Access)'],
        ['Pakej Polisi Lain (Tulis Sendiri)...', 'Other policy package (custom)...'],
        ['Masukkan nama polisi baharu...', 'Enter the new policy name...'],
        ['Pusat Pengajian Baharu', 'New school'],
        ['Nama mesra untuk paparan. Boleh dikosongkan.', 'Friendly name for display. May be left blank.'],
        ['Digunakan untuk notifikasi akaun (pilihan).', 'Used for account notifications (optional).'],
        ['Putuskan pautan Google', 'Unlink Google'], ['Sambung akaun Google', 'Connect Google account'],
        ['atau Gmail manual', 'or manual Gmail'], ['Alamat Gmail manual', 'Manual Gmail address'],
        ['Akaun Admin hanya boleh dipaut dengan e-mel USM', 'Admin accounts can only be linked with a USM email'],
        ['Gmail yang dipaut menerima', 'The linked Gmail receives'],
        ['kod OTP pemulihan kata laluan', 'password-recovery OTP code'],
        ['apabila anda terlupa kata laluan.', 'when you forget your password.'],
        /* ---- Analytics page ---- */
        ['Ringkasan status kad', 'Card status summary'],
        ['Klik untuk lihat semua rekod pelajar', 'Click to view all student records'],
        ['Jumlah rekod kad', 'Total card records'], ['Semua rekod pelajar', 'All student records'],
        ['Papar semua →', 'View all →'],
        ['Klik untuk lihat kad pelajar aktif', 'Click to view active student cards'],
        ['—% daripada rekod', '—% of records'], ['Papar aktif →', 'View active →'],
        ['Klik untuk lihat senarai pelajar yang kad mereka hampir luput (30 hari)', 'Click to view the list of students whose cards are about to expire (30 days)'],
        ['Luput dalam 30 hari', 'Expiring within 30 days'],
        ['Perlu perhatian / pembaharuan', 'Needs attention / renewal'],
        ['Klik lihat senarai →', 'Click to view list →'],
        ['Klik untuk lihat kad pelajar yang telah tamat tempoh', 'Click to view student cards that have expired'],
        ['Tarikh luput sebelum hari ini', 'Expiry date before today'], ['Papar tamat →', 'View expired →'],
        ['USMSmart / Statistik', 'USMSmart / Statistics'], ['Analisis Statistik', 'Statistical Analysis'],
        ['Ringkasan pendaftaran pelajar mengikut pusat pengajian dan bulan.', 'Summary of student registrations by school and month.'],
        ['PDF ringkasan', 'Summary PDF'], ['PNG semua carta', 'PNG of all charts'],
        ['Laporan Excel', 'Excel report'], ['Sorotan analitik', 'Analytics highlights'],
        ['Jumlah pelajar', 'Total students'],
        ['Jumlah rekod dalam senarai semasa', 'Total records in the current list'],
        ['Pusat dengan rekod pelajar', 'Schools with student records'],
        ['Pusat terbesar', 'Largest school'], ['Belum ada data', 'No data yet'],
        ['Pendaftaran terkini', 'Latest registration'],
        ['Tarikh terbaru yang tersedia', 'Most recent date available'],
        ['Penapis analitik', 'Analytics filters'], ['Penapis analisis', 'Analysis filters'],
        ['Gunakan penapis untuk mengemas kini kad ringkasan dan semua carta serentak.', 'Use the filters to update the summary cards and all charts at once.'],
        ['Paparan: semua pusat pengajian', 'Showing: all schools'],
        ['Buka senarai pelajar', 'Open student list'], ['Reset semua', 'Reset all'],
        ['Analisis mengikut pusat pengajian', 'Analysis by school'],
        ['Bandingkan jumlah dan perkadaran pelajar melalui carta di bawah.', 'Compare student counts and proportions using the charts below.'],
        ['Ringkasan visual', 'Visual summary'], ['Taburan pelajar', 'Student distribution'],
        ['Peratusan mengikut pusat pengajian', 'Percentages by school'],
        ['Jumlah mengikut pusat', 'Totals by school'],
        ['Perbandingan jumlah pelajar berdaftar', 'Comparison of registered student counts'],
        ['Trend pendaftaran bulanan', 'Monthly registration trend'],
        ['Perbandingan pendaftaran bagi setiap pusat pengajian', 'Registration comparison for each school'],
        ['Bulan mula', 'Start month'], ['Bulan akhir', 'End month'],
        ['Kosongkan tarikh', 'Clear dates'], ['Memuatkan data carta…', 'Loading chart data…'],
        /* ---- Command palette & inline JS panels ---- */
        ['Cari dan jalankan arahan', 'Search and run commands'],
        ['Cari pelajar, modul atau arahan…', 'Search students, modules or commands…'],
        ['Hasil carian', 'Search results'], ['pilih', 'select'], ['buka', 'open'],
        ['Memuatkan permintaan…', 'Loading requests…'],
        ['Permintaan tidak dapat dimuatkan.', 'The requests could not be loaded.'],
        ['Bersambung', 'Connected'], ['Sambungan terhad', 'Limited connection'],
        ['Tidak bersambung', 'Not connected'], ['Tiada fail sambungan', 'No connection file'],
        ['Status sumber tak dikenali', 'Unknown source status'],
        ['Pangkalan data direktori', 'Directory database'], ['Pangkalan data sumber:', 'Source database:'],
        ['statistik log', 'log statistics'],
        ['Angka log dibaca dari pelayan hanya apabila anda bukakan.', 'Log figures are read from the server only when you open it.'],
        ['statistik sesi', 'session statistics'],
        ['Carta sesi dihantar hanya apabila anda bukakan.', 'Session charts are fetched only when you open it.'],
        ['statistik eksport', 'export statistics'],
        ['Carta eksport Sysweb dilukis hanya apabila anda bukakan.', 'Sysweb export charts are drawn only when you open it.'],
        ['statistik kualiti data', 'data quality statistics'],
        ['Carta kualiti data dilukis hanya apabila anda bukakan.', 'Data quality charts are drawn only when you open it.'],
        ['Tunjuk', 'Show'], ['Sembunyikan', 'Hide'],
        ['Tunjuk statistik log', 'Show log statistics'], ['Sembunyikan statistik log', 'Hide log statistics'], ['Tiada statistik log', 'No log statistics'],
        ['Tunjuk statistik sesi', 'Show session statistics'], ['Sembunyikan statistik sesi', 'Hide session statistics'], ['Tiada statistik sesi', 'No session statistics'],
        ['Tunjuk statistik eksport', 'Show export statistics'], ['Sembunyikan statistik eksport', 'Hide export statistics'], ['Tiada statistik eksport', 'No export statistics'],
        ['Tunjuk statistik kualiti data', 'Show data quality statistics'], ['Sembunyikan statistik kualiti data', 'Hide data quality statistics'], ['Tiada statistik kualiti data', 'No data quality statistics'],
        /* ---- Public pages: home / login / password-reset ---- */
        ['USMSmart — Portal Pengurusan Kad Kampus', 'USMSmart — Campus Card Management Portal'],
        ['USMSmart — portal pengurusan kad Kampus Kejuruteraan UDA. Tarik rekod, semak UID, analisis dan eksport dalam satu ruang yang selamat.', 'USMSmart — the card management portal for the UDA Engineering Campus. Pull records, check UIDs, analyze and export in one secure space.'],
        ['USMSmart memusatkan pengurusan kad akses, sesi dan laporan Kampus Kejuruteraan USM dalam satu portal — selamat, pantas dan boleh dipercayai.', 'USMSmart centralizes access-card, session and report management for the USM Engineering Campus in one portal — secure, fast and reliable.'],
        ['USMSmart — kembali ke atas', 'USMSmart — back to top'],
        ['Navigasi laman', 'Site navigation'], ['Ciri', 'Features'], ['Aliran Kerja', 'Workflow'],
        ['Sediakan portal', 'Set up the portal'], ['Log masuk', 'Log in'],
        ['Tukar kepada mod gelap', 'Switch to dark mode'], ['Mod gelap', 'Dark mode'],
        ['Buka menu', 'Open menu'], ['Cipta akaun pentadbir', 'Create admin account'],
        ['Log masuk ke portal', 'Log in to the portal'],
        ['Pengenalan USMSmart', 'Introduction to USMSmart'],
        ['PORTAL PENGURUSAN KAD', 'CARD MANAGEMENT PORTAL'],
        ['Pengurusan yang lebih', 'Smarter'], ['pintar.', 'management.'],
        ['Satu ruang untuk mengurus rekod pelajar, kad akses dan aktiviti sistem — daripada tarikan data sehingga semakan UID, eksport dan penghantaran ke Sysweb.', 'One space to manage student records, access cards and system activity — from data pulls through UID checks, exports and delivery to Sysweb.'],
        ['Terokai ciri', 'Explore features'], ['Tonton demo', 'Watch the demo'], ['34 saat', '34 seconds'],
        ['Rekod pelajar dan kad dalam satu tempat', 'Student and card records in one place'],
        ['Analitik dan sejarah aktiviti yang jelas', 'Clear analytics and activity history'],
        ['Akses mengikut peranan pengguna', 'Access based on user roles'],
        ['Statistik penggunaan portal', 'Portal usage statistics'],
        ['Pengguna berdaftar', 'Registered users'], ['Dalam talian', 'Online'],
        ['Log masuk hari ini', 'Logins today'], ['Modul teras', 'Core modules'],
        ['Tatal ke ciri portal', 'Scroll to portal features'], ['Tatal untuk terokai', 'Scroll to explore'],
        ['MODUL TERAS', 'CORE MODULES'], ['Satu portal.', 'One portal.'],
        ['Lapan modul teras.', 'Eight core modules.'],
        ['Setiap modul dibina untuk satu tugas dan disiapkan sepenuhnya — daripada tarikan data pertama sehingga rekod bersih dihantar keluar.', 'Every module is built for one job and fully finished — from the first data pull to clean records being sent out.'],
        ['Integriti', 'Integrity'], ['Analitik', 'Analytics'], ['Direktori', 'Directory'],
        ['Gerbang', 'Gateway'], ['Keselamatan', 'Security'],
        ['Tarik rekod pelajar dan staf terus daripada pangkalan data kampus, dengan penapis StatusKad dan jenis pemegang.', 'Pull student and staff records straight from the campus database, with StatusKad and holder-type filters.'],
        ['Sahkan UID lapan aksara, kesan pendua dan asingkan rekod layak sebelum apa-apa dihantar keluar.', 'Validate eight-character UIDs, detect duplicates and separate eligible records before anything is sent out.'],
        ['KPI, carta taburan sekolah dan trend tarikh luput — ringkasan yang boleh dibaca dalam satu pandangan.', 'KPIs, school-distribution charts and expiry-date trends — a summary readable at a glance.'],
        ['Cari, tapis dan isih pemegang kad pelajar serta staf dengan status kad yang sentiasa terkini.', 'Search, filter and sort student and staff cardholders with always-current card status.'],
        ['Hantar rekod ke gerbang Sysweb dan pantau status penghantaran tanpa meninggalkan portal.', 'Send records to the Sysweb gateway and monitor delivery status without leaving the portal.'],
        ['Akaun & Peranan', 'Accounts & Roles'],
        ['Peranan Admin dan Viewer, permintaan perubahan untuk kelulusan, serta log audit bagi setiap tindakan.', 'Admin and Viewer roles, change requests for approval, and an audit log for every action.'],
        ['ALIRAN KERJA', 'WORKFLOW'], ['Empat langkah.', 'Four steps.'], ['Siap.', 'Done.'],
        ['Aliran yang sama setiap kali — pendek, jelas dan boleh dijejak dari awal hingga akhir.', 'The same flow every time — short, clear and traceable from start to finish.'],
        ['Guna kata laluan, passkey atau akaun Google yang telah dipautkan pada profil anda.', 'Use a password, passkey or the Google account linked to your profile.'],
        ['Pilih jenis pemegang dan status, kemudian tarik rekod terkini daripada sumber kampus.', 'Choose the holder type and status, then pull the latest records from the campus source.'],
        ['Semak & sahkan', 'Check & verify'],
        ['Semakan UID mengasingkan rekod layak daripada pendua dan rekod tidak lengkap.', 'The UID check separates eligible records from duplicates and incomplete records.'],
        ['Eksport / hantar', 'Export / send'],
        ['Muat turun CSV bersih atau hantar terus ke Sysweb; semuanya tercatat dalam log audit.', 'Download a clean CSV or send straight to Sysweb; everything is recorded in the audit log.'],
        ['Lihat portal', 'See the real'], ['sebenar.', 'portal.'],
        ['Rakaman skrin aliran kerja penuh — tarik rekod, semak UID dan eksport — terus daripada dashboard USMSmart. Semua data dalam rakaman ialah contoh; tiada rekod sebenar dipaparkan.', 'A screen recording of the full workflow — pull records, check UIDs and export — straight from the USMSmart dashboard. All data in the recording is sample data; no real records are shown.'],
        ['Demo USMSmart: tarik rekod, semak UID dan eksport', 'USMSmart demo: pull records, check UIDs and export'],
        ['Direkod daripada dashboard sebenar dengan data contoh.', 'Recorded from the real dashboard with sample data.'],
        ['Log masuk tanpa kata laluan', 'Passwordless login'], ['Akaun Google', 'Google Account'],
        ['Pautan sekali, log masuk satu klik', 'Link once, log in with one click'],
        ['Log audit', 'Audit log'], ['Setiap tindakan tercatat', 'Every action recorded'],
        ['Sandaran automatik', 'Automatic backups'],
        ['Backup harian tanpa tindakan manual', 'Daily backups with no manual action'],
        ['Soalan', 'Frequently asked'], ['lazim.', 'questions.'],
        ['Jawapan ringkas sebelum anda log masuk. Untuk bantuan lanjut, hubungi pentadbir portal.', 'Short answers before you log in. For further help, contact the portal administrator.'],
        ['Masih ada soalan?', 'Still have questions?'],
        ['Panduan penuh dan sokongan pentadbir tersedia di dalam portal — log masuk untuk meneruskan.', 'The full guide and administrator support are available inside the portal — log in to continue.'],
        ['Siapa yang boleh menggunakan USMSmart?', 'Who can use USMSmart?'],
        ['Portal ini terhad kepada pengguna yang dibenarkan oleh pentadbir kampus. Akaun dicipta oleh Admin — tiada pendaftaran terbuka, dan setiap sesi disahkan semula pada setiap permintaan.', 'This portal is limited to users authorized by the campus administrator. Accounts are created by Admins — there is no open registration, and every session is re-verified on every request.'],
        ['Apakah beza Admin dan Viewer?', 'What is the difference between Admin and Viewer?'],
        ['Admin mengurus akaun, eksport, sandaran dan tetapan sistem. Viewer boleh melihat dan menyemak data, tetapi perubahan besar dihantar sebagai permintaan perubahan untuk kelulusan Admin.', 'Admins manage accounts, exports, backups and system settings. Viewers can view and review data, but major changes are submitted as change requests for Admin approval.'],
        ['Bolehkah saya log masuk dengan akaun Google?', 'Can I log in with a Google account?'],
        ['Boleh, setelah pentadbir mengaktifkan integrasi Google dan akaun anda dipautkan. Pautkan sekali melalui', 'Yes, once the administrator enables Google integration and your account is linked. Link once via'],
        ['dengan memilih akaun Google sebenar anda — selepas itu log masuk hanya satu klik.', 'by choosing your real Google account — after that, logging in is just one click.'],
        ['Saya terlupa kata laluan.', 'I forgot my password.'], ['Gunakan pautan', 'Use the'],
        ['Lupa kata laluan', 'Forgot password'],
        ['di halaman log masuk untuk menerima kod OTP melalui e-mel dan menetapkan kata laluan baharu, atau hubungi pentadbir untuk set semula secara manual.', 'link on the login page to receive an OTP code by email and set a new password, or contact the administrator for a manual reset.'],
        ['Adakah data portal selamat?', "Is the portal's data secure?"],
        ['Kata laluan disimpan sebagai cincangan (hash), sesi tamat automatik selepas 30 minit tidak aktif, tindakan sensitif memerlukan kebenaran Admin, dan semua perubahan direkodkan dalam log audit yang boleh diperiksa.', 'Passwords are stored as hashes, sessions expire automatically after 30 minutes of inactivity, sensitive actions require Admin permission, and all changes are recorded in an inspectable audit log.'],
        ['PERSEDIAAN', 'SETUP'], ['MULA SEKARANG', 'GET STARTED'],
        ['Sediakan portal anda.', 'Set up your portal.'], ['Sedia untuk mula?', 'Ready to start?'],
        ['Cipta akaun pentadbir pertama untuk mengaktifkan USMSmart. Proses mengambil masa kurang seminit.', 'Create the first admin account to activate USMSmart. The process takes less than a minute.'],
        ['Log masuk untuk meneruskan ke dashboard, atau teruskan meneroka ciri portal di atas.', 'Log in to continue to the dashboard, or keep exploring the portal features above.'],
        ['Portal pengurusan kad yang teratur — daripada tarikan rekod sehingga eksport dan penghantaran.', 'An orderly card management portal — from record pulls through exports and delivery.'],
        ['Pautan footer', 'Footer links'], ['Tukar kepada mod cerah', 'Switch to light mode'],
        ['Mod cerah', 'Light mode'], ['Tutup menu', 'Close menu'],
        /* ---- Login page ---- */
        ['Persediaan Pentadbir', 'Admin Setup'], ['Log Masuk', 'Log In'],
        ['USMSmart — Log Masuk', 'USMSmart — Log In'], ['USMSmart — Persediaan Pentadbir', 'USMSmart — Admin Setup'],
        ['Log masuk ke USMSmart — portal pengurusan kad pintar Kampus Kejuruteraan USM. Sokongan log masuk Google, passkey dan kata laluan.', 'Log in to USMSmart — the smart card management portal of the USM Engineering Campus. Supports Google, passkey and password login.'],
        ['Langkau ke borang log masuk', 'Skip to the login form'],
        ['Kembali ke laman utama', 'Back to the home page'], ['Laman utama', 'Home page'],
        ['Satu ruang untuk mengurus rekod pelajar, pemindahan data dan aktiviti sistem dengan lebih teratur.', 'One space to manage student records, data transfers and system activity in a more orderly way.'],
        ['Kenali portal sebelum log masuk', 'Get to know the portal before logging in'],
        ['Rekod kad pelajar dan staf dalam satu portal', 'Student and staff card records in one portal'],
        ['Semakan UID, analitik dan kualiti data', 'UID checks, analytics and data quality'],
        ['Penghantaran rekod ke Sysweb secara langsung', 'Send records to Sysweb directly'],
        ['Tatal ke bawah untuk pratonton modul dan statistik langsung.', 'Scroll down for a module preview and live statistics.'],
        ['Sediakan pentadbir', 'Set up the admin'], ['Selamat kembali', 'Welcome back'],
        ['Cipta akaun pentadbir pertama untuk mengaktifkan portal. Gunakan kata laluan unik sekurang-kurangnya 12 aksara.', 'Create the first admin account to activate the portal. Use a unique password of at least 12 characters.'],
        ['Log masuk untuk meneruskan ke dashboard USMSmart. Akaun Viewer mempunyai akses baca sahaja.', 'Log in to continue to the USMSmart dashboard. Viewer accounts have read-only access.'],
        ['Nama pengguna atau Gmail', 'Username or Gmail'],
        ['Masukkan nama pengguna atau Gmail', 'Enter your username or Gmail'],
        ['Masukkan kata laluan', 'Enter your password'], ['Papar kata laluan', 'Show password'],
        ['Caps Lock aktif — kata laluan sensitif huruf besar/kecil.', 'Caps Lock is on — passwords are case-sensitive.'],
        ['Lupa kata laluan?', 'Forgot password?'], ['atau log masuk pantas', 'or quick sign-in'],
        ['Log masuk dengan Akaun Google', 'Log in with a Google Account'],
        ['Akses Admin melalui Google: e-mel USM sahaja', 'Admin access via Google: USM email only'],
        ['Log masuk dengan passkey', 'Log in with a passkey'],
        ['Guna Face ID, Windows Hello atau cap jari peranti ini. Kata laluan masih boleh digunakan jika akaun belum mempunyai passkey.', "Use Face ID, Windows Hello or this device's fingerprint. A password can still be used if the account has no passkey yet."],
        ['Akses terhad kepada pengguna yang dibenarkan. Jangan kongsi kata laluan anda.', 'Access is limited to authorized users. Do not share your password.'],
        ['Statistik penggunaan', 'Usage statistics'], ['Jumlah akaun berdaftar', 'Total registered accounts'],
        ['Aktif dalam 5 minit terakhir', 'Active in the last 5 minutes'],
        ['Log masuk berjaya hari ini', 'Successful logins today'],
        ['Sila tunggu…', 'Please wait…'],
        ['Permintaan gagal. Sila cuba lagi.', 'The request failed. Please try again.'],
        ['Tidak dapat menghubungi pelayan. Sila cuba lagi.', 'Could not reach the server. Please try again.'],
        ['Sembunyikan kata laluan', 'Hide password'],
        ['Petua: tekan / untuk mencari', 'Tip: press / to search'],
        ['Kosongkan tapisan', 'Clear filters'],
        ['Menunggu peranti…', 'Waiting for device…'],
        ['Masukkan nama pengguna dahulu, kemudian tekan passkey.', 'Enter your username first, then press passkey.'],
        ['Pelayar atau sambungan ini tidak menyokong passkey. Sila guna kata laluan.', 'This browser or connection does not support passkeys. Please use a password.'],
        ['Passkey tidak tersedia.', 'Passkey unavailable.'],
        ['Pengesahan passkey gagal.', 'Passkey verification failed.'],
        ['Passkey dibatalkan atau tamat masa. Cuba lagi.', 'Passkey was cancelled or timed out. Try again.'],
        ['Passkey memerlukan sambungan HTTPS atau localhost.', 'Passkeys require an HTTPS or localhost connection.'],
        ['Passkey tidak dapat digunakan. Sila guna kata laluan.', 'The passkey could not be used. Please use a password.'],
        ['Mengesahkan akaun Google…', 'Verifying Google account…'],
        ['Log masuk dengan Google gagal.', 'Google login failed.'],
        ['Gagal log masuk dengan akaun Google.', 'Failed to log in with the Google account.'],
        ['Log masuk Google belum diaktifkan oleh pentadbir. Sila log masuk dengan nama pengguna dan kata laluan.', 'Google login has not been enabled by the administrator. Please log in with your username and password.'],
        ['Perkhidmatan Google tidak dapat dimuatkan. Semak sambungan internet, atau log masuk dengan kata laluan.', 'The Google service could not load. Check your internet connection, or log in with a password.'],
        /* ---- Password-reset page ---- */
        ['USMSmart — Set Semula Kata Laluan', 'USMSmart — Reset Password'],
        ['Kembali ke halaman log masuk USMSmart', 'Back to the USMSmart login page'],
        ['OTP Gmail', 'Gmail OTP'], ['Pautan Emel', 'Email Link'], ['Kod OTP Gmail', 'Gmail OTP Code'],
        ['Masukkan', 'Enter your'], ['nama pengguna', 'username'], ['atau', 'or'],
        ['alamat Gmail', 'Gmail address'],
        ['yang telah dipautkan ke akaun anda untuk menerima kod OTP 6-digit.', 'that has been linked to your account to receive a 6-digit OTP code.'],
        ['Nama pengguna atau Alamat Gmail', 'Username or Gmail Address'],
        ['cth: administrator atau nama@gmail.com', 'e.g. administrator or name@gmail.com'],
        ['Hantar Kod OTP', 'Send OTP Code'], ['Masukkan Kod OTP', 'Enter OTP Code'],
        ['Kod pengesahan 6 digit telah dihantar ke Gmail anda. Sahkan sebelum kod tamat tempoh.', 'A 6-digit verification code has been sent to your Gmail. Verify it before the code expires.'],
        ['Kod OTP 6 Digit', '6-Digit OTP Code'], ['Sahkan & Tetapkan Kata Laluan', 'Verify & Set Password'],
        ['Hantar semula kod OTP', 'Resend OTP code'], ['Pautan Set Semula', 'Reset Link'],
        ['Masukkan nama pengguna akaun anda. Pautan set semula sekali guna akan dihantar ke e-mel berdaftar, atau disediakan oleh pentadbir jika akaun tiada e-mel.', 'Enter your account username. A one-time reset link will be sent to the registered email, or provided by the administrator if the account has no email.'],
        ['Masukkan nama pengguna', 'Enter your username'], ['Hantar permintaan', 'Send request'],
        ['Permintaan direkodkan', 'Request recorded'],
        ['Semak peti masuk e-mel berdaftar anda, termasuk folder spam.', 'Check your registered email inbox, including the spam folder.'],
        ['Tiada e-mel? Pentadbir boleh menjana pautan dari halaman Pengurusan Akaun.', 'No email? The administrator can generate a link from the Account Management page.'],
        ['Pautan sah selama 30 minit dan hanya boleh digunakan sekali.', 'The link is valid for 30 minutes and can only be used once.'],
        ['Kembali ke log masuk', 'Back to login'],
        ['Atas sebab keselamatan, jangan kongsi pautan atau kod OTP anda dengan sesiapa.', 'For security reasons, do not share your link or OTP code with anyone.'],
        ['Tetapkan kata laluan baharu', 'Set a new password'],
        ['Pautan ini sah untuk akaun di bawah. Pilih kata laluan baharu yang unik dan tidak digunakan di tempat lain.', 'This link is valid for the account below. Choose a new, unique password that is not used anywhere else.'],
        ['Masa tinggal sebelum pautan tamat', 'Time remaining before the link expires'],
        ['Ulang kata laluan baharu', 'Repeat the new password'], ['12–72 aksara', '12–72 characters'],
        ['Kedua-dua kata laluan sepadan', 'Both passwords match'], ['Simpan kata laluan', 'Save password'],
        ['Kata laluan berjaya ditetapkan', 'Password set successfully'],
        ['Semua sesi log masuk sedia ada telah dinyahaktifkan demi keselamatan. Log masuk semula dengan kata laluan baharu anda.', 'All existing login sessions have been deactivated for security. Log in again with your new password.'],
        ['Pautan ini sekali guna. Selepas disimpan, pautan yang sama tidak boleh digunakan lagi.', 'This link is single-use. Once saved, the same link can no longer be used.'],
        ['Pautan tidak sah', 'Invalid link'],
        ['Pautan set semula ini tidak dikenali. Ia mungkin salah disalin atau telah ditukar dengan permintaan baharu.', 'This reset link is not recognized. It may have been copied incorrectly or replaced by a newer request.'],
        ['Pautan telah tamat', 'The link has expired'],
        ['Pautan set semula hanya sah selama 30 minit. Minta pautan baharu untuk meneruskan.', 'Reset links are only valid for 30 minutes. Request a new link to continue.'],
        ['Pautan telah digunakan', 'The link has already been used'],
        ['Kata laluan bagi pautan ini telah ditetapkan. Jika itu bukan anda, minta pautan baharu atau hubungi pentadbir dengan segera.', "The password for this link has already been set. If that wasn't you, request a new link or contact the administrator immediately."],
        ['Perkhidmatan tidak tersedia', 'Service unavailable'],
        ['Perkhidmatan set semula tidak dapat dicapai buat masa ini. Sila hubungi pentadbir USMSmart.', 'The reset service cannot be reached right now. Please contact the USMSmart administrator.'],
        ['Minta pautan baharu', 'Request a new link'],
        ['Menghantar OTP…', 'Sending OTP…'], ['Penghantaran OTP gagal.', 'OTP delivery failed.'],
        ['Kod OTP 6 digit telah dihantar ke akaun Gmail', 'A 6-digit OTP code has been sent to the Gmail account'],
        ['. Sila semak peti masuk anda.', '. Please check your inbox.'],
        ['Ralat penghantaran OTP.', 'OTP delivery error.'],
        ['Mengesahkan…', 'Verifying…'], ['Pengesahan OTP gagal.', 'OTP verification failed.'],
        ['Kod OTP tidak sah.', 'Invalid OTP code.'], ['Menghantar…', 'Sending…'],
        ['Permintaan set semula telah direkodkan.', 'The reset request has been recorded.'],
        ['Lemah — tambah panjang dan variasi', 'Weak — add length and variety'],
        ['Sederhana — tambah variasi aksara', 'Fair — add more character variety'],
        ['Kuat', 'Strong'], ['Cemerlang — kata laluan yang kukuh', 'Excellent — a robust password'],
        ['Pautan telah tamat.', 'The link has expired.'], ['untuk meneruskan.', 'to continue.'],
        ['Menyimpan…', 'Saving…'], ['Pautan tidak sah.', 'Invalid link.'],
        /* ---- dashboard.js: Sysweb status, validation, cross-check ---- */
        ['Rekod Sedia Dihantar', 'Records Ready to Send'],
        ['Eksport UDA berjaya. Anda kini boleh meneruskan langkah integrasi ke Sysweb.', 'UDA export succeeded. You can now proceed with the Sysweb integration step.'],
        ['Tarikh Integrasi', 'Integration Date'],
        ['Nama tiada', 'Name missing'], ['Nombor pengenalan tiada', 'Identifier number missing'],
        ['Kategori tidak dikenali', 'Unrecognized category'],
        ['UID converted bukan hexadecimal bait-pair', 'Converted UID is not byte-pair hexadecimal'],
        ['Prefiks Sysweb (00000000) tidak sepadan', 'Sysweb prefix (00000000) does not match'],
        ['Susunan byte UID converted terbalik', 'Converted UID byte order is reversed'],
        ['Tarikh tamat kad bukan format ISO', 'Card expiry date is not in ISO format'],
        ['No. siri kad tiada', 'Card serial number missing'], ['Kad sudah luput', 'Card already expired'],
        ['Kad tamat dalam 30 hari', 'Card expires within 30 days'],
        ['Tiada rekod tarikh tamat', 'No expiry date on record'],
        ['Kata laluan belum dijana', 'Password not yet generated'],
        ['UID kad bercanggah dengan rekod kad', 'Card UID conflicts with the card record'],
        ['Pengenalan tiada dalam rekod kad', 'Identifier not found in the card records'],
        ['Lebih dari satu rekod kad ditemui untuk pengenalan ini', 'More than one card record found for this identifier'],
        ['Hanya rekod UDA tersedia, rekod kad rasmi tiada', 'Only the UDA record is available; no official card record'],
        ['Pangkalan kad Sysweb langsung tidak mengesahkan pengenalan ini', 'The live Sysweb card database does not confirm this identifier'],
        ['Salinan tempatan dan pangkalan kad Sysweb langsung tidak sependapat', 'The local copy and the live Sysweb card database disagree'],
        ['No. pengenalan dipakai dengan UID Sysweb berbeza', 'Identifier number used with a different Sysweb UID'],
        ['UID kad dipakai oleh pengenalan lain', 'Card UID used by another identifier'],
        ['No. siri kad berkembar', 'Duplicate card serial number'],
        ['Sepadan rekod kad', 'Matches card record'], ['Hanya rekod UDA', 'UDA record only'],
        ['Rekod kad bercanggah', 'Conflicting card record'], ['UID tidak sepadan', 'UID mismatch'],
        ['Tiada dalam rekod kad', 'Not in card records'], ['Belum semak silang', 'Not cross-checked yet'],
        ['Rekod kad dibaca langsung daripada pangkalan data Sysweb (', 'Card records read directly from the Sysweb database ('],
        ['pengenalan ditemui)', 'identifiers found)'],
        ['Salinan tempatan digunakan: pangkalan data Sysweb tidak dapat dihubungi.', 'Local copy used: the Sysweb database could not be reached.'],
        ['Salinan tempatan digunakan: jadual kad Sysweb tidak dapat dibaca.', 'Local copy used: the Sysweb card table could not be read.'],
        ['Tiada pengenalan untuk disemak dengan pangkalan data Sysweb.', 'No identifiers to check against the Sysweb database.'],
        ['belum dikonfigurasi.', 'not configured yet.'],
        ['rekod kad: Sysweb langsung', 'card records: live Sysweb'],
        ['rekod kad: salinan tempatan', 'card records: local copy'],
        ['rekod boleh dilengkap medan yang tiada daripada rekod kad.', 'records can have missing fields filled from the card records.'],
        ['Tiada percanggahan atau medan kosong yang boleh dilengkap.', 'No conflicts or empty fields to fill.'],
        ['Diresahkan terhadap pangkalan kad Sysweb:', 'Resolved against the Sysweb card database:'],
        ['Tiada semakan langsung dijalankan:', 'No live check was run:'],
        ['keputusan bergantung pada salinan tempatan sahaja.', 'results depend on the local copy only.'],
        ['Hanya medan yang kosong dilengkap; nilai yang admin taip tidak diubah.', 'Only empty fields are filled; values typed by the admin are not changed.'],
        ['Hanya Admin boleh menyemak silang rekod Sysweb.', 'Only Admins can cross-check Sysweb records.'],
        ['Menyemak dengan rekod kad…', 'Checking against card records…'],
        ['Semakan silang dengan rekod kad gagal.', 'Cross-check against card records failed.'],
        ['Hanya Admin boleh melengkap rekod Sysweb.', 'Only Admins can fill in Sysweb records.'],
        ['Gagal melengkapkan rekod Sysweb.', 'Failed to fill in Sysweb records.'],
        ['Medan rekod Sysweb dilengkap daripada rekod kad.', 'Sysweb record fields filled from the card records.'],
        ['Tapis rekod dengan isu ini', 'Filter records with this issue'],
        ['Tiada isu data ditemui pada kesemua rekod tersimpan.', 'No data issues found in any saved record.'],
        ['Semakan merangkumi nama, pengenalan, UID, susunan byte, prefiks Sysweb, no. siri, tarikh kad dan status kata laluan.', 'The check covers name, identifier, UID, byte order, Sysweb prefix, serial number, card dates and password status.'],
        ['Belum ada rekod untuk disemak.', 'No records to check yet.'],
        /* ---- Sysweb: passwords & record editing ---- */
        ['Permintaan kata laluan ditolak.', 'Password request denied.'],
        ['Hanya Admin boleh memaparkan kata laluan.', 'Only Admins can view passwords.'],
        ['Kata laluan dipaparkan buat sementara. Paparan ini tercatat dalam audit log.', 'Password shown temporarily. This view is recorded in the audit log.'],
        ['Hanya Admin boleh menyalin kata laluan.', 'Only Admins can copy passwords.'],
        ['Kata laluan disalin. Tindakan ini tercatat dalam audit log.', 'Password copied. This action is recorded in the audit log.'],
        ['Papan keratan tidak tersedia.', 'Clipboard unavailable.'],
        ['Hanya Admin boleh set semula kata laluan.', 'Only Admins can reset passwords.'],
        ['Rekod Sysweb tidak ditemui. Muat semula senarai.', 'Sysweb record not found. Reload the list.'],
        ['Set semula kata laluan Sysweb untuk', 'Reset the Sysweb password for'],
        ['? Kata laluan lama tidak lagi sah.', '? The old password will no longer be valid.'],
        ['Kata laluan baharu dijana dan hanya dipaparkan sekali.', 'A new password was generated and is shown only once.'],
        ['Hanya Admin boleh menjana kata laluan.', 'Only Admins can generate passwords.'],
        ['Dijana oleh pelayan', 'Generated by the server'],
        ['Format UID sah —', 'UID format valid —'],
        ['UID mesti aksara hexadecimal sahaja dan bilangan aksara genap.', 'The UID must contain only hexadecimal characters and have an even character count.'],
        ['UID mentah mesti aksara hexadecimal dengan bilangan aksara genap.', 'The raw UID must be hexadecimal with an even character count.'],
        ['UID converted perlu pembetulan dahulu.', 'The converted UID needs correction first.'],
        ['Tidak sepadan — little-endian UID mentah ialah', 'Mismatch — the little-endian raw UID is'],
        ['UID mentah mesti hexadecimal dengan bilangan aksara genap.', 'The raw UID must be hexadecimal with an even character count.'],
        ['Tambah Rekod Sysweb Baharu', 'Add New Sysweb Record'],
        ['Rekod manual ditambah terus ke pangkalan integrasi Sysweb.', 'Manual records are added directly to the Sysweb integration database.'],
        ['Nama dan nombor pengenalan wajib diisi.', 'Name and identifier number are required.'],
        ['UID converted mesti hexadecimal dengan bilangan aksara genap.', 'The converted UID must be hexadecimal with an even character count.'],
        ['Susunan byte UID converted tidak sepadan dengan UID mentah kad.', "The converted UID byte order does not match the card's raw UID."],
        ['Gagal menyimpan rekod Sysweb.', 'Failed to save the Sysweb record.'],
        ['Ralat rangkaian semasa menyimpan rekod.', 'Network error while saving the record.'],
        ['Padam rekod Sysweb untuk', 'Delete the Sysweb record for'],
        ['? Tindakan ini tidak boleh diundur.', '? This action cannot be undone.'],
        ['Gagal memadam rekod.', 'Failed to delete the record.'],
        ['Ralat memadam rekod.', 'Error deleting the record.'],
        ['disalin ke papan keratan.', 'copied to the clipboard.'],
        /* ---- Sysweb: export history, resend, send flow ---- */
        ['Gagal membaca sejarah.', 'Failed to read the history.'],
        ['Belum ada sejarah eksport Sysweb.', 'No Sysweb export history yet.'],
        ['Tiada batch sepadan dengan julat atau tapisan ini.', 'No batches match this range or filter.'],
        ['Tidak dapat memuatkan sejarah:', 'Could not load the history:'],
        ['Hanya Admin boleh menghantar semula rekod.', 'Only Admins can resend records.'],
        ['Tiada rekod gagal untuk batch ini.', 'No failed records for this batch.'],
        ['Rekod gagal tidak dapat dipulihkan.', 'The failed records could not be recovered.'],
        ['Penghantaran semula selesai.', 'Resend complete.'],
        ['Penghantaran semula sebahagian/gagal.', 'Resend partially completed/failed.'],
        ['Gagal menghantar semula:', 'Failed to resend:'],
        ['Tiada rekod Sysweb untuk dimuat turun.', 'No Sysweb records to download.'],
        ['Hanya Admin boleh mengeksport kata laluan.', 'Only Admins can export passwords.'],
        ['Eksport ini akan menyertakan kata laluan Sysweb bagi', 'This export will include Sysweb passwords for'],
        ['rekod dalam satu fail. Fail tersebut tidak dilindungi dan eksport ini direkodkan dalam audit log. Teruskan?', 'records in one file. The file is unprotected and this export is recorded in the audit log. Continue?'],
        ['rekod dimuat turun BERSAMA kata laluan. Simpan fail ini dengan selamat.', 'records downloaded WITH passwords. Keep this file safe.'],
        ['rekod Sysweb dimuat turun tanpa kata laluan (ikut paparan penapis semasa).', 'Sysweb records downloaded without passwords (per the current filter view).'],
        ['Adakah anda pasti ingin mengosongkan semua rekod integrasi Sysweb? Tindakan ini tidak boleh diundur.', 'Are you sure you want to clear all Sysweb integration records? This action cannot be undone.'],
        ['Rekod Sysweb telah dikosongkan.', 'Sysweb records have been cleared.'],
        ['Gagal mengosongkan rekod.', 'Failed to clear the records.'],
        ['Penghantaran dibatalkan — kesemua', 'Submission cancelled — all'],
        ['rekod kepada penerima integrasi Sysweb', 'records to the Sysweb integration receiver'],
        ['rekod selebihnya', 'remaining records'],
        ['rekod Tidak sah dihalang oleh pusat semakan)', 'Invalid records blocked by the review center)'],
        ['... Sila tunggu.', '... Please wait.'],
        ['rekod baharu disimpan', 'new records saved'],
        ['berulang dilangkau', 'duplicates skipped'],
        ['ditolak pelayan', 'rejected by the server'],
        ['dihalang tempatan', 'blocked locally'],
        ['tercatat dalam sejarah.', 'recorded in the history.'],
        ['dihalang pusat semakan)', 'blocked by the review center)'],
        ['. Kata laluan dijana oleh pelayan untuk setiap rekod baharu.', '. Passwords were generated by the server for each new record.'],
        ['dihalang pusat semakan', 'blocked by the review center'],
        ['rekod baharu disimpan (', 'new records saved ('],
        ['rekod berulang dilangkau)', 'duplicate records skipped)'],
        ['Pastikan Apache dan pangkalan data sedang berjalan.', 'Make sure Apache and the database are running.'],
        ['Pilihan Pelajar (', 'Selected Students ('],
        ['rekod pelajar dipilih sedia untuk Sysweb.', 'student records selected and ready for Sysweb.'],
        ['Direktori Pelajar (', 'Student Directory ('],
        ['rekod pelajar sedia untuk Sysweb.', 'student records ready for Sysweb.'],
        ['Direktori Staf (', 'Staff Directory ('],
        ['rekod staf sedia untuk Sysweb.', 'staff records ready for Sysweb.'],
        ['rekod berstatus Tidak sah.', 'records are marked Invalid.'],
        ['rekod berstatus Tidak sah dan akan DIHALANG daripada penghantaran ini:', 'records are marked Invalid and will be BLOCKED from this submission:'],
        ['rekod berstatus Tidak sah dan akan dihalang daripada penghantaran.', 'records are marked Invalid and will be blocked from submission.'],
        ['rekod terpilih dieksport tanpa kata laluan.', 'selected records exported without passwords.'],
        /* ---- School / policy selectors ---- */
        ['Pilih pusat pengajian untuk mencadangkan polisi palang.', 'Select a school to suggest a turnstile policy.'],
        ['Pusat pengajian ini tiada polisi lalai. Kosong bermakna Tiada polisi.', 'This school has no default policy. Empty means No policy.'],
        ['Dipautkan automatik daripada pusat pengajian:', 'Automatically linked from the school:'],
        ['Polisi sedia ada dikekalkan. Pautan sekolah:', 'Existing policy kept. School link:'],
        ['Senarai sekolah tidak sah.', 'Invalid school list.'],
        ['Pilih sekolah / pusat pengajian', 'Select a school'],
        ['Gagal memuatkan pilihan sekolah daripada SQL Server:', 'Failed to load school options from SQL Server:'],
        ['Sekolah tidak dapat dimuatkan — semak sambungan SQL Server', 'Schools could not be loaded — check the SQL Server connection'],
        /* ---- Data quality ---- */
        ['Sedang mengimbas kualiti data...', 'Scanning data quality...'],
        ['Imbasan kualiti data gagal.', 'Data quality scan failed.'],
        ['Imbasan gagal.', 'Scan failed.'], ['Imbasan tidak berjaya.', 'Scan was not successful.'],
        ['Tiada isu sepadan. Data kelihatan bersih untuk penapis ini.', 'No matching issues. The data looks clean for this filter.'],
        ['Pratonton padanan', 'Match preview'],
        ['Kad / UID tidak sah', 'Invalid card / UID'], ['Kad luput', 'Expired card'],
        ['Tiada pusat pengajian', 'No school'], ['Lain-lain', 'Other'],
        ['Tiada isu ditemui — data kelihatan bersih untuk semua rekod yang diimbas.', 'No issues found — the data looks clean for all scanned records.'],
        ['Tiada isu bagi tapisan ini.', 'No issues for this filter.'],
        ['Menapis jenis isu:', 'Filtering issue type:'],
        ['Menunjukkan semua jenis isu', 'Showing all issue types'],
        ['Isu tanpa pusat pengajian tidak boleh dicari.', 'Issues without a school cannot be searched.'],
        ['Menapis pusat pengajian:', 'Filtering school:'],
        ['Menunjukkan semua pusat pengajian', 'Showing all schools'],
        ['Pendua dilangkau', 'Duplicates skipped'],
        ['1 - 5 pelajar', '1 - 5 students'], ['6 - 20 pelajar', '6 - 20 students'],
        ['21 - 50 pelajar', '21 - 50 students'], ['lebih 50 pelajar', 'more than 50 students'],
        /* ---- History charts (exports / sessions) ---- */
        ['Mgg', 'Wk'], ['Bersihkan tapisan:', 'Clear filter:'], ['Tiada pengguna', 'No users'],
        ['Tiada sejarah eksport untuk dipaparkan.', 'No export history to display.'],
        ['Belum ada sejarah eksport Sysweb untuk dipaparkan.', 'No Sysweb export history to display yet.'],
        ['Tiada batch dalam julat atau tapisan ini.', 'No batches in this range or filter.'],
        ['Bilangan batch', 'Number of batches'], ['Batch eksport', 'Export batch'],
        ['Menunjukkan semua batch.', 'Showing all batches.'],
        ['Menapis sejarah eksport:', 'Filtering export history:'],
        ['Tapisan sejarah eksport dibersihkan.', 'Export history filter cleared.'],
        ['Belum ada sesi penghantaran untuk dipaparkan.', 'No submission sessions to display yet.'],
        ['Statistik sesi hanya tersedia untuk Admin.', 'Session statistics are only available to Admins.'],
        ['Tiada sesi dalam julat atau tapisan ini.', 'No sessions in this range or filter.'],
        ['Menunjukkan semua sesi.', 'Showing all sessions.'],
        ['Menapis sejarah sesi:', 'Filtering session history:'],
        ['Tapisan sejarah sesi dibersihkan.', 'Session history filter cleared.'],
        /* ---- Duplicate preview / staff directory ---- */
        ['Rekod padanan tidak tersedia. Jalankan Imbas Semula.', 'Matching records unavailable. Run a rescan.'],
        ['ID rekod', 'Record ID'], ['Edit rekod ini', 'Edit this record'],
        ['Buka untuk pembetulan', 'Open for correction'], ['Medan:', 'Field:'],
        ['Rekod dipilih', 'Selected record'], ['Rekod yang sepadan', 'Matching record'],
        ['Perbandingan baca sahaja. Medan yang sama ditandakan. Gunakan butang Edit pada kad yang berkaitan untuk membetulkan data. Tiada rekod dipadam atau digabungkan secara automatik.', 'Read-only comparison. Identical fields are highlighted. Use the Edit button on the relevant card to correct the data. No records are deleted or merged automatically.'],
        ['Rekod tidak ditemui dalam direktori. Memuatkan semula senarai...', 'Record not found in the directory. Reloading the list...'],
        ['Tiada isu untuk dieksport. Jalankan imbasan dahulu.', 'No issues to export. Run a scan first.'],
        ['Hanya Admin boleh mengimport data staf.', 'Only Admins can import staff data.'],
        ['Import/sync rekod daripada dbo.Staf ke direktori MySQL tempatan?', 'Import/sync records from dbo.Staf into the local MySQL directory?'],
        ['Rekod rasmi SQL Server tidak akan diubah. Rekod tempatan dengan nombor staf sama akan dikemas kini.', 'The official SQL Server records will not be changed. Local records with the same staff number will be updated.'],
        ['Mengimport…', 'Importing…'], ['Import staf gagal.', 'Staff import failed.'],
        ['Import selesai.', 'Import complete.'],
        ['Import staf gagal. Semak log Apache.', 'Staff import failed. Check the Apache log.'],
        ['Sedang memuatkan rekod staf tempatan…', 'Loading local staff records…'],
        ['Menghubungkan ke MySQL tempatan…', 'Connecting to the local MySQL…'],
        ['Respons direktori staf tidak sah.', 'Invalid staff directory response.'],
        ['Data ujian dimuatkan daripada MySQL tempatan. Rekod rasmi SQL Server tidak diubah.', 'Test data loaded from the local MySQL. The official SQL Server records are unchanged.'],
        ['Direktori staf tidak dapat dimuatkan.', 'The staff directory could not be loaded.'],
        ['Gagal memuatkan data. Semak sambungan MySQL dan kebenaran jadual.', 'Failed to load data. Check the MySQL connection and table permissions.'],
        ['Tiada rekod staf tempatan. Tekan “Tambah Staf” untuk mula.', 'No local staff records. Press “Add Staff” to start.'],
        ['Hanya Admin boleh mengurus rekod staf.', 'Only Admins can manage staff records.'],
        ['Kemaskini rekod staf', 'Update staff record'],
        ['Rekod staf tidak ditemui. Muat semula senarai.', 'Staff record not found. Reload the list.'],
        ['Rekod staf gagal disimpan.', 'Staff record could not be saved.'],
        ['Rekod staf berjaya disimpan.', 'Staff record saved successfully.'],
        ['Padam rekod staf ujian tempatan untuk', 'Delete the local test staff record for'],
        ['Tindakan ini tidak mengubah dbo.Staf di SQL Server.', 'This action does not change dbo.Staf on SQL Server.'],
        ['Rekod staf gagal dipadam.', 'Staff record could not be deleted.'],
        ['Rekod staf dipadam.', 'Staff record deleted.'],
        /* ---- Student editing / card status ---- */
        ['Tambah Pelajar Baharu', 'Add New Student'],
        ['Menjana nombor siri…', 'Generating serial number…'],
        ['UID HEX TIDAK SAH', 'INVALID HEX UID'],
        ['Ralat: Nama dan nombor matrik wajib diisi.', 'Error: Name and matric number are required.'],
        ['Ralat: Tarikh tamat mesti sama atau selepas tarikh mula.', 'Error: The expiry date must be on or after the start date.'],
        ['Sahkan kemas kini rekod pelajar ini?', 'Confirm updating this student record?'],
        ['Sahkan penambahan pelajar baharu?', 'Confirm adding the new student?'],
        ['Pastikan maklumat adalah betul sebelum meneruskan.', 'Make sure the information is correct before continuing.'],
        ['Rekod tidak dapat disimpan.', 'The record could not be saved.'],
        ['Tamat dalam', 'Expires in'],
        ['Tiada kad tamat tempoh atau akan tamat dalam', 'No cards expired or expiring within'],
        ['Cuba kata kunci lain atau kosongkan penapis untuk melihat semua pelajar.', 'Try another keyword or clear the filters to see all students.'],
        ['Kosongkan carian dan penapis', 'Clear search and filters'],
        ['Lihat profil', 'View profile'],
        ['Edit Pelajar / Request', 'Edit Student / Request'],
        ['Padam Pelajar / Request', 'Delete Student / Request'],
        ['rekod terlibat dalam kemungkinan pendua berdasarkan matrik, UID atau nombor kad. Sila sahkan sebelum membuat perubahan.', 'records are involved in possible duplicates based on matric, UID or card number. Please verify before making changes.'],
        ['Lihat semua rekod', 'View all records'],
        /* ---- Card history / student profile ---- */
        ['Sejarah tidak tersedia.', 'History unavailable.'], ['Rekod sejarah', 'History records'],
        ['UID semasa', 'Current UID'], ['Nombor kad', 'Card number'],
        ['Belum ada sejarah kad.', 'No card history yet.'],
        ['Kad direkodkan', 'Card recorded'], ['Butiran kad dikemaskini', 'Card details updated'],
        ['Peristiwa', 'Event'], ['Sejarah kad gagal dimuatkan.', 'Card history failed to load.'],
        ['Rekod pelajar tidak ditemui. Muat semula senarai dan cuba lagi.', 'Student record not found. Reload the list and try again.'],
        ['Profil pelajar', 'Student profile'], ['Tarikh luput tiada', 'No expiry date'],
        ['Tiada tarikh luput yang sah untuk dipaparkan.', 'No valid expiry date to display.'],
        ['Hampir luput', 'Expiring soon'], ['Luput hari ini.', 'Expires today.'],
        ['Luput tidak lama lagi', 'Expiring shortly'], ['Nombor matrik', 'Matric number'],
        ['Semakan diperlukan: nilai', 'Review needed: the value'],
        ['sepadan dengan sekurang-kurangnya satu rekod lain.', 'matches at least one other record.'],
        ['Rekod semasa', 'Current record'], ['Rekod sepadan', 'Matching record'],
        ['Tiada rekod sepadan ditemui dalam rekod yang sedang dimuatkan. Cuba muat semula data sebelum membuat keputusan.', 'No matching record found among the loaded records. Try reloading the data before deciding.'],
        ['tiada untuk disalin.', 'not available to copy.'],
        ['Tidak dapat menyalin secara automatik.', 'Could not copy automatically.'],
        /* ---- Bulk actions / quick filters ---- */
        ['Pilih maksimum 500 rekod untuk tindakan pukal. Gunakan penapisan untuk mengecilkan senarai.', 'Select at most 500 records for bulk actions. Use filtering to narrow the list.'],
        ['Pilih pelajar yang hendak dipadam.', 'Select the students to delete.'],
        ['Had maksimum ialah 500 rekod bagi setiap tindakan.', 'The maximum is 500 records per action.'],
        ['rekod pelajar secara kekal? Tindakan ini tidak boleh dibuat asal.', 'student records permanently? This action cannot be undone.'],
        ['Pemadaman pukal gagal.', 'Bulk deletion failed.'], ['Rekod dipadam.', 'Records deleted.'],
        ['Ralat memadam rekod secara pukal.', 'Error deleting records in bulk.'],
        ['Pilih sekurang-kurangnya seorang pelajar di Direktori dahulu.', 'Select at least one student in the Directory first.'],
        ['Bilangan hari untuk dilanjutkan', 'Number of days to extend'],
        ['Masukkan nombor bulat dari 1 hingga 1825.', 'Enter a whole number from 1 to 1825.'],
        ['Tarikh luput baharu', 'New expiry date'], ['Pilih tarikh luput', 'Choose an expiry date'],
        ['Pilih tarikh dalam format YYYY-MM-DD.', 'Choose a date in YYYY-MM-DD format.'],
        ['Nama polisi baharu', 'New policy name'],
        ['Masukkan nama polisi yang hendak digunakan.', 'Enter the policy name to use.'],
        ['Nama pusat pengajian baharu', 'New school name'],
        ['Masukkan nama pusat pengajian', 'Enter the school name'],
        ['Nama ini akan digunakan untuk semua rekod terpilih.', 'This name will be applied to all selected records.'],
        ['Tiada rekod dipilih. Kembali ke direktori dan pilih pelajar.', 'No records selected. Return to the directory and select students.'],
        ['Lengkapkan jenis kemas kini dan nilai.', 'Complete the update type and value.'],
        ['Bilangan hari mesti antara 1 hingga 1825.', 'The number of days must be between 1 and 1825.'],
        ['Pilih tarikh luput yang sah.', 'Choose a valid expiry date.'],
        ['Sahkan kemas kini', 'Confirm updating'],
        ['rekod pelajar? Tindakan ini akan mengubah data dalam pangkalan data.', 'student records? This action will change data in the database.'],
        ['Kemas kini pukal gagal.', 'Bulk update failed.'],
        ['Rekod berjaya dikemas kini.', 'Records updated successfully.'],
        ['Ralat mengemas kini rekod secara pukal.', 'Error updating records in bulk.'],
        ['Pilih pelajar yang hendak dikemas kini.', 'Select the students to update.'],
        ['Pilih jenis kemas kini:', 'Choose an update type:'],
        ['1 = Lanjut tempoh (hari)', '1 = Extend validity (days)'],
        ['2 = Tetapkan tarikh luput (YYYY-MM-DD)', '2 = Set expiry date (YYYY-MM-DD)'],
        ['3 = Tukar polisi', '3 = Change policy'], ['4 = Tukar pusat pengajian', '4 = Change school'],
        ['Pilihan kemas kini tidak sah.', 'Invalid update choice.'],
        ['Bilangan hari untuk dilanjutkan (1–1825)', 'Number of days to extend (1–1825)'],
        ['Tarikh luput baharu (YYYY-MM-DD)', 'New expiry date (YYYY-MM-DD)'],
        ['Teruskan kemas kini', 'Proceed with updating'],
        ['Rekod dikemas kini.', 'Records updated.'],
        ['Pelajar Kad Hampir Luput (≤ 30 Hari)', 'Students with Cards Expiring Soon (≤ 30 Days)'],
        ['Pelajar Kad Tamat Segera (≤ 7 Hari)', 'Students with Cards Expiring Imminently (≤ 7 Days)'],
        ['Pelajar Kad Tamat (≤ 14 Hari)', 'Students with Cards Expiring (≤ 14 Days)'],
        ['Pelajar Kad Telah Tamat Tempoh', 'Students with Expired Cards'],
        ['Pelajar Kad Masih Aktif', 'Students with Still-Active Cards'],
        /* ---- Card modal / scanner / avatar ---- */
        ['(Tamat tempoh)', '(Expired)'], ['(Aktif)', '(Active)'],
        ['Pustaka pengimbas tidak dapat dimuatkan', 'The scanner library could not be loaded'],
        ['. Semak sambungan dan cuba lagi.', '. Check the connection and try again.'],
        ['Berjaya Imbas: ', 'Scan successful: '],
        ['Ralat kamera: Sila pastikan kebenaran kamera diberikan.', 'Camera error: please make sure camera permission is granted.'],
        /* ---- Passkeys ---- */
        ['Passkey tidak tersedia dalam pelayar atau sambungan ini.', 'Passkeys are not available in this browser or connection.'],
        ['Senarai passkey tidak dapat dibaca.', 'The passkey list could not be read.'],
        ['Belum ada passkey. Tambah satu untuk log masuk tanpa kata laluan.', 'No passkeys yet. Add one to log in without a password.'],
        ['Belum digunakan untuk log masuk', 'Not yet used to log in'],
        ['Passkey tidak dapat dibaca:', 'The passkey could not be read:'],
        ['Passkey memerlukan HTTPS atau localhost dan pelayar moden.', 'Passkeys require HTTPS or localhost and a modern browser.'],
        ['Pendaftaran passkey tidak dapat dimulakan.', 'Passkey registration could not be started.'],
        ['Passkey tidak dapat disimpan.', 'The passkey could not be saved.'],
        ['Passkey berjaya didaftarkan.', 'Passkey registered successfully.'],
        ['Pendaftaran dibatalkan atau tamat masa. Cuba lagi.', 'Registration was cancelled or timed out. Try again.'],
        ['Pendaftaran passkey gagal.', 'Passkey registration failed.'],
        ['Buang passkey ini? Anda masih boleh log masuk dengan kata laluan.', 'Remove this passkey? You can still log in with your password.'],
        ['Passkey tidak dapat dibuang.', 'The passkey could not be removed.'],
        ['Passkey dibuang.', 'Passkey removed.'], ['Gagal membuang passkey:', 'Failed to remove the passkey:'],
        /* ---- Avatar / profile ---- */
        ['Pilih satu fail imej.', 'Choose an image file.'], ['Pilih fail JPG atau PNG.', 'Choose a JPG or PNG file.'],
        ['Imej terlalu besar (maksimum 8 MB).', 'Image too large (maximum 8 MB).'],
        ['Fail tidak dapat dibaca.', 'The file could not be read.'],
        ['Imey tidak dapat dimuatkan dari fail itu.', 'The image could not be loaded from that file.'],
        ['Pelayar ini tidak menyokong pemprosesan imej.', 'This browser does not support image processing.'],
        ['Gambar tidak dapat disimpan.', 'The picture could not be saved.'],
        ['Menyimpan gambar...', 'Saving picture...'],
        ['Gambar profil dikemas kini.', 'Profile picture updated.'],
        ['Gambar profil dibuang.', 'Profile picture removed.'],
        ['Profil tidak dapat dimuatkan.', 'The profile could not be loaded.'],
        ['Gagal memuatkan profil:', 'Failed to load the profile:'],
        ['Profil tidak dapat disimpan.', 'The profile could not be saved.'],
        ['Gagal menyimpan profil:', 'Failed to save the profile:'],
        /* ---- Google linking / password change ---- */
        ['Gagal memautkan akaun Google.', 'Failed to link the Google account.'],
        ['Pautan Google belum diaktifkan oleh pentadbir. Gunakan alamat Gmail manual di bawah sebagai alternatif.', 'Google linking has not been enabled by the administrator. Use the manual Gmail address below as an alternative.'],
        ['Perkhidmatan Google belum dimuatkan. Semak sambungan internet dan cuba sebentar lagi.', 'The Google service has not loaded. Check your internet connection and try again shortly.'],
        ['Sila pilih akaun Google anda pada butang Google di atas — tanpa perlu menaip.', 'Please pick your Google account on the Google button above — no typing needed.'],
        ['Adakah anda pasti untuk memutuskan pautan akaun Google? Anda tidak akan menerima OTP Gmail sekiranya terlupa kata laluan.', 'Are you sure you want to unlink the Google account? You will not receive Gmail OTPs if you forget your password.'],
        ['Gagal memutuskan pautan akaun.', 'Failed to unlink the account.'],
        ['Kata laluan baharu tidak sepadan.', 'The new passwords do not match.'],
        ['Tukar kata laluan akan melog keluar anda daripada semua peranti. Teruskan?', 'Changing the password will log you out of all devices. Continue?'],
        ['Kata laluan tidak dapat ditukar.', 'The password could not be changed.'],
        ['Gagal menukar kata laluan:', 'Failed to change the password:'],
        ['✗ Kata laluan tidak sepadan', '✗ Passwords do not match'],
        /* ---- Account management ---- */
        ['Mencipta...', 'Creating...'],
        ['Akaun tidak dapat dibuat', 'The account could not be created'],
        ['Akaun berjaya dibuat', 'Account created successfully'],
        ['Gagal mencipta akaun:', 'Failed to create the account:'],
        ['Senarai pengguna tidak tersedia', 'The user list is unavailable'],
        ['Gagal memuatkan senarai akaun.', 'Failed to load the account list.'],
        ['Pengurusan pengguna gagal:', 'User management failed:'],
        ['Tiada akaun sepadan dengan carian.', 'No accounts match the search.'],
        ['Tukar peranan', 'Change role'],
        ['Jana pautan set semula sekali guna', 'Generate a one-time reset link'],
        ['Nyahaktifkan akaun', 'Deactivate account'], ['Aktifkan akaun', 'Activate account'],
        ['Padam akaun', 'Delete account'], ['Akaun tidak ditemui.', 'Account not found.'],
        ['Aktifkan', 'Activate'], ['Nyahaktifkan', 'Deactivate'],
        ['secara kekal. Taip nama pengguna untuk mengesahkan:', 'permanently. Type the username to confirm:'],
        ['Nama pengguna tidak sepadan. Pemadaman dibatalkan.', 'The username does not match. Deletion cancelled.'],
        ['Kemas kini gagal', 'Update failed'],
        ['Akaun berjaya dikemas kini', 'Account updated successfully'],
        ['Gagal mengurus akaun:', 'Failed to manage the account:'],
        /* ---- Reset request queue ---- */
        ['Senarai permintaan tidak tersedia', 'The request list is unavailable'],
        ['E-mel dihantar', 'Email sent'], ['Menunggu pentadbir', 'Waiting for admin'],
        ['Jana pautan sekali guna (membatalkan pautan sedia ada)', 'Generate a one-time link (cancels any existing link)'],
        ['Jana pautan', 'Generate link'], ['Batalkan permintaan ini', 'Cancel this request'],
        ['Permintaan set semula gagal dimuatkan:', 'Reset requests failed to load:'],
        ['Jana pautan set semula sekali guna untuk', 'Generate a one-time reset link for'],
        ['? Pautan sedia ada (jika ada) akan dibatalkan.', '? Any existing link will be cancelled.'],
        ['Pautan tidak dapat dijana', 'The link could not be generated'],
        ['Gagal menjana pautan:', 'Failed to generate the link:'],
        ['Pautan disalin. Serahkan kepada pengguna melalui saluran selamat.', 'Link copied. Deliver it to the user through a secure channel.'],
        ['Salin manual: pautan telah dipilih, tekan Ctrl+C.', 'Manual copy: the link is selected, press Ctrl+C.'],
        ['Batalkan permintaan set semula untuk', 'Cancel the reset request for'],
        ['? Pautan yang belum digunakan akan dimansuhkan.', '? Any unused link will be revoked.'],
        ['Permintaan tidak dapat dibatalkan', 'The request could not be cancelled'],
        ['Permintaan dibatalkan', 'Request cancelled'], ['Gagal membatalkan:', 'Failed to cancel:'],
        /* ---- Audit log ---- */
        ['Tambah rekod', 'Add record'], ['Pemadaman', 'Deletion'],
        ['Eksport / UDA', 'Export / UDA'], ['Sesi log masuk', 'Login session'],
        ['Sandaran', 'Backup'], ['Pemulihan', 'Restore'],
        ['Statistik log tidak tersedia.', 'Log statistics unavailable.'],
        ['Statistik log tidak dapat dimuatkan.', 'Log statistics could not be loaded.'],
        ['Tiada peristiwa bagi tapisan ini.', 'No events for this filter.'],
        ['Tiada data minggu lepas', 'No data for last week'],
        ['Menapis statistik log:', 'Filtering log statistics:'],
        ['Menunjukkan semua tindakan', 'Showing all actions'],
        ['Rekod tanpa nama pengguna tidak boleh dicari.', 'Records without a username cannot be searched.'],
        ['Menapis log pengguna:', 'Filtering user log:'],
        ['Menunjukkan semua pengguna', 'Showing all users'],
        ['Gagal memuatkan rekod', 'Failed to load records'],
        ['Tiada log sejarah rekod dijumpai bagi tapisan semasa.', 'No record history logs found for the current filter.'],
        ['Ralat memuatkan log:', 'Error loading the log:'],
        ['IMPORT CSV', 'CSV IMPORT'], ['EKSPORT CSV', 'CSV EXPORT'], ['SIMPAN UDA', 'UDA SAVE'],
        ['Tiada perincian perubahan.', 'No change details.'],
        ['Rekod dikemas kini tanpa perubahan medan.', 'Record updated with no field changes.'],
        ['(kosong)', '(empty)'],
        ['Butiran audit tidak ditemui. Muat semula senarai.', 'Audit details not found. Reload the list.'],
        ['Tiada perincian tambahan direkodkan.', 'No additional details recorded.'],
        ['ID Audit', 'Audit ID'],

        /* ---- dashboard.js: backup / restore / CSV import ---- */
        ['Akses ditolak — hanya Admin boleh mengurus sandaran.', 'Access denied — only Admins can manage backups.'],
        ['Status sandaran tidak tersedia.', 'Backup status unavailable.'],
        ['Automatik', 'Automatic'],
        ['Belum ada sandaran tersimpan.', 'No backups saved yet.'],
        ['Sandaran gagal.', 'Backup failed.'],
        ['Sandaran telah disimpan pada pelayan. Muat turun salinan juga?', 'The backup has been saved on the server. Download a copy too?'],
        ['Akses ditolak — hanya Admin boleh membuat sandaran pangkalan data.', 'Access denied — only Admins can create database backups.'],
        ['Menjana fail sandaran pangkalan data...', 'Generating the database backup file...'],
        ['SANDARAN PELAYAN', 'SERVER BACKUP'],
        ['Automatik: sandaran harian pada permintaan pertama selepas tarikh baharu. Simpanan: 14 hari. Fail baharu disahkan dengan struktur JSON, bilangan rekod dan checksum SHA-256.', 'Automatic: a daily backup on the first request after a new date. Retention: 14 days. New files are verified for JSON structure, record counts and a SHA-256 checksum.'],
        ['OK untuk cipta sandaran manual sekarang; Cancel untuk tutup.', 'OK to create a manual backup now; Cancel to close.'],
        ['Akses ditolak — hanya Admin boleh memulihkan sandaran.', 'Access denied — only Admins can restore backups.'],
        ['Pilih fail sandaran .JSON yang sah (maksimum 15 MB).', 'Choose a valid .JSON backup file (maximum 15 MB).'],
        ['Memeriksa fail sandaran dahulu...', 'Checking the backup file first...'],
        ['Pemeriksaan fail sandaran gagal.', 'Backup file check failed.'],
        ['AMARAN: Pulihkan pangkalan data daripada fail ini?', 'WARNING: Restore the database from this file?'],
        ['Rekod yang ditemui:', 'Records found:'],
        ['• Pelajar:', '• Students:'],
        ['• Sesi pengangkutan:', '• Transport sessions:'],
        ['• Audit dalam fail:', '• Audit entries in file:'],
        ['Pemeriksaan awal lulus, tetapi ini BUKAN ujian pemulihan sebenar. Pemulihan akan menggantikan rekod pelajar/UDA/sesi semasa. Teruskan hanya jika anda mempunyai sandaran semasa.', 'The pre-check passed, but this is NOT an actual restore test. Restoring will replace the current student/UDA/session records. Continue only if you have a current backup.'],
        ['Sedang memulihkan pangkalan data...', 'Restoring the database...'],
        ['Pemulihan gagal.', 'Restore failed.'],
        ['Sandaran/pemulihan:', 'Backup/restore:'],
        ['Ralat tidak diketahui', 'Unknown error'],
        ['Akses ditolak — hanya Admin boleh mengeksport CSV.', 'Access denied — only Admins can export CSV.'],
        ['Paparan maksimum 250 baris. Laporan ralat memuatkan semua baris bermasalah.', 'Showing at most 250 rows. The error report contains all problem rows.'],
        ['Siri', 'Serial'],
        ['Status / sebab', 'Status / reason'],
        ['Akses ditolak — hanya Admin boleh mengimport CSV.', 'Access denied — only Admins can import CSV.'],
        ['Pilih fail .csv yang tidak melebihi 5 MB.', 'Choose a .csv file no larger than 5 MB.'],
        ['Menganalisis CSV dan mengesan pendua...', 'Analyzing the CSV and detecting duplicates...'],
        ['Pratonton gagal.', 'Preview failed.'],
        ['Ralat pratonton CSV:', 'CSV preview error:'],
        ['Tiada ralat untuk dimuat turun.', 'No errors to download.'],
        ['Mengimport...', 'Importing...'],
        ['Import gagal.', 'Import failed.'],
        ['Ralat import CSV:', 'CSV import error:'],
        ['Cuba import semula', 'Try importing again'],

        /* ---- dashboard.js: live data pull (Transporter) ---- */
        ['Penapis dikosongkan', 'Filters cleared'],
        ['Semak julat tarikh dan tekan “Tarik untuk Transporter UDA” untuk memuatkan semula data.', 'Check the date range and press “Pull for UDA Transporter” to reload the data.'],
        ['Bilangan rekod tidak sah', 'Invalid record count'],
        ['Masukkan bilangan rekod yang sah.', 'Enter a valid record count.'],
        ['Masukkan nombor bulat yang lebih besar daripada sifar.', 'Enter a whole number greater than zero.'],
        ['Julat tarikh tidak sah', 'Invalid date range'],
        ['Pilih tarikh mula dan tarikh tamat yang sah.', 'Choose valid start and end dates.'],
        ['Pilih julat tarikh yang sah.', 'Choose a valid date range.'],
        ['Sedang menarik data…', 'Pulling data…'],
        ['Meminta sehingga', 'Requesting up to'],
        ['Menghubungkan ke Live Database...', 'Connecting to the Live Database...'],
        ['Gagal menarik data', 'Failed to pull data'],
        ['Permintaan tidak berjaya.', 'The request was not successful.'],
        ['Permintaan gagal.', 'The request failed.'],
        ['Format respons SQL Server tidak sah.', 'Invalid SQL Server response format.'],
        ['Sambungan berjaya — tiada rekod baharu', 'Connection successful — no new records'],
        ['SQL Server berjaya dihubungi, tetapi tiada rekod baharu sepadan dengan tarikh dan penapis yang dipilih. Rekod yang telah berada dalam UDA dikecualikan.', 'SQL Server was reached, but no new records match the chosen dates and filters. Records already in UDA are excluded.'],
        ['Tiada rekod baharu untuk julat tarikh ini.', 'No new records for this date range.'],
        ['Data berjaya dimuatkan', 'Data loaded successfully'],
        ['Berjaya ambil', 'Successfully fetched'],
        ['Tidak dapat memuatkan data', 'Could not load the data'],
        ['Ralat rangkaian atau format respons. Semak sambungan SQL Server dan cuba semula.', 'Network or response-format error. Check the SQL Server connection and try again.'],
        ['Ralat mengambil data:', 'Error fetching data:'],
        ['Semakan UID tidak dijalankan kerana tarikan gagal.', 'The UID check was not run because the pull failed.'],

        /* ---- dashboard.js: analytics (Analisis Grafik) ---- */
        ['awal', 'earliest'],
        ['terkini', 'latest'],
        ['Semua bulan', 'All months'],
        ['Tiada data untuk dipaparkan.', 'No data to display.'],
        ['USMSmart - Analisis Statistik', 'USMSmart - Statistical Analysis'],
        ['Ringkasan statistik kad pelajar', 'Student card statistics summary'],
        ['Ringkasan status kad (semua rekod)', 'Card status summary (all records)'],
        ['Jumlah rekod kad', 'Total card records'],
        ['Luput dalam 30 hari', 'Expiring within 30 days'],
        ['Paparan', 'View'],
        ['Julat bulan trend', 'Trend month range'],
        ['Jumlah pelajar', 'Total students'],
        ['Pusat terbesar', 'Largest school'],
        ['Pendaftaran terkini', 'Latest registration'],
        ['Pelajar mengikut pusat pengajian', 'Students by school'],
        ['Trend pendaftaran bulanan', 'Monthly registration trend'],
        ['Dijana oleh USMSmart pada', 'Generated by USMSmart on'],
        ['PDF ringkasan analisis dimuat turun.', 'Analysis summary PDF downloaded.'],
        ['Peratusan mengikut pusat pengajian', 'Percentage by school'],
        ['Taburan pelajar', 'Student distribution'],
        ['Jumlah mengikut pusat', 'Totals by school'],
        ['Perbandingan jumlah pelajar berdaftar', 'Comparison of registered student counts'],
        ['Perbandingan pendaftaran bagi setiap pusat pengajian', 'Registration comparison for each school'],
        ['Carta belum tersedia. Cuba lagi.', 'The chart is not ready yet. Try again.'],
        ['Gagal menjana PNG. Cuba lagi.', 'Failed to generate the PNG. Try again.'],
        ['PNG semua carta dimuat turun.', 'PNG of all charts downloaded.'],
        ['Carta belum tersedia. Buka tab Analisis Statistik dan cuba lagi.', 'The chart is not ready yet. Open the Statistical Analysis tab and try again.'],
        ['Kadar aktif (%)', 'Active rate (%)'],
        ['Pelajar di pusat terbesar', 'Students in the largest school'],
        ['Bulan', 'Month'],
        ['Trend Bulanan', 'Monthly Trend'],
        ['Laporan Excel analisis dimuat turun.', 'Analysis Excel report downloaded.'],
        ['Skop', 'Scope'],
        ['Carta ditapis untuk', 'Chart filtered for'],
        ['Menapis statistik:', 'Filtering statistics:'],
        ['Senarai dibuka untuk', 'List opened for'],
        ['semua pusat pengajian', 'all schools'],
        ['Memaparkan rekod', 'Showing record'],
        ['Tiada rekod mengikut pusat', 'No records by school'],
        ['Rekod bagi pusat yang dipilih', 'Records for the selected school'],
        ['Jumlah rekod dalam senarai semasa', 'Total records in the current list'],
        ['Bulan mula mesti sebelum atau sama dengan bulan akhir.', 'The start month must be before or equal to the end month.'],
        ['Tiada data bagi julat bulan dipilih.', 'No data for the selected month range.'],
        ['Gagal memaparkan carta perbandingan bulanan:', 'Failed to render the monthly comparison chart:'],

        /* ---- dashboard.js: statistics panels (sessions / quality / audit / exports) ---- */
        ['Semua hari', 'All days'],
        ['Statistik Sesi Penghantaran', 'Submission Session Statistics'],
        ['Sejarah sesi penghantaran UDA', 'UDA submission session history'],
        ['Jumlah sesi dalam pangkalan data', 'Total sessions in the database'],
        ['Nama sesi', 'Session name'],
        ['Jumlah rekod', 'Record count'],
        ['Trend sesi penghantaran', 'Submission session trend'],
        ['Bilangan sesi dan pelajar dihantar mengikut masa', 'Number of sessions and students sent over time'],
        ['Taburan sesi mengikut jumlah rekod', 'Session distribution by record count'],
        ['Sesi dengan rekod terbanyak dalam julat', 'Session with the most records in the range'],
        ['Belum diimbas', 'Not scanned yet'],
        ['Statistik Kualiti Data', 'Data Quality Statistics'],
        ['Statistik mengikut tapisan imbasan', 'Statistics per the scan filter'],
        ['Isu dalam tapisan', 'Issues in filter'],
        ['Rekod terjejas', 'Affected records'],
        ['Pusat terjejas', 'Affected schools'],
        ['Jumlah isu ditemui', 'Total issues found'],
        ['Imbasan terakhir', 'Last scan'],
        ['Trend isu bulanan', 'Monthly issue trend'],
        ['Bilangan isu mengikut bulan pendaftaran', 'Issue counts by registration month'],
        ['Campuran jenis isu', 'Issue type mix'],
        ['Taburan isu mengikut jenis', 'Issue distribution by type'],
        ['Pusat pengajian paling banyak isu', 'Schools with the most issues'],
        ['Isu mengikut pusat pengajian', 'Issues by school'],
        ['Statistik Log Audit', 'Audit Log Statistics'],
        ['Statistik mengikut tapisan log audit', 'Statistics per the audit log filter'],
        ['kini', 'now'],
        ['Peristiwa dalam julat carta', 'Events in chart range'],
        ['Jumlah dalam tapisan', 'Total in filter'],
        ['7 hari lepas', 'Last 7 days'],
        ['7 hari sebelumnya', 'Previous 7 days'],
        ['Pengguna aktif', 'Active users'],
        ['Julat hari carta', 'Chart day range'],
        ['Jumlah peristiwa', 'Event count'],
        ['Aktiviti harian', 'Daily activity'],
        ['Peristiwa mengikut hari dan kategori tindakan', 'Events by day and action category'],
        ['Campuran tindakan', 'Action mix'],
        ['Taburan jenis tindakan dalam tapisan', 'Distribution of action types in the filter'],
        ['Pengguna paling aktif', 'Most active users'],
        ['Bilangan peristiwa mengikut pengguna', 'Event counts by user'],
        ['Statistik Eksport Sysweb', 'Sysweb Export Statistics'],
        ['Sejarah eksport dan keputusan penghantaran', 'Export history and delivery results'],
        ['Batch dalam julat', 'Batches in range'],
        ['Rekod diproses', 'Records processed'],
        ['Rekod gagal', 'Failed records'],
        ['Trend eksport', 'Export trend'],
        ['Rekod disimpan, dilangkau dan gagal mengikut masa', 'Records saved, skipped and failed over time'],
        ['Status batch', 'Batch status'],
        ['Taburan batch mengikut keputusan penghantaran', 'Batch distribution by delivery result'],
        ['Pengguna paling banyak eksport', 'Users with the most exports'],
        ['Bilangan batch mengikut pengguna', 'Batch counts by user'],
        ['Statistik audit belum dimuatkan. Tukar julat atau tapisan dan cuba lagi.', 'Audit statistics not loaded yet. Change the range or filter and try again.'],
        ['PDF ringkasan dimuat turun.', 'Summary PDF downloaded.'],
        ['Carta belum tersedia. Buka panel statistik ini dan cuba lagi.', 'The chart is not ready yet. Open this statistics panel and try again.'],
        ['Laporan Excel dimuat turun.', 'Excel report downloaded.'],

        /* ---- dashboard.js: UDA policies / export verification ---- */
        ['Padam rekod pelajar ini secara kekal?', 'Delete this student record permanently?'],
        ['Ralat memadam rekod.', 'Error deleting the record.'],
        ['Senarai polisi gagal dimuatkan.', 'The policy list failed to load.'],
        ['Pilih polisi sedia ada dahulu.', 'Choose an existing policy first.'],
        ['Tetapkan polisi', 'Set policy'],
        ['Polisi tidak dapat disimpan.', 'The policy could not be saved.'],
        ['Ralat menetapkan polisi:', 'Error setting the policy:'],
        ['Polisi tiada', 'No policy'],
        ['Format nombor kad tidak sah', 'Invalid card number format'],
        ['Lulus semakan awal', 'Passed pre-check'],
        ['Perlu tindakan', 'Action needed'],
        ['Tiada polisi — disekat', 'No policy — blocked'],
        ['Pilih polisi…', 'Choose a policy…'],
        ['Eksport disekat:', 'Export blocked:'],
        ['rekod terpilih', 'selected record(s)'],
        ['Sesi berjaya direkodkan! Langkah seterusnya: Sysweb sedia dibuka.', 'Session recorded successfully! Next step: Sysweb is ready to open.'],

        /* ---- dashboard.js: sessions / UID eligibility ---- */
        ['Gagal muat sejarah sesi', 'Failed to load session history'],
        ['Tiada sesi sepadan dengan julat, tapisan atau carian ini.', 'No sessions match this range, filter or search.'],
        ['Namakan', 'Rename'],
        ['Masukkan nama sesi (maksimum 120 aksara):', 'Enter a session name (maximum 120 characters):'],
        ['Nama sesi wajib diisi dan maksimum 120 aksara.', 'The session name is required and limited to 120 characters.'],
        ['Nama sesi gagal disimpan.', 'The session name could not be saved.'],
        ['Nama sesi berjaya dikemas kini.', 'Session name updated successfully.'],
        ['Sesi dipadam dan pelajar dilepaskan.', 'Session deleted and students released.'],
        ['Ralat memadam sesi.', 'Error deleting the session.'],
        ['Menunggu rekod ditarik — semakan UID akan dibuat pada rekod yang diterima sahaja.', 'Waiting for records to be pulled — the UID check will run only on received records.'],
        ['Semakan UID tidak tersedia untuk tarikan ini.', 'The UID check is not available for this pull.'],
        ['Semakan pada', 'Checked on'],
        ['Tiada rekod layak.', 'No eligible records.'],
        ['Kad yang memegang UID', 'The card holding UID'],
        ['Rekod ini', 'This record'],
        ['Tiada rekod disekat.', 'No blocked records.'],
        ['Tiada rekod layak untuk dieksport.', 'No eligible records to export.'],
        ['Eksport CSV berjaya:', 'CSV export succeeded:'],
        ['Tiada sesi sepadan dengan penapis untuk dieksport.', 'No sessions match the filter for export.'],
        ['Tiada data sejarah untuk dieksport.', 'No history data to export.'],
        ['Eksport berjaya:', 'Export succeeded:'],

        /* ---- dashboard.js: voice search ---- */
        ['Carian suara — klik dan sebut nama, matrik atau kata kunci', 'Voice search — click and say a name, matric number or keyword'],
        ['Carian suara tidak disokong dalam pelayar ini', 'Voice search is not supported in this browser'],
        ['Carian suara', 'Voice search'],
        ['Carian suara tidak dapat dimulakan.', 'Voice search could not be started.'],
        ['Tiada suara dikesan. Klik mikrofon dan cuba lagi.', 'No speech detected. Click the microphone and try again.'],
        ['Carian suara gagal. Cuba lagi.', 'Voice search failed. Try again.'],
        ['Akses mikrofon ditolak. Benarkan mikrofon dalam tetapan pelayar untuk carian suara.', 'Microphone access denied. Allow the microphone in your browser settings for voice search.'],
        ['Mikrofon tidak ditemui. Sambungkan mikrofon dan cuba lagi.', 'No microphone found. Connect a microphone and try again.'],
        ['Carian suara memerlukan sambungan internet. Semak rangkaian dan cuba lagi.', 'Voice search requires an internet connection. Check your network and try again.'],

        /* ---- retention & archive ---- */
        ['Retensi & Arkib', 'Retention & Archive'],
        ['KAWALAN REKOD', 'RECORD CONTROL'],
        ['Rekod tamat dipindahkan ke arkib selepas kelulusan Admin kedua.', 'Expired records move to the archive after a second Admin approves.'],
        ['Dasar retensi', 'Retention policy'],
        ['Pelajar yang kadnya tamat melebihi had ini akan disenaraikan untuk arkib.', 'Students whose cards expired beyond this window are listed for archiving.'],
        ['Simpan rekod aktif (bulan)', 'Keep active records (months)'],
        ['Sertakan sesi pengangkutan lama dalam permintaan arkib.', 'Include old transport sessions in the archive request.'],
        ['Pratonton', 'Preview'],
        ['Hantar untuk kelulusan', 'Send for approval'],
        ['Pratonton belum dijalankan.', 'Preview has not been run.'],
        ['Pelajar layak', 'Eligible students'],
        ['Sesi layak', 'Eligible sessions'],
        ['Dikecualikan', 'Excluded'],
        ['Masih ada rekod UDA atau polisi.', 'Still has a UDA record or policy.'],
        ['Contoh pelajar', 'Student sample'],
        ['Sepuluh rekod paling lama dalam senarai layak.', 'The ten oldest records in the eligible list.'],
        ['Contoh sesi pengangkutan', 'Transport session sample'],
        ['Sesi lama yang akan dipindahkan apabila kotak di atas ditanda.', 'Old sessions move when the box above is checked.'],
        ['Tarikh luput', 'Expiry Date'],
        ['Status', 'Status'],
        ['Menunggu kelulusan', 'Awaiting approval'],
        ['Admin lain boleh meluluskan. Admin yang sama perlu taip kod pengesahan.', 'Another Admin can approve. The same Admin must type a confirmation code.'],
        ['Sejarah arkib', 'Archive history'],
        ['Manifest CSV tersedia untuk permintaan yang diluluskan.', 'CSV manifests are available for approved requests.'],
        ['Memuatkan sejarah arkib...', 'Loading archive history...'],
        ['Peminta', 'Requested by'],
        ['Jumlah', 'Total'],
        ['Manifest', 'Manifest']
    ];
    /* ================= Engine ================= */

    /* Exact lookup (first occurrence wins). Keys are trimmed rendered text. */
    var EXACT = Object.create(null);
    for (var pi = 0; pi < PAIRS.length; pi++) {
        var pk = PAIRS[pi][0].trim();
        if (!(pk in EXACT)) EXACT[pk] = PAIRS[pi][1];
    }

    /* Malay month abbreviations / full names — used ONLY for chart labels and
       month-range patterns, never for general DOM text (a student may be named "Mei"). */
    var CHART_MONTHS = {
        'Jan': 'Jan', 'Feb': 'Feb', 'Mac': 'Mar', 'Apr': 'Apr', 'Mei': 'May', 'Jun': 'Jun',
        'Jul': 'Jul', 'Ogo': 'Aug', 'Sep': 'Sep', 'Okt': 'Oct', 'Nov': 'Nov', 'Dis': 'Dec',
        'Januari': 'January', 'Februari': 'February', 'April': 'April',
        'Julai': 'July', 'Ogos': 'August', 'September': 'September',
        'Oktober': 'October', 'November': 'November', 'Disember': 'December'
    };
    function trMonth(v) {
        if (typeof v !== 'string') return v;
        var parts = v.trim().split(/\s+/);
        if (parts.length === 2 && CHART_MONTHS[parts[0]]) return CHART_MONTHS[parts[0]] + ' ' + parts[1];
        return CHART_MONTHS[v.trim()] || v;
    }

    /* Anchored patterns for numeric / interpolated strings. No /g flag here. */
    var PATTERNS = [
        [/^(\d+) pelajar dipilih$/, '$1 students selected'],
        [/^Menunjukkan (\d+) dari (\d+) rekod$/, 'Showing $1 of $2 records'],
        [/^Halaman (\d+) \/ (\d+)$/, 'Page $1 / $2'],
        [/^Luput (\d+) hari$/, 'Expired $1 days ago'],
        [/^Tamat dalam (\d+) hari$/, 'Expires in $1 days'],
        [/^Luput dalam (\d+) hari$/, 'Expiring within $1 days'],
        [/^(\d+) hari lagi$/, '$1 days left'],
        [/^Jumlah: (\d+)$/, 'Total: $1'],
        [/^Sedia: (\d+)$/, 'Ready: $1'],
        [/^Ralat: (\d+)$/, 'Errors: $1'],
        [/^(\d+) rekod dipaparkan$/, '$1 records shown'],
        [/^(\d+) dipilih$/, '$1 selected'],
        [/^(\d+) rekod sedia dihantar$/, '$1 records ready to send'],
        [/^(\d+) rekod pelajar$/, '$1 student records'],
        [/^(\d+) rekod$/, '$1 records'],
        [/^(\d+) Pelajar$/, '$1 Students'],
        [/^(\d+) pelajar$/, '$1 students'],
        [/^(\d+) isu$/, '$1 issues'],
        [/^(\d+) batch$/, '$1 batches'],
        [/^(\d+) sesi$/, '$1 sessions'],
        [/^(\d+) peristiwa$/, '$1 events'],
        [/^(\d+) pengguna$/, '$1 users'],
        [/^(\d+) akaun$/, '$1 accounts'],
        [/^(\d+) orang$/, '$1 people'],
        [/^(\d+) rekod dipilih$/, '$1 records selected'],
        [/^(\d+) rekod disemak$/, '$1 records checked'],
        [/^(\d+) kata laluan dijana\.$/, '$1 passwords generated.'],
        [/^(\d+) pusat lagi$/, '$1 more schools'],
        [/^(\d+) hari terakhir$/, 'Last $1 days'],
        [/^(\d+) bulan trend$/, '$1 trend months'],
        [/^(\d+) hari carta$/, '$1 chart days'],
        [/^Sesi #(\d+)$/, 'Session #$1'],
        [/^akaun #(\d+)$/, 'account #$1'],
        [/^Senarai Pelajar - Sesi #(\d+)$/, 'Student List - Session #$1'],
        [/^Perbandingan sebelah-menyebelah: rekod #(\d+)$/, 'Side-by-side comparison: record #$1'],
        [/^Menapis: (.+)$/, function (m, g) { return 'Filtering: ' + translateCore(g); }],
        [/^Lengkap (\d+) rekod$/, 'Fill $1 records'],
        [/^(.*) \(Tamat tempoh\)$/, function (m, g) { return translateCore(g) + ' (Expired)'; }],
        [/^Memaparkan rekod (\d+)–(\d+) daripada (\d+) rekod$/, 'Showing records $1–$2 of $3'],
        [/^Tunjuk (.+)$/, function (m, g) { return 'Show ' + translateCore(g); }],
        [/^Sembunyikan (.+)$/, function (m, g) { return 'Hide ' + translateCore(g); }],
        [/^Tapisan: (\d+) hari terakhir$/, 'Filter: last $1 days'],
        [/^Tapisan: (.+)$/, function (m, g) { return 'Filter: ' + translateCore(g); }],
        [/^([\d.]+)% daripada (semua )?rekod\)?$/, function (m, p, s) { return p + '% of ' + (s ? 'all ' : '') + 'records' + (m.charAt(m.length - 1) === ')' ? ')' : ''); }],
        [/^Berjaya ambil (\d+) rekod$/, 'Successfully fetched $1 records'],
        [/^Meminta sehingga (\d+)$/, 'Requesting up to $1'],
        [/^(\d+) pusat pengajian \/ jabatan berbeza$/, '$1 distinct schools / departments'],
        [/^Pangkalan rekod Sysweb dikemas kini \((\d+) rekod\)\.?$/, 'The Sysweb record database was updated ($1 records).'],
        [/^Hantar (\d+) rekod selebihnya \((\d+) perlu semakan\)\?$/, 'Send $1 remaining records ($2 need review)?'],
        [/^Hantar (\d+) rekod selebihnya\?$/, 'Send $1 remaining records?'],
        [/^Pilih rekod (.+)$/, function (m, g) { return 'Select record ' + translateCore(g); }],
        [/^([A-Za-z]+ \d{4}) hingga ([A-Za-z]+ \d{4})$/, function (m, a, b) { return trMonth(a) + ' to ' + trMonth(b); }],
        [/^Dijana oleh USMSmart pada (.+)$/, function (m, g) { return 'Generated by USMSmart on ' + g; }],
        [/^Memaparkan rekod pelajar: (.+)$/, function (m, g) { return 'Showing student records: ' + g; }],
        [/^Imbasan terakhir: (.+)$/, function (m, g) { return 'Last scan: ' + g; }],
        [/^Pelayan: (.+)$/, function (m, g) { return 'Server: ' + g; }],
        [/^Skop: (.+)$/, function (m, g) { return 'Scope: ' + translateCore(g); }],
        [/^Carta: (.+)$/, function (m, g) { return 'Chart: ' + translateCore(g); }],
        [/^Ralat: (.+)$/, function (m, g) { return 'Error: ' + translateCore(g); }],
        [/^ID rekod: (.+)$/, 'Record ID: $1'],
        [/^Perubahan: (.+)$/, function (m, g) { return 'Changes: ' + translateCore(g); }],
        [/^Nama: (.+)$/, 'Name: $1'],
        [/^Pelajar: (.+)$/, 'Student: $1'],
        [/^Staf: (.+)$/, 'Staff: $1'],
        [/^Salin: (.+)$/, 'Copy: $1'],
        [/^Diproses: (.+)$/, 'Processed: $1'],
        [/^Fail: (.+)$/, 'File: $1'],
        [/^Sedia: (.+)$/, function (m, g) { return 'Ready: ' + translateCore(g); }]
    ];

    /* Distinctive multi-word fragments (longest-first). Generic single words are
       deliberately excluded so record data (names, notes, schools) passes through. */
    var FRAGMENTS = [
        ['Pelajar Luput Pada ', 'Students Expiring On '],
        [' · Luput pada ', ' · Expiring on '],
        ['Pelajar Kad Hampir Luput (≤ 30 Hari)', 'Students with Cards Expiring Soon (≤ 30 Days)'],
        ['Pelajar Kad Tamat Segera (≤ 7 Hari)', 'Students with Cards Expiring Imminently (≤ 7 Days)'],
        ['Pelajar Kad Tamat (≤ 14 Hari)', 'Students with Cards Expiring (≤ 14 Days)'],
        ['Pelajar Kad Telah Tamat Tempoh', 'Students with Expired Cards'],
        ['Pelajar Kad Masih Aktif', 'Students with Still-Active Cards'],
        [' sesi dalam julat ini · ', ' sessions in this range · '],
        [' pelajar dihantar · ', ' students sent · '],
        [' pelajar dihantar  |  ', ' students sent  |  '],
        [' hari berbeza · ', ' distinct days · '],
        [' hari berbeza  |  ', ' distinct days  |  '],
        [' batch dalam julat ini · ', ' batches in this range · '],
        [' rekod diproses · ', ' records processed · '],
        [' isu dalam tapisan ini · ', ' issues in this filter · '],
        [' peristiwa dalam tapisan ini · ', ' events in this filter · '],
        [' jenis tindakan · ', ' action types · '],
        [' jenis isu', ' issue types'],
        [' rekod terjejas  |  ', ' affected records  |  '],
        [' isu  |  ', ' issues  |  '],
        [' sesi  |  ', ' sessions  |  '],
        [' batch  |  ', ' batches  |  '],
        [' disimpan baharu  |  ', ' newly saved  |  '],
        [' peristiwa  |  ', ' events  |  '],
        [' hari ini  |  ', ' today  |  '],
        [' dalam 7 hari  |  ', ' in 7 days  |  '],
        [' sesi tanpa nama', ' unnamed sessions'],
        ['% minggu ke minggu', '% week over week'],
        ['· tamat ', '· expired '],
        [' dalam carta', ' in chart'],
        [' bulan dalam carta', ' months in chart'],
        [' rekod · ', ' records · '],
        ['pusat pengajian / jabatan berbeza', 'distinct schools / departments'],
        ['; medan belum dipetakan: ', '; unmapped fields: '],
        ['kad telah tamat tempoh; ', 'cards have expired; '],
        ['kad telah tamat tempoh dan ', 'cards have expired and '],
        ['kad akan tamat dalam ', 'cards will expire in '],
        ['kad akan luput dalam 30 hari akan datang.', 'cards will expire in the next 30 days.'],
        ['kad pelajar telah tamat tempoh dan memerlukan tindakan.', 'student cards have expired and need action.'],
        ['kad pelajar akan tamat tempoh dalam tempoh 30 hari akan datang.', 'student cards will expire within the next 30 days.'],
        [' hari lagi', ' days left'],
        ['perlu semakan)', 'need review)'],
        ['rekod gagal daripada ', 'records failed of '],
        ['rekod dieksport ke CSV.', 'records exported to CSV.'],
        ['rekod dijumpai)', 'records found)'],
        ['isu dipaparkan daripada ', 'issues shown of '],
        ['isu ditemui.', 'issues found.'],
        ['rekod sepadan ditemui.', 'matching records found.'],
        ['pusat daripada ', 'schools of '],
        ['pusat lain (', 'other schools ('],
        ['isu) tiada dalam carta', 'issues) not in chart'],
        ['pengguna daripada ', 'users of '],
        ['pengguna lain (', 'other users ('],
        ['sesi daripada ', 'sessions of '],
        ['sesi lain (', 'other sessions ('],
        ['pelajar) tiada dalam carta', 'students) not in chart'],
        ['batch di luar julat tidak dipaparkan', 'batches outside the range are not shown'],
        ['batch tiada tarikh sah', 'batches without a valid date'],
        ['· pelayan menghantar ', '· server sent '],
        ['batch terakhir sahaja.', 'latest batches only.'],
        ['sesi di luar julat tidak dipaparkan', 'sessions outside the range are not shown'],
        ['sesi tiada tarikh sah', 'sessions without a valid date'],
        ['sesi hidup dalam pangkalan data.', 'live sessions in the database.'],
        ['luput 30h  |  ', 'expiring in 30d  |  '],
        ['tamat tempoh (semua rekod)', 'expired (all records)'],
        ['pusat  |  ', 'schools  |  '],
        ['pelajar  |  ', 'students  |  '],
        ['— bulan ', '— month '],
        ['rekod ditarik, ', 'records pulled, '],
        ['rekod layak.', 'eligible records.'],
        ['rekod terpilih?', 'selected records?'],
        ['— semak baris sah dan ralat sebelum import.', '— review the valid rows and errors before importing.'],
        ['. UID mesti hexadecimal dengan bilangan aksara genap.', '. The UID must be hexadecimal with an even character count.'],
        ['Eksport disekat: UID tidak sah untuk ', 'Export blocked: invalid UID for '],
        ['Eksport disekat: nombor kad mengandungi aksara tidak disokong untuk ', 'Export blocked: the card number contains unsupported characters for '],
        ['rekod tiada polisi palang. Tetapkan polisi palang dahulu.', 'records have no turnstile policy. Set a turnstile policy first.'],
        ['rekod tiada polisi palang dan akan disekat.', 'records have no turnstile policy and will be blocked.'],
        ['rekod baharu diterima. Semak ringkasan kualiti data sebelum memilih rekod untuk penukaran UDA.', 'new records received. Review the data quality summary before selecting records for UDA conversion.'],
        ['rekod mengikut penapis, kemudian menyemak rekod UDA sedia ada, polisi palang dan UID eksport untuk rekod tersebut sahaja.', 'records per the filters, then checking existing UDA records, turnstile policies and export UIDs for those records only.'],
        ['baris lagi dalam fail Excel', 'more rows in the Excel file'],
        ['% daripada semua rekod)', '% of all records)'],
        ['% daripada semua rekod', '% of all records'],
        ['Pelajar · ', 'Student · '],
        ['Staf · ', 'Staff · '],
        [' Pemilik (', ' Owner ('],
        [' Batas hari ini: ', " Today's limit: "],
        [' Skop: ', ' Scope: '],
        ['Tapisan: ', 'Filter: '],
        ['Carian: "', 'Search: "'],
        ['Pelayan: ', 'Server: '],
        ['Memaparkan rekod pelajar: ', 'Showing student records: '],
        [' Paparan: ', ' View: '],
        ['Sedia: ', 'Ready: '],
        ['Fail: ', 'File: '],
        ['Ralat sandaran: ', 'Backup error: '],
        ['Ralat DB: ', 'DB error: '],
        ['Meminta sehingga ', 'Requesting up to '],
        ['baris sah? Baris ralat akan dilangkau.', 'valid rows? Error rows will be skipped.'],
        ['. Gunakan Chrome atau Edge terkini.', '. Use the latest Chrome or Edge.'],
        ['• Pelajar: ', '• Students: '],
        ['• Sesi pengangkutan: ', '• Transport sessions: '],
        ['• Audit dalam fail: ', '• Audit entries in file: '],
        [' hari terakhir', ' last days'],
        [' bulan trend', ' trend months'],
        [' hari carta', ' chart days'],
        [' batch terakhir', ' latest batches'],
        [' pusat lagi', ' more schools'],
        [' pelajar)', ' students)'],
        [' sesi.', ' sessions.'],
        [' bulan.', ' months.'],
        [' rekod.', ' records.'],
        /* User-guide sentences split by inline <strong> around module names: the
           names translate via EXACT; these distinctive connectives fill the gaps. */
        ['Gunakan tab ', 'Use the '],
        [' untuk modul pengurusan dan ', ' tab for management modules and '],
        [' untuk ringkasan serta carta. Butang ', ' for summaries and charts. The '],
        [' di bahagian atas boleh dibuka bila-bila masa.', ' button at the top can be opened at any time.'],
        ['Buka ', 'Open the '],
        ['Gunakan ', 'Use '],
        [' untuk memahami ringkasan data dan carta yang dipaparkan.', ' to understand the data summaries and charts shown.'],
        [' untuk menyemak aktiviti atau perubahan yang direkodkan.', ' to review recorded activity or changes.'],
        [' mempunyai akses pengurusan tambahan seperti pengurusan akaun dan tindakan pentadbiran.', ' has additional management access such as account management and administrative actions.'],
        [' mempunyai akses yang lebih terhad; gunakan aliran permintaan perubahan jika disediakan.', ' has more limited access; use the change-request flow if provided.'],
        [' untuk pilihan profil yang tersedia, dan log keluar apabila selesai terutama pada komputer kongsi.', ' for the available profile options, and log out when finished, especially on shared computers.'],
        ['Jika halaman tidak dikemas kini, cuba muat semula dengan ', 'If the page does not update, try reloading with '],
        ['. Jika masalah berterusan, catat mesej ralat dan hubungi pentadbir.', '. If the problem persists, note the error message and contact the administrator.']
    ];
    /* Auto-add every distinctive (>=3 word) dictionary key as a fragment so
       interpolated sentences still translate. Longest-first; curated wins ties. */
    (function () {
        var seen = Object.create(null);
        var merged = [];
        var i;
        for (i = 0; i < FRAGMENTS.length; i++) { var fk = FRAGMENTS[i][0]; if (!(fk in seen)) { seen[fk] = 1; merged.push(FRAGMENTS[i]); } }
        for (i = 0; i < PAIRS.length; i++) {
            var k = PAIRS[i][0].trim();
            if (k.split(/\s+/).length >= 3 && !(k in seen)) { seen[k] = 1; merged.push([k, PAIRS[i][1]]); }
        }
        merged.sort(function (a, b) { return b[0].length - a[0].length; });
        FRAGMENTS = merged;
    })();

    /* Number + Malay unit pass. A digit immediately followed by a Malay counter is
       UI copy, never record data. Compound forms run before the generic unit map. */
    var UNIT_MAP = { rekod: 'records', pelajar: 'students', hari: 'days', bulan: 'months', minggu: 'weeks', sesi: 'sessions', batch: 'batches', isu: 'issues', peristiwa: 'events', pengguna: 'users', akaun: 'accounts', orang: 'people', pusat: 'schools', gagal: 'failed' };
    var NUMERIC = [
        [/\b(\d+) rekod pelajar\b/g, '$1 student records'],
        [/\b(\d+) rekod staf\b/g, '$1 staff records'],
        [/\b(\d+) rekod dipilih\b/g, '$1 records selected'],
        [/\b(\d+) rekod disemak\b/g, '$1 records checked'],
        [/\b(\d+) rekod sedia dihantar\b/g, '$1 records ready to send'],
        [/\b(\d+) rekod layak\b/g, '$1 eligible records'],
        [/\b(\d+) rekod baharu\b/g, '$1 new records'],
        [/\b(\d+) tidak disahkan\b/g, '$1 not confirmed'],
        [/\b(\d+) disahkan\b/g, '$1 confirmed'],
        [/\b(\d+) hari lagi\b/g, '$1 days left'],
        [/\bLuput (\d+) hari\b/g, 'Expired $1 days ago'],
        [/\b(\d+) hari terakhir\b/g, 'last $1 days'],
        [/\b(\d+) hari lepas\b/g, 'Last $1 days'],
        [/\b(\d+) bulan trend\b/g, '$1 trend months'],
        [/\b(\d+) hari carta\b/g, '$1 chart days'],
        [/\b(\d+) batch terakhir\b/g, '$1 latest batches'],
        [/\b(\d+) pusat lagi\b/g, '$1 more schools'],
        [/\b(\d+) baris lagi\b/g, '$1 more rows'],
        [/\b(\d+) Pelajar\b/g, '$1 Students'],
        [/\b(\d+) Staf\b/g, '$1 Staff'],
        [/\b(\d+) (rekod|pelajar|hari|bulan|minggu|sesi|batch|isu|peristiwa|pengguna|akaun|orang|pusat|gagal)\b/g, function (m, n, u) { return n + ' ' + (UNIT_MAP[u] || u); }]
    ];

    function preserve(orig, translated) {
        var lead = orig.length - orig.replace(/^\s+/, '').length;
        var tail = orig.length - orig.replace(/\s+$/, '').length;
        if (lead + tail >= orig.length) return translated;
        return orig.slice(0, lead) + translated + (tail ? orig.slice(orig.length - tail) : '');
    }

    /* Core string translator: exact -> anchored pattern -> fragments -> numeric units. */
    function translateCore(s) {
        if (typeof s !== 'string' || !s) return s;
        var t = s.trim();
        if (!t) return s;
        if (EXACT[t] !== undefined) return preserve(s, EXACT[t]);
        for (var i = 0; i < PATTERNS.length; i++) {
            if (PATTERNS[i][0].test(t)) return preserve(s, t.replace(PATTERNS[i][0], PATTERNS[i][1]));
        }
        var out = s;
        for (var f = 0; f < FRAGMENTS.length; f++) {
            if (out.indexOf(FRAGMENTS[f][0]) !== -1) out = out.split(FRAGMENTS[f][0]).join(FRAGMENTS[f][1]);
        }
        for (var n = 0; n < NUMERIC.length; n++) out = out.replace(NUMERIC[n][0], NUMERIC[n][1]);
        return out;
    }

    /* ---- Baseline stores (restore only what we changed, only if untouched since) ---- */
    var textBase = new Map();
    var attrBase = new Map();
    var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEXTAREA: 1, PRE: 1, CODE: 1 };
    var ATTRS = ['placeholder', 'title', 'aria-label', 'aria-placeholder', 'alt'];

    function applyText(node) {
        var cur = node.nodeValue;
        var rec = textBase.get(node);
        if (rec && cur === rec.last) return;
        var tr = translateCore(cur);
        if (tr !== cur) { textBase.set(node, { base: cur, last: tr }); node.nodeValue = tr; }
        else if (rec) { textBase.delete(node); }
    }

    function applyAttr(el, store, name, cur) {
        var rec = store[name];
        if (rec && cur === rec.last) return;
        var tr = translateCore(cur);
        if (tr !== cur) { store[name] = { base: cur, last: tr }; if (name === 'value') el.value = tr; else el.setAttribute(name, tr); }
        else if (rec) { delete store[name]; }
    }

    function applyAttrs(el) {
        var store = attrBase.get(el);
        function ensure() { if (!store) { store = {}; attrBase.set(el, store); } return store; }
        for (var i = 0; i < ATTRS.length; i++) {
            var a = ATTRS[i];
            if (el.hasAttribute('data-usm-i18n-' + a)) continue;
            var v = el.getAttribute(a);
            if (v != null && v !== '') applyAttr(el, ensure(), a, v);
        }
        if (el.tagName === 'INPUT') {
            var ty = (el.type || '').toLowerCase();
            if (ty === 'button' || ty === 'submit') { var iv = el.value; if (iv) applyAttr(el, ensure(), 'value', iv); }
        }
        if (el.tagName === 'META' && (el.getAttribute('name') || '').toLowerCase() === 'description') {
            var mc = el.getAttribute('content'); if (mc) applyAttr(el, ensure(), 'content', mc);
        }
    }

    function visit(node) {
        var nt = node.nodeType;
        if (nt === 3) { applyText(node); return; }
        if (nt !== 1) return;
        var el = node, tag = el.tagName;
        if (SKIP_TAGS[tag]) return;
        if (el.id === 'usm-lang-toggle' || el.id === 'usmsmart-ai-messages') return;
        if (el.hasAttribute('data-usm-nolang') || el.hasAttribute('data-usm-i18n')) return;
        if (el.isContentEditable) return;
        if (el.classList && el.classList.contains('usm-manual-video-transcript')) return;
        applyAttrs(el);
        for (var c = el.firstChild; c; c = c.nextSibling) visit(c);
    }

    /* Chrome built by feature modules carries data-usm-i18n; they re-render via T()
       on usm:langchange. This is a safety net for leaf labels only (never clobbers counts). */
    function applyTags() {
        if (window.__usmLang !== 'en') return;
        var nodes = document.querySelectorAll('[data-usm-i18n]');
        for (var i = 0; i < nodes.length; i++) {
            var el = nodes[i], key = el.getAttribute('data-usm-i18n');
            if (TAGS[key] && el.children.length === 0) el.textContent = TAGS[key];
        }
    }

    function applyAll() { visit(document.documentElement); applyTags(); translateCharts(); }

    function restoreAll() {
        textBase.forEach(function (rec, node) { try { if (node.nodeValue === rec.last) node.nodeValue = rec.base; } catch (e) {} });
        textBase.clear();
        attrBase.forEach(function (store, el) {
            Object.keys(store).forEach(function (a) {
                var rec = store[a];
                try {
                    if (a === 'value') { if (el.value === rec.last) el.value = rec.base; }
                    else if (el.getAttribute(a) === rec.last) el.setAttribute(a, rec.base);
                } catch (e) {}
            });
        });
        attrBase.clear();
        restoreCharts();
    }

    /* ---- Chart.js: translate labels, dataset names, scale titles, ticks & tooltips ---- */
    var chartBase = new WeakMap();
    function trChartLabel(v) {
        if (typeof v !== 'string') return v;
        var t = v.trim();
        if (CHART_MONTHS[t]) return preserve(v, CHART_MONTHS[t]);
        if (EXACT[t] !== undefined) return preserve(v, EXACT[t]);
        return translateCore(v);
    }
    function captureChart(c) {
        if (chartBase.has(c)) return chartBase.get(c);
        var b = { labels: null, ds: [], scales: [] };
        try {
            if (c.data && c.data.labels) b.labels = c.data.labels.slice();
            if (c.data && c.data.datasets) b.ds = c.data.datasets.map(function (d) { return d.label; });
            if (c.options && c.options.scales) Object.keys(c.options.scales).forEach(function (k) {
                var s = c.options.scales[k];
                if (s && s.title && typeof s.title.text === 'string') b.scales.push({ key: k, text: s.title.text });
            });
        } catch (e) {}
        chartBase.set(c, b);
        return b;
    }
    function ensureCallbacks(c) {
        try {
            var tt = c.options && c.options.plugins && c.options.plugins.tooltip;
            if (tt && tt.callbacks) ['title', 'label', 'footer', 'beforeLabel', 'afterLabel'].forEach(function (k) {
                var orig = tt.callbacks[k];
                if (typeof orig === 'function' && !orig.__usmWrapped) {
                    tt.callbacks[k] = function () {
                        var r = orig.apply(this, arguments);
                        if (window.__usmLang !== 'en') return r;
                        if (typeof r === 'string') return translateCore(r);
                        if (Array.isArray(r)) return r.map(function (x) { return typeof x === 'string' ? translateCore(x) : x; });
                        return r;
                    };
                    tt.callbacks[k].__usmWrapped = true;
                }
            });
            if (c.options && c.options.scales) Object.keys(c.options.scales).forEach(function (k) {
                var sc = c.options.scales[k];
                if (sc && sc.ticks && typeof sc.ticks.callback === 'function' && !sc.ticks.callback.__usmWrapped) {
                    var o = sc.ticks.callback;
                    sc.ticks.callback = function (v, i, t) { var r = o.call(this, v, i, t); return (window.__usmLang === 'en' && typeof r === 'string') ? trChartLabel(r) : r; };
                    sc.ticks.callback.__usmWrapped = true;
                }
            });
        } catch (e) {}
    }
    function translateChart(c) {
        if (!c || !c.data) return;
        var b = captureChart(c);
        try {
            if (b.labels && c.data.labels) c.data.labels = b.labels.map(trChartLabel);
            if (c.data.datasets) c.data.datasets.forEach(function (d, i) { if (typeof b.ds[i] === 'string') d.label = trChartLabel(b.ds[i]); });
            b.scales.forEach(function (s) { var sc = c.options.scales[s.key]; if (sc && sc.title) sc.title.text = trChartLabel(s.text); });
            ensureCallbacks(c);
            c.update('none');
        } catch (e) {}
    }
    function restoreChart(c) {
        var b = chartBase.get(c); if (!b || !c.data) return;
        try {
            if (b.labels) c.data.labels = b.labels.slice();
            if (c.data.datasets) c.data.datasets.forEach(function (d, i) { if (b.ds[i] !== undefined) d.label = b.ds[i]; });
            b.scales.forEach(function (s) { var sc = c.options.scales[s.key]; if (sc && sc.title) sc.title.text = s.text; });
            c.update('none');
        } catch (e) {}
    }
    function eachChart(fn) {
        try {
            var C = window.Chart;
            if (C && C.instances) Object.keys(C.instances).forEach(function (id) { var c = C.instances[id]; if (c && c.canvas) fn(c); });
        } catch (e) {}
    }
    function translateCharts() { if (window.__usmLang === 'en') eachChart(translateChart); }
    function restoreCharts() { eachChart(restoreChart); }
    function hookCharts() {
        try {
            var Real = window.Chart;
            if (!Real || Real.__usmProxied || typeof Proxy === 'undefined') return;
            var P = new Proxy(Real, {
                construct: function (t, args) {
                    var inst = Reflect.construct(t, args);
                    try { if (window.__usmLang === 'en') translateChart(inst); else captureChart(inst); } catch (e) {}
                    return inst;
                }
            });
            P.__usmProxied = true;
            window.Chart = P;
        } catch (e) {}
    }

    /* ---- Native dialogs: translate each line, leave prompt defaults untouched ---- */
    function wrapDialogs() {
        ['confirm', 'alert', 'prompt'].forEach(function (fn) {
            var orig = window[fn];
            if (typeof orig !== 'function' || orig.__usmWrapped) return;
            window[fn] = function (msg) {
                var m = msg;
                if (window.__usmLang === 'en' && typeof msg === 'string') {
                    m = msg.split('\n').map(function (line) { return translateCore(line); }).join('\n');
                }
                if (fn === 'prompt') return orig.call(window, m, arguments[1]);
                return orig.call(window, m);
            };
            window[fn].__usmWrapped = true;
        });
    }

    /* ---- Toggle button ---- */
    var GLOBE = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
    function paintToggle() {
        var btn = document.getElementById('usm-lang-toggle'); if (!btn) return;
        var en = window.__usmLang === 'en';
        btn.setAttribute('aria-pressed', en ? 'true' : 'false');
        btn.title = en ? 'Switch to Malay / Tukar ke Bahasa Melayu' : 'Switch to English / Tukar ke Bahasa Inggeris';
        btn.setAttribute('aria-label', en ? 'Language: English. Switch to Malay.' : 'Bahasa: Melayu. Switch to English.');
        var code = btn.querySelector('.usm-lang-code'); if (code) code.textContent = en ? 'EN' : 'MS';
    }
    function buildToggle() {
        if (document.getElementById('usm-lang-toggle')) return;
        var btn = document.createElement('button');
        btn.id = 'usm-lang-toggle';
        btn.type = 'button';
        btn.className = 'usm-lang-btn';
        btn.innerHTML = GLOBE + '<span class="usm-lang-code">MS</span>';
        btn.addEventListener('click', function () { setLang(window.__usmLang === 'en' ? 'ms' : 'en'); });
        var placed = false;
        var dash = document.querySelector('button[onclick="toggleDarkMode()"]');
        if (dash && dash.parentNode) { dash.parentNode.insertBefore(btn, dash); placed = true; }
        if (!placed) { var hm = document.getElementById('hm-theme-toggle'); if (hm && hm.parentNode) { hm.parentNode.insertBefore(btn, hm); btn.classList.add('usm-lang-glass'); placed = true; } }
        if (!placed) { var tt = document.getElementById('themeToggle'); if (tt && tt.parentNode) { tt.parentNode.insertBefore(btn, tt); btn.classList.add('usm-lang-fixed'); placed = true; } }
        if (!placed) { document.body.appendChild(btn); btn.classList.add('usm-lang-fixed'); }
        paintToggle();
    }

    function setLang(l) {
        if (l !== 'en' && l !== 'ms') return;
        if (window.__usmLang === l) return;
        window.__usmLang = l;
        try { window.localStorage.setItem(KEY, l); } catch (e) {}
        document.documentElement.lang = (l === 'en') ? 'en' : 'ms';
        if (l === 'en') applyAll(); else restoreAll();
        paintToggle();
        try { window.dispatchEvent(new CustomEvent('usm:langchange', { detail: { lang: l } })); } catch (e) {}
    }
    window.__usmSetLang = setLang;

    /* ---- Debounced observer: re-apply EN when the app rebuilds DOM from data ---- */
    var mo = null, moTimer = null, applying = false;
    function runApply() {
        if (window.__usmLang !== 'en') return;
        applying = true;
        try { applyAll(); } catch (e) {}
        if (mo) mo.takeRecords();
        applying = false;
    }
    function startObserver() {
        if (mo || typeof MutationObserver === 'undefined') return;
        mo = new MutationObserver(function () {
            if (window.__usmLang !== 'en' || applying) return;
            if (moTimer) clearTimeout(moTimer);
            moTimer = setTimeout(function () { moTimer = null; runApply(); }, 220);
        });
        mo.observe(document.documentElement, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['placeholder', 'title', 'aria-label', 'aria-placeholder', 'alt', 'value'] });
    }

    function boot() {
        try {
            document.documentElement.lang = (window.__usmLang === 'en') ? 'en' : 'ms';
            buildToggle();
            hookCharts();
            wrapDialogs();
            if (window.__usmLang === 'en') {
                runApply();
                try { window.dispatchEvent(new CustomEvent('usm:langchange', { detail: { lang: 'en' } })); } catch (e) {}
            }
            startObserver();
            paintToggle();
        } catch (e) {}
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();