        const nativeFetch = window.fetch.bind(window);
        window.fetch = function(resource, options) {
            options = options || {};
            const method = String(options.method || 'GET').toUpperCase();
            if (method === 'POST') {
                const headers = new Headers(options.headers || {});
                headers.set('X-CSRF-Token', USMSMART_CSRF_TOKEN);
                options.headers = headers;
            }
            return nativeFetch(resource, options);
        };
        // The greeting panels follow the visitor's clock and locale. The PHP process runs in
        // the server's zone, not the campus one, so a greeting computed there would be hours off.
        // The dashboard clock re-renders on the minute boundary so it never shows a stale minute.
        (function initGreetingPanels() {
            const greetingFor = function (hour) {
                return hour < 12 ? 'Selamat pagi'
                    : hour < 15 ? 'Selamat tengah hari'
                    : hour < 19 ? 'Selamat petang'
                    : 'Selamat malam';
            };
            const longDate = function (now) {
                try {
                    return new Intl.DateTimeFormat('ms-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
                } catch (error) {
                    return now.toLocaleDateString();
                }
            };
            const clock12 = function (now) {
                try {
                    return new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).format(now);
                } catch (error) {
                    return now.toLocaleTimeString();
                }
            };
            const dashGreeting = document.getElementById('usm-dash-greeting');
            const dashDate = document.getElementById('usm-dash-date');
            const dashTime = document.getElementById('usm-dash-time');
            const welcomeGreeting = document.getElementById('usm-welcome-greeting');
            const welcomeDate = document.getElementById('usm-welcome-date');
            const paint = function () {
                const now = new Date();
                const greeting = greetingFor(now.getHours());
                const date = longDate(now);
                if (dashGreeting) dashGreeting.textContent = greeting;
                if (dashDate) dashDate.textContent = date;
                if (dashTime) {
                    dashTime.textContent = clock12(now);
                    dashTime.setAttribute('datetime', now.toISOString());
                }
                if (welcomeGreeting) welcomeGreeting.textContent = greeting;
                if (welcomeDate) welcomeDate.textContent = date;
            };
            paint();
            if (!dashTime) return;
            // Repaint when the minute turns, then once a minute. A background tab throttles
            // timers, so a return to the tab also repaints instead of waiting for the next tick.
            setTimeout(function () {
                paint();
                setInterval(paint, 60000);
            }, (60 - new Date().getSeconds()) * 1000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) paint();
            });
        })();

        let allMasterStudents = [];
        let filteredMasterStudents = [];
        let currentPage = 1;
        let rowsPerPage = 10;
        let masterSelectedIds = new Set();
        let duplicateStudentIds = new Set();
        let duplicateOnlyFilter = false;
        let duplicateAlertDismissed = false;

        let existingPolicyOptions = [];
        let currentFetchedRecords = [];
        let selectedRecordIds = new Set();
        let udaCurrentPage = 1;
        let udaPageSize = 25;
        let convertedUdaRecords = [];
        let allSessionsData = [];

        // Sysweb Integration State & Functions (Dedicated Segment + Portal)
        let usmUdaExportSucceeded = false;
        let lastExportedUdaRecords = [];
        let allSyswebSavedRecords = [];
        let syswebCardSourceInfo = null;
        let syswebPage = 1;
        let syswebPageSize = 15;
        let syswebTypeMode = '';
        let syswebStatusFilter = '';
        let syswebIssueFilter = '';
        let syswebSavedChecks = [];
        let syswebFilteredRecords = [];
        let syswebSelectedIndexes = new Set();
        let syswebDrawerType = 'student';
        let currentSyswebSource = 'uda';
        let currentSyswebSourceName = 'USMSmart UDA Live Export';
        let currentSyswebSourceLabel = 'Eksport UDA Terkini';
        let syswebPreviousView = 'transporter';

        const USM_SYSWEB_SAMPLE_RECORDS = [
            { name: 'Demo Student One', identifier: 'DEMO2026001', type: 'student', converted_uid: 'AABBCC01', sysweb_uid: '00000000AABBCC01' },
            { name: 'Demo Staff One', identifier: 'STAFFDEMO01', type: 'staff', converted_uid: '11223344', sysweb_uid: '0000000011223344' }
        ];
        function normaliseSyswebSourceUid(value) {
            const raw = String(value ?? '').trim().replace(/\s+/g, '').toUpperCase();
            return /^(?:[0-9A-F]{2})+$/.test(raw) ? raw : '';
        }

        // A printed serial is never a byte-pair hex string and a card UID always is, so this separates a
        // legacy row that still parks its UID in cardSerial from one holding a real serial number.
        function staffPrintedSerial(item) {
            const candidate = String(item?.cardSerial ?? '').trim();
            return normaliseSyswebSourceUid(candidate) === '' ? candidate : '';
        }

        // Same byte-pair reversal the server re-proves on receive, so a reversed card UID is
        // caught here too instead of trusting whoever assembled the batch.
        function syswebLittleEndianOf(hex) {
            return hex.match(/.{2}/g).reverse().join('');
        }

        const SYSWEB_STATUS_META = {
            valid: { label: 'Sah', cls: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300', icon: 'fa-circle-check' },
            review: { label: 'Perlu semakan', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300', icon: 'fa-triangle-exclamation' },
            invalid: { label: 'Tidak sah', cls: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300', icon: 'fa-circle-xmark' }
        };

        function syswebStatusChip(status, extraClass) {
            const meta = SYSWEB_STATUS_META[status] || SYSWEB_STATUS_META.review;
            return '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + meta.cls + ' ' + (extraClass || '') + '"><i class="fa-solid ' + meta.icon + ' text-[9px]"></i>' + meta.label + '</span>';
        }

        function getStaffHexUid(staff) {
            const rawCard = String(staff?.cardUid || staff?.cardSerial || '').trim().replace(/\s+/g, '');
            if (/^[0-9a-fA-F]+$/.test(rawCard) && rawCard.length % 2 === 0 && rawCard.length >= 4) {
                return convertUidToLittleEndian(rawCard) || rawCard.toUpperCase();
            }
            // Deterministic 8-character hex for a staff row that carries no card UID at all
            let hash = 0;
            const seed = String(staff?.staffNo || staff?.name || staff?.id || 'STAFF');
            for (let i = 0; i < seed.length; i++) {
                hash = ((hash << 5) - hash) + seed.charCodeAt(i);
                hash |= 0;
            }
            return (Math.abs(hash) >>> 0).toString(16).toUpperCase().padStart(8, '0').slice(-8);
        }

        function getStudentHexUid(student) {
            const rawUid = String(student?.usmUid || '').trim().replace(/\s+/g, '');
            if (/^[0-9a-fA-F]+$/.test(rawUid) && rawUid.length % 2 === 0) {
                return convertUidToLittleEndian(rawUid) || rawUid.toUpperCase();
            }
            let hash = 0;
            const seed = String(student?.matric || student?.name || student?.id || 'STUDENT');
            for (let i = 0; i < seed.length; i++) {
                hash = ((hash << 5) - hash) + seed.charCodeAt(i);
                hash |= 0;
            }
            return (Math.abs(hash) >>> 0).toString(16).toUpperCase().padStart(8, '0').slice(-8);
        }

        function syswebDetailsFromSource(item, isStaff) {
            return isStaff ? {
                school: String(item.department || item.school || '').trim(),
                policy: String(item.cardStatus || item.policy || '').trim(),
                serial_no: String(item.serialNo || item.serial_no || staffPrintedSerial(item) || '').trim(),
                date_joined: String(item.dateJoined || item.date_joined || '').slice(0, 10),
                expiry_date: String(item.expiryDate || item.expiry_date || '').slice(0, 10),
                nationality: '',
                notes: String(item.notes || '').trim()
            } : {
                school: sanitizeSchoolName(String(item.school || '').trim()),
                policy: String(item.policy || '').trim(),
                serial_no: String(item.serialNo || item.serial_no || item.usmUid || '').trim(),
                date_joined: String(item.dateJoined || item.date_joined || '').slice(0, 10),
                expiry_date: String(item.expiryDate || item.expiry_date || '').slice(0, 10),
                nationality: String(item.nationality || '').trim(),
                notes: ''
            };
        }

        function formatRecordsForSysweb(items, defaultType = 'student') {
            return (items || []).map(item => {
                const isStaff = defaultType === 'staff' || item.staffNo || item.cardholderType === 'staff';
                const name = String(item.name || (isStaff ? 'Staf' : 'Pelajar')).trim();
                const identifier = String(isStaff ? (item.staffNo || item.identifier || 'STAF') : (item.matric || item.identifier || 'PELAJAR')).trim();
                const convertedUid = isStaff ? getStaffHexUid(item) : getStudentHexUid(item);
                const syswebUid = '00000000' + convertedUid;
                const sourceUid = normaliseSyswebSourceUid(isStaff ? (item.cardUid || item.cardSerial) : item.usmUid);
                const base = Object.assign({
                    name: name,
                    identifier: identifier,
                    type: isStaff ? 'staff' : 'student',
                    converted_uid: convertedUid,
                    sysweb_uid: syswebUid
                }, syswebDetailsFromSource(item, isStaff));
                if (sourceUid) base.source_uid = sourceUid;
                return base;
            });
        }

        function enableSyswebAfterSuccessfulUdaExport(records) {
            usmUdaExportSucceeded = true;
            const sourceList = Array.isArray(records) && records.length ? records : convertedUdaRecords;
            if (Array.isArray(sourceList) && sourceList.length) {
                lastExportedUdaRecords = sourceList.map(r => {
                    const rawLe = String(r.udaConvertedUid || r.converted_uid || '').trim().toUpperCase();
                    const isStaff = r.cardholderType === 'staff' || r.type === 'staff' || Boolean(r.staffNo);
                    const staged = Object.assign({
                        name: r.name || (isStaff ? 'Staf' : 'Pelajar'),
                        identifier: isStaff ? (r.staffNo || r.identifier || 'STAF') : (r.matric || r.identifier || 'UNKNOWN'),
                        type: isStaff ? 'staff' : 'student',
                        converted_uid: rawLe,
                        sysweb_uid: (rawLe.startsWith('00000000') ? rawLe : ('00000000' + rawLe))
                    }, r.school || r.department ? syswebDetailsFromSource(r, isStaff) : {});
                    const rawSource = normaliseSyswebSourceUid(r.source_uid || r.usmUid);
                    if (rawSource) staged.source_uid = rawSource;
                    return staged;
                });
                currentSyswebSource = 'uda';
                currentSyswebSourceName = 'USMSmart UDA Live Export';
                currentSyswebSourceLabel = 'Eksport UDA Terkini';
            }
            const btn = document.getElementById('usm-sysweb-after-uda-btn');
            if (btn) {
                btn.disabled = false;
                btn.removeAttribute('title');
            }
            const badge = document.getElementById('usm-sysweb-pending-badge');
            if (badge && lastExportedUdaRecords.length) {
                badge.innerHTML = '<i class="fa-solid fa-circle-check text-[10px]"></i> ' + lastExportedUdaRecords.length + ' Rekod Sedia Dihantar';
            }
            const note = document.getElementById('usm-sysweb-after-uda-note');
            if (note) {
                note.innerHTML = '<i class="fa-solid fa-circle-check text-emerald-500"></i> Eksport UDA berjaya. Anda kini boleh meneruskan langkah integrasi ke Sysweb.';
            }
        }

        function getActiveSyswebRecords() {
            if (currentSyswebSource === 'uda' && lastExportedUdaRecords && lastExportedUdaRecords.length > 0) {
                return lastExportedUdaRecords;
            }
            return USM_SYSWEB_SAMPLE_RECORDS;
        }

        function toggleSyswebSource() {
            if (currentSyswebSource === 'uda') {
                currentSyswebSource = 'sample';
            } else {
                currentSyswebSource = 'uda';
            }
            renderSyswebSegment();
            renderSyswebTable();
        }

        function navigateToSyswebSegment(fromView = 'transporter', batchRecords = null, batchName = null, batchLabel = null) {
            syswebPreviousView = fromView;
            if (Array.isArray(batchRecords) && batchRecords.length) {
                lastExportedUdaRecords = batchRecords;
                currentSyswebSource = 'uda';
                if (batchName) currentSyswebSourceName = batchName;
                if (batchLabel) currentSyswebSourceLabel = batchLabel;
                usmUdaExportSucceeded = true;
            }
            hideAllMainViews();
            const view = document.getElementById('view-sysweb');
            if (view) view.classList.remove('hidden');
            setMainTabState('sysweb');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            renderSyswebSegment();
            renderSyswebSegmentButtons();
            renderSyswebSavedThead();
            loadSyswebSavedRecords();
            loadSyswebExportHistory();
        }

        function backFromSysweb() {
            hideAllMainViews();
            if (syswebPreviousView === 'student_directory' || syswebPreviousView === 'directory') {
                navigateToDirectoryPage();
            } else if (syswebPreviousView === 'staff_directory') {
                navigateToStaffDirectory();
            } else if (syswebPreviousView === 'analytics') {
                switchMainTab('analytics');
            } else {
                backToMainTransporter();
            }
        }

        function renderSyswebSegment() {
            const records = getActiveSyswebRecords();
            const titleEl = document.getElementById('sysweb-batch-title');
            const pillEl = document.getElementById('sysweb-batch-source-pill');
            const countEl = document.getElementById('sysweb-batch-count');
            const toggleBtn = document.getElementById('sysweb-batch-toggle-btn');
            const tbody = document.getElementById('sysweb-batch-tbody');

            if (titleEl) {
                titleEl.textContent = 'Batch Penghantaran Aktif: ' + (currentSyswebSource === 'uda' ? currentSyswebSourceLabel : 'Sampel Ujian Demo');
            }
            if (pillEl) {
                pillEl.textContent = currentSyswebSource === 'uda' ? currentSyswebSourceName : 'Modul Sampel';
            }
            if (countEl) {
                countEl.textContent = records.length;
            }
            if (toggleBtn) {
                toggleBtn.innerHTML = currentSyswebSource === 'uda'
                    ? '<i class="fa-solid fa-shuffle text-indigo-500 mr-1"></i><span>Tukar ke Sampel Demo</span>'
                    : '<i class="fa-solid fa-database text-emerald-600 mr-1"></i><span>Kembali ke ' + escapeHtml(currentSyswebSourceLabel || 'Batch Aktif') + ' (' + lastExportedUdaRecords.length + ')</span>';
            }
            if (tbody) {
                if (!records.length) {
                    tbody.innerHTML = '<tr><td colspan="9" class="p-4 text-center text-slate-400">Tiada rekod dalam batch ini.</td></tr>';
                } else {
                    const batchChecks = validateSyswebBatch(records, allSyswebSavedRecords, {});
                    tbody.innerHTML = records.map((r, i) => {
                        const meta = syswebMetaOf(r);
                        const expiryState = syswebExpiryState(r);
                        const check = batchChecks[i];
                        return '<tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-900/60 transition-colors">' +
                            '<td class="p-2.5 text-slate-400 text-center">' + (i + 1) + '</td>' +
                            '<td class="p-2.5 font-medium text-slate-900 dark:text-white font-sans">' + escapeHtml(r.name) + '</td>' +
                            '<td class="p-2.5">' + escapeHtml(r.identifier) + '</td>' +
                            '<td class="p-2.5 font-sans"><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + meta.badge + '"><i class="fa-solid ' + meta.icon + ' text-[9px]"></i>' + meta.label + '</span></td>' +
                            '<td class="p-2.5 font-sans text-slate-600 dark:text-zinc-400">' + escapeHtml(r.school || '-') + '</td>' +
                            '<td class="p-2.5 font-sans"><span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + expiryState.chip + '">' + escapeHtml(r.expiry_date || 'Tiada') + '</span></td>' +
                            '<td class="p-2.5 font-mono text-emerald-600 dark:text-emerald-400 font-bold">' + escapeHtml(r.converted_uid) + '</td>' +
                            '<td class="p-2.5 font-mono text-indigo-600 dark:text-indigo-400 font-bold">' + escapeHtml(r.sysweb_uid) + '</td>' +
                            '<td class="p-2.5 font-sans" title="' + escapeHtml(check.labels.join(' \u00b7 ') || 'Tiada isu') + '">' + syswebStatusChip(check.status) + '</td>' +
                        '</tr>';
                    }).join('');
                }
            }

            const splitEl = document.getElementById('sysweb-batch-split');
            if (splitEl) {
                const studentRecords = records.filter(r => String(r.type || 'student').toLowerCase() !== 'staff');
                const staffRecords = records.filter(r => String(r.type || 'student').toLowerCase() === 'staff');
                const chip = (count, icon, label, cls) => '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + cls + '"><i class="fa-solid ' + icon + ' text-[9px]"></i>' + count + ' ' + label + '</span>';
                let chips = '';
                if (studentRecords.length) chips += chip(studentRecords.length, 'fa-graduation-cap', 'Pelajar', 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300');
                if (staffRecords.length) chips += chip(staffRecords.length, 'fa-id-badge', 'Staf', 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300');
                const uniqueSchools = new Set(records.map(r => String(r.school || '').trim()).filter(Boolean));
                if (uniqueSchools.size) chips += '<span class="text-[10px] text-slate-500 dark:text-zinc-400 font-sans">' + uniqueSchools.size + ' pusat pengajian / jabatan berbeza</span>';
                splitEl.innerHTML = chips || '<span class="text-[10px] text-slate-400 font-sans">Batch ini belum membezakan kategori.</span>';
            }
        }

        async function loadSyswebSavedRecords(showToastFeedback = false) {
            try {
                const response = await fetch('index.php?action=get_sysweb_records', {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data && Array.isArray(data.records)) {
                    allSyswebSavedRecords = data.records;
                } else {
                    allSyswebSavedRecords = [];
                }
                syswebCardSourceInfo = (data && data.card_source && typeof data.card_source === 'object') ? data.card_source : null;
            } catch (err) {
                console.warn('Gagal memuatkan rekod Sysweb:', err);
            }
            populateSyswebFilterOptions();
            renderSyswebSavedThead();
            applySyswebFilters();
            if (showToastFeedback) {
                showToast('Pangkalan rekod Sysweb dikemas kini (' + allSyswebSavedRecords.length + ' rekod).');
            }
        }

        const SYSWEB_TYPE_META = {
            student: {
                label: 'Pelajar', icon: 'fa-graduation-cap',
                badge: 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
                identifier: 'No. Matrik', school: 'Pusat Pengajian', policy: 'Polisi Kad'
            },
            staff: {
                label: 'Staf', icon: 'fa-id-badge',
                badge: 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300',
                identifier: 'No. Staf', school: 'Jabatan', policy: 'Status Kad'
            }
        };
        function syswebMetaOf(record) {
            return SYSWEB_TYPE_META[String((record && record.type) || 'student').toLowerCase() === 'staff' ? 'staff' : 'student'];
        }
        function formatSyswebDateTime(iso) {
            if (!iso) return '';
            const d = new Date(iso);
            return isNaN(d.getTime()) ? String(iso) : d.toLocaleString('ms-MY', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
        function syswebExpiryState(record) {
            const raw = String(record.expiry_date || '').slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(raw)) return { key: 'none', chip: 'bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-400', icon: 'fa-circle-minus', label: 'Tiada rekod' };
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const days = Math.round((new Date(raw + 'T00:00:00').getTime() - today.getTime()) / 86400000);
            if (days < 0) return { key: 'expired', chip: 'bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300', icon: 'fa-circle-xmark', label: 'Luput ' + Math.abs(days) + ' hari' };
            if (days <= 7) return { key: 'soon', chip: 'bg-orange-100 text-orange-800 dark:bg-orange-950/70 dark:text-orange-300', icon: 'fa-triangle-exclamation', label: days === 0 ? 'Tamat hari ini' : days + ' hari lagi' };
            if (days <= 30) return { key: 'soon', chip: 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300', icon: 'fa-clock', label: days + ' hari lagi' };
            return { key: 'valid', chip: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300', icon: 'fa-circle-check', label: 'Aktif' };
        }
        function syswebSearchableText(record) {
            const meta = syswebMetaOf(record);
            return [
                record.name, record.identifier, meta.label, record.type, record.school, record.policy,
                record.serial_no, record.nationality, record.notes, record.date_joined, record.expiry_date,
                record.converted_uid, record.sysweb_uid, record.source_uid,
                formatSyswebDateTime(record.received_at), formatSyswebDateTime(record.updated_at)
            ].map(value => String(value ?? '').toLowerCase()).join(' ');
        }

        function setSyswebSelectOptions(select, values, allLabel) {
            if (!select) return;
            const current = select.value;
            select.innerHTML = '';
            const first = document.createElement('option');
            first.value = ''; first.textContent = allLabel;
            select.appendChild(first);
            values.forEach(value => {
                const option = document.createElement('option');
                option.value = value; option.textContent = value;
                select.appendChild(option);
            });
            select.value = values.indexOf(current) !== -1 ? current : '';
        }

        function populateSyswebFilterOptions() {
            const base = allSyswebSavedRecords.filter(r => !syswebTypeMode || String(r.type || 'student').toLowerCase() === syswebTypeMode);
            const unique = key => Array.from(new Set(base.map(r => String(r[key] || '').trim()).filter(Boolean))).sort((a, b) => a.localeCompare(b));
            const meta = syswebTypeMode ? SYSWEB_TYPE_META[syswebTypeMode] : null;
            const schoolLabel = meta ? meta.school : 'Pusat Pengajian / Jabatan';
            const policyLabel = meta ? meta.policy : 'Polisi / Status Kad';
            const schoolHeading = document.getElementById('sysweb-school-filter-label');
            const policyHeading = document.getElementById('sysweb-policy-filter-label');
            if (schoolHeading) schoolHeading.textContent = schoolLabel;
            if (policyHeading) policyHeading.textContent = policyLabel;
            setSyswebSelectOptions(document.getElementById('sysweb-school-filter'), unique('school'), 'Semua ' + schoolLabel.toLowerCase());
            setSyswebSelectOptions(document.getElementById('sysweb-policy-filter'), unique('policy'), 'Semua ' + policyLabel.toLowerCase());
        }

        // 'invalid' holds a record back from dispatch; 'review' is a human decision, not an error.
        function validateSyswebRecord(record, context) {
            const issues = [];
            const add = (code, level, label) => issues.push({ code: code, level: level, label: label });
            const ctx = context || {};
            const name = String(record.name || '').trim();
            const identifier = String(record.identifier || '').trim();
            const type = String(record.type || 'student').toLowerCase();
            const converted = String(record.converted_uid || '').trim().toUpperCase();
            const sysuid = String(record.sysweb_uid || '').trim().toUpperCase();
            const source = normaliseSyswebSourceUid(record.source_uid);
            const expiry = String(record.expiry_date || '').slice(0, 10);
            const serial = String(record.serial_no || '').trim();
            const hexOk = /^(?:[0-9A-F]{2})+$/.test(converted);

            if (!name) add('name', 'invalid', 'Nama tiada');
            if (!identifier) add('identifier', 'invalid', 'Nombor pengenalan tiada');
            if (type !== 'student' && type !== 'staff') add('type', 'invalid', 'Kategori tidak dikenali');
            if (!hexOk) add('uid', 'invalid', 'UID converted bukan hexadecimal bait-pair');
            else if (sysuid !== '00000000' + converted) add('prefix', 'invalid', 'Prefiks Sysweb (00000000) tidak sepadan');
            if (hexOk && source && syswebLittleEndianOf(source) !== converted) add('byteorder', 'invalid', 'Susunan byte UID converted terbalik');
            if (ctx.duplicateIdentifier) add('duplicate-id', 'invalid', ctx.duplicateIdentifier);
            if (ctx.duplicateUid) add('duplicate-uid', 'invalid', ctx.duplicateUid);
            if (expiry && !/^\d{4}-\d{2}-\d{2}$/.test(expiry)) add('date', 'invalid', 'Tarikh tamat kad bukan format ISO');
            if (!serial) add('serial', 'review', 'No. siri kad tiada');
            if (ctx.duplicateSerial) add('duplicate-serial', 'review', ctx.duplicateSerial);
            if (ctx.expired) add('expired', 'review', 'Kad sudah luput');
            else if (ctx.expiringSoon) add('expiring', 'review', 'Kad tamat dalam 30 hari');
            else if (!expiry) add('expiry-missing', 'review', 'Tiada rekod tarikh tamat');
            if (ctx.missingPassword) add('password', 'review', 'Kata laluan belum dijana');
            const cardSource = String(record.card_source || '');
            if (cardSource === 'conflict') add('card-conflict', 'invalid', 'UID kad bercanggah dengan rekod kad');
            else if (cardSource === 'absent') add('card-absent', 'review', 'Pengenalan tiada dalam rekod kad');
            else if (cardSource === 'ambiguous') add('card-ambiguous', 'review', 'Lebih dari satu rekod kad ditemui untuk pengenalan ini');
            else if (cardSource === 'uda_only') add('card-uda-only', 'review', 'Hanya rekod UDA tersedia, rekod kad rasmi tiada');
            const cardLive = String(record.card_live || '');
            if (cardLive === 'unconfirmed') add('card-unconfirmed', 'review', 'Pangkalan kad Sysweb langsung tidak mengesahkan pengenalan ini');
            else if (cardLive === 'contradicted') add('card-live-differs', 'review', 'Salinan tempatan dan pangkalan kad Sysweb langsung tidak sependapat');

            const status = issues.some(i => i.level === 'invalid') ? 'invalid' : (issues.length ? 'review' : 'valid');
            return { status: status, issues: issues, labels: issues.map(i => i.label) };
        }

        // Duplication is only meaningful against a set, so the batch and the saved store are
        // tallied together before each record is judged.
        function validateSyswebBatch(records, otherRecords, options) {
            const list = Array.isArray(records) ? records : [];
            const opts = options || {};
            const identities = Object.create(null);
            const uids = Object.create(null);
            const serials = Object.create(null);
            const bump = (map, key, entry) => { if (key) { if (!map[key]) map[key] = []; map[key].push(entry); } };
            const keyOf = r => ({
                ident: (String(r.type || 'student').toLowerCase() === 'staff' ? 'staff' : 'student') + '|' + String(r.identifier || '').trim().toLowerCase(),
                uid: String(r.sysweb_uid || '').trim().toUpperCase(),
                serial: String(r.serial_no || '').trim().toLowerCase()
            });
            (otherRecords || []).concat(opts.countBatch === false ? [] : list).forEach(r => {
                const k = keyOf(r);
                bump(identities, k.ident, { uid: k.uid });
                bump(uids, k.uid, { ident: k.ident });
                bump(serials, k.serial, { ident: k.ident });
            });
            return list.map(r => {
                const k = keyOf(r);
                const context = {};
                if ((identities[k.ident] || []).some(h => h.uid && k.uid && h.uid !== k.uid)) context.duplicateIdentifier = 'No. pengenalan dipakai dengan UID Sysweb berbeza';
                if ((uids[k.uid] || []).some(h => h.ident && k.ident && h.ident !== k.ident)) context.duplicateUid = 'UID kad dipakai oleh pengenalan lain';
                if (k.serial && (serials[k.serial] || []).length > 1) context.duplicateSerial = 'No. siri kad berkembar';
                const state = syswebExpiryState(r);
                if (state.key === 'expired') context.expired = true;
                else if (state.key === 'soon') context.expiringSoon = true;
                if (opts.requirePassword && !r.has_password) context.missingPassword = true;
                return validateSyswebRecord(r, context);
            });
        }

        function recomputeSyswebValidation() {
            syswebSavedChecks = validateSyswebBatch(allSyswebSavedRecords, [], { requirePassword: true });
        }

        const SYSWEB_CARD_SOURCE_META = {
            matched: { label: 'Sepadan rekod kad', cls: 'text-emerald-700 dark:text-emerald-300' },
            uda_only: { label: 'Hanya rekod UDA', cls: 'text-amber-800 dark:text-amber-300' },
            ambiguous: { label: 'Rekod kad bercanggah', cls: 'text-amber-800 dark:text-amber-300' },
            conflict: { label: 'UID tidak sepadan', cls: 'text-rose-700 dark:text-rose-300' },
            absent: { label: 'Tiada dalam rekod kad', cls: 'text-rose-700 dark:text-rose-300' },
            skip: { label: 'Belum semak silang', cls: 'text-slate-500 dark:text-zinc-400' }
        };
        const SYSWEB_FILL_LABELS = { serial_no: 'No. siri', expiry_date: 'Tarikh tamat', policy: 'Polisi' };

        function syswebCardSourceBadge(info) {
            const source = info || syswebCardSourceInfo || {};
            const mode = String(source.mode || 'not_configured');
            const cls = mode === 'live'
                ? 'text-emerald-700 dark:text-emerald-300'
                : 'text-amber-700 dark:text-amber-300';
            let label;
            if (mode === 'live') {
                label = 'Rekod kad dibaca langsung daripada pangkalan data Sysweb ('
                    + Number(source.confirmed || 0) + ' daripada ' + Number(source.queried || 0) + ' pengenalan ditemui)';
                const unmapped = Array.isArray(source.unmapped) ? source.unmapped.filter(Boolean) : [];
                if (unmapped.length) label += '; medan belum dipetakan: ' + unmapped.join(', ');
            } else if (mode === 'unavailable') {
                label = 'Salinan tempatan digunakan: pangkalan data Sysweb tidak dapat dihubungi.';
            } else if (mode === 'unreadable') {
                label = 'Salinan tempatan digunakan: jadual kad Sysweb tidak dapat dibaca.';
            } else if (mode === 'idle') {
                label = 'Tiada pengenalan untuk disemak dengan pangkalan data Sysweb.';
            } else {
                label = 'Salinan tempatan digunakan: pangkalan data Sysweb belum dikonfigurasi.';
            }
            return '<p class="text-[11px] font-semibold ' + cls + '"><i class="fa-solid '
                + (mode === 'live' ? 'fa-database' : 'fa-triangle-exclamation') + ' mr-1"></i>' + escapeHtml(label) + '</p>';
        }

        function renderSyswebReconcileStrip() {
            const text = document.getElementById('sysweb-reconcile-summary');
            const detail = document.getElementById('sysweb-reconcile-detail');
            if (!text || !detail) return;
            const rows = Array.isArray(allSyswebSavedRecords) ? allSyswebSavedRecords : [];
            if (!rows.some(r => r && r.card_source)) { text.innerHTML = escapeHtml('Belum semak silang dengan rekod kad.') + syswebCardSourceBadge(); return; }
            const tally = Object.create(null);
            rows.forEach(r => { const key = String(r.card_source || 'skip'); tally[key] = (tally[key] || 0) + 1; });
            text.innerHTML = Object.keys(SYSWEB_CARD_SOURCE_META).filter(key => tally[key]).map(key =>
                '<span class="font-semibold ' + SYSWEB_CARD_SOURCE_META[key].cls + '">' + SYSWEB_CARD_SOURCE_META[key].label
                + ' <span class="font-mono">' + tally[key] + '</span></span>').join('<span class="text-slate-300 dark:text-zinc-400 mx-1">·</span>')
                + '<span class="text-slate-300 dark:text-zinc-400 mx-1">·</span>'
                + '<span class="text-[11px] font-semibold ' + ((syswebCardSourceInfo || {}).mode === 'live' ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300') + '">'
                + escapeHtml(((syswebCardSourceInfo || {}).mode) === 'live' ? 'rekod kad: Sysweb langsung' : 'rekod kad: salinan tempatan') + '</span>';
            const fillable = rows.filter(r => r.can_complete === true).length;
            const hasPreview = detail.childElementCount > 0;
            if (fillable && !hasPreview) {
                detail.innerHTML = '<p class="text-[11px] text-slate-500 dark:text-zinc-400">' + fillable
                    + ' rekod boleh dilengkap medan yang tiada daripada rekod kad.</p>';
            } else if (!fillable && hasPreview && !detail.querySelector('button')) {
                detail.innerHTML = '';
            }
        }

        function renderSyswebReconcilePreview(data) {
            const detail = document.getElementById('sysweb-reconcile-detail');
            if (!detail) return;
            const interesting = (data.records || []).filter(r => (r.will_fill || []).length || r.status === 'conflict' || r.status === 'ambiguous' || r.status === 'absent');
            const chips = Object.keys(data.counts || {}).filter(key => data.counts[key] && SYSWEB_CARD_SOURCE_META[key]).map(key =>
                '<span class="px-2 py-1 rounded-lg text-[10px] font-bold border ' + SYSWEB_CARD_SOURCE_META[key].cls
                + ' border-slate-200 dark:border-zinc-800">' + SYSWEB_CARD_SOURCE_META[key].label + ' <span class="font-mono">' + data.counts[key] + '</span></span>').join('');
            const list = interesting.length
                ? '<div class="flex flex-wrap items-center gap-1.5">' + interesting.map(r =>
                    '<span class="px-2 py-1 rounded-lg text-[10px] font-mono border border-slate-200 dark:border-zinc-800 ' + (SYSWEB_CARD_SOURCE_META[r.status] || SYSWEB_CARD_SOURCE_META.skip).cls + '" title="'
                    + escapeHtml((r.type || 'student') + ' · ' + r.status) + '">' + escapeHtml(String(r.identifier || '(tanpa pengenalan)'))
                    + (r.will_fill && r.will_fill.length ? ' → ' + r.will_fill.map(f => escapeHtml(SYSWEB_FILL_LABELS[f] || f)).join(', ') : '') + '</span>').join('') + '</div>'
                : '<p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold">Tiada percanggahan atau medan kosong yang boleh dilengkap.</p>';
            const action = USM_SMART_IS_ADMIN && data.fillable_records
                ? '<button type="button" onclick="applySyswebReconcile()" class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-bold transition cursor-pointer"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i>Lengkap ' + data.fillable_records + ' rekod</button>'
                : '';
            const liveChecked = (data.card_source || {}).mode === 'live';
            const liveCounts = data.live || {};
            detail.innerHTML = syswebCardSourceBadge(data.card_source)
                + '<p class="text-[11px] text-slate-500 dark:text-zinc-400 mb-1">' + (liveChecked
                    ? 'Diresahkan terhadap pangkalan kad Sysweb: ' + Number(liveCounts.confirmed || 0) + ' disahkan, '
                        + Number(liveCounts.unconfirmed || 0) + ' tidak disahkan, ' + Number(liveCounts.contradicted || 0) + ' percanggahan sumber.'
                    : 'Tiada semakan langsung dijalankan: ' + Number(liveCounts.unknown || 0) + ' keputusan bergantung pada salinan tempatan sahaja.') + '</p>'
                + '<div class="flex flex-wrap items-center gap-1.5">' + chips + '</div>' + list
                + '<div class="flex flex-wrap items-center justify-between gap-2"><p class="text-[11px] text-slate-500 dark:text-zinc-400">' + escapeHtml(data.message || '')
                + ' Hanya medan yang kosong dilengkap; nilai yang admin taip tidak diubah.</p>' + action + '</div>';
        }

        async function previewSyswebReconcile() {
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh menyemak silang rekod Sysweb.'); return; }
            const detail = document.getElementById('sysweb-reconcile-detail');
            if (detail) detail.innerHTML = '<p class="text-[11px] text-slate-500 dark:text-zinc-400">Menyemak dengan rekod kad…</p>';
            try {
                const data = await syswebCredentialRequest('sysweb_reconcile_records', { apply: false }, 'Semakan silang dengan rekod kad gagal.');
                // The header strip and the record table are drawn from the list this preview just
                // re-graded, so reload that list before painting the panel: otherwise the strip keeps
                // saying nothing has been checked while the panel below it already shows verdicts.
                await loadSyswebSavedRecords();
                renderSyswebReconcilePreview(data);
            } catch (e) {
                if (detail) detail.innerHTML = '<p class="text-[11px] font-semibold text-rose-700 dark:text-rose-300">' + escapeHtml(e.message) + '</p>';
            }
        }

        async function applySyswebReconcile() {
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh melengkap rekod Sysweb.'); return; }
            const detail = document.getElementById('sysweb-reconcile-detail');
            if (detail) detail.innerHTML = '<p class="text-[11px] text-slate-500 dark:text-zinc-400">Menyimpan…</p>';
            try {
                const data = await syswebCredentialRequest('sysweb_reconcile_records', { apply: true }, 'Gagal melengkapkan rekod Sysweb.');
                showToast(data.message || 'Medan rekod Sysweb dilengkap daripada rekod kad.');
            } catch (e) { showToast(e.message); }
            if (detail) detail.innerHTML = '';
            await loadSyswebSavedRecords();
        }

        function setSyswebIssueFilter(code) {
            syswebIssueFilter = syswebIssueFilter === code ? '' : code;
            syswebPage = 1;
            applySyswebFilters();
        }

        function renderSyswebQualityCentre() {
            const checks = syswebSavedChecks;
            const countOf = s => checks.filter(c => c.status === s).length;
            const put = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value); };
            const valid = countOf('valid'); const review = countOf('review'); const invalid = countOf('invalid');
            put('sysweb-quality-valid', valid); put('sysweb-quality-review', review); put('sysweb-quality-invalid', invalid);
            put('sysweb-quality-total', checks.length + ' rekod disemak');

            const byCode = Object.create(null);
            checks.forEach(c => c.issues.forEach(i => {
                if (!byCode[i.code]) byCode[i.code] = { level: i.level, label: i.label, n: 0 };
                byCode[i.code].n++;
            }));
            const host = document.getElementById('sysweb-quality-breakdown');
            if (host) {
                const entries = Object.keys(byCode).map(code => ({ code: code, meta: byCode[code] })).sort((a, b) => b.meta.n - a.meta.n);
                host.innerHTML = entries.length
                    ? entries.map(e => '<button type="button" onclick="setSyswebIssueFilter(\'' + e.code + '\')" title="Tapis rekod dengan isu ini" class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer ' + (syswebIssueFilter === e.code
                        ? 'border-slate-800 bg-slate-800 text-white dark:border-white dark:bg-white dark:text-zinc-900'
                        : (e.meta.level === 'invalid' ? 'border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40' : 'border-amber-200 dark:border-amber-900 text-amber-800 dark:text-amber-300 hover:bg-amber-50 dark:hover:bg-amber-950/40')) + '">'
                        + escapeHtml(e.meta.label) + ' <span class="font-mono">' + e.meta.n + '</span></button>').join('')
                    : '<p class="text-[11px] text-emerald-700 dark:text-emerald-300 font-semibold"><i class="fa-solid fa-circle-check mr-1"></i>Tiada isu data ditemui pada kesemua rekod tersimpan.</p>';
            }
            put('sysweb-quality-hint', checks.length
                ? (invalid ? invalid + ' rekod berstatus Tidak sah dan akan dihalang daripada penghantaran. ' : '') + 'Semakan merangkumi nama, pengenalan, UID, susunan byte, prefiks Sysweb, no. siri, tarikh kad dan status kata laluan.'
                : 'Belum ada rekod untuk disemak.');
            renderSyswebReconcileStrip();
        }
        function renderSyswebWorkspaceContext() {
            const cfg = syswebTypeMode === 'student'
                ? { title:'Ruang Kerja Pelajar', desc:'Rekod pelajar sahaja — carian matrik, pusat pengajian, polisi dan kad.', icon:'fa-graduation-cap', cls:'bg-blue-100 text-blue-700', count:allSyswebSavedRecords.filter(r=>String(r.type||'student').toLowerCase()==='student').length }
                : syswebTypeMode === 'staff'
                    ? { title:'Ruang Kerja Staf', desc:'Rekod staf sahaja — carian nombor staf, jabatan, status dan kad.', icon:'fa-id-badge', cls:'bg-purple-100 text-purple-700', count:allSyswebSavedRecords.filter(r=>String(r.type||'student').toLowerCase()==='staff').length }
                    : { title:'Semua Rekod Sysweb', desc:'Paparan gabungan rekod pelajar dan staf.', icon:'fa-layer-group', cls:'bg-emerald-100 text-emerald-700', count:allSyswebSavedRecords.length };
            const title=document.getElementById('sysweb-workspace-title'), desc=document.getElementById('sysweb-workspace-desc'), icon=document.getElementById('sysweb-workspace-icon'), count=document.getElementById('sysweb-workspace-count');
            if(title) title.textContent=cfg.title; if(desc) desc.textContent=cfg.desc; if(count) count.textContent=cfg.count+' rekod';
            if(icon) { icon.className='w-10 h-10 rounded-xl flex items-center justify-center '+cfg.cls; icon.innerHTML='<i class="fa-solid '+cfg.icon+'"></i>'; }
        }
        function openSyswebDetail(index) {
            const r = allSyswebSavedRecords[Number(index)]; if (!r) return;
            const meta = syswebMetaOf(r), check = validateSyswebRecord(r);
            const fields = [ ['Kategori',meta.label], ['Nama',r.name], [meta.identifier,r.identifier], [meta.school,r.school], [meta.policy,r.policy], ['No. Siri Kad',r.serial_no], ['Tarikh Daftar',r.date_joined], ['Tarikh Tamat',r.expiry_date], ['Warganegara',r.nationality], ['UID Converted (LE)',r.converted_uid], ['Sysweb UID',r.sysweb_uid], ['Tarikh Integrasi',formatSyswebDateTime(r.received_at)], ['Status Semakan',check.status === 'valid' ? 'Lengkap' : check.issues.join(' • ')] ];
            const content=document.getElementById('sysweb-detail-content');
            if(content) content.innerHTML=fields.map(([k,v])=>'<div class="rounded-xl border border-slate-200 dark:border-zinc-700 p-3 min-w-0"><p class="text-[10px] uppercase tracking-wide text-slate-500 dark:text-zinc-400 font-bold mb-1">'+escapeHtml(k)+'</p><p class="font-semibold text-slate-800 dark:text-zinc-100 break-words">'+escapeHtml(v || '-')+'</p></div>').join('');
            const title=document.getElementById('sysweb-detail-title'); if(title) title.textContent=r.name || 'Maklumat Sysweb';
            document.getElementById('sysweb-record-detail')?.classList.remove('hidden');
        }
        function closeSyswebDetail(){ document.getElementById('sysweb-record-detail')?.classList.add('hidden'); }

        function setSyswebTypeMode(mode) {
            syswebTypeMode = (mode === 'student' || mode === 'staff') ? mode : '';
            syswebPage = 1;
            renderSyswebSegmentButtons();
            populateSyswebFilterOptions();
            renderSyswebSavedThead();
            applySyswebFilters();
        }

        function renderSyswebSegmentButtons() {
            const inactive = 'sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer bg-transparent text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800';
            const activeByType = {
                '': 'sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer bg-emerald-600 text-white shadow-sm',
                student: 'sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer bg-blue-600 text-white shadow-sm',
                staff: 'sysweb-seg-btn px-3 py-1.5 rounded-lg text-[11px] font-bold transition cursor-pointer bg-purple-600 text-white shadow-sm'
            };
            [['all', ''], ['student', 'student'], ['staff', 'staff']].forEach(([name, value]) => {
                const btn = document.getElementById('sysweb-seg-' + name);
                if (btn) btn.className = (value === syswebTypeMode ? activeByType[value] : inactive) + (value === '' && syswebTypeMode !== '' ? ' text-slate-600 dark:text-zinc-300' : '');
            });
        }

        function clearSyswebFilters() {
            ['sysweb-search-input', 'sysweb-school-filter', 'sysweb-policy-filter', 'sysweb-expiry-filter', 'sysweb-status-filter', 'sysweb-received-from', 'sysweb-received-to']
                .forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            syswebTypeMode = '';
            syswebIssueFilter = '';
            syswebPage = 1;
            renderSyswebSegmentButtons();
            populateSyswebFilterOptions();
            renderSyswebSavedThead();
            applySyswebFilters();
        }

        function applySyswebFilters() {
            const query = String(document.getElementById('sysweb-search-input')?.value || '').trim().toLowerCase();
            const school = String(document.getElementById('sysweb-school-filter')?.value || '');
            const policy = String(document.getElementById('sysweb-policy-filter')?.value || '');
            const expiry = String(document.getElementById('sysweb-expiry-filter')?.value || '');
            const from = String(document.getElementById('sysweb-received-from')?.value || '');
            const to = String(document.getElementById('sysweb-received-to')?.value || '');
            const statusFilter = String(document.getElementById('sysweb-status-filter')?.value || '');

            recomputeSyswebValidation();
            const matches = [];
            allSyswebSavedRecords.forEach((r, idx) => {
                if (syswebTypeMode && String(r.type || 'student').toLowerCase() !== syswebTypeMode) return;
                const check = syswebSavedChecks[idx] || { status: 'review', issues: [], labels: [] };
                if (statusFilter && check.status !== statusFilter) return;
                if (syswebIssueFilter && !check.issues.some(i => i.code === syswebIssueFilter)) return;
                if (school && String(r.school || '').trim() !== school) return;
                if (policy && String(r.policy || '').trim() !== policy) return;
                if (expiry && syswebExpiryState(r).key !== expiry) return;
                if (from || to) {
                    const day = String(r.received_at || '').slice(0, 10);
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) return;
                    if (from && day < from) return;
                    if (to && day > to) return;
                }
                if (query && syswebSearchableText(r).indexOf(query) === -1) return;
                matches.push(Object.assign({ __index: idx, __check: check }, r));
            });
            syswebFilteredRecords = matches;

            renderSyswebStats(school, policy, expiry, from, to, query);
            renderSyswebWorkspaceContext();
            renderSyswebQualityCentre();
            renderSyswebSavedTable();
            updateSyswebBulkToolbar();
        }

        function updateSyswebBulkToolbar() {
            const toolbar = document.getElementById('sysweb-bulk-toolbar');
            const count = document.getElementById('sysweb-bulk-count');
            if (toolbar) toolbar.classList.toggle('hidden', syswebSelectedIndexes.size === 0);
            if (count) count.textContent = syswebSelectedIndexes.size + ' rekod dipilih';
        }

        function toggleSyswebRecordSelection(index, checked) {
            const n = Number(index);
            if (!Number.isInteger(n) || n < 0 || n >= allSyswebSavedRecords.length) return;
            if (checked) syswebSelectedIndexes.add(n); else syswebSelectedIndexes.delete(n);
            updateSyswebBulkToolbar();
        }

        function selectAllFilteredSysweb() {
            syswebFilteredRecords.forEach(r => { if (Number.isInteger(r.__index)) syswebSelectedIndexes.add(r.__index); });
            renderSyswebSavedTable();
            updateSyswebBulkToolbar();
        }

        function clearSyswebSelection() {
            syswebSelectedIndexes.clear();
            renderSyswebSavedTable();
            updateSyswebBulkToolbar();
        }

        function validateSelectedSysweb() {
            const indexes = Array.from(syswebSelectedIndexes).filter(i => allSyswebSavedRecords[i]);
            if (!indexes.length) { showToast('Pilih sekurang-kurangnya satu rekod.'); return; }
            recomputeSyswebValidation();
            let valid = 0, review = 0, invalid = 0;
            indexes.forEach(i => {
                const verdict = syswebSavedChecks[i] || validateSyswebRecord(allSyswebSavedRecords[i]);
                if (verdict.status === 'valid') valid++; else if (verdict.status === 'invalid') invalid++; else review++;
            });
            applySyswebFilters();
            showToast('Semakan selesai: ' + valid + ' sah, ' + review + ' perlu semakan, ' + invalid + ' tidak sah.');
        }

        function exportSelectedSyswebCSV() {
            const rows = Array.from(syswebSelectedIndexes).sort((a,b) => a-b).map(i => allSyswebSavedRecords[i]).filter(Boolean);
            if (!rows.length) { showToast('Tiada rekod dipilih.'); return; }
            const q = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
            const head = ['Nama','Pengenalan','Kategori','Sekolah/Jabatan','Polisi','No Siri','Tarikh Tamat','Converted UID','Sysweb UID','Status Semakan'];
            const lines = [head.map(q).join(',')];
            rows.forEach(r => {
                const verdict = validateSyswebRecord(r);
                lines.push([r.name,r.identifier,syswebMetaOf(r).label,r.school,r.policy,r.serial_no,r.expiry_date,r.converted_uid,r.sysweb_uid,SYSWEB_STATUS_META[verdict.status]?.label || verdict.status].map(q).join(','));
            });
            const blob = new Blob(['\uFEFF' + lines.join('\r\n')], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob); const a = document.createElement('a');
            a.href = url; a.download = 'sysweb_rekod_dipilih_' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
            showToast(rows.length + ' rekod terpilih dieksport tanpa kata laluan.');
        }

        function renderSyswebStats(school, policy, expiry, from, to, query) {
            const studentCount = allSyswebSavedRecords.filter(r => String(r.type || 'student').toLowerCase() !== 'staff').length;
            const staffCount = allSyswebSavedRecords.filter(r => String(r.type || 'student').toLowerCase() === 'staff').length;
            const setStat = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
            setStat('sysweb-stat-total', allSyswebSavedRecords.length);
            setStat('sysweb-stat-students', studentCount);
            setStat('sysweb-stat-staff', staffCount);
            setStat('sysweb-seg-all-count', '(' + allSyswebSavedRecords.length + ')');
            setStat('sysweb-seg-student-count', '(' + studentCount + ')');
            setStat('sysweb-seg-staff-count', '(' + staffCount + ')');
            setStat('sysweb-records-counter', syswebFilteredRecords.length + ' rekod dipaparkan');

            const lastSync = document.getElementById('sysweb-last-sync-time');
            if (lastSync) {
                const last = allSyswebSavedRecords[allSyswebSavedRecords.length - 1];
                lastSync.textContent = last && last.received_at ? 'Kemaskini: ' + formatSyswebDateTime(last.received_at) : 'Belum ada transaksi';
            }

            const summary = document.getElementById('sysweb-active-filter-summary');
            if (summary) {
                const meta = syswebTypeMode ? SYSWEB_TYPE_META[syswebTypeMode] : null;
                const bits = [];
                if (syswebTypeMode) bits.push('Kategori: ' + meta.label);
                if (school) bits.push((meta ? meta.school : 'Pusat Pengajian / Jabatan') + ': ' + school);
                if (policy) bits.push((meta ? meta.policy : 'Polisi / Status Kad') + ': ' + policy);
                if (expiry) bits.push('Kekeslahan: ' + { valid: 'Aktif', soon: 'Sangat tamat', expired: 'Luput', none: 'Tiada rekod tamat' }[expiry]);
                if (from) bits.push('Diterima mulai ' + from);
                if (to) bits.push('Diterima hingga ' + to);
                if (query) bits.push('Kata kunci: "' + query + '"');
                summary.textContent = !allSyswebSavedRecords.length
                    ? 'Pangkalan rekod masih kosong — hantar satu batch daripada Pengangkut UDA atau tambah rekod manual.'
                    : (bits.length ? 'Penapis aktif: ' + bits.join(' \u00b7 ') : 'Tiada penapis \u2014 semua ' + allSyswebSavedRecords.length + ' rekod dipaparkan.');
            }
        }

        function renderSyswebSavedThead() {
            const thead = document.getElementById('sysweb-saved-thead');
            if (!thead) return;
            const meta = syswebTypeMode ? SYSWEB_TYPE_META[syswebTypeMode] : null;
            const cols = [
                { label: 'Pilih', cls: 'p-3 text-center w-12' },
                { label: '#', cls: 'p-3 text-center w-10' },
                { label: 'Nama', cls: 'p-3 font-sans' },
                { label: meta ? meta.identifier : 'Pengenalan (Matrik/Staf)', cls: 'p-3' },
                { label: 'Kategori', cls: 'p-3 font-sans', hidden: Boolean(meta) }
            ];
            const details = meta
                ? [meta.school, meta.policy, 'No. Siri Kad', 'Tamat Kad']
                : ['Pusat Pengajian / Jabatan', 'Polisi / Status Kad', 'No. Siri Kad', 'Tamat Kad'];
            details.forEach(label => cols.push({ label: label, cls: 'p-3 font-sans' }));
            cols.push({ label: 'UID Converted (LE)', cls: 'p-3 font-mono' });
            cols.push({ label: 'Sysweb UID (00000000+UID)', cls: 'p-3 font-mono' });
            cols.push({ label: 'Semakan', cls: 'p-3 font-sans' });
            cols.push({ label: 'Kata Laluan Sysweb', cls: 'p-3 font-sans text-amber-600 dark:text-amber-400' });
            cols.push({ label: 'Tarikh & Masa Integrasi', cls: 'p-3 font-sans' });
            cols.push({ label: 'Tindakan', cls: 'p-3 text-center' });
            thead.innerHTML = '<tr>' + cols.filter(c => !c.hidden).map(c => '<th class="' + c.cls + '">' + escapeHtml(c.label) + '</th>').join('') + '</tr>';
        }

        function renderSyswebSavedTable() {
            const tbody = document.getElementById('sysweb-saved-tbody');
            if (!tbody) return;
            const totalFiltered = syswebFilteredRecords.length;
            const totalPages = Math.max(1, Math.ceil(totalFiltered / syswebPageSize));
            syswebPage = Math.min(Math.max(1, syswebPage), totalPages);
            const startIdx = (syswebPage - 1) * syswebPageSize;
            const paginated = syswebFilteredRecords.slice(startIdx, startIdx + syswebPageSize);
            const endIdx = Math.min(startIdx + syswebPageSize, totalFiltered);
            const columnCount = syswebTypeMode ? 14 : 15;

            const info = document.getElementById('sysweb-pagination-info');
            if (info) info.textContent = totalFiltered === 0 ? 'Tiada rekod' : ('Menunjukkan ' + (startIdx + 1) + '\u2013' + endIdx + ' daripada ' + totalFiltered + ' rekod');
            const indicator = document.getElementById('sysweb-page-indicator');
            if (indicator) indicator.textContent = syswebPage + ' / ' + totalPages;
            const prevBtn = document.getElementById('sysweb-prev-btn');
            if (prevBtn) prevBtn.disabled = syswebPage <= 1;
            const nextBtn = document.getElementById('sysweb-next-btn');
            if (nextBtn) nextBtn.disabled = syswebPage >= totalPages;

            if (!totalFiltered) {
                tbody.innerHTML = '<tr><td colspan="' + columnCount + '" class="p-8 text-center text-slate-400">'
                    + (allSyswebSavedRecords.length ? 'Tiada rekod integrasi Sysweb yang sepadan dengan penapis ini.' : 'Belum ada rekod disimpan dalam pangkalan integrasi Sysweb.')
                    + '</td></tr>';
                return;
            }

            const showType = !syswebTypeMode;
            tbody.innerHTML = paginated.map((r, i) => {
                const meta = syswebMetaOf(r);
                const idx = r.__index;
                const expiryState = syswebExpiryState(r);
                const check = r.__check || syswebSavedChecks[idx] || validateSyswebRecord(r);
                const extras = [];
                if (r.nationality) extras.push('Warganegara: ' + r.nationality);
                if (r.date_joined) extras.push('Masuk: ' + r.date_joined);
                if (r.notes) extras.push('Nota: ' + r.notes);
                const cells = [
                    '<td class="p-3 text-center"><input type="checkbox" aria-label="Pilih rekod ' + escapeHtml(r.identifier || r.name || '') + '" onchange="toggleSyswebRecordSelection(' + idx + ',this.checked)" ' + (syswebSelectedIndexes.has(idx) ? 'checked' : '') + ' class="rounded accent-emerald-600"></td>',
                    '<td class="p-3 text-slate-400 text-center font-mono text-[11px]">' + (startIdx + i + 1) + '</td>',
                    '<td class="p-3 font-sans"><div class="space-y-0.5"><button type="button" onclick="openSyswebDetail(' + idx + ')" class="text-left font-semibold text-slate-900 dark:text-white hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline decoration-dotted underline-offset-4">' + escapeHtml(r.name || '-') + '</button>'
                        + (extras.length ? '<p class="text-[10px] text-slate-400 dark:text-zinc-400 font-mono">' + escapeHtml(extras.join(' \u00b7 ')) + '</p>' : '')
                        + (r.updated_at ? '<p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">Disunting ' + escapeHtml(formatSyswebDateTime(r.updated_at)) + '</p>' : '')
                        + '</div></td>',
                    '<td class="p-3 font-mono font-medium">' + escapeHtml(r.identifier || '-') + '</td>'
                ];
                if (showType) {
                    cells.push('<td class="p-3 font-sans"><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + meta.badge + '"><i class="fa-solid ' + meta.icon + ' text-[9px]"></i>' + meta.label + '</span></td>');
                }
                cells.push('<td class="p-3 font-sans text-slate-700 dark:text-zinc-300">' + escapeHtml(r.school || '-') + '</td>');
                cells.push('<td class="p-3 font-sans">' + (r.policy ? '<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300">' + escapeHtml(r.policy) + '</span>' : '<span class="text-slate-400">-</span>') + '</td>');
                cells.push('<td class="p-3 font-mono text-[11px] text-slate-600 dark:text-zinc-400">' + escapeHtml(r.serial_no || '-') + '</td>');
                cells.push('<td class="p-3 font-sans"><div class="space-y-0.5"><p class="font-mono text-[11px] text-slate-700 dark:text-zinc-300">' + escapeHtml(r.expiry_date || '-') + '</p>'
                    + '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + expiryState.chip + '"><i class="fa-solid ' + expiryState.icon + ' text-[9px]"></i>' + expiryState.label + '</span></div></td>');
                cells.push('<td class="p-3 font-mono text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">' + escapeHtml(r.converted_uid || '-') + '</td>');
                cells.push('<td class="p-3 font-mono text-indigo-600 dark:text-indigo-400 font-bold text-[11px]"><div class="flex items-center gap-1.5">'
                    + '<span>' + escapeHtml(r.sysweb_uid || '-') + '</span>'
                    + '<button type="button" onclick="copySyswebUid(\'' + escapeHtml(r.sysweb_uid || '') + '\')" class="text-slate-400 hover:text-indigo-600 p-1 rounded transition" title="Salin Sysweb UID"><i class="fa-regular fa-copy text-xs"></i></button></div></td>');
                cells.push('<td class="p-3 font-sans" title="' + escapeHtml(check.labels.join(' \u00b7 ') || 'Tiada isu data') + '">' + syswebStatusChip(check.status, 'text-[9px]') + '</td>');
                const revealedPw = syswebRevealedPasswords[idx];
                cells.push('<td class="p-3 font-mono text-xs"><div class="flex items-center gap-1.5">'
                    + (USM_SMART_IS_ADMIN
                        ? (revealedPw
                            ? '<span class="font-bold text-amber-600 dark:text-amber-400 tracking-widest">' + escapeHtml(revealedPw) + '</span>'
                                + '<button type="button" onclick="hideSyswebPassword(' + idx + ')" class="text-amber-600 hover:text-amber-800 p-1 rounded transition" title="Sembunyi semula"><i class="fa-solid fa-eye-slash text-xs"></i></button>'
                            : '<span class="text-slate-400 dark:text-zinc-400 tracking-widest">' + (r.has_password === false ? 'belum dijana' : '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') + '</span>'
                                + (r.has_password === false ? '' : '<button type="button" onclick="revealSyswebPassword(' + idx + ')" class="text-slate-400 hover:text-amber-600 p-1 rounded transition" title="Paparkan (direkod dalam audit log)"><i class="fa-regular fa-eye text-xs"></i></button>')
                                + (r.has_password === false ? '' : '<button type="button" onclick="copySyswebPassword(' + idx + ')" class="text-slate-400 hover:text-amber-600 p-1 rounded transition" title="Salin kata laluan"><i class="fa-regular fa-copy text-xs"></i></button>'))
                        : '<span class="text-slate-400 dark:text-zinc-400 tracking-widest">\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022</span>')
                    + (USM_SMART_IS_ADMIN && r.has_password !== false ? '<button type="button" onclick="resetSyswebPassword(' + idx + ')" class="text-slate-400 hover:text-rose-600 p-1 rounded transition" title="Set semula kata laluan"><i class="fa-solid fa-rotate-right text-xs"></i></button>' : '')
                    + '</div></td>');
                cells.push('<td class="p-3 text-slate-500 font-sans text-[11px]">' + escapeHtml(r.received_at ? formatSyswebDateTime(r.received_at) : 'Sebelum ini') + '</td>');
                cells.push('<td class="p-3 text-center"><div class="inline-flex items-center gap-1.5">'
                    + (USM_SMART_IS_ADMIN
                        ? '<button type="button" onclick="openSyswebEditDrawer(' + idx + ')" class="rounded-lg border border-blue-200 dark:border-blue-900 px-2 py-1 text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-950/40 transition cursor-pointer" title="Sunting butiran penuh"><i class="fa-solid fa-pen text-[10px]"></i></button>'
                            + '<button type="button" onclick="deleteSyswebRecord(' + idx + ')" class="rounded-lg border border-rose-200 dark:border-rose-900 px-2 py-1 text-rose-700 dark:text-rose-300 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer" title="Padam rekod"><i class="fa-solid fa-trash text-[10px]"></i></button>'
                        : '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300"><i class="fa-solid fa-check text-[9px]"></i> Disimpan</span>')
                    + '</div></td>');
                return '<tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-900/60 transition-colors">' + cells.join('') + '</tr>';
            }).join('');
        }

        let syswebRevealedPasswords = Object.create(null);

        async function syswebCredentialRequest(action, body, fallbackMessage) {
            const res = await fetch('index.php?action=' + action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': USMSMART_CSRF_TOKEN },
                body: JSON.stringify(body || {})
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) throw new Error(data.message || fallbackMessage || 'Permintaan kata laluan ditolak.');
            return data;
        }

        async function revealSyswebPassword(index) {
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh memaparkan kata laluan.'); return; }
            try {
                const data = await syswebCredentialRequest('get_sysweb_password', { index: Number(index) });
                syswebRevealedPasswords[index] = data.password;
                renderSyswebSavedTable();
                showToast('Kata laluan dipaparkan buat sementara. Paparan ini tercatat dalam audit log.');
            } catch (e) { showToast(e.message); }
        }

        function hideSyswebPassword(index) {
            delete syswebRevealedPasswords[index];
            renderSyswebSavedTable();
        }

        async function copySyswebPassword(index) {
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh menyalin kata laluan.'); return; }
            try {
                let value = syswebRevealedPasswords[index];
                if (!value) {
                    const data = await syswebCredentialRequest('get_sysweb_password', { index: Number(index) });
                    value = data.password;
                }
                await navigator.clipboard.writeText(value);
                showToast('Kata laluan disalin. Tindakan ini tercatat dalam audit log.');
            } catch (e) { showToast(e.message || 'Papan keratan tidak tersedia.'); }
        }

        async function resetSyswebPassword(index) {
            const record = allSyswebSavedRecords[index];
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh set semula kata laluan.'); return; }
            if (!record) { showToast('Rekod Sysweb tidak ditemui. Muat semula senarai.'); return; }
            if (!confirm('Set semula kata laluan Sysweb untuk ' + (record.name || record.identifier) + '? Kata laluan lama tidak lagi sah.')) return;
            try {
                const data = await syswebCredentialRequest('reset_sysweb_password', { index: Number(index) });
                syswebRevealedPasswords[index] = data.password;
                await loadSyswebSavedRecords();
                showToast('Kata laluan baharu dijana dan hanya dipaparkan sekali.');
            } catch (e) { showToast(e.message); }
        }

        async function generateMissingSyswebPasswords() {
            if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh menjana kata laluan.'); return; }
            try {
                const data = await syswebCredentialRequest('sysweb_generate_missing_passwords', {});
                showToast(data.message || ((data.issued || 0) + ' kata laluan dijana.'));
                await loadSyswebSavedRecords();
            } catch (e) { showToast(e.message); }
        }

        const SYSWEB_DRAWER_FIELDS = {
            name: 'sysweb-f-name', identifier: 'sysweb-f-identifier', school: 'sysweb-f-school',
            policy: 'sysweb-f-policy', serial_no: 'sysweb-f-serial', date_joined: 'sysweb-f-joined',
            expiry_date: 'sysweb-f-expiry', nationality: 'sysweb-f-nationality', notes: 'sysweb-f-notes',
            converted_uid: 'sysweb-f-uid',
            source_uid: 'sysweb-f-source-uid'
        };

        function setSyswebDrawerType(type) {
            syswebDrawerType = type === 'staff' ? 'staff' : 'student';
            const meta = SYSWEB_TYPE_META[syswebDrawerType];
            const typeInput = document.getElementById('sysweb-f-type');
            if (typeInput) typeInput.value = syswebDrawerType;
            const activeCls = syswebDrawerType === 'staff'
                ? 'sysweb-kind-btn px-3 py-1.5 rounded-md text-[11px] font-bold transition cursor-pointer bg-purple-600 text-white shadow-sm'
                : 'sysweb-kind-btn px-3 py-1.5 rounded-md text-[11px] font-bold transition cursor-pointer bg-blue-600 text-white shadow-sm';
            const inactiveCls = 'sysweb-kind-btn px-3 py-1.5 rounded-md text-[11px] font-bold transition cursor-pointer bg-transparent text-slate-600 dark:text-zinc-300 hover:bg-slate-100 dark:hover:bg-zinc-800';
            const studentBtn = document.getElementById('sysweb-kind-student');
            const staffBtn = document.getElementById('sysweb-kind-staff');
            if (studentBtn) studentBtn.className = syswebDrawerType === 'student' ? activeCls : inactiveCls;
            if (staffBtn) staffBtn.className = syswebDrawerType === 'staff' ? activeCls : inactiveCls;
            const labelIdentifier = document.getElementById('sysweb-l-identifier');
            const labelSchool = document.getElementById('sysweb-l-school');
            const labelPolicy = document.getElementById('sysweb-l-policy');
            if (labelIdentifier) labelIdentifier.textContent = meta.identifier;
            if (labelSchool) labelSchool.textContent = meta.school;
            if (labelPolicy) labelPolicy.textContent = meta.policy;
            const nationalityField = document.getElementById('sysweb-nationality-field');
            const notesField = document.getElementById('sysweb-notes-field');
            if (nationalityField) nationalityField.classList.toggle('hidden', syswebDrawerType === 'staff');
            if (notesField) notesField.classList.toggle('hidden', syswebDrawerType !== 'staff');
            syncSyswebUidPreview();
        }

        function syncSyswebUidPreview() {
            const uid = String(document.getElementById('sysweb-f-uid')?.value || '').trim().replace(/\s+/g, '').toUpperCase();
            const valid = /^(?:[0-9A-F]{2})+$/.test(uid);
            const uidOut = document.getElementById('sysweb-derived-uid');
            const pwdOut = document.getElementById('sysweb-derived-password');
            const hint = document.getElementById('sysweb-f-uid-hint');
            if (uidOut) uidOut.textContent = valid ? ('00000000' + uid) : '-';
            if (pwdOut) pwdOut.textContent = valid ? 'Dijana oleh pelayan' : '-';
            if (hint) {
                hint.textContent = uid === ''
                    ? 'Mesti hexadecimal dengan bilangan aksara genap.'
                    : (valid ? 'Format UID sah \u2014 ' + (uid.length / 2) + ' bait.' : 'UID mesti aksara hexadecimal sahaja dan bilangan aksara genap.');
                hint.className = 'text-[10px] mt-1 ' + (uid === '' ? 'text-slate-500 dark:text-zinc-400' : (valid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'));
            }
            const rawField = document.getElementById('sysweb-f-source-uid');
            const rawHint = document.getElementById('sysweb-f-source-uid-hint');
            const raw = String(rawField?.value || '').trim().replace(/\s+/g, '').toUpperCase();
            if (rawHint) {
                const rawValid = /^(?:[0-9A-F]{2})+$/.test(raw);
                if (raw === '') {
                    rawHint.textContent = 'Isi untuk membolehkan pelayan mengesahkan susunan byte UID converted.';
                    rawHint.className = 'text-[10px] mt-1 text-slate-500 dark:text-zinc-400';
                } else if (!rawValid) {
                    rawHint.textContent = 'UID mentah mesti aksara hexadecimal dengan bilangan aksara genap.';
                    rawHint.className = 'text-[10px] mt-1 text-rose-600 dark:text-rose-400';
                } else if (!valid) {
                    rawHint.textContent = 'UID converted perlu pembetulan dahulu.';
                    rawHint.className = 'text-[10px] mt-1 text-slate-500 dark:text-zinc-400';
                } else {
                    const expected = syswebLittleEndianOf(raw);
                    rawHint.textContent = expected === uid ? 'Susunan byte sepadan \u2014 converted = ' + expected : 'Tidak sepadan \u2014 little-endian UID mentah ialah ' + expected;
                    rawHint.className = 'text-[10px] mt-1 ' + (expected === uid ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400');
                }
            }
        }

        // The card prints its UID big-endian; Sysweb wants it byte-reversed. Rather than make the
        // operator do that in their head, derive the converted value from the raw one.
        function applySyswebRawUidByteOrder() {
            const rawField = document.getElementById('sysweb-f-source-uid');
            const uidField = document.getElementById('sysweb-f-uid');
            const raw = String(rawField?.value || '').trim().replace(/\s+/g, '').toUpperCase();
            if (!/^(?:[0-9A-F]{2})+$/.test(raw)) { showSyswebDrawerError('UID mentah mesti hexadecimal dengan bilangan aksara genap.'); return; }
            if (uidField) uidField.value = syswebLittleEndianOf(raw);
            showSyswebDrawerError('');
            syncSyswebUidPreview();
        }

        function openSyswebEditDrawer(index) {
            const drawer = document.getElementById('sysweb-edit-drawer');
            if (!drawer) return;
            const isNew = Number(index) < 0;
            const record = isNew ? null : allSyswebSavedRecords[Number(index)];
            if (!isNew && !record) { showToast('Rekod Sysweb tidak ditemui. Muat semula senarai.'); return; }

            document.getElementById('sysweb-f-index').value = isNew ? '-1' : String(index);
            document.getElementById('sysweb-drawer-title').textContent = isNew ? 'Tambah Rekod Sysweb Baharu' : 'Sunting Rekod Sysweb';
            document.getElementById('sysweb-drawer-subtitle').textContent = isNew
                ? 'Rekod manual ditambah terus ke pangkalan integrasi Sysweb.'
                : ((record.name || '') + (record.identifier ? ' \u00b7 ' + record.identifier : ''));
            document.getElementById('sysweb-drawer-save-label').textContent = isNew ? 'Tambah Rekod' : 'Simpan Perubahan';
            const deleteBtn = document.getElementById('sysweb-drawer-delete');
            if (deleteBtn) deleteBtn.classList.toggle('hidden', isNew);

            setSyswebDrawerType(isNew ? 'student' : (String(record.type || 'student').toLowerCase() === 'staff' ? 'staff' : 'student'));
            Object.keys(SYSWEB_DRAWER_FIELDS).forEach(key => {
                const el = document.getElementById(SYSWEB_DRAWER_FIELDS[key]);
                if (!el) return;
                const value = isNew ? '' : String(record[key] ?? '');
                el.value = (key === 'date_joined' || key === 'expiry_date') ? value.slice(0, 10) : (key === 'converted_uid' ? value.toUpperCase() : value);
            });
            showSyswebDrawerError('');
            drawer.classList.remove('hidden');
            syncSyswebUidPreview();
            const firstField = document.getElementById('sysweb-f-name');
            if (firstField) firstField.focus();
        }

        function closeSyswebEditDrawer() {
            const drawer = document.getElementById('sysweb-edit-drawer');
            if (drawer) drawer.classList.add('hidden');
        }

        function showSyswebDrawerError(message) {
            const box = document.getElementById('sysweb-drawer-error');
            if (!box) return;
            box.textContent = message;
            box.classList.toggle('hidden', message === '');
        }

        async function saveSyswebDrawer(event) {
            event.preventDefault();
            const index = Number(document.getElementById('sysweb-f-index')?.value ?? -1);
            const isNew = index < 0;
            const uid = String(document.getElementById('sysweb-f-uid')?.value || '').trim().replace(/\s+/g, '').toUpperCase();
            const payload = {
                name: String(document.getElementById('sysweb-f-name')?.value || '').trim(),
                identifier: String(document.getElementById('sysweb-f-identifier')?.value || '').trim(),
                type: syswebDrawerType,
                converted_uid: uid,
                source_uid: String(document.getElementById('sysweb-f-source-uid')?.value || '').trim().replace(/\s+/g, '').toUpperCase(),
                school: String(document.getElementById('sysweb-f-school')?.value || '').trim(),
                policy: String(document.getElementById('sysweb-f-policy')?.value || '').trim(),
                serial_no: String(document.getElementById('sysweb-f-serial')?.value || '').trim(),
                date_joined: String(document.getElementById('sysweb-f-joined')?.value || '').trim(),
                expiry_date: String(document.getElementById('sysweb-f-expiry')?.value || '').trim(),
                nationality: syswebDrawerType === 'student' ? String(document.getElementById('sysweb-f-nationality')?.value || '').trim() : '',
                notes: syswebDrawerType === 'staff' ? String(document.getElementById('sysweb-f-notes')?.value || '').trim() : ''
            };
            if (!payload.name || !payload.identifier) { showSyswebDrawerError('Nama dan nombor pengenalan wajib diisi.'); return; }
            if (!/^(?:[0-9A-F]{2})+$/.test(uid)) { showSyswebDrawerError('UID converted mesti hexadecimal dengan bilangan aksara genap.'); return; }
            const rawUid = String(document.getElementById('sysweb-f-source-uid')?.value || '').trim().replace(/\s+/g, '').toUpperCase();
            if (rawUid && !/^(?:[0-9A-F]{2})+$/.test(rawUid)) { showSyswebDrawerError('UID mentah mesti hexadecimal dengan bilangan aksara genap.'); return; }
            if (rawUid && syswebLittleEndianOf(rawUid) !== uid) { showSyswebDrawerError('Susunan byte UID converted tidak sepadan dengan UID mentah kad.'); return; }
            if (!isNew) payload.index = index;

            const saveBtn = event.submitter || document.querySelector('button[form="sysweb-edit-form"]');
            if (saveBtn) saveBtn.disabled = true;
            try {
                const res = await fetch('index.php?action=' + (isNew ? 'create_sysweb_record' : 'update_sysweb_record'), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': USMSMART_CSRF_TOKEN },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Gagal menyimpan rekod Sysweb.');
                closeSyswebEditDrawer();
                showToast(data.message);
                await loadSyswebSavedRecords();
            } catch (err) {
                showSyswebDrawerError(err.message || 'Ralat rangkaian semasa menyimpan rekod.');
            } finally {
                if (saveBtn) saveBtn.disabled = false;
            }
        }

        function deleteSyswebRecordFromDrawer() {
            const index = Number(document.getElementById('sysweb-f-index')?.value ?? -1);
            closeSyswebEditDrawer();
            if (index >= 0) deleteSyswebRecord(index);
        }

        document.addEventListener('keydown', function (event) {
            const drawer = document.getElementById('sysweb-edit-drawer');
            if (event.key === 'Escape' && drawer && !drawer.classList.contains('hidden')) closeSyswebEditDrawer();
        });

        async function deleteSyswebRecord(index) {
            const r = allSyswebSavedRecords[index]; if (!r) return;
            if (!confirm('Padam rekod Sysweb untuk ' + (r.name || r.identifier) + '? Tindakan ini tidak boleh diundur.')) return;
            try {
                const res = await fetch('index.php?action=delete_sysweb_record', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN},body:JSON.stringify({index:index})});
                const data = await res.json(); if (!res.ok || !data.success) throw new Error(data.message || 'Gagal memadam rekod.');
                showToast(data.message); await loadSyswebSavedRecords();
            } catch (e) { showToast(e.message || 'Ralat memadam rekod.'); }
        }

        function copySyswebUid(uid) {
            if (!uid) return;
            navigator.clipboard.writeText(uid).then(() => {
                showToast('Sysweb UID ' + uid + ' disalin ke papan keratan.');
            }).catch(() => {
                showToast('Salin: ' + uid);
            });
        }

        async function loadSyswebExportHistory() {
            const host = document.getElementById('sysweb-export-history-list');
            if (!host) return;
            hideSyswebHistoryStats();
            try {
                const response = await fetch('index.php?action=get_sysweb_export_history', {credentials:'same-origin', headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Gagal membaca sejarah.');
                syswebHistoryRows = Array.isArray(data.history) ? data.history : [];
                const items = syswebHistoryFiltered();
                renderSyswebHistoryStats();
                if (!syswebHistoryRows.length) { host.innerHTML = '<div class="p-4 text-xs text-slate-400">Belum ada sejarah eksport Sysweb.</div>'; return; }
                if (!items.length) { host.innerHTML = '<div class="p-4 text-xs text-slate-400">Tiada batch sepadan dengan julat atau tapisan ini.</div>'; return; }
                host.innerHTML = items.map(item => {
                    const status = item.status === 'success' ? ['Berjaya','bg-emerald-100 text-emerald-700'] : (item.status === 'partial' ? ['Sebahagian','bg-amber-100 text-amber-700'] : ['Gagal','bg-rose-100 text-rose-700']);
                    const retry = Number(item.failed_count || 0) > 0 && Array.isArray(item.failed_records) ? '<button type="button" onclick="retrySyswebFailedBatch(\'' + String(item.batch_id || '').replace(/[^A-Za-z0-9-]/g,'') + '\')" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-[11px] font-bold"><i class="fa-solid fa-rotate-right mr-1"></i>Cuba semula ' + Number(item.failed_count) + '</button>' : '';
                    const metric = (label, value, cls) => '<span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold ' + cls + '"><span class="opacity-70">' + label + '</span><span class="font-mono">' + value + '</span></span>';
                    const metrics = metric('Pelajar', Number(item.student_count || 0), 'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300')
                        + metric('Staf', Number(item.staff_count || 0), 'bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300')
                        + metric('Jumlah', Number(item.total || 0), 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300')
                        + metric('Berjaya', Number(item.saved_new || 0) + Number(item.duplicates_skipped || 0), 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300')
                        + metric('Baharu', Number(item.saved_new || 0), 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300')
                        + (Number(item.duplicates_skipped || 0) ? metric('Duplikasi', Number(item.duplicates_skipped), 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400') : '')
                        + (Number(item.failed_count || 0) ? metric('Gagal', Number(item.failed_count), 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300') : '')
                        + (item.passwords_issued ? metric('Kata laluan', Number(item.passwords_issued), 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300') : '');
                    return '<div class="px-4 py-3 flex flex-wrap items-center justify-between gap-3"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="font-mono text-xs font-bold text-slate-800 dark:text-zinc-100">' + escapeHtml(item.batch_id || '-') + '</span><span class="px-2 py-0.5 rounded-full text-[10px] font-bold ' + status[1] + '">' + status[0] + '</span></div><p class="text-[11px] text-slate-500 dark:text-zinc-400 mt-1">' + escapeHtml(item.source || 'USMSmart') + ' · ' + escapeHtml(formatSyswebDateTime(item.created_at) || item.created_at || '') + ' · Oleh ' + escapeHtml(item.user || '-') + '</p><div class="flex flex-wrap items-center gap-1.5 mt-1.5">' + metrics + '</div></div><div class="flex items-center gap-2">' + retry + '</div></div>';
                }).join('');
            } catch (error) { hideSyswebHistoryStats(); host.innerHTML = '<div class="p-4 text-xs text-rose-600">Tidak dapat memuatkan sejarah: ' + escapeHtml(error.message) + '</div>'; }
        }

        async function retrySyswebFailedBatch(batchId) {
            if (!USMSMART_IS_ADMIN) { showToast('Hanya Admin boleh menghantar semula rekod.'); return; }
            try {
                const historyResponse = await fetch('index.php?action=get_sysweb_export_history', {credentials:'same-origin', headers:{'Accept':'application/json'}});
                const historyData = await historyResponse.json();
                const batch = (historyData.history || []).find(x => x.batch_id === batchId);
                if (!batch || !Array.isArray(batch.failed_records) || !batch.failed_records.length) { showToast('Tiada rekod gagal untuk batch ini.'); return; }
                const records = batch.failed_records.map(x => x.record).filter(Boolean);
                if (!records.length) { showToast('Rekod gagal tidak dapat dipulihkan.'); return; }
                if (!confirm('Hantar semula ' + records.length + ' rekod gagal daripada ' + batchId + '?')) return;
                const response = await fetch('index.php?action=sysweb_demo_receive', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN}, body:JSON.stringify({source:'Retry ' + batchId, records})});
                const result = await response.json();
                showToast(result.message || (result.success ? 'Penghantaran semula selesai.' : 'Penghantaran semula sebahagian/gagal.'));
                await loadSyswebExportHistory();
                await loadSyswebSavedRecords();
            } catch (error) { showToast('Gagal menghantar semula: ' + error.message); }
        }

        async function sendSyswebRecordsFromSegment() {
            const sendBtn = document.getElementById('sysweb-segment-send-btn');
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i><span>Sedang Menghantar...</span>';
            }
            await sendSyswebRecords();
            if (sendBtn) {
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1.5"></i><span>Hantar Batch Ini Sekarang</span>';
            }
            loadSyswebSavedRecords();
        }

        async function downloadSyswebCSV(authorisePasswords) {
            const rows = syswebFilteredRecords.length ? syswebFilteredRecords : allSyswebSavedRecords;
            if (!rows.length) {
                showToast('Tiada rekod Sysweb untuk dimuat turun.');
                return;
            }
            let credentials = null;
            if (authorisePasswords) {
                if (!USM_SMART_IS_ADMIN) { showToast('Hanya Admin boleh mengeksport kata laluan.'); return; }
                if (!confirm('Eksport ini akan menyertakan kata laluan Sysweb bagi ' + rows.length + ' rekod dalam satu fail. Fail tersebut tidak dilindungi dan eksport ini direkodkan dalam audit log. Teruskan?')) return;
                try {
                    const data = await syswebCredentialRequest('sysweb_authorize_password_export', {});
                    credentials = Object.create(null);
                    (data.passwords || []).forEach(p => { credentials[p.index] = p.password; });
                } catch (e) { showToast(e.message); return; }
            }
            const storeIndexOf = r => Number.isFinite(r.__index) ? r.__index : allSyswebSavedRecords.indexOf(r);
            const cell = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
            const head = ['Bil', 'Nama', 'Pengenalan', 'Kategori', 'Sekolah/Jabatan', 'Polisi/Status Kad', 'No Siri Kad', 'Tarikh Masuk', 'Tarikh Tamat', 'Kekeslahan Kad', 'Kewarganegaraan', 'Converted_UID', 'Sysweb_UID', 'Semakan', 'Isu Ditemui', 'Tarikh_Diterima', 'Tarikh_Disunting'];
            if (credentials) head.push('Kata_Laluan_Dibenarkan');
            let csv = head.join(',') + "\r\n";
            const checks = credentials ? validateSyswebBatch(rows, [], {}) : null;
            rows.forEach((r, idx) => {
                const check = r.__check || (checks ? checks[idx] : validateSyswebRecord(r));
                const line = [
                    idx + 1, cell(r.name), cell(r.identifier), cell(syswebMetaOf(r).label), cell(r.school), cell(r.policy),
                    cell(r.serial_no), cell(r.date_joined), cell(r.expiry_date), cell(syswebExpiryState(r).label),
                    cell(r.nationality), cell(r.converted_uid), cell(r.sysweb_uid),
                    cell(SYSWEB_STATUS_META[check.status].label), cell(check.labels.join('; ')),
                    cell(r.received_at), cell(r.updated_at)
                ];
                if (credentials) line.push(cell(credentials[storeIndexOf(r)] || ''));
                csv += line.join(',') + "\r\n";
            });
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = (credentials ? 'rekod_integrasi_sysweb_dengan_kata_laluan_' : 'rekod_integrasi_sysweb_') + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast(credentials
                ? rows.length + ' rekod dimuat turun BERSAMA kata laluan. Simpan fail ini dengan selamat.'
                : rows.length + ' rekod Sysweb dimuat turun tanpa kata laluan (ikut paparan penapis semasa).');
        }

        function confirmSyswebCredentialExport() {
            downloadSyswebCSV(true);
        }

        async function confirmClearSyswebData() {
            if (!confirm('Adakah anda pasti ingin mengosongkan semua rekod integrasi Sysweb? Tindakan ini tidak boleh diundur.')) return;
            try {
                const res = await fetch('index.php?action=clear_sysweb_records', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-CSRF-Token': USMSMART_CSRF_TOKEN }
                });
                const d = await res.json();
                if (d.success) {
                    showToast(d.message || 'Rekod Sysweb telah dikosongkan.');
                    allSyswebSavedRecords = [];
                    syswebFilteredRecords = [];
                    syswebSelectedIndexes.clear();
                    applySyswebFilters();
                } else {
                    showToast(d.message || 'Gagal mengosongkan rekod.');
                }
            } catch (e) {
                showToast('Ralat rangkaian: ' + e.message);
            }
        }

        function openSyswebDemo() {
            navigateToSyswebSegment('transporter');
        }

        function openSyswebWithRecords(records, sourceName = 'USMSmart Live Records', sourceLabel = 'Rekod Pilihan') {
            navigateToSyswebSegment('directory', records, sourceName, sourceLabel);
        }

        function closeSyswebDemo() {
            const m = document.getElementById('usm-sysweb-demo-modal');
            if (m) {
                m.classList.add('hidden');
                m.classList.remove('flex');
            }
        }

        function renderSyswebTable() {
            const records = getActiveSyswebRecords();
            const tbody = document.getElementById('usm-sysweb-demo-samples');
            const badge = document.getElementById('usm-sysweb-record-count-badge');
            const indicator = document.getElementById('usm-sysweb-source-indicator');
            const toggleBtn = document.getElementById('usm-sysweb-toggle-source-btn');

            if (badge) badge.textContent = records.length + ' rekod sedia dihantar';
            if (indicator) {
                indicator.textContent = (currentSyswebSource === 'uda' && lastExportedUdaRecords.length)
                    ? '(Daripada ' + (currentSyswebSourceLabel || 'Eksport Terkini') + ': ' + lastExportedUdaRecords.length + ' rekod)'
                    : '(Rekod Contoh / Demo: 2 rekod)';
            }
            if (toggleBtn) {
                if (lastExportedUdaRecords && lastExportedUdaRecords.length > 0) {
                    toggleBtn.classList.remove('hidden');
                    toggleBtn.innerHTML = currentSyswebSource === 'uda'
                        ? '<i class="fa-solid fa-flask mr-1"></i><span>Tukar ke Rekod Contoh (Demo)</span>'
                        : '<i class="fa-solid fa-database mr-1"></i><span>Kembali ke ' + escapeHtml(currentSyswebSourceLabel || 'Rekod Aktif') + ' (' + lastExportedUdaRecords.length + ')</span>';
                } else {
                    toggleBtn.classList.add('hidden');
                }
            }

            if (tbody) {
                if (!records.length) {
                    tbody.innerHTML = '<tr><td colspan="6" class="p-4 text-center text-slate-400">Tiada rekod untuk dipaparkan.</td></tr>';
                } else {
                    tbody.innerHTML = records.map((r, i) => {
                        const typeBadge = r.type === 'staff'
                            ? '<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-100 text-purple-700 dark:bg-purple-950/60 dark:text-purple-300">Staf</span>'
                            : '<span class="inline-flex items-center whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300">Pelajar</span>';
                        return '<tr class="hover:bg-slate-50/60 dark:hover:bg-zinc-900/60 transition-colors">' +
                            '<td class="p-2.5 text-slate-400">' + (i + 1) + '</td>' +
                            '<td class="p-2.5 font-medium text-slate-900 dark:text-white">' + escapeHtml(r.name) + '</td>' +
                            '<td class="p-2.5">' + escapeHtml(r.identifier) + '</td>' +
                            '<td class="p-2.5">' + typeBadge + '</td>' +
                            '<td class="p-2.5 font-mono text-emerald-600 dark:text-emerald-400">' + escapeHtml(r.converted_uid) + '</td>' +
                            '<td class="p-2.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">' + escapeHtml(r.sysweb_uid) + '</td>' +
                        '</tr>';
                    }).join('');
                }
            }
        }

        function exportSingleStudentToSysweb(studentId) {
            const student = (allMasterStudents || []).find(s => Number(s.id) === Number(studentId));
            if (!student) {
                showToast('Rekod pelajar tidak ditemui.');
                return;
            }
            const formatted = formatRecordsForSysweb([student], 'student');
            navigateToSyswebSegment('student_directory', formatted, 'USMSmart Direktori Pelajar', 'Pelajar: ' + (student.name || student.matric));
            showToast('Memuatkan rekod pelajar ke Segmen Sysweb...');
        }

        function bulkExportSelectedSysweb() {
            if (!masterSelectedIds || masterSelectedIds.size === 0) {
                showToast('Sila pilih sekurang-kurangnya satu rekod pelajar.');
                return;
            }
            const selectedStudents = (allMasterStudents || []).filter(s => masterSelectedIds.has(String(s.id)));
            if (!selectedStudents.length) {
                showToast('Tiada rekod pelajar dipilih.');
                return;
            }
            const formatted = formatRecordsForSysweb(selectedStudents, 'student');
            navigateToSyswebSegment('student_directory', formatted, 'USMSmart Direktori Pelajar (Pukal)', 'Pilihan Pelajar (' + formatted.length + ' rekod)');
            showToast(formatted.length + ' rekod pelajar dipilih sedia untuk Sysweb.');
        }

        function exportFilteredStudentsToSysweb() {
            const records = (filteredMasterStudents && filteredMasterStudents.length) ? filteredMasterStudents : allMasterStudents;
            if (!records || !records.length) {
                showToast('Tiada rekod pelajar untuk dieksport.');
                return;
            }
            const formatted = formatRecordsForSysweb(records, 'student');
            navigateToSyswebSegment('student_directory', formatted, 'USMSmart Direktori Pelajar', 'Direktori Pelajar (' + formatted.length + ' rekod)');
            showToast(formatted.length + ' rekod pelajar sedia untuk Sysweb.');
        }

        function exportSingleStaffToSysweb(staffId) {
            const staff = (allStaffDirectoryRecords || []).find(s => Number(s.id) === Number(staffId));
            if (!staff) {
                showToast('Rekod staf tidak ditemui.');
                return;
            }
            const formatted = formatRecordsForSysweb([staff], 'staff');
            navigateToSyswebSegment('staff_directory', formatted, 'USMSmart Direktori Staf', 'Staf: ' + (staff.name || staff.staffNo));
            showToast('Memuatkan rekod staf ke Segmen Sysweb...');
        }

        function exportStaffDirectoryToSysweb() {
            const query = String(document.getElementById('staff-directory-search')?.value || '').trim().toLocaleLowerCase();
            let staffRecords = (allStaffDirectoryRecords || []);
            if (query) {
                staffRecords = staffRecords.filter(record => [record.name, record.staffNo, record.department, record.cardUid, record.cardSerial, record.cardStatus, record.expiryDate, record.notes].some(value => String(value ?? '').toLocaleLowerCase().includes(query)));
            }
            if (!staffRecords.length) {
                showToast('Tiada rekod staf untuk dieksport.');
                return;
            }
            const formatted = formatRecordsForSysweb(staffRecords, 'staff');
            navigateToSyswebSegment('staff_directory', formatted, 'USMSmart Direktori Staf', 'Direktori Staf (' + formatted.length + ' rekod)');
            showToast(formatted.length + ' rekod staf sedia untuk Sysweb.');
        }

        async function sendSyswebRecords() {
            const batch = getActiveSyswebRecords();
            if (!batch.length) {
                showToast('Tiada rekod untuk dihantar.');
                return;
            }
            const verdicts = validateSyswebBatch(batch, allSyswebSavedRecords, {});
            const sendable = []; const blocked = [];
            batch.forEach((r, i) => (verdicts[i].status === 'invalid' ? blocked : sendable).push({ record: r, check: verdicts[i] }));
            const reviewCount = verdicts.filter(c => c.status === 'review').length;
            if (!sendable.length) {
                showToast('Penghantaran dibatalkan — kesemua ' + blocked.length + ' rekod berstatus Tidak sah.');
                return;
            }
            if (blocked.length) {
                const preview = blocked.slice(0, 8).map(b => '- ' + (b.record.name || '-') + ' (' + (b.record.identifier || '-') + '): ' + b.check.labels.join(', ')).join('\n');
                if (!confirm(blocked.length + ' rekod berstatus Tidak sah dan akan DIHALANG daripada penghantaran ini:\n\n' + preview + (blocked.length > 8 ? '\n... dan ' + (blocked.length - 8) + ' lagi.' : '') + '\n\nHantar ' + sendable.length + ' rekod selebihnya' + (reviewCount ? ' (termasuk ' + reviewCount + ' perlu semakan)' : '') + '?')) return;
            }
            const records = sendable.map(x => x.record);
            const out = document.getElementById('usm-sysweb-demo-result');
            const segOut = document.getElementById('sysweb-segment-json-result');
            const sendBtn = document.getElementById('usm-sysweb-send-btn');
            const statusBadge = document.getElementById('usm-sysweb-status-badge');
            const techBadge = document.getElementById('sysweb-technical-status');
            const footerStatus = document.getElementById('usm-sysweb-footer-status');

            const msg = 'Menghantar ' + records.length + ' rekod kepada penerima integrasi Sysweb' + (blocked.length ? ' (' + blocked.length + ' rekod Tidak sah dihalang oleh pusat semakan)' : '') + '... Sila tunggu.';
            if (out) out.textContent = msg;
            if (segOut) segOut.textContent = msg;
            if (sendBtn) {
                sendBtn.disabled = true;
                sendBtn.classList.add('opacity-60', 'cursor-not-allowed');
            }
            if (statusBadge) statusBadge.textContent = 'Sedang diproses...';
            if (techBadge) techBadge.textContent = 'Memproses...';
            if (footerStatus) footerStatus.textContent = 'Menghubungkan ke Sysweb...';

            try {
                const response = await fetch('index.php?action=sysweb_demo_receive', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': USMSMART_CSRF_TOKEN,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        source: (currentSyswebSource === 'uda' ? (currentSyswebSourceName || 'USMSmart Live Records') : 'USMSmart Demo Sample'),
                        records: records
                    })
                });
                const data = await response.json();
                const jsonStr = JSON.stringify(data, null, 2);
                if (out) out.textContent = jsonStr;
                if (segOut) segOut.textContent = jsonStr;

                const savedNew = Number(data.saved_new ?? 0);
                const dupes = Number(data.duplicates_skipped ?? 0);
                const rejectedCount = Number(data.rejected_count ?? 0);
                const held = blocked.length;
                if (!response.ok) {
                    showToast(data.message || 'Integrasi Sysweb gagal.');
                    if (statusBadge) statusBadge.textContent = 'Gagal';
                    if (techBadge) techBadge.textContent = 'Ralat';
                    if (footerStatus) footerStatus.innerHTML = '<span class="text-rose-600 font-bold">Penghantaran gagal</span>';
                } else if (!data.success) {
                    const summary = savedNew + ' rekod baharu disimpan' + (dupes ? ', ' + dupes + ' berulang dilangkau' : '') + (rejectedCount ? ', ' + rejectedCount + ' ditolak pelayan' : '') + (held ? ', ' + held + ' dihalang tempatan' : '');
                    showToast('Sebahagian selesai: ' + summary + '. Batch ' + (data.batch_id || '-') + ' tercatat dalam sejarah.');
                    if (statusBadge) statusBadge.textContent = 'Sebahagian (200 OK)';
                    if (techBadge) techBadge.textContent = '200 OK Sebahagian';
                    if (footerStatus) footerStatus.innerHTML = '<span class="text-amber-600 font-bold"><i class="fa-solid fa-circle-exclamation"></i> ' + escapeHtml(summary) + '</span>';
                    loadSyswebSavedRecords();
                } else {
                    showToast('Integrasi Sysweb selesai! ' + savedNew + ' rekod baharu disimpan' + (held ? ' (' + held + ' dihalang pusat semakan)' : '') + '. Kata laluan dijana oleh pelayan untuk setiap rekod baharu.');
                    if (statusBadge) statusBadge.textContent = 'Selesai (200 OK)';
                    if (techBadge) techBadge.textContent = '200 OK Selesai';
                    if (footerStatus) footerStatus.innerHTML = '<span class="text-emerald-600 font-bold"><i class="fa-solid fa-circle-check"></i> ' + savedNew + ' rekod baharu disimpan (' + dupes + ' rekod berulang dilangkau)' + (held ? ' &middot; ' + held + ' dihalang pusat semakan' : '') + '</span>';
                    loadSyswebSavedRecords();
                }
                loadSyswebExportHistory();
            } catch (e) {
                const errStr = 'Ralat: ' + e.message + '\nPastikan Apache dan pangkalan data sedang berjalan.';
                if (out) out.textContent = errStr;
                if (segOut) segOut.textContent = errStr;
                showToast('Ralat rangkaian menghubungi Sysweb.');
                if (statusBadge) statusBadge.textContent = 'Ralat Rangkaian';
                if (techBadge) techBadge.textContent = 'Ralat Rangkaian';
                if (footerStatus) footerStatus.innerHTML = '<span class="text-rose-600 font-bold">Ralat sambungan</span>';
            } finally {
                if (sendBtn) {
                    sendBtn.disabled = false;
                    sendBtn.classList.remove('opacity-60', 'cursor-not-allowed');
                }
            }
        }
        document.getElementById('usm-sysweb-demo-modal')?.addEventListener('click', function(e) {
            if (e.target === this) closeSyswebDemo();
        });
        let pieChartInstance = null;
        let barChartInstance = null;
        let monthlyComparisonChartInstance = null;
        let selectedAnalyticsSchool = '';
        let html5QrCode = null;

        const OFFICIAL_SCHOOLS = [
            "Pusat Pengajian Kejuruteraan Aeroangkasa (School of Aerospace Engineering)",
            "Pusat Pengajian Kejuruteraan Awam (School of Civil Engineering)",
            "Pusat Pengajian Kejuruteraan Elektrik & Elektronik (School of Electrical & Electronic Engineering)",
            "Pusat Pengajian Kejuruteraan Bahan & Sumber Mineral (School of Materials & Mineral Resources Engineering)",
            "Pusat Pengajian Kejuruteraan Kimia (School of Chemical Engineering)",
            "Pusat Pengajian Kejuruteraan Mekanikal (School of Mechanical Engineering)",
            "Pusat Pengajian Bahasa, Literasi & Terjemahan (PPBLT) (School of Languages, Literacies and Translation)"
        ];

        function sanitizeSchoolName(rawSchool) {
            if (!rawSchool) return '';
            let s = rawSchool.toLowerCase();
            if (s.includes('aero')) return OFFICIAL_SCHOOLS[0];
            if (s.includes('awam') || s.includes('civil')) return OFFICIAL_SCHOOLS[1];
            if (s.includes('elektrik') || s.includes('electrical')) return OFFICIAL_SCHOOLS[2];
            if (s.includes('bahan') || s.includes('materials') || s.includes('mineral')) return OFFICIAL_SCHOOLS[3];
            if (s.includes('kimia') || s.includes('chemical')) return OFFICIAL_SCHOOLS[4];
            if (s.includes('mekanikal') || s.includes('mechanical')) return OFFICIAL_SCHOOLS[5];
            if (s.includes('bahasa') || s.includes('language') || s.includes('ppblt') || s.includes('literasi') || s.includes('translation')) return OFFICIAL_SCHOOLS[6];
            return String(rawSchool).trim();
        }

            // Client half of the school -> policy link. The keys are taken from OFFICIAL_SCHOOLS, so a
            // renamed school cannot leave the two halves pointing at different names, and the value is read
            // through sanitizeSchoolName() so a raw CSV-style string resolves the same way it does in PHP.
            // Only an empty box is preselected; anything the operator typed stays.
            const SCHOOL_POLICY_LINKS = {};
            SCHOOL_POLICY_LINKS[OFFICIAL_SCHOOLS[0]] = 'sa-stud_normal';
            SCHOOL_POLICY_LINKS[OFFICIAL_SCHOOLS[1]] = 'sc-stud_normal';
            SCHOOL_POLICY_LINKS[OFFICIAL_SCHOOLS[3]] = 'sr-stud_undergrad';
            SCHOOL_POLICY_LINKS[OFFICIAL_SCHOOLS[4]] = 'sce-stud_labcom';

            function usmsmartSchoolPolicyFor(school) {
                const key = String(sanitizeSchoolName(school) || '').trim();
                return key === '' ? '' : (SCHOOL_POLICY_LINKS[key] || '');
            }

            function usmsmartLinkSchoolPolicy() {
                const schoolField = document.getElementById('edit-page-school');
                const policyField = document.getElementById('edit-page-policy');
                const hint = document.getElementById('edit-page-policy-hint');
                if (!schoolField || !policyField) return '';
                const linked = usmsmartSchoolPolicyFor(schoolField.value);
                const filledNow = linked !== '' && policyField.value.trim() === '';
                if (filledNow) policyField.value = linked;
                if (hint) {
                    const chosen = String(schoolField.value || '').trim();
                    if (chosen === '') hint.textContent = 'Pilih pusat pengajian untuk mencadangkan polisi palang.';
                    else if (linked === '') hint.textContent = 'Pusat pengajian ini tiada polisi lalai. Kosong bermakna Tiada polisi.';
                    else if (filledNow) hint.textContent = 'Dipautkan automatik daripada pusat pengajian: ' + linked;
                    else hint.textContent = 'Polisi sedia ada dikekalkan. Pautan sekolah: ' + linked + '.';
                }
                return filledNow ? linked : '';
            }


        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (char) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char];
            });
        }

        let liveSchoolOptions = { student: [], staff: [] };

        function setSelectOptions(select, values, placeholder, selectedValue) {
            if (!select) return;
            const previous = selectedValue !== undefined ? String(selectedValue) : String(select.value || '');
            const unique = Array.from(new Set((values || []).map(v => String(v || '').trim()).filter(Boolean)));
            if (previous && !unique.includes(previous)) unique.push(previous);
            unique.sort((a, b) => a.localeCompare(b, undefined, {sensitivity:'base'}));
            select.innerHTML = '';
            const first = document.createElement('option'); first.value = ''; first.textContent = placeholder; select.appendChild(first);
            unique.forEach(value => {
                const option = document.createElement('option'); option.value = value; option.textContent = value;
                if (value === previous) option.selected = true;
                select.appendChild(option);
            });
        }

        async function loadLiveSchoolOptions(cardholderType, preserveEditValue) {
            const type = cardholderType === 'staff' ? 'staff' : 'student';
            const editSelect = document.getElementById('edit-page-school');
            const liveSelect = document.getElementById('uda-live-school');
            try {
                const response = await fetch('index.php?action=get_school_options&cardholderType=' + encodeURIComponent(type), {credentials:'same-origin', headers:{'Accept':'application/json'}});
                const payload = await response.json();
                if (!response.ok || !payload.success || !Array.isArray(payload.schools)) throw new Error(payload.message || 'Senarai sekolah tidak sah.');
                liveSchoolOptions[type] = payload.schools;
                if (type === 'student') {
                    setSelectOptions(editSelect, payload.schools, 'Pilih sekolah / pusat pengajian', preserveEditValue);
                }
                if (liveSelect) {
                    const selected = liveSelect.value;
                    setSelectOptions(liveSelect, payload.schools, 'Semua sekolah / program', selected);
                }
                return payload.schools;
            } catch (error) {
                console.error('Gagal memuatkan pilihan sekolah daripada SQL Server:', error);
                if (type === 'student' && editSelect && !editSelect.options.length) {
                    editSelect.innerHTML = '<option value="">Sekolah tidak dapat dimuatkan — semak sambungan SQL Server</option>';
                }
                return [];
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            let today = new Date();
            let formattedToday = today.toISOString().split('T')[0];
            let startDateInput = document.getElementById('start-date');
            let endDateInput = document.getElementById('end-date');
            if (startDateInput && !startDateInput.value) startDateInput.value = today.getFullYear() + '-01-01';
            if (endDateInput && !endDateInput.value) endDateInput.value = formattedToday;
            loadLiveSchoolOptions('student');
            const cardholderTypeSelect = document.getElementById('uda-cardholder-type');
            if (cardholderTypeSelect) cardholderTypeSelect.addEventListener('change', () => { loadLiveSchoolOptions(cardholderTypeSelect.value); });
            loadMasterStudents();
            loadSessionHistory();

            // ===== NEW: My Profile listeners =====
            const newPass = document.getElementById('profile-new-password');
            const confirmPass = document.getElementById('profile-confirm-password');
            if (newPass) newPass.addEventListener('input', () => {
                updatePasswordStrength(newPass.value);
                updatePasswordMatch();
            });
            if (confirmPass) confirmPass.addEventListener('input', updatePasswordMatch);

            // Escape closes profile modal
            document.addEventListener('keydown', (e) => {
                const modal = document.getElementById('my-profile-modal');
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeMyProfile();
            });

            // Backdrop click closes profile modal
            const profileModal = document.getElementById('my-profile-modal');
            if (profileModal) {
                profileModal.addEventListener('click', (e) => {
                    if (e.target === profileModal) closeMyProfile();
                });
            }

            // Stop camera on page unload (best-effort)
            window.addEventListener('pagehide', () => {
                if (html5QrCode) { try { html5QrCode.stop().catch(() => {}); } catch (_) {} }
            });
        });

        function toggleDarkMode() {
            let html = document.getElementById('html-root');
            let icon = document.getElementById('theme-icon');
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                icon.className = "fa-solid fa-moon text-xs";
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                icon.className = "fa-solid fa-sun text-xs";
                localStorage.setItem('theme', 'dark');
            }
        }

        if (localStorage.getItem('theme') === 'dark') {
            document.getElementById('html-root').classList.add('dark');
            document.getElementById('theme-icon').className = "fa-solid fa-sun text-xs";
        }

        function hideAllMainViews() {
            ['view-welcome','view-transporter','view-analytics','view-directory-page','view-staff-directory','view-edit-student','view-user-management','view-audit-history','view-bulk-update','view-data-quality','view-sysweb'].forEach(id => {
                const el = document.getElementById(id); if (el) el.classList.add('hidden');
            });
            // Deep segments belong to no top tab, so no tab is marked current.
            setMainTabState(null);
        }

        // One place decides how an active tab looks. Seven separate className literals used to restate
        // the tab styling and disagree with each other, and with the markup, about padding, radius and
        // font size — so the pill visibly changed shape the first time a tab was clicked.
        function setMainTabState(active) {
            ['transporter', 'analytics', 'sysweb'].forEach(name => {
                const btn = document.getElementById('btn-tab-' + name);
                if (!btn) return;
                btn.classList.toggle('is-active', active === name);
                if (active === name) btn.setAttribute('aria-current', 'page'); else btn.removeAttribute('aria-current');
            });
            // Every opener reaches here, and each shows its own view right after, so reading the
            // visible view one microtask later is enough to light the matching rail entry.
            queueMicrotask(usmRailSync);
        }

        function usmRailSync() {
            document.querySelectorAll('#usm-rail [data-usm-view]').forEach(link => {
                const view = document.getElementById(link.getAttribute('data-usm-view'));
                const on = !!view && !view.classList.contains('hidden');
                link.classList.toggle('is-active', on);
                if (on) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
            });
            usmRevealVisible();
        }

        // One view is visible at a time by construction, so the unhidden one is the segment to animate.
        function usmRevealVisible() {
            const view = Array.from(document.querySelectorAll('[id^="view-"]'))
                .find(node => !node.classList.contains('hidden') && !node.hidden);
            if (view) usmRevealArm(view.id);
        }

        function switchMainTab(tab) {
            let tTrans = document.getElementById('view-transporter');
            let tAnal = document.getElementById('view-analytics');
            let tDir = document.getElementById('view-directory-page');
            let tEdit = document.getElementById('view-edit-student');
            let tUsers = document.getElementById('view-user-management');
            let tAudit = document.getElementById('view-audit-history');
            let tSysweb = document.getElementById('view-sysweb');
            let tWelcome = document.getElementById('view-welcome');
            // The three button handles are gone: setMainTabState() is the only writer now.
            if (tDir) tDir.classList.add('hidden');
            if (tEdit) tEdit.classList.add('hidden');
            if (tUsers) tUsers.classList.add('hidden');
            if (tAudit) tAudit.classList.add('hidden');
            if (tSysweb) tSysweb.classList.add('hidden');
            if (tWelcome) tWelcome.classList.add('hidden');
            if (tab === 'analytics') {
                if (tTrans) tTrans.classList.add('hidden');
                if (tAnal) tAnal.classList.remove('hidden');
                setMainTabState('analytics');
                usmRevealArm('view-analytics');
                renderAnalyticsCharts();
                renderMonthlyComparisonChart();
            } else if (tab === 'sysweb') {
                navigateToSyswebSegment('header');
            } else {
                if (tAnal) tAnal.classList.add('hidden');
                if (tTrans) tTrans.classList.remove('hidden');
                setMainTabState('transporter');
            }
        }

        let dataQualityIssues = [];
        let dataQualityScannedAt = '';
        function navigateToDataQuality() {
            hideAllMainViews();
            const view = document.getElementById('view-data-quality');
            if (view) view.classList.remove('hidden');
            window.scrollTo({top:0, behavior:'smooth'});
            usmRevealArm('view-data-quality');
            loadDataQuality(false);
        }

        async function loadDataQuality(forceRefresh = false) {
            const tbody = document.getElementById('quality-issues-tbody');
            const button = document.getElementById('quality-scan-button');
            if (!tbody) return;
            if (forceRefresh || !dataQualityScannedAt) tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Sedang mengimbas kualiti data...</td></tr>';
            if (button) { button.disabled = true; button.classList.add('opacity-60'); }
            try {
                const response = await fetch('index.php?action=data_quality_scan', {headers:{'Accept':'application/json'}, cache:'no-store'});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Imbasan kualiti data gagal.');
                dataQualityIssues = Array.isArray(result.issues) ? result.issues : [];
                dataQualityScannedAt = result.scanned_at || '';
                const c = result.counts || {};
                document.getElementById('quality-total-records').textContent = c.total_records ?? 0;
                document.getElementById('quality-affected').textContent = c.affected_records ?? 0;
                document.getElementById('quality-duplicates').textContent = c.duplicates ?? 0;
                document.getElementById('quality-invalid-cards').textContent = c.invalid_cards ?? 0;
                document.getElementById('quality-expired').textContent = c.expired ?? 0;
                document.getElementById('quality-scanned-at').textContent = dataQualityScannedAt ? 'Imbasan: ' + dataQualityScannedAt : '';
                renderDataQualityIssues();
            } catch (error) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-rose-600">' + escapeHtml(error.message || 'Imbasan gagal.') + '</td></tr>';
                document.getElementById('quality-result-count').textContent = 'Imbasan tidak berjaya.';
                hideQualityStatsPanel();
                showToast(error.message || 'Imbasan kualiti data gagal.');
            } finally { if (button) { button.disabled = false; button.classList.remove('opacity-60'); } }
        }

        function renderDataQualityIssues() {
            const tbody = document.getElementById('quality-issues-tbody');
            if (!tbody) return;
            // One predicate for the whole segment: the list and the statistics card cannot disagree,
            // because they are the same function call.
            scheduleQualityStats();
            const rows = qualityFilteredIssues();
            document.getElementById('quality-result-count').textContent = rows.length + ' isu dipaparkan daripada ' + dataQualityIssues.length + ' isu ditemui.';
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="p-8 text-center text-slate-500">' + (dataQualityScannedAt ? 'Tiada isu sepadan. Data kelihatan bersih untuk penapis ini.' : 'Tiada hasil.') + '</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(i => {
                const badge = i.severity === 'info' ? 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' : (String(i.type).includes('duplicate') || i.type === 'invalid_uid' || i.type === 'invalid_serial' ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300');
                const isDuplicateIssue = String(i.type || '').includes('_duplicate');
                const duplicateMatchesHtml = Array.isArray(i.duplicateMatches) && i.duplicateMatches.length ? '<div class="mt-2 text-[11px] text-rose-700 dark:text-rose-300">'+i.duplicateMatches.length+' rekod sepadan ditemui.</div>' : '';
                const previewButton = isDuplicateIssue && Array.isArray(i.duplicateMatches) && i.duplicateMatches.length ? '<button type="button" onclick="openDuplicatePreview('+Number(i.id)+')" class="mt-2 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-semibold"><i class="fa-solid fa-eye mr-1"></i>Pratonton padanan</button>' : '';
                return '<tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-900/60"><td class="p-3 font-medium text-slate-900 dark:text-zinc-100">'+escapeHtml(i.name || '—')+'<div class="text-[10px] text-slate-400 mt-1">ID '+escapeHtml(i.id)+'</div></td><td class="p-3 font-mono">'+escapeHtml(i.matric || '—')+'</td><td class="p-3"><span class="px-2 py-1 rounded-lg text-[10px] font-semibold '+badge+'">'+escapeHtml(i.label || i.type)+'</span></td><td class="p-3 text-slate-600 dark:text-zinc-300 max-w-sm whitespace-normal">'+escapeHtml(i.message || '')+duplicateMatchesHtml+previewButton+'</td><td class="p-3">'+escapeHtml(i.expiryDate || '—')+'</td><td class="p-3 text-right"><button type="button" onclick="editDataQualityRecord('+Number(i.id)+')" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 hover:bg-white dark:hover:bg-zinc-800 text-indigo-600 dark:text-indigo-400 font-semibold">Edit</button></td></tr>';
            }).join('');
        }

        // Qualiti Data statistics, in the same card language as analytics and the audit log. Everything
        // below is built from qualityFilteredIssues() — the exact rows the table is showing — so the four
        // figures cannot drift from the list underneath them the way the five whole-scan counters at the
        // top of the segment always do.
        const QUALITY_GROUP_META = {
            duplicate: { label: 'Pendua', color: '#F43F5E' },
            incomplete: { label: 'Maklumat tidak lengkap', color: '#F59E0B' },
            card: { label: 'Kad / UID tidak sah', color: '#6366F1' },
            dates: { label: 'Tarikh tidak sah', color: '#06B6D4' },
            expired: { label: 'Kad luput', color: '#10B981' },
            other: { label: 'Lain-lain', color: '#94A3B8' }
        };
        const QUALITY_GROUP_ORDER = ['duplicate', 'incomplete', 'card', 'dates', 'expired', 'other'];
        const QUALITY_TYPE_META = {
            matric_duplicate: { label: 'Matrik pendua', color: '#F43F5E', group: 'duplicate' },
            uid_duplicate: { label: 'UID pendua', color: '#E11D48', group: 'duplicate' },
            serial_duplicate: { label: 'Nombor kad pendua', color: '#FB7185', group: 'duplicate' },
            missing_fields: { label: 'Maklumat tidak lengkap', color: '#F59E0B', group: 'incomplete' },
            invalid_uid: { label: 'UID tidak sah', color: '#6366F1', group: 'card' },
            invalid_serial: { label: 'Nombor kad tidak sah', color: '#8B5CF6', group: 'card' },
            invalid_dates: { label: 'Tarikh tidak sah', color: '#06B6D4', group: 'dates' },
            expiry_before_joined: { label: 'Julat tarikh tidak sah', color: '#0EA5E9', group: 'dates' },
            expired: { label: 'Kad telah luput', color: '#10B981', group: 'expired' }
        };
        const QUALITY_NO_SCHOOL = 'Tiada pusat pengajian';
        let qualityStatsRange = 12;
        let qualityTrendChartInstance = null;
        let qualityTypeChartInstance = null;
        let qualitySchoolChartInstance = null;
        let qualityStatsTimer = null;

        function qualityTypeMeta(issue) {
            return QUALITY_TYPE_META[String((issue && issue.type) || '')] || null;
        }

        function qualityGroupOf(issue) {
            const meta = qualityTypeMeta(issue);
            return meta && QUALITY_GROUP_META[meta.group] ? meta.group : 'other';
        }

        // The scan only reads the first ten characters of date_joined, so an issue whose record has an
        // empty or malformed join date has no month to sit in. It stays in every figure except the trend
        // chart, and the caption says how many — the chart is never allowed to imply it covered them.
        function qualityMonthOf(issue) {
            const raw = String((issue && issue.dateJoined) || '').trim().slice(0, 7);
            return /^\d{4}-\d{2}$/.test(raw) ? raw : '';
        }

        // Contiguous, newest-anchored, and trimmed at the oldest month that actually has an issue: a 36
        // month window over data that starts in 2025 must not draw two years of empty bars.
        function qualityMonthAxis(months, keep) {
            if (!months.length) return [];
            const sorted = months.slice().sort();
            const oldest = sorted[0];
            let cursor = sorted[sorted.length - 1];
            const span = Math.max(1, Math.min(120, Number(keep) || 12));
            const axis = [];
            for (let i = 0; i < span && cursor >= oldest; i++) {
                axis.unshift(cursor);
                const parts = cursor.split('-');
                const previous = new Date(Number(parts[0]), Number(parts[1]) - 2, 1);
                cursor = previous.getFullYear() + '-' + String(previous.getMonth() + 1).padStart(2, '0');
            }
            return axis;
        }

        function qualityMonthLabel(month) {
            const parts = String(month || '').split('-');
            const parsed = new Date(Number(parts[0]), Number(parts[1]) - 1, 1);
            return Number.isNaN(parsed.getTime()) ? String(month)
                : new Intl.DateTimeFormat('ms-MY', { month: 'short', year: '2-digit' }).format(parsed);
        }

        function qualitySchoolOf(issue) {
            const school = String((issue && issue.school) || '').trim();
            return school || QUALITY_NO_SCHOOL;
        }

        function destroyQualityStatsCharts() {
            [qualityTrendChartInstance, qualityTypeChartInstance, qualitySchoolChartInstance].forEach(instance => {
                if (instance) instance.destroy();
            });
            qualityTrendChartInstance = null;
            qualityTypeChartInstance = null;
            qualitySchoolChartInstance = null;
        }

        function hideQualityStatsPanel() {
            const panel = document.getElementById('quality-stats-panel');
            if (panel) panel.hidden = true;
            destroyQualityStatsCharts();
        }

        // The search box repaints on every keystroke. Rebuilding three charts per key is visible jank, so
        // the figures and charts land together on a short timer instead.
        function scheduleQualityStats() {
            if (qualityStatsTimer) clearTimeout(qualityStatsTimer);
            qualityStatsTimer = setTimeout(() => { qualityStatsTimer = null; renderQualityStats(); }, 220);
        }

        function qualityFilteredIssues() {
            return dataQualityIssues.filter(qualityFilterMatches);
        }

        function qualityFilterMatches(issue) {
            if (!issue) return false;
            const type = String(document.getElementById('quality-filter')?.value || '');
            const query = String(document.getElementById('quality-search')?.value || '').trim().toLowerCase();
            if (type && issue.type !== type) return false;
            if (query && ![issue.name, issue.matric, issue.message, issue.label, issue.school, issue.usmUid, issue.serialNo]
                .some(value => String(value || '').toLowerCase().includes(query))) return false;
            return true;
        }

        function qualityDistinct(values) {
            const seen = [];
            values.forEach(value => { if (value && seen.indexOf(value) === -1) seen.push(value); });
            return seen;
        }

        function renderQualityStats() {
            const panel = document.getElementById('quality-stats-panel');
            if (!panel) return;
            const caption = document.getElementById('quality-stats-caption');
            if (!dataQualityScannedAt || !dataQualityIssues.length) {
                panel.hidden = true;
                destroyQualityStatsCharts();
                if (caption) caption.textContent = dataQualityScannedAt
                    ? 'Tiada isu ditemui — data kelihatan bersih untuk semua rekod yang diimbas.'
                    : 'Jalankan imbasan untuk memaparkan statistik.';
                return;
            }
            const rows = qualityFilteredIssues();
            panel.hidden = false;

            const records = qualityDistinct(rows.map(issue => String(issue.id)));
            const schools = qualityDistinct(rows.map(qualitySchoolOf));
            const types = qualityDistinct(rows.map(issue => String(issue.type || '')));
            usmCountUp(document.getElementById('quality-stats-in-filter'), rows.length);
            usmCountUp(document.getElementById('quality-stats-records'), records.length);
            usmCountUp(document.getElementById('quality-stats-schools'), schools.length);
            usmCountUp(document.getElementById('quality-stats-types'), types.length);

            const months = rows.map(qualityMonthOf).filter(Boolean);
            const axis = qualityMonthAxis(months, qualityStatsRange);
            // Only a missing or malformed tarikh daftar is "undated"; a record that simply falls outside
            // the selected 12/24/36 month window still has a date, so it is counted separately and the
            // bars never have to explain a total that does not add up.
            const undated = rows.length - months.length;
            const offWindow = months.filter(month => axis.indexOf(month) === -1).length;
            if (caption) {
                caption.textContent = rows.length
                    ? auditNumber(rows.length) + ' isu dalam tapisan ini · ' + auditNumber(records.length) + ' rekod · '
                        + axis.length + ' bulan dalam carta'
                        + (offWindow > 0 ? ' · ' + auditNumber(offWindow) + ' isu lebih lama dari carta' : '')
                        + (undated > 0 ? ' · ' + auditNumber(undated) + ' isu tiada tarikh daftar sah' : '')
                    : 'Tiada isu bagi tapisan ini.';
            }

            const theme = usmChartTheme();
            const common = commonChartOptions(theme);
            destroyQualityStatsCharts();
            if (!rows.length) { usmRevealCheck(); return; }

            qualityTrendChartInstance = axis.length ? new Chart(document.getElementById('qualityTrendChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: axis.map(qualityMonthLabel),
                    datasets: QUALITY_GROUP_ORDER.filter(group => rows.some(issue => qualityGroupOf(issue) === group)).map(group => ({
                        label: QUALITY_GROUP_META[group].label,
                        data: axis.map(month => rows.filter(issue => qualityGroupOf(issue) === group && qualityMonthOf(issue) === month).length),
                        backgroundColor: QUALITY_GROUP_META[group].color + 'D9',
                        borderColor: QUALITY_GROUP_META[group].color,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 34,
                        stack: 'issues'
                    }))
                },
                options: {
                    ...common,
                    // The hero has no matching control in this segment — the list filters by text and jenis
                    // isu, not by month — so it is deliberately not clickable rather than a dead end.
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 22 : 0 },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => axis[items[0] ? items[0].dataIndex : 0] || '',
                                label: context => ' ' + context.dataset.label + ': ' + context.raw + ' isu'
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } },
                        y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } }
                    }
                }
            }) : null;

            const typeCounts = {};
            rows.forEach(issue => {
                const key = String(issue.type || 'other');
                typeCounts[key] = (typeCounts[key] || 0) + 1;
            });
            const ranked = Object.keys(typeCounts).sort((a, b) => typeCounts[b] - typeCounts[a] || (a < b ? -1 : 1));
            const listed = ranked.slice(0, 6);
            const overflow = ranked.slice(6);
            const typeLabels = listed.map(type => (QUALITY_TYPE_META[type] ? QUALITY_TYPE_META[type].label : type));
            const typeValues = listed.map(type => typeCounts[type]);
            const typeColors = listed.map(type => (QUALITY_TYPE_META[type] ? QUALITY_TYPE_META[type].color : '#94A3B8'));
            const leftover = overflow.reduce((sum, type) => sum + typeCounts[type], 0);
            if (leftover > 0) { typeLabels.push('Lain-lain'); typeValues.push(leftover); typeColors.push('#CBD5E1'); }
            qualityTypeChartInstance = new Chart(document.getElementById('qualityTypeChart').getContext('2d'), {
                type: 'doughnut',
                data: { labels: typeLabels, datasets: [{ data: typeValues, backgroundColor: typeColors, borderColor: theme.border, borderWidth: 3, hoverOffset: 7, spacing: 2 }] },
                options: {
                    ...common,
                    cutout: '62%',
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        qualityDrillType(elements[0].index >= listed.length ? '' : listed[elements[0].index]);
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => items[0] ? items[0].label : '',
                                label: context => {
                                    const value = Number(context.raw) || 0;
                                    return ' ' + value + ' isu (' + (rows.length ? ((value / rows.length) * 100).toFixed(1) : '0.0') + '%)';
                                }
                            }
                        }
                    }
                }
            });

            const schoolCounts = {};
            rows.forEach(issue => {
                const key = qualitySchoolOf(issue);
                schoolCounts[key] = (schoolCounts[key] || 0) + 1;
            });
            const schoolsAll = Object.keys(schoolCounts).sort((a, b) => schoolCounts[b] - schoolCounts[a] || (a < b ? -1 : 1));
            const schoolsRanked = schoolsAll.slice(0, 8);
            // Eight bars is what fits a 264px box; the rest must be named, not quietly dropped — the same
            // rule that makes the trend caption count the issues outside the selected window.
            const hiddenSchools = schoolsAll.slice(8);
            const hiddenIssues = hiddenSchools.reduce((sum, name) => sum + schoolCounts[name], 0);
            const schoolNote = document.getElementById('quality-school-note');
            if (schoolNote) {
                schoolNote.textContent = hiddenSchools.length
                    ? schoolsRanked.length + ' pusat daripada ' + schoolsAll.length + ' · ' + hiddenSchools.length
                        + ' pusat lain (' + auditNumber(hiddenIssues) + ' isu) tiada dalam carta'
                    : 'Klik satu bar untuk menapis mengikut pusat pengajian';
            }
            const schoolGroups = QUALITY_GROUP_ORDER.filter(group => rows.some(issue => qualityGroupOf(issue) === group));
            qualitySchoolChartInstance = new Chart(document.getElementById('qualitySchoolChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: schoolsRanked.map(name => name.length > 26 ? name.slice(0, 25) + '…' : name),
                    datasets: schoolGroups.map(group => ({
                        label: QUALITY_GROUP_META[group].label,
                        data: schoolsRanked.map(name => rows.filter(issue => qualitySchoolOf(issue) === name && qualityGroupOf(issue) === group).length),
                        backgroundColor: QUALITY_GROUP_META[group].color + 'D9',
                        borderColor: QUALITY_GROUP_META[group].color,
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 18,
                        stack: 'issues'
                    }))
                },
                options: {
                    ...common,
                    indexAxis: 'y',
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 45 : 0 },
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        qualityDrillSchool(schoolsRanked[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => schoolsRanked[items[0] ? items[0].dataIndex : 0] || '',
                                label: context => ' ' + context.dataset.label + ': ' + context.raw + ' isu'
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 6, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                        y: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { color: theme.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 }, crossAlign: 'far' } }
                    }
                }
            });
            usmRevealCheck();
        }

        function setQualityStatsRange(months, button) {
            qualityStatsRange = Math.max(1, Number(months) || 12);
            document.querySelectorAll('#quality-stats-panel .usm-stats-range').forEach(btn => btn.classList.toggle('is-on', btn === button));
            if (dataQualityScannedAt) renderQualityStats();
        }

        function qualityDrillType(type) {
            const select = document.getElementById('quality-filter');
            const value = String(type || '');
            if (select) select.value = value;
            renderDataQualityIssues();
            const meta = QUALITY_TYPE_META[value];
            showToast(value ? 'Menapis jenis isu: ' + (meta ? meta.label : value) : 'Menunjukkan semua jenis isu');
        }

        function qualityDrillSchool(school) {
            const value = String(school || '').trim();
            if (value === QUALITY_NO_SCHOOL) { showToast('Isu tanpa pusat pengajian tidak boleh dicari.'); return; }
            const search = document.getElementById('quality-search');
            if (search) search.value = value;
            renderDataQualityIssues();
            showToast(value ? 'Menapis pusat pengajian: ' + value : 'Menunjukkan semua pusat pengajian');
        }

        // ===== Stage HS: statistics for the two Sejarah screens =====
        // Colour lives here, not in CSS, because Chart.js paints on canvas: the same six-token family the
        // audit and quality cards use, reused so a "Gagal" bar means the same thing on every segment.
        const USM_HISTORY_ALL_DAYS = 3660;
        const USM_HISTORY_STATUS_META = {
            success: { label: 'Berjaya', color: '#10B981' },
            partial: { label: 'Sebahagian', color: '#F59E0B' },
            failed: { label: 'Gagal', color: '#F43F5E' },
            other: { label: 'Lain-lain', color: '#94A3B8' }
        };
        const USM_HISTORY_STATUS_ORDER = ['success', 'partial', 'failed', 'other'];
        const USM_HISTORY_OUTCOME_META = {
            saved: { label: 'Disimpan baharu', field: 'saved_new', color: '#6366F1' },
            skipped: { label: 'Pendua dilangkau', field: 'duplicates_skipped', color: '#06B6D4' },
            failed: { label: 'Gagal', field: 'failed_count', color: '#F43F5E' }
        };
        const USM_HISTORY_OUTCOME_ORDER = ['saved', 'skipped', 'failed'];
        const USM_SESSION_SIZE_META = [
            { key: 'zero', label: '0 pelajar', color: '#94A3B8', min: 0, max: 0 },
            { key: 'xs', label: '1 - 5 pelajar', color: '#6366F1', min: 1, max: 5 },
            { key: 's', label: '6 - 20 pelajar', color: '#06B6D4', min: 6, max: 20 },
            { key: 'm', label: '21 - 50 pelajar', color: '#F59E0B', min: 21, max: 50 },
            { key: 'l', label: 'lebih 50 pelajar', color: '#10B981', min: 51, max: Number.MAX_SAFE_INTEGER }
        ];

        function usmHistoryDateKey(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        // Both feeds already start with the local calendar date (PHP writes DATE_ATOM, MySQL prints
        // "YYYY-MM-DD HH:MM:SS"), so the date is read as text and never re-interpreted through a timezone.
        function usmHistoryDayKey(value) {
            const raw = String(value || '');
            const matched = /^\d{4}-\d{2}-\d{2}/.exec(raw);
            if (matched) return matched[0];
            const parsed = new Date(raw);
            if (Number.isNaN(parsed.getTime())) return '';
            return usmHistoryDateKey(parsed);
        }

        function usmHistoryNumber(value) {
            return Math.max(0, Number(value) || 0);
        }

        function usmHistoryTodayKey() {
            const now = new Date();
            now.setHours(12, 0, 0, 0);
            return usmHistoryDateKey(now);
        }

        function usmHistoryMondayDate(date) {
            const copy = new Date(date.getTime());
            copy.setHours(12, 0, 0, 0);
            copy.setDate(copy.getDate() - ((copy.getDay() + 6) % 7));
            return copy;
        }

        function usmHistoryMondayKey(dayKey) {
            const parsed = new Date(dayKey + 'T12:00:00');
            if (Number.isNaN(parsed.getTime())) return dayKey;
            return usmHistoryDateKey(usmHistoryMondayDate(parsed));
        }

        function usmHistoryWindowKey(days) {
            if (days >= USM_HISTORY_ALL_DAYS) return '0000-01-01';
            const floor = new Date();
            floor.setHours(12, 0, 0, 0);
            floor.setDate(floor.getDate() - (Math.max(1, days) - 1));
            return usmHistoryDateKey(floor);
        }

        // One bucket axis serves both heroes: contiguous days ending today, trimmed at the oldest row inside
        // the window. A span over 45 days moves to Monday-start weeks so 90 hari and Semua stay readable; the
        // caption names which bucket is in use, so no bar is silently a different length than the last one.
        function usmHistoryAxis(days, keys) {
            const windowKey = usmHistoryWindowKey(days);
            const inside = keys.filter(key => !!key && key >= windowKey);
            if (!inside.length) {
                return {
                    axis: [], weekly: false, buckets: keys.map(() => ''),
                    drillKind: 'day', unit: 'hari', label: () => ''
                };
            }
            let startKey = inside.reduce((min, key) => key < min ? key : min, inside[0]);
            if (startKey < windowKey) startKey = windowKey;
            const todayKey = usmHistoryTodayKey();
            if (startKey > todayKey) startKey = todayKey;
            const startDate = new Date(startKey + 'T12:00:00');
            const todayDate = new Date(todayKey + 'T12:00:00');
            const weekly = Math.round((todayDate.getTime() - startDate.getTime()) / 86400000) > 45;
            const axis = [];
            const cursor = weekly ? usmHistoryMondayDate(startDate) : new Date(startDate.getTime());
            // A bucket that starts after today is stopped before it is pushed: an empty week to the right of
            // the last data point reads as "nothing happened since", which is a claim about the future.
            for (let guard = 0; guard < 560 && cursor.getTime() <= todayDate.getTime(); guard++) {
                axis.push(usmHistoryDateKey(cursor));
                cursor.setDate(cursor.getDate() + (weekly ? 7 : 1));
            }
            return {
                axis: axis,
                weekly: weekly,
                buckets: keys.map(key => !key ? '' : (weekly ? usmHistoryMondayKey(key) : key)),
                drillKind: weekly ? 'week' : 'day',
                unit: weekly ? 'minggu' : 'hari',
                label: index => (weekly ? 'Mgg ' : '') + auditDayLabel(axis[index])
            };
        }

        function usmHistoryCountDistinct(values) {
            const seen = [];
            values.forEach(value => { if (value && seen.indexOf(value) === -1) seen.push(value); });
            return seen;
        }

        function usmHistoryRank(counts) {
            return Object.keys(counts).sort((a, b) => counts[b] - counts[a] || (a < b ? -1 : 1));
        }

        function usmHistoryEllipsis(text, max) {
            const value = String(text || '');
            return value.length > max ? value.slice(0, max - 1) + '…' : value;
        }

        function usmHistoryDrillLabel(drill, names) {
            if (!drill || !drill.kind) return '';
            if (drill.kind === 'day') return 'hari ' + auditDayLabel(drill.value);
            if (drill.kind === 'week') return 'minggu ' + auditDayLabel(drill.value);
            return (names[drill.kind] ? names[drill.kind][drill.value] : '') || String(drill.value);
        }

        function usmSyncDrillReset(button, drill, names, clearFn) {
            if (!button) return;
            const label = usmHistoryDrillLabel(drill, names);
            button.hidden = !label;
            button.textContent = label ? 'Bersihkan tapisan: ' + label : '';
            if (label) button.setAttribute('aria-label', clearFn + ' - tapisan ' + label);
        }

        /* -------------------------------------------------------------- Sejarah Eksport Sysweb */

        let syswebHistoryRows = [];
        let syswebHistoryDays = 30;
        let syswebHistoryDrill = { kind: '', value: '' };
        let syswebHistoryTrendChartInstance = null;
        let syswebHistoryStatusChartInstance = null;
        let syswebHistoryUserChartInstance = null;

        function syswebHistoryDayKey(item) { return usmHistoryDayKey(item && item.created_at); }

        function syswebHistoryStatusKey(item) {
            const status = String(item && item.status || '');
            return USM_HISTORY_STATUS_META[status] ? status : 'other';
        }

        function syswebHistoryUserOf(item) {
            const user = String(item && item.user || '').trim();
            return user && user !== '-' ? user : 'Tiada pengguna';
        }

        function syswebHistoryInWindow(item) {
            if (syswebHistoryDays >= USM_HISTORY_ALL_DAYS) return true;
            const day = syswebHistoryDayKey(item);
            return !!day && day >= usmHistoryWindowKey(syswebHistoryDays);
        }

        function syswebHistoryMatches(item) {
            const drill = syswebHistoryDrill;
            if (!drill || !drill.kind) return true;
            if (drill.kind === 'status') return syswebHistoryStatusKey(item) === drill.value;
            if (drill.kind === 'user') return syswebHistoryUserOf(item) === drill.value;
            const day = syswebHistoryDayKey(item);
            if (!day) return false;
            if (drill.kind === 'day') return day === drill.value;
            if (drill.kind === 'week') return usmHistoryMondayKey(day) === drill.value;
            return true;
        }

        // The one predicate. loadSyswebExportHistory() renders the list from this, and every figure on the
        // card comes from the same call.
        function syswebHistoryFiltered() {
            return syswebHistoryRows.filter(item => syswebHistoryInWindow(item) && syswebHistoryMatches(item));
        }

        function destroySyswebHistoryCharts() {
            [syswebHistoryTrendChartInstance, syswebHistoryStatusChartInstance, syswebHistoryUserChartInstance]
                .forEach(chart => { if (chart) chart.destroy(); });
            syswebHistoryTrendChartInstance = null;
            syswebHistoryStatusChartInstance = null;
            syswebHistoryUserChartInstance = null;
        }

        function hideSyswebHistoryStats() {
            const panel = document.getElementById('sysweb-history-stats-panel');
            if (panel) panel.hidden = true;
            const caption = document.getElementById('sysweb-history-stats-caption');
            if (caption) caption.textContent = 'Tiada sejarah eksport untuk dipaparkan.';
            destroySyswebHistoryCharts();
        }

        function syswebHistoryDrillNames() {
            const statuses = {};
            USM_HISTORY_STATUS_ORDER.forEach(key => { statuses[key] = USM_HISTORY_STATUS_META[key].label; });
            const users = {};
            syswebHistoryRows.forEach(item => { users[syswebHistoryUserOf(item)] = syswebHistoryUserOf(item); });
            return Object.assign({ status: statuses, user: users }, {});
        }

        function renderSyswebHistoryStats() {
            const panel = document.getElementById('sysweb-history-stats-panel');
            if (!panel) return;
            const caption = document.getElementById('sysweb-history-stats-caption');
            if (!syswebHistoryRows.length) {
                panel.hidden = true;
                destroySyswebHistoryCharts();
                if (caption) caption.textContent = 'Belum ada sejarah eksport Sysweb untuk dipaparkan.';
                usmSyncDrillReset(document.getElementById('sysweb-history-drill-reset'), { kind: '' });
                return;
            }
            panel.hidden = false;
            const rows = syswebHistoryFiltered();
            const records = rows.reduce((sum, item) => sum + usmHistoryNumber(item.total), 0);
            const saved = rows.reduce((sum, item) => sum + usmHistoryNumber(item.saved_new), 0);
            const failed = rows.reduce((sum, item) => sum + usmHistoryNumber(item.failed_count), 0);
            usmCountUp(document.getElementById('sysweb-history-stats-batches'), rows.length);
            usmCountUp(document.getElementById('sysweb-history-stats-records'), records);
            usmCountUp(document.getElementById('sysweb-history-stats-saved'), saved);
            usmCountUp(document.getElementById('sysweb-history-stats-failed'), failed);
            usmSyncDrillReset(document.getElementById('sysweb-history-drill-reset'), syswebHistoryDrill, syswebHistoryDrillNames(), 'clearSyswebHistoryDrill');

            const keyed = rows.map(syswebHistoryDayKey);
            const undated = keyed.filter(day => !day).length;
            // The pill narrows the rows themselves, so nothing on display can be older than the chart; what
            // has to be named instead is how many of the batches the server sent the window leaves out.
            const hiddenByWindow = syswebHistoryRows.filter(item => !syswebHistoryInWindow(item)).length;
            const view = usmHistoryAxis(syswebHistoryDays, keyed);
            if (caption) {
                caption.textContent = (rows.length
                    ? auditNumber(rows.length) + ' batch dalam julat ini · ' + auditNumber(records) + ' rekod diproses · '
                        + view.axis.length + ' ' + view.unit + ' dalam carta'
                    : 'Tiada batch dalam julat atau tapisan ini.')
                    + (hiddenByWindow > 0 ? ' · ' + auditNumber(hiddenByWindow) + ' batch di luar julat tidak dipaparkan' : '')
                    + (undated > 0 ? ' · ' + auditNumber(undated) + ' batch tiada tarikh sah' : '')
                    + ' · pelayan menghantar ' + auditNumber(syswebHistoryRows.length) + ' batch terakhir sahaja.';
            }

            const neutralUserNote = 'Klik satu bar untuk menapis mengikut pengguna';
            destroySyswebHistoryCharts();
            if (!rows.length) {
                // The note is written by the chart code below. Reset it here too, otherwise an empty population
                // keeps naming users that are no longer drawn in any chart.
                const emptyUserNote = document.getElementById('sysweb-history-user-note');
                if (emptyUserNote) emptyUserNote.textContent = neutralUserNote;
                return;
            }
            const theme = usmChartTheme();
            const common = commonChartOptions(theme);

            const buckets = {};
            view.axis.forEach(bucket => { buckets[bucket] = { saved: 0, skipped: 0, failed: 0, batches: 0 }; });
            rows.forEach((item, index) => {
                const cell = buckets[view.buckets[index]];
                if (!cell) return;
                USM_HISTORY_OUTCOME_ORDER.forEach(key => {
                    cell[key] += usmHistoryNumber(item[USM_HISTORY_OUTCOME_META[key].field]);
                });
                cell.batches += 1;
            });

            syswebHistoryTrendChartInstance = new Chart(document.getElementById('syswebHistoryTrendChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: view.axis.map((bucket, index) => view.label(index)),
                    datasets: USM_HISTORY_OUTCOME_ORDER.map(key => ({
                        type: 'bar',
                        label: USM_HISTORY_OUTCOME_META[key].label,
                        data: view.axis.map(bucket => buckets[bucket][key]),
                        backgroundColor: USM_HISTORY_OUTCOME_META[key].color + 'D9',
                        borderColor: USM_HISTORY_OUTCOME_META[key].color,
                        borderWidth: 1,
                        borderRadius: 5,
                        borderSkipped: false,
                        maxBarThickness: 26,
                        stack: 'rekod',
                        yAxisID: 'y'
                    })).concat([{
                        type: 'line',
                        label: 'Bilangan batch',
                        data: view.axis.map(bucket => buckets[bucket].batches),
                        borderColor: '#7C3AED',
                        backgroundColor: '#7C3AED',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: theme.border,
                        pointBorderColor: '#7C3AED',
                        tension: .35,
                        yAxisID: 'y1'
                    }])
                },
                options: {
                    ...common,
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 22 : 0 },
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSyswebHistory(view.drillKind, view.axis[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => view.axis[items[0] ? items[0].dataIndex : 0] || '',
                                label: context => ' ' + context.dataset.label + ': ' + auditNumber(context.raw) + (context.dataset.yAxisID === 'y1' ? ' batch' : ' rekod')
                            }
                        }
                    },
                    scales: {
                        x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } },
                        y: { stacked: true, beginAtZero: true, position: 'left', title: { display: true, text: 'Rekod', color: theme.text, font: { size: 10 } }, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                        y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Batch', color: theme.text, font: { size: 10 } }, ticks: { precision: 0, color: '#7C3AED', padding: 6, font: { size: 10 } }, grid: { display: false }, border: { display: false } }
                    }
                }
            });

            const statusCounts = {};
            rows.forEach(item => {
                const key = syswebHistoryStatusKey(item);
                statusCounts[key] = (statusCounts[key] || 0) + 1;
            });
            const statusRanked = USM_HISTORY_STATUS_ORDER.filter(key => statusCounts[key]);
            syswebHistoryStatusChartInstance = new Chart(document.getElementById('syswebHistoryStatusChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statusRanked.map(key => USM_HISTORY_STATUS_META[key].label),
                    datasets: [{
                        data: statusRanked.map(key => statusCounts[key]),
                        backgroundColor: statusRanked.map(key => USM_HISTORY_STATUS_META[key].color),
                        borderColor: theme.border,
                        borderWidth: 3,
                        hoverOffset: 7,
                        spacing: 2
                    }]
                },
                options: {
                    ...common,
                    cutout: '62%',
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSyswebHistory('status', statusRanked[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => items[0] ? items[0].label : '',
                                label: context => {
                                    const value = usmHistoryNumber(context.raw);
                                    return ' ' + auditNumber(value) + ' batch (' + (rows.length ? ((value / rows.length) * 100).toFixed(1) : '0.0') + '%)';
                                }
                            }
                        }
                    }
                }
            });

            const userCounts = {};
            const userRecords = {};
            rows.forEach(item => {
                const key = syswebHistoryUserOf(item);
                userCounts[key] = (userCounts[key] || 0) + 1;
                userRecords[key] = (userRecords[key] || 0) + usmHistoryNumber(item.total);
            });
            const usersAll = usmHistoryRank(userCounts);
            const usersRanked = usersAll.slice(0, 5);
            const usersHidden = usersAll.slice(5);
            const hiddenBatches = usersHidden.reduce((sum, name) => sum + userCounts[name], 0);
            const userNote = document.getElementById('sysweb-history-user-note');
            if (userNote) {
                userNote.textContent = usersHidden.length
                    ? usersRanked.length + ' pengguna daripada ' + usersAll.length + ' · ' + usersHidden.length
                        + ' pengguna lain (' + auditNumber(hiddenBatches) + ' batch) tiada dalam carta'
                    : neutralUserNote;
            }
            syswebHistoryUserChartInstance = new Chart(document.getElementById('syswebHistoryUserChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: usersRanked.map(name => usmHistoryEllipsis(name, 24)),
                    datasets: [{
                        label: 'Batch eksport',
                        data: usersRanked.map(name => userCounts[name]),
                        backgroundColor: ['#6366F1D9', '#06B6D4D9', '#8B5CF6D9', '#F59E0BD9', '#10B981D9'],
                        borderColor: ['#6366F1', '#06B6D4', '#8B5CF6', '#F59E0B', '#10B981'],
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 22
                    }]
                },
                options: {
                    ...common,
                    indexAxis: 'y',
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 45 : 0 },
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSyswebHistory('user', usersRanked[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { display: false },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => usersRanked[items[0] ? items[0].dataIndex : 0] || '',
                                label: context => ' ' + auditNumber(context.raw) + ' batch · ' + auditNumber(userRecords[usersRanked[context.dataIndex]] || 0) + ' rekod'
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 6, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                        y: { grid: { display: false }, border: { display: false }, ticks: { color: theme.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 }, crossAlign: 'far' } }
                    }
                }
            });
        }

        // The list is only ever rendered by loadSyswebExportHistory(), which re-reads the file and then calls
        // both renderers with the same predicate. Re-running it on a pill or a chart click is what keeps the
        // rows under the card from drifting away from the figures above them.
        function setSyswebHistoryRange(days, button) {
            syswebHistoryDays = Math.max(1, Number(days) || 30);
            document.querySelectorAll('#sysweb-history-stats-panel .usm-stats-range').forEach(btn => btn.classList.toggle('is-on', btn === button));
            loadSyswebExportHistory();
        }

        function drillSyswebHistory(kind, value) {
            const next = String(value || '');
            if (!next) return;
            const same = syswebHistoryDrill.kind === kind && syswebHistoryDrill.value === next;
            syswebHistoryDrill = same ? { kind: '', value: '' } : { kind: kind, value: next };
            loadSyswebExportHistory();
            const names = syswebHistoryDrillNames();
            showToast(same
                ? 'Menunjukkan semua batch.'
                : 'Menapis sejarah eksport: ' + usmHistoryDrillLabel(syswebHistoryDrill, names));
        }

        function clearSyswebHistoryDrill() {
            syswebHistoryDrill = { kind: '', value: '' };
            loadSyswebExportHistory();
            showToast('Tapisan sejarah eksport dibersihkan.');
        }

        /* ---------------------------------------------------- Sejarah Sesi Penghantaran UDA */

        let sessionHistoryDays = 30;
        let sessionHistoryDrill = { kind: '', value: '' };
        let sessionHistoryTrendChartInstance = null;
        let sessionSizeChartInstance = null;
        let sessionTopChartInstance = null;
        let sessionHistoryStatsTimer = null;

        function usmSessionDayKey(session) { return usmHistoryDayKey(session && session.session_date); }

        function usmSessionSizeMeta(session) {
            const total = usmHistoryNumber(session && session.total_records);
            for (let index = 0; index < USM_SESSION_SIZE_META.length; index++) {
                const bucket = USM_SESSION_SIZE_META[index];
                if (total >= bucket.min && total <= bucket.max) return bucket;
            }
            return USM_SESSION_SIZE_META[USM_SESSION_SIZE_META.length - 1];
        }

        function usmSessionLabel(session) {
            const name = String(session && session.session_name || '').trim();
            return name || ('Sesi #' + String(session && session.id));
        }

        function usmSessionInWindow(session) {
            if (sessionHistoryDays >= USM_HISTORY_ALL_DAYS) return true;
            const day = usmSessionDayKey(session);
            return !!day && day >= usmHistoryWindowKey(sessionHistoryDays);
        }

        function usmSessionMatches(session) {
            const drill = sessionHistoryDrill;
            if (!drill || !drill.kind) return true;
            if (drill.kind === 'id') return String(session && session.id) === String(drill.value);
            if (drill.kind === 'size') return usmSessionSizeMeta(session).key === drill.value;
            const day = usmSessionDayKey(session);
            if (!day) return false;
            if (drill.kind === 'day') return day === drill.value;
            if (drill.kind === 'week') return usmHistoryMondayKey(day) === drill.value;
            return true;
        }

        function sessionHistoryMatchesText(session, filter) {
            if (!filter) return true;
            return [session.id, session.session_name, session.session_date, session.total_records]
                .some(value => String(value ?? '').toLowerCase().includes(filter));
        }

        // The one predicate: search box text, range pill, chart drill. renderSessionHistoryBase() draws the
        // table from this and the card counts the same array.
        function sessionHistoryFiltered() {
            const filter = (document.getElementById('session-history-filter')?.value || '').trim().toLowerCase();
            return (Array.isArray(allSessionsData) ? allSessionsData : [])
                .filter(session => usmSessionInWindow(session) && usmSessionMatches(session) && sessionHistoryMatchesText(session, filter));
        }

        function destroySessionHistoryCharts() {
            [sessionHistoryTrendChartInstance, sessionSizeChartInstance, sessionTopChartInstance]
                .forEach(chart => { if (chart) chart.destroy(); });
            sessionHistoryTrendChartInstance = null;
            sessionSizeChartInstance = null;
            sessionTopChartInstance = null;
        }

        function sessionHistoryDrillNames() {
            const sizes = {};
            USM_SESSION_SIZE_META.forEach(bucket => { sizes[bucket.key] = bucket.label; });
            const ids = {};
            (Array.isArray(allSessionsData) ? allSessionsData : []).forEach(session => { ids[String(session.id)] = usmSessionLabel(session); });
            return { size: sizes, id: ids };
        }

        function renderSessionHistoryStats() {
            const panel = document.getElementById('uda-session-stats-panel');
            if (!panel) return;
            const caption = document.getElementById('session-history-stats-caption');
            // A viewer gets a JSON object from the admin-only endpoint, never an array, so the card simply
            // has no population to describe and stays hidden — the same gate the table already obeys.
            if (!Array.isArray(allSessionsData) || !allSessionsData.length) {
                panel.hidden = true;
                destroySessionHistoryCharts();
                if (caption) caption.textContent = Array.isArray(allSessionsData)
                    ? 'Belum ada sesi penghantaran untuk dipaparkan.'
                    : 'Statistik sesi hanya tersedia untuk Admin.';
                usmSyncDrillReset(document.getElementById('session-history-drill-reset'), { kind: '' });
                return;
            }
            panel.hidden = false;
            const rows = sessionHistoryFiltered();
            const records = rows.reduce((sum, session) => sum + usmHistoryNumber(session.total_records), 0);
            const days = usmHistoryCountDistinct(rows.map(usmSessionDayKey));
            const unnamed = rows.filter(session => !String(session.session_name || '').trim()).length;
            usmCountUp(document.getElementById('session-stats-sessions'), rows.length);
            usmCountUp(document.getElementById('session-stats-records'), records);
            usmCountUp(document.getElementById('session-stats-days'), days.length);
            usmCountUp(document.getElementById('session-stats-unnamed'), unnamed);
            usmSyncDrillReset(document.getElementById('session-history-drill-reset'), sessionHistoryDrill, sessionHistoryDrillNames(), 'clearSessionHistoryDrill');

            const keyed = rows.map(usmSessionDayKey);
            const undated = keyed.filter(day => !day).length;
            const hiddenByWindow = allSessionsData.filter(session => !usmSessionInWindow(session)).length;
            const view = usmHistoryAxis(sessionHistoryDays, keyed);
            if (caption) {
                caption.textContent = (rows.length
                    ? auditNumber(rows.length) + ' sesi dalam julat ini · ' + auditNumber(records) + ' pelajar dihantar · '
                        + view.axis.length + ' ' + view.unit + ' dalam carta'
                    : 'Tiada sesi dalam julat atau tapisan ini.')
                    + (hiddenByWindow > 0 ? ' · ' + auditNumber(hiddenByWindow) + ' sesi di luar julat tidak dipaparkan' : '')
                    + (undated > 0 ? ' · ' + auditNumber(undated) + ' sesi tiada tarikh sah' : '')
                    + ' · ' + auditNumber(allSessionsData.length) + ' sesi hidup dalam pangkalan data.';
            }

            const neutralSessionNote = 'Klik satu bar untuk menapis mengikut sesi';
            destroySessionHistoryCharts();
            if (!rows.length) {
                const emptySessionNote = document.getElementById('session-top-note');
                if (emptySessionNote) emptySessionNote.textContent = neutralSessionNote;
                return;
            }
            const theme = usmChartTheme();
            const common = commonChartOptions(theme);

            const buckets = {};
            view.axis.forEach(bucket => { buckets[bucket] = { sessions: 0, records: 0 }; });
            rows.forEach((session, index) => {
                const cell = buckets[view.buckets[index]];
                if (!cell) return;
                cell.sessions += 1;
                cell.records += usmHistoryNumber(session.total_records);
            });

            sessionHistoryTrendChartInstance = new Chart(document.getElementById('sessionHistoryTrendChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: view.axis.map((bucket, index) => view.label(index)),
                    datasets: [{
                        type: 'bar',
                        label: 'Sesi',
                        data: view.axis.map(bucket => buckets[bucket].sessions),
                        backgroundColor: '#6366F1D9',
                        borderColor: '#6366F1',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 26,
                        yAxisID: 'y'
                    }, {
                        type: 'line',
                        label: 'Pelajar dihantar',
                        data: view.axis.map(bucket => buckets[bucket].records),
                        borderColor: '#06B6D4',
                        backgroundColor: '#06B6D4',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        pointBackgroundColor: theme.border,
                        pointBorderColor: '#06B6D4',
                        tension: .35,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    ...common,
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 30 : 0 },
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSessionHistory(view.drillKind, view.axis[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => view.axis[items[0] ? items[0].dataIndex : 0] || '',
                                label: context => ' ' + context.dataset.label + ': ' + auditNumber(context.raw)
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } },
                        y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Sesi', color: theme.text, font: { size: 10 } }, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                        y1: { beginAtZero: true, position: 'right', title: { display: true, text: 'Pelajar', color: theme.text, font: { size: 10 } }, ticks: { precision: 0, color: '#06B6D4', padding: 6, font: { size: 10 } }, grid: { display: false }, border: { display: false } }
                    }
                }
            });

            const sizeCounts = {};
            const sizeRecords = {};
            rows.forEach(session => {
                const bucket = usmSessionSizeMeta(session);
                sizeCounts[bucket.key] = (sizeCounts[bucket.key] || 0) + 1;
                sizeRecords[bucket.key] = (sizeRecords[bucket.key] || 0) + usmHistoryNumber(session.total_records);
            });
            const sizesRanked = USM_SESSION_SIZE_META.filter(bucket => sizeCounts[bucket.key]);
            sessionSizeChartInstance = new Chart(document.getElementById('sessionSizeChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: sizesRanked.map(bucket => bucket.label),
                    datasets: [{
                        data: sizesRanked.map(bucket => sizeCounts[bucket.key]),
                        backgroundColor: sizesRanked.map(bucket => bucket.color),
                        borderColor: theme.border,
                        borderWidth: 3,
                        hoverOffset: 7,
                        spacing: 2
                    }]
                },
                options: {
                    ...common,
                    cutout: '62%',
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSessionHistory('size', (sizesRanked[elements[0].index] || {}).key || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => items[0] ? items[0].label : '',
                                label: context => {
                                    const bucket = sizesRanked[context.dataIndex] || {};
                                    const value = usmHistoryNumber(context.raw);
                                    return ' ' + auditNumber(value) + ' sesi (' + (rows.length ? ((value / rows.length) * 100).toFixed(1) : '0.0') + '%) · '
                                        + auditNumber(sizeRecords[bucket.key] || 0) + ' pelajar';
                                }
                            }
                        }
                    }
                }
            });

            const byId = {};
            rows.forEach(session => {
                const key = String(session.id);
                byId[key] = { label: usmSessionLabel(session), records: (byId[key] ? byId[key].records : 0) + usmHistoryNumber(session.total_records) };
            });
            const sessionsAll = Object.keys(byId).sort((a, b) => byId[b].records - byId[a].records || Number(a) - Number(b));
            const sessionsRanked = sessionsAll.slice(0, 8);
            // Eight bars is what fits the box; the rest is named in the note, never quietly dropped.
            const rest = sessionsAll.slice(8);
            const restRecords = rest.reduce((sum, key) => sum + byId[key].records, 0);
            const topNote = document.getElementById('session-top-note');
            if (topNote) {
                topNote.textContent = rest.length
                    ? sessionsRanked.length + ' sesi daripada ' + sessionsAll.length + ' · ' + rest.length
                        + ' sesi lain (' + auditNumber(restRecords) + ' pelajar) tiada dalam carta'
                    : neutralSessionNote;
            }
            sessionTopChartInstance = new Chart(document.getElementById('sessionTopChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: sessionsRanked.map(key => usmHistoryEllipsis(byId[key].label, 26)),
                    datasets: [{
                        label: 'Pelajar dihantar',
                        data: sessionsRanked.map(key => byId[key].records),
                        backgroundColor: '#8B5CF6D9',
                        borderColor: '#8B5CF6',
                        borderWidth: 1,
                        borderRadius: 6,
                        borderSkipped: false,
                        maxBarThickness: 18
                    }]
                },
                options: {
                    ...common,
                    indexAxis: 'y',
                    animation: { ...common.animation, delay: context => context.mode === 'default' ? context.dataIndex * 45 : 0 },
                    onClick: (event, elements) => {
                        if (!elements.length) return;
                        drillSessionHistory('id', sessionsRanked[elements[0].index] || '');
                    },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: {
                        ...common.plugins,
                        legend: { display: false },
                        tooltip: {
                            ...common.plugins.tooltip,
                            callbacks: {
                                title: items => byId[sessionsRanked[items[0] ? items[0].dataIndex : 0]] ? byId[sessionsRanked[items[0].dataIndex]].label : '',
                                label: context => ' ' + auditNumber(context.raw) + ' pelajar'
                            }
                        }
                    },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 6, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                        y: { grid: { display: false }, border: { display: false }, ticks: { color: theme.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 }, crossAlign: 'far' } }
                    }
                }
            });
        }

        // The table repaints synchronously on every keystroke; the charts wait for the typing to stop so a
        // ten-character search does not destroy and rebuild three Chart.js instances per key.
        function scheduleSessionHistoryStats() {
            if (sessionHistoryStatsTimer) clearTimeout(sessionHistoryStatsTimer);
            sessionHistoryStatsTimer = setTimeout(() => {
                sessionHistoryStatsTimer = null;
                renderSessionHistoryStats();
            }, 220);
        }

        function setSessionHistoryRange(days, button) {
            sessionHistoryDays = Math.max(1, Number(days) || 30);
            document.querySelectorAll('#uda-session-stats-panel .usm-stats-range').forEach(btn => btn.classList.toggle('is-on', btn === button));
            renderSessionHistory();
        }

        function drillSessionHistory(kind, value) {
            const next = String(value || '');
            if (!next) return;
            const same = sessionHistoryDrill.kind === kind && sessionHistoryDrill.value === next;
            sessionHistoryDrill = same ? { kind: '', value: '' } : { kind: kind, value: next };
            renderSessionHistory();
            showToast(same
                ? 'Menunjukkan semua sesi.'
                : 'Menapis sejarah sesi: ' + usmHistoryDrillLabel(sessionHistoryDrill, sessionHistoryDrillNames()));
        }

        function clearSessionHistoryDrill() {
            sessionHistoryDrill = { kind: '', value: '' };
            renderSessionHistory();
            showToast('Tapisan sejarah sesi dibersihkan.');
        }

        function openDuplicatePreview(recordId) {
            const duplicateIssues = dataQualityIssues.filter(item => Number(item.id) === Number(recordId) && String(item.type || '').includes('_duplicate') && Array.isArray(item.duplicateMatches) && item.duplicateMatches.length);
            if (!duplicateIssues.length) {
                showToast('Rekod padanan tidak tersedia. Jalankan Imbas Semula.');
                return;
            }
            const issue = duplicateIssues[0];
            const allMatches = [];
            duplicateIssues.forEach(item => item.duplicateMatches.forEach(match => {
                const key = String(match.id) + '|' + String(match.matchedField) + '|' + String(match.matchedValue);
                if (!allMatches.some(existing => existing._key === key)) allMatches.push(Object.assign({}, match, {_key:key}));
            }));
            const fields = [
                ['ID rekod', 'id'], ['Nama', 'name'], ['Matrik', 'matric'], ['Pusat pengajian', 'school'],
                ['UID kad', 'usmUid'], ['Nombor siri kad', 'serialNo'], ['Polisi palang', 'policy'],
                ['Tarikh daftar', 'dateJoined'], ['Tarikh luput', 'expiryDate']
            ];
            const value = (record, key) => escapeHtml(record && record[key] !== undefined && String(record[key]).trim() !== '' ? record[key] : '—');
            const card = (record, heading, matched) => {
                const id = Number(record && record.id);
                const tone = matched ? 'rose' : 'indigo';
                const action = Number.isFinite(id) && id > 0
                    ? '<div class="px-4 pb-4 flex flex-wrap gap-2"><button type="button" onclick="editDataQualityRecord('+id+')" class="px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit rekod ini</button><button type="button" onclick="closeDuplicatePreview(); editDataQualityRecord('+id+')" class="px-3 py-2 rounded-lg border border-slate-300 dark:border-zinc-600 text-slate-700 dark:text-zinc-200 text-xs font-semibold"><i class="fa-solid fa-arrow-up-right-from-square mr-1"></i>Buka untuk pembetulan</button></div>' : '';
                return '<section class="min-w-0 rounded-xl border '+(matched?'border-rose-300 dark:border-rose-900':'border-indigo-200 dark:border-indigo-900')+' overflow-hidden bg-white dark:bg-[#121214]"><div class="px-4 py-3 '+(matched?'bg-rose-50 dark:bg-rose-950/30':'bg-indigo-50 dark:bg-indigo-950/30')+'"><p class="text-[10px] uppercase tracking-wider font-bold '+(matched?'text-rose-700 dark:text-rose-300':'text-indigo-700 dark:text-indigo-300')+'">'+heading+'</p><h4 class="font-bold text-sm text-slate-900 dark:text-white mt-1">'+value(record,'name')+'</h4><p class="text-[11px] text-slate-500 mt-1">ID rekod: '+value(record,'id')+'</p></div><dl class="p-4 space-y-3">'+fields.map(([label,key])=>{
                    const same = matched && record && record.matchedField === key;
                    return '<div class="grid grid-cols-[minmax(100px,0.8fr)_minmax(0,1.2fr)] gap-3 rounded-md '+(same?'bg-rose-100 dark:bg-rose-950/50 ring-1 ring-rose-300 dark:ring-rose-800 p-2':'')+'"><dt class="text-[11px] text-slate-500 dark:text-zinc-400">'+label+(same?' <span class="font-bold text-rose-600">(SEPADAN)</span>':'')+'</dt><dd class="text-xs font-medium text-slate-900 dark:text-zinc-100 break-words">'+value(record,key)+'</dd></div>';
                }).join('')+'</dl>'+(matched?'<div class="mx-4 mb-4 p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 text-xs text-rose-800 dark:text-rose-200"><strong>Nilai yang sama:</strong> '+escapeHtml(record.matchedValue || '—')+'<br><strong>Medan:</strong> '+escapeHtml(record.matchedField === 'matric' ? 'Matrik' : (record.matchedField === 'usm_uid' ? 'UID kad' : 'Nombor siri kad'))+'</div>':'')+action+'</section>';
            };
            const current = {id:issue.id,name:issue.name,matric:issue.matric,school:issue.school,usmUid:issue.usmUid,serialNo:issue.serialNo,policy:issue.policy,dateJoined:issue.dateJoined,expiryDate:issue.expiryDate};
            document.getElementById('duplicate-preview-title').textContent = 'Perbandingan sebelah-menyebelah: rekod #' + issue.id;
            const pairs = allMatches.map((match,index) => '<div class="space-y-2"><p class="text-xs font-bold text-slate-600 dark:text-zinc-300">Padanan '+(index+1)+' daripada '+allMatches.length+'</p><div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">'+card(current,'Rekod dipilih',false)+card(match,'Rekod yang sepadan',true)+'</div></div>').join('');
            document.getElementById('duplicate-preview-content').innerHTML = '<div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 text-xs text-amber-900 dark:text-amber-100"><strong>Perbandingan baca sahaja.</strong> Medan yang sama ditandakan. Gunakan butang Edit pada kad yang berkaitan untuk membetulkan data. Tiada rekod dipadam atau digabungkan secara automatik.</div>'+pairs;
            const modal = document.getElementById('duplicate-preview-modal');
            modal.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }
        function closeDuplicatePreview() {
            const modal = document.getElementById('duplicate-preview-modal');
            if (modal) modal.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        function editDataQualityRecord(id) {
            const student = (allMasterStudents || []).find(r => Number(r.id) === Number(id));
            if (!student) { showToast('Rekod tidak ditemui dalam direktori. Memuatkan semula senarai...'); loadMasterStudents(); return; }
            navigateToEditPage(student);
        }

        function exportDataQualityIssues() {
            if (!dataQualityIssues.length) { showToast('Tiada isu untuk dieksport. Jalankan imbasan dahulu.'); return; }
            const cols = ['ID','Nama','Matrik','Jenis Isu','Perincian','Pusat Pengajian','UID','Nombor Kad','Tarikh Daftar','Tarikh Luput'];
            const cell = v => { let value = String(v ?? ''); if (/^[=+@\-\t\r]/.test(value)) value = "'" + value; return '"' + value.replace(/"/g,'""') + '"'; };
            const lines = [cols.map(cell).join(',')].concat(dataQualityIssues.map(i => [i.id,i.name,i.matric,i.label,i.message,i.school,i.usmUid,i.serialNo,i.dateJoined,i.expiryDate].map(cell).join(',')));
            const blob = new Blob(['\uFEFF' + lines.join('\r\n')], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = 'usmsmart_data_quality_issues.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        }

        let auditCurrentPage = 1;
        let auditPerPage = 20;
        const auditRowsById = Object.create(null);
        let auditTotalPages = 1;
        let auditSearchDebounceTimer = null;
        let auditHistoryPreviousView = 'directory';

        function navigateToAuditHistory(fromView = 'directory') {
            auditHistoryPreviousView = fromView;
            hideAllMainViews();
            const view = document.getElementById('view-audit-history');
            if (view) view.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            usmRevealArm('view-audit-history');
            loadAuditHistoryPage(1);
        }

        function backFromAuditHistory() {
            if (auditHistoryPreviousView === 'transporter') {
                backToMainTransporter();
            } else {
                navigateToDirectoryPage();
            }
        }

        // Direktori Kad holds both record types. The rail has one entry; this switch decides which
        // table it shows. Both panels keep their own filters, forms and fetch calls untouched.
        let directoryPanel = 'student';

        function switchDirectoryPanel(panel, focusTab = false) {
            const name = panel === 'staff' ? 'staff' : 'student';
            const studentPanel = document.getElementById('dir-panel-student');
            const staffPanel = document.getElementById('view-staff-directory');
            if (!studentPanel || !staffPanel) return;
            directoryPanel = name;
            const staffActive = name === 'staff';
            studentPanel.classList.toggle('hidden', staffActive);
            staffPanel.classList.toggle('hidden', !staffActive);
            [['student', 'dir-tab-student'], ['staff', 'dir-tab-staff']].forEach(([key, id]) => {
                const button = document.getElementById(id);
                if (!button) return;
                const on = key === name;
                button.classList.toggle('is-active', on);
                button.setAttribute('aria-selected', on ? 'true' : 'false');
                button.tabIndex = on ? 0 : -1;
                if (on && focusTab) button.focus();
            });
            if (staffActive) loadStaffDirectory(!staffDirectoryLoaded);
        }

        function handleDirectoryTabKey(event) {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            switchDirectoryPanel(directoryPanel === 'staff' ? 'student' : 'staff', true);
        }

        function navigateToDirectoryPage(panel = 'student') {
            hideAllMainViews();
            const view = document.getElementById('view-directory-page');
            if (view) view.classList.remove('hidden');
            switchDirectoryPanel(panel);
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        let allStaffDirectoryRecords = [];
        let staffDirectoryLoaded = false;
        function navigateToStaffDirectory() {
            navigateToDirectoryPage('staff');
        }

        // The greeting hands over to the dashboard. Focus moves to <main> so keyboard users
        // are not left on a button that has just been hidden.
        function continueFromWelcome() {
            switchMainTab('transporter');
            const main = document.getElementById('usm-main');
            if (main) main.focus({preventScroll: true});
        }
        async function syncLiveStaffToLocal() {
            if (!USMSMART_IS_ADMIN) { showToast('Hanya Admin boleh mengimport data staf.'); return; }
            if (!window.confirm('Import/sync rekod daripada dbo.Staf ke direktori MySQL tempatan?\n\nRekod rasmi SQL Server tidak akan diubah. Rekod tempatan dengan nombor staf sama akan dikemas kini.')) return;
            const button = document.getElementById('sync-live-staff-button');
            if (button) { button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>Mengimport…'; }
            try {
                const response = await fetch(location.pathname + '?action=sync_live_staff_to_local', {
                    method:'POST', credentials:'same-origin',
                    headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN},
                    body:JSON.stringify({})
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || data.error || 'Import staf gagal.');
                staffDirectoryLoaded = false;
                await loadStaffDirectory(true);
                showToast((data.message || 'Import selesai.') + ' Diproses: ' + Number(data.processed_records || 0) + ' / ' + Number(data.source_records || 0) + ' rekod.');
            } catch (error) {
                showToast(error.message || 'Import staf gagal. Semak log Apache.');
            } finally {
                if (button) { button.disabled = false; button.innerHTML = '<i class="fa-solid fa-cloud-arrow-down"></i>Import dari SQL Server'; }
            }
        }

        async function loadStaffDirectory(forceRefresh = false) {
            const tbody = document.getElementById('staff-directory-tbody');
            const message = document.getElementById('staff-directory-message');
            if (!tbody || (!forceRefresh && staffDirectoryLoaded)) return;
            tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">Sedang memuatkan rekod staf tempatan…</td></tr>';
            message.textContent = 'Menghubungkan ke MySQL tempatan…';
            try {
                const response = await fetch(location.pathname + '?action=get_local_staff', {credentials:'same-origin', headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (!response.ok || !data.success || !Array.isArray(data.records)) throw new Error(data.message || data.error || 'Respons direktori staf tidak sah.');
                allStaffDirectoryRecords = data.records;
                staffDirectoryLoaded = true;
                filterStaffDirectory();
                message.textContent = 'Data ujian dimuatkan daripada MySQL tempatan. Rekod rasmi SQL Server tidak diubah.';
            } catch (error) {
                staffDirectoryLoaded = false;
                tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-8 text-center text-rose-600 dark:text-rose-400">' + escapeHtml(error.message || 'Direktori staf tidak dapat dimuatkan.') + '</td></tr>';
                document.getElementById('staff-directory-count').textContent = '—';
                message.textContent = 'Gagal memuatkan data. Semak sambungan MySQL dan kebenaran jadual.';
            }
        }
        function filterStaffDirectory() {
            const tbody = document.getElementById('staff-directory-tbody');
            const count = document.getElementById('staff-directory-count');
            if (!tbody || !count) return;
            const query = String(document.getElementById('staff-directory-search')?.value || '').trim().toLocaleLowerCase();
            const rows = allStaffDirectoryRecords.filter(record => [record.name, record.staffNo, record.department, record.cardUid, record.cardSerial, record.cardStatus, record.expiryDate, record.notes].some(value => String(value ?? '').toLocaleLowerCase().includes(query)));
            count.textContent = rows.length.toLocaleString();
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">Tiada rekod staf tempatan. Tekan “Tambah Staf” untuk mula.</td></tr>';
                return;
            }
            tbody.innerHTML = rows.map(record => {
                const expiry = String(record.expiryDate || '').slice(0, 10);
                const status = String(record.cardStatus || 'Tidak dinyatakan');
                const statusLower = status.toLocaleLowerCase();
                const statusClass = /aktif|active|valid/.test(statusLower) && !/tidak/.test(statusLower) ? 'bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400' : (/tamat|expired|luput/.test(statusLower) ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300' : 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300');
                const actions = USMSMART_IS_ADMIN ? '<div class="flex items-center gap-1.5"><button type="button" onclick="editLocalStaff(' + Number(record.id) + ')" class="rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 px-2.5 py-2 font-semibold" title="Kemaskini"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit</button><button type="button" onclick="exportSingleStaffToSysweb(' + Number(record.id) + ')" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-300 px-2.5 py-2 font-semibold" title="Eksport ke Sysweb"><i class="fa-solid fa-network-wired mr-1"></i>Sysweb</button><button type="button" onclick="deleteLocalStaff(' + Number(record.id) + ')" class="rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 px-2.5 py-2 font-semibold" title="Padam"><i class="fa-solid fa-trash-can mr-1"></i>Padam</button></div>' : '<div class="flex items-center gap-1.5"><button type="button" onclick="exportSingleStaffToSysweb(' + Number(record.id) + ')" class="rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-zinc-800 dark:hover:bg-zinc-700 dark:text-zinc-300 px-2.5 py-2 font-semibold" title="Eksport ke Sysweb"><i class="fa-solid fa-network-wired mr-1"></i>Sysweb</button></div>';
                return '<tr class="hover:bg-indigo-50/60 dark:hover:bg-indigo-950/10">' +
                    '<td class="px-3 py-3 font-semibold text-slate-900 dark:text-white">' + escapeHtml(record.name || '—') + (record.notes ? '<div class="mt-1 text-[10px] font-normal text-slate-500">' + escapeHtml(record.notes) + '</div>' : '') + '</td>' +
                    '<td class="px-3 py-3 whitespace-nowrap font-mono">' + escapeHtml(record.staffNo || '—') + '</td>' +
                    '<td class="px-3 py-3">' + escapeHtml(record.department || '—') + '</td>' +
                    '<td class="px-3 py-3 whitespace-nowrap font-mono">' + escapeHtml(record.cardUid || '—') + '</td>' +
                    '<td class="px-3 py-3 whitespace-nowrap">' + escapeHtml(record.cardSerial || '—') + '</td>' +
                    '<td class="px-3 py-3"><span class="inline-flex rounded-full px-2 py-1 text-[10px] font-semibold ' + statusClass + '">' + escapeHtml(status) + '</span></td>' +
                    '<td class="px-3 py-3 whitespace-nowrap">' + escapeHtml(expiry || '—') + '</td>' +
                    '<td class="px-3 py-3">' + actions + '</td></tr>';
            }).join('');
        }
        function openLocalStaffForm(record = null) {
            if (!USMSMART_IS_ADMIN) { showToast('Hanya Admin boleh mengurus rekod staf.'); return; }
            const panel = document.getElementById('local-staff-form-panel');
            document.getElementById('local-staff-form').reset();
            document.getElementById('local-staff-id').value = record ? record.id : '0';
            document.getElementById('local-staff-form-title').textContent = record ? 'Kemaskini rekod staf' : 'Tambah rekod staf';
            document.getElementById('local-staff-no').value = record?.staffNo || '';
            document.getElementById('local-staff-name').value = record?.name || '';
            document.getElementById('local-staff-department').value = record?.department || '';
            document.getElementById('local-staff-uid').value = record?.cardUid || '';
            document.getElementById('local-staff-card').value = record?.cardSerial || '';
            document.getElementById('local-staff-status').value = record?.cardStatus || 'Aktif';
            document.getElementById('local-staff-joined').value = String(record?.dateJoined || '').slice(0,10);
            document.getElementById('local-staff-expiry').value = String(record?.expiryDate || '').slice(0,10);
            document.getElementById('local-staff-notes').value = record?.notes || '';
            panel.classList.remove('hidden');
            panel.scrollIntoView({behavior:'smooth',block:'start'});
            document.getElementById('local-staff-no').focus();
        }
        function closeLocalStaffForm() { document.getElementById('local-staff-form-panel')?.classList.add('hidden'); }
        function editLocalStaff(id) {
            const record = allStaffDirectoryRecords.find(row => Number(row.id) === Number(id));
            if (!record) { showToast('Rekod staf tidak ditemui. Muat semula senarai.'); return; }
            openLocalStaffForm(record);
        }
        async function submitLocalStaffForm(event) {
            event.preventDefault();
            if (!USMSMART_IS_ADMIN) { showToast('Hanya Admin boleh mengurus rekod staf.'); return; }
            const button = document.getElementById('local-staff-submit');
            const body = {
                id:Number(document.getElementById('local-staff-id').value || 0),
                staffNo:document.getElementById('local-staff-no').value.trim(),
                name:document.getElementById('local-staff-name').value.trim(),
                department:document.getElementById('local-staff-department').value.trim(),
                cardUid:document.getElementById('local-staff-uid').value.trim().toUpperCase(),
                cardSerial:document.getElementById('local-staff-card').value.trim(),
                cardStatus:document.getElementById('local-staff-status').value,
                dateJoined:document.getElementById('local-staff-joined').value,
                expiryDate:document.getElementById('local-staff-expiry').value,
                notes:document.getElementById('local-staff-notes').value.trim()
            };
            button.disabled = true; button.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Menyimpan…';
            try {
                const response = await fetch(location.pathname + '?action=save_local_staff', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN},body:JSON.stringify(body)});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Rekod staf gagal disimpan.');
                closeLocalStaffForm(); staffDirectoryLoaded = false; await loadStaffDirectory(true); showToast(data.message || 'Rekod staf berjaya disimpan.');
            } catch (error) { showToast(error.message || 'Rekod staf gagal disimpan.'); }
            finally { button.disabled = false; button.innerHTML = '<i class="fa-solid fa-floppy-disk mr-1.5"></i>Simpan Rekod'; }
        }
        async function deleteLocalStaff(id) {
            if (!USMSMART_IS_ADMIN) { showToast('Hanya Admin boleh mengurus rekod staf.'); return; }
            const record = allStaffDirectoryRecords.find(row => Number(row.id) === Number(id));
            if (!record) { showToast('Rekod staf tidak ditemui.'); return; }
            if (!window.confirm('Padam rekod staf ujian tempatan untuk ' + String(record.name || record.staffNo) + '?\n\nTindakan ini tidak mengubah dbo.Staf di SQL Server.')) return;
            try {
                const response = await fetch(location.pathname + '?action=delete_local_staff', {method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-Token':USMSMART_CSRF_TOKEN},body:JSON.stringify({id:Number(id)})});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Rekod staf gagal dipadam.');
                staffDirectoryLoaded = false; await loadStaffDirectory(true); showToast(data.message || 'Rekod staf dipadam.');
            } catch (error) { showToast(error.message || 'Rekod staf gagal dipadam.'); }
        }

        function backToMainTransporter() {
            hideAllMainViews();
            document.getElementById('view-transporter').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function navigateToEditPage(student = null) {
            hideAllMainViews();
            document.getElementById('view-edit-student').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            if (student) {
                document.getElementById('edit-page-title').innerText = 'Kemaskini Rekod Pelajar';
                document.getElementById('edit-page-id').value = student.id;
                document.getElementById('edit-page-name').value = student.name;
                document.getElementById('edit-page-matric').value = student.matric;
                const editSchool = document.getElementById('edit-page-school');
                const studentSchool = String(student.school || '').trim();
                if (studentSchool && !Array.from(editSchool.options).some(option => option.value === studentSchool)) { const option = document.createElement('option'); option.value = studentSchool; option.textContent = studentSchool; editSchool.appendChild(option); }
                editSchool.value = studentSchool;
                document.getElementById('edit-page-uid').value = student.usmUid;
                document.getElementById('edit-page-serial').value = student.serialNo;
                document.getElementById('edit-page-policy').value = student.policy;
                document.getElementById('edit-page-date').value = student.dateJoined;
                document.getElementById('edit-page-expiry').value = student.expiryDate;
                document.getElementById('edit-page-nationality').value = student.nationality || '';
                document.getElementById('edit-page-education').value = student.educationLevel || '';
                document.getElementById('edit-page-category').value = student.studentCategory || 'auto';
                updateLiveUidPreview(student.usmUid);
                usmsmartLinkSchoolPolicy();
            } else {
                document.getElementById('edit-page-title').innerText = 'Tambah Pelajar Baharu';
                document.getElementById('edit-page-form').reset();
                document.getElementById('edit-page-id').value = 0;
                document.getElementById('edit-page-date').value = new Date().toISOString().split('T')[0];
                document.getElementById('edit-page-expiry').value = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0];
                document.getElementById('live-uid-preview').innerText = '-';
                assignAutoCardSerial();
                usmsmartLinkSchoolPolicy();
            }
        }

        /* The add form asks for nothing in this box. The number below is the one the server would
           hand out; if it is edited or cleared, the server allocates again on save. */
        async function assignAutoCardSerial() {
            const field = document.getElementById('edit-page-serial');
            if (!field) return;
            field.value = '';
            field.placeholder = 'Menjana nombor siri…';
            try {
                const res = await fetch('index.php?action=next_card_serial', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok || !data.success || !data.serial) throw new Error('allocation refused');
                field.value = String(data.serial);
                field.placeholder = 'Contoh: ' + String(data.serial);
            } catch (error) {
                field.placeholder = 'Kosong untuk nombor siri automatik';
            }
        }

        function updateLiveUidPreview(val) {
            const preview = document.getElementById('live-uid-preview');
            if (!preview) return;
            const raw = String(val || '').trim().replace(/\s+/g, '');
            const converted = convertUidToLittleEndian(raw);
            preview.innerText = raw === '' ? '-' : (converted || 'UID HEX TIDAK SAH');
            preview.classList.toggle('text-rose-600', raw !== '' && !converted);
            preview.classList.toggle('text-emerald-600', !!converted);
        }

        let studentSaveInProgress = false;
        async function submitEditPageForm(e) {
            e.preventDefault();
            if (studentSaveInProgress) return;
            const form = document.getElementById('edit-page-form');
            const submitButton = form.querySelector('button[type="submit"]');
            const payload = {
                id: document.getElementById('edit-page-id').value,
                name: document.getElementById('edit-page-name').value.trim(),
                matric: document.getElementById('edit-page-matric').value.trim(),
                school: document.getElementById('edit-page-school').value,
                usmUid: document.getElementById('edit-page-uid').value.trim(),
                serialNo: document.getElementById('edit-page-serial').value.trim(),
                policy: document.getElementById('edit-page-policy').value.trim(),
                dateJoined: document.getElementById('edit-page-date').value,
                expiryDate: document.getElementById('edit-page-expiry').value,
                nationality: document.getElementById('edit-page-nationality').value.trim(),
                educationLevel: document.getElementById('edit-page-education').value,
                studentCategory: document.getElementById('edit-page-category').value
            };
            if (!payload.name || !payload.matric) { showToast('Ralat: Nama dan nombor matrik wajib diisi.'); return; }
            if (!payload.dateJoined || !payload.expiryDate || payload.expiryDate < payload.dateJoined) { showToast('Ralat: Tarikh tamat mesti sama atau selepas tarikh mula.'); return; }
            const isEdit = Number(payload.id) > 0;
            const confirmed = window.confirm(
                (isEdit ? 'Sahkan kemas kini rekod pelajar ini?' : 'Sahkan penambahan pelajar baharu?') + '\n\n' +
                'Nama: ' + payload.name + '\nMatrik: ' + payload.matric + '\nSekolah: ' + payload.school +
                '\nTarikh tamat: ' + payload.expiryDate + '\n\nPastikan maklumat adalah betul sebelum meneruskan.'
            );
            if (!confirmed) return;
            studentSaveInProgress = true;
            if (submitButton) { submitButton.disabled = true; submitButton.dataset.originalText = submitButton.textContent; submitButton.textContent = 'Menyimpan...'; }
            try {
                const res = await fetch('index.php?action=save_student', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.message || 'Rekod tidak dapat disimpan.');
                showToast(result.message);
                await loadMasterStudents();
                navigateToDirectoryPage();
            } catch (err) {
                showToast('Ralat: ' + err.message);
            } finally {
                studentSaveInProgress = false;
                if (submitButton) { submitButton.disabled = false; submitButton.textContent = submitButton.dataset.originalText || 'Simpan'; }
            }
        }

        function convertUidToLittleEndian(hexString) {
            if (!hexString) return '';
            const clean = String(hexString).trim().replace(/\s+/g, '');
            if (!/^(?:[0-9a-fA-F]{2})+$/.test(clean)) return '';
            const pairs = [];
            for (let i = 0; i < clean.length; i += 2) pairs.push(clean.substr(i, 2));
            return pairs.reverse().join('').toUpperCase();
        }

        // Restore the preferred student-directory page size without changing server data.
        (function restoreStudentPageSize() {
            try {
                const saved = parseInt(localStorage.getItem('usmsmart_student_page_size') || '10', 10);
                if ([10, 25, 50, 100].includes(saved)) {
                    rowsPerPage = saved;
                    const control = document.getElementById('master-page-size');
                    if (control) control.value = String(saved);
                }
            } catch (e) { /* Storage may be disabled; keep the default. */ }
        })();

        function clearMasterStudentFilters() {
            const ids = ['master-search', 'master-school-filter', 'master-status-filter', 'master-education-filter', 'master-category-filter'];
            ids.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
            duplicateOnlyFilter = false;
            currentPage = 1;
            filterMasterTable();
            const search = document.getElementById('master-search');
            if (search) search.focus();
        }

        async function loadMasterStudents() {
            try {
                let res = await fetch(location.pathname + '?action=get_all_students');
                let data = await res.json();
                if (data.error) return;
                allMasterStudents = data.map(r => Object.assign({}, r, { school: sanitizeSchoolName(r.school) }));
                const existingIds = new Set(allMasterStudents.map(r => String(r.id)));
                masterSelectedIds = new Set(Array.from(masterSelectedIds).filter(id => existingIds.has(id)));
                detectStudentDuplicates();
                renderCardStatusStatistics();
                renderExpiryAlerts();
                updateSchoolFilterDropdown();
                filterMasterTable();
            } catch (err) { console.error(err); }
        }

        function updateSchoolFilterDropdown() {
            const schoolFilter = document.getElementById('master-school-filter');
            if (!schoolFilter) return;
            const currentVal = schoolFilter.value;
            const schools = Array.from(new Set([
                ...OFFICIAL_SCHOOLS,
                ...(allMasterStudents || []).map(r => String(r.school || '').trim()).filter(Boolean)
            ])).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
            schoolFilter.innerHTML = '<option value="">Semua Pusat Pengajian</option>';
            schools.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s; opt.textContent = s;
                if (s === currentVal) opt.selected = true;
                schoolFilter.appendChild(opt);
            });
            const educationFilter = document.getElementById('master-education-filter');
            if (educationFilter) {
                const selected = educationFilter.value;
                const levels = Array.from(new Set((allMasterStudents || []).map(r => String(r.educationLevel || '').trim()).filter(Boolean))).sort((a,b)=>a.localeCompare(b));
                educationFilter.innerHTML = '<option value="">Semua tahap</option>';
                levels.forEach(level => { const opt=document.createElement('option'); opt.value=level; opt.textContent=level; if(level===selected)opt.selected=true; educationFilter.appendChild(opt); });
            }
        }

        function renderExpiryAlerts() {
            const box = document.getElementById('expiry-alerts');
            if (!box) return;
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const windowSelect = document.getElementById('expiry-alert-window');
            const windowDays = Math.max(1, Math.min(365, parseInt(windowSelect && windowSelect.value ? windowSelect.value : '30', 10) || 30));
            const limit = new Date(today); limit.setDate(limit.getDate() + windowDays);
            const weekLimit = new Date(today); weekLimit.setDate(weekLimit.getDate() + 7);
            const rows = [];
            let expired = 0, week = 0, soon = 0;
            (allMasterStudents || []).forEach(r => {
                if (!r.expiryDate) return;
                const dateText = String(r.expiryDate).slice(0, 10);
                const d = new Date(dateText + 'T00:00:00');
                if (Number.isNaN(d.getTime())) return;
                if (d < today) {
                    expired++;
                    rows.push({ record: r, date: d, days: Math.round((d - today) / 86400000), status: 'Tamat tempoh' });
                } else if (d <= limit) {
                    soon++;
                    if (d <= weekLimit) week++;
                    rows.push({ record: r, date: d, days: Math.round((d - today) / 86400000), status: 'Tamat dalam ' + Math.round((d - today) / 86400000) + ' hari' });
                }
            });
            const summary = document.getElementById('expiry-alert-summary');
            const expiredCount = document.getElementById('expiry-alert-expired-count');
            const weekCount = document.getElementById('expiry-alert-week-count');
            const soonCount = document.getElementById('expiry-alert-soon-count');
            const windowLabel = document.getElementById('expiry-alert-window-label');
            const tbody = document.getElementById('expiry-alert-tbody');
            if (summary) summary.textContent = expired + ' kad telah tamat tempoh; ' + soon + ' kad akan tamat dalam ' + windowDays + ' hari akan datang.';
            if (expiredCount) expiredCount.textContent = String(expired);
            if (weekCount) weekCount.textContent = String(week);
            if (soonCount) soonCount.textContent = String(soon);
            if (windowLabel) windowLabel.textContent = 'Tamat dalam ' + windowDays + ' hari';
            rows.sort((a, b) => a.date - b.date);
            if (tbody) {
                if (!rows.length) {
                    tbody.innerHTML = '<tr><td colspan="4" class="p-3 text-center">Tiada kad tamat tempoh atau akan tamat dalam ' + windowDays + ' hari.</td></tr>';
                } else {
                    tbody.innerHTML = rows.slice(0, 10).map(item => '<tr class="border-t border-amber-200 dark:border-amber-800"><td class="p-2.5 font-medium">' + escapeHtml(item.record.name) + '</td><td class="p-2.5">' + escapeHtml(item.record.matric) + '</td><td class="p-2.5 whitespace-nowrap">' + escapeHtml(String(item.record.expiryDate).slice(0, 10)) + '</td><td class="p-2.5"><span class="inline-block rounded-md px-2 py-1 ' + (item.days < 0 ? 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-200' : 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100') + '">' + escapeHtml(item.status) + '</span></td></tr>').join('');
                }
            }
            box.classList.toggle('hidden', (allMasterStudents || []).length === 0);
        }

        function renderMasterTable(records) {
            filteredMasterStudents = records;
            let totalPages = Math.ceil(filteredMasterStudents.length / rowsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;
            let startIdx = (currentPage - 1) * rowsPerPage;
            let endIdx = startIdx + rowsPerPage;
            let paginatedRecords = filteredMasterStudents.slice(startIdx, endIdx);
            let tbody = document.getElementById('master-student-tbody');
            tbody.innerHTML = '';
            if (filteredMasterStudents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="p-8 text-center"><div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-zinc-800 dark:text-zinc-300"><i class="fa-solid fa-magnifying-glass text-lg"></i></div><p class="font-semibold text-slate-700 dark:text-zinc-200">Tiada rekod sepadan</p><p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Cuba kata kunci lain atau kosongkan penapis untuk melihat semua pelajar.</p><button type="button" onclick="clearMasterStudentFilters()" class="mt-3 rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"><i class="fa-solid fa-filter-circle-xmark mr-1"></i>Kosongkan carian dan penapis</button></td></tr>';
                document.getElementById('pagination-info').innerText = 'Menunjukkan 0 dari 0 rekod';
                document.getElementById('page-indicator').innerText = '1 / 1';
                document.getElementById('prev-page-btn').disabled = true;
                document.getElementById('next-page-btn').disabled = true;
                const selectAll = document.getElementById('master-select-all');
                if (selectAll) { selectAll.checked = false; selectAll.indeterminate = false; }
                updateMasterBulkToolbar();
                return;
            }
            const today = new Date(); today.setHours(0,0,0,0);
            paginatedRecords.forEach((r, idx) => {
                let actualIdx = startIdx + idx + 1;
                let tr = document.createElement('tr');
                const isDuplicate = duplicateStudentIds.has(String(r.id));
                tr.className = "hover:bg-slate-50/50 dark:hover:bg-zinc-900/50 transition-colors" + (isDuplicate ? ' bg-amber-50/50 dark:bg-amber-950/10' : '');

                let expiryBadgeHtml = '';
                const expiryRaw = String(r.expiryDate || '').slice(0, 10);
                if (/^\d{4}-\d{2}-\d{2}$/.test(expiryRaw)) {
                    const expDate = new Date(expiryRaw + 'T00:00:00');
                    const diffDays = Math.round((expDate.getTime() - today.getTime()) / 86400000);
                    if (diffDays < 0) {
                        expiryBadgeHtml = '<div class="space-y-0.5"><div class="text-slate-800 dark:text-zinc-200 font-mono text-[11px]">' + escapeHtml(expiryRaw) + '</div><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300"><i class="fa-solid fa-circle-xmark text-[9px]"></i> Tamat (' + Math.abs(diffDays) + 'h lalu)</span></div>';
                    } else if (diffDays <= 7) {
                        expiryBadgeHtml = '<div class="space-y-0.5"><div class="text-slate-800 dark:text-zinc-200 font-mono text-[11px] font-bold text-orange-600 dark:text-orange-400">' + escapeHtml(expiryRaw) + '</div><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-orange-100 text-orange-800 dark:bg-orange-950/70 dark:text-orange-300 animate-pulse"><i class="fa-solid fa-triangle-exclamation text-[9px]"></i> ' + (diffDays === 0 ? 'Tamat hari ini' : diffDays + ' hari lagi') + '</span></div>';
                    } else if (diffDays <= 30) {
                        expiryBadgeHtml = '<div class="space-y-0.5"><div class="text-slate-800 dark:text-zinc-200 font-mono text-[11px] font-bold text-amber-600 dark:text-amber-400">' + escapeHtml(expiryRaw) + '</div><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300"><i class="fa-solid fa-clock text-[9px]"></i> ' + diffDays + ' hari lagi</span></div>';
                    } else {
                        expiryBadgeHtml = '<div class="space-y-0.5"><div class="text-slate-800 dark:text-zinc-200 font-mono text-[11px]">' + escapeHtml(expiryRaw) + '</div><span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-md text-[10px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"><i class="fa-solid fa-circle-check text-[9px]"></i> Aktif</span></div>';
                    }
                } else {
                    expiryBadgeHtml = '<span class="text-slate-400 text-[11px]">Tiada rekod</span>';
                }

                tr.innerHTML = '<td class="p-3.5 text-center"><input type="checkbox" aria-label="Pilih ' + escapeHtml(r.name) + '" ' + (masterSelectedIds.has(String(r.id)) ? 'checked' : '') + ' onchange="toggleMasterStudentSelection(' + Number(r.id) + ', this.checked)" class="rounded accent-indigo-600 cursor-pointer"></td>' +
                               '<td class="p-3.5">' + actualIdx + '</td>' +
                               '<td class="p-3.5 font-medium text-slate-900 dark:text-white"><button type="button" onclick="openStudentProfile(' + Number(r.id) + ')" class="text-left hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline">' + escapeHtml(r.name) + '</button>' + (isDuplicate ? '<span class="ml-2 inline-flex rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">SEMAK PENDUA</span>' : '') + '</td>' +
                               '<td class="p-3.5">' + escapeHtml(r.matric) + '</td>' +
                               '<td class="p-3.5">' + escapeHtml(r.school) + '</td>' +
                               '<td class="p-3.5 font-bold text-indigo-600 dark:text-indigo-400">' + escapeHtml(r.usmUid) + '</td>' +
                               '<td class="p-3.5 text-slate-500">' + escapeHtml(r.dateJoined || '-') + '</td>' +
                               '<td class="p-3.5">' + expiryBadgeHtml + '</td>' +
                               '<td class="p-3.5"><div class="usm-row-actions">' +
                                   '<button onclick="openStudentProfile(' + Number(r.id) + ')" class="usm-icon-btn usm-act usm-act-view" title="Lihat profil" aria-label="Lihat profil ' + escapeHtml(r.name) + '"><i class="fa-regular fa-eye" aria-hidden="true"></i></button>' +
                                   '<button onclick="exportSingleStudentToSysweb(' + Number(r.id) + ')" class="usm-icon-btn usm-act usm-act-sysweb" title="Eksport ke Sysweb" aria-label="Eksport ke Sysweb ' + escapeHtml(r.name) + '"><i class="fa-solid fa-network-wired" aria-hidden="true"></i></button>' +
                                   '<button onclick=\'openCardModal(' + JSON.stringify(r).replace(/'/g, "\\'") + ')\' class="usm-icon-btn usm-act usm-act-card" title="Cetak Kad" aria-label="Cetak Kad ' + escapeHtml(r.name) + '"><i class="fa-solid fa-id-card" aria-hidden="true"></i></button>' +
                                   '<button onclick=\'navigateToEditPage(' + JSON.stringify(r).replace(/'/g, "\\'") + ')\' class="usm-icon-btn usm-act usm-act-edit" title="Edit Pelajar / Request" aria-label="Edit Pelajar / Request ' + escapeHtml(r.name) + '"><i class="fa-solid fa-pen-to-square" aria-hidden="true"></i></button>' +
                                   '<button onclick="deleteStudentRecord(' + r.id + ')" class="usm-icon-btn usm-act usm-act-delete" title="Padam Pelajar / Request" aria-label="Padam Pelajar / Request ' + escapeHtml(r.name) + '"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>' +
                               '</div></td>';
                tbody.appendChild(tr);
            });
            document.getElementById('pagination-info').innerText = 'Menunjukkan ' + (startIdx + 1) + '-' + Math.min(endIdx, filteredMasterStudents.length) + ' dari ' + filteredMasterStudents.length + ' rekod';
            document.getElementById('page-indicator').innerText = currentPage + ' / ' + totalPages;
            document.getElementById('prev-page-btn').disabled = (currentPage === 1);
            document.getElementById('next-page-btn').disabled = (currentPage === totalPages);
            const pageIds = paginatedRecords.map(r => String(r.id));
            const selectAll = document.getElementById('master-select-all');
            if (selectAll) {
                selectAll.checked = pageIds.length > 0 && pageIds.every(id => masterSelectedIds.has(id));
                selectAll.indeterminate = pageIds.some(id => masterSelectedIds.has(id)) && !selectAll.checked;
            }
            updateMasterBulkToolbar();
        }

        function normalizedDuplicateValue(value) {
            return String(value || '').trim().replace(/\s+/g, '').toLocaleUpperCase();
        }

        function detectStudentDuplicates() {
            const fields = [
                { key: 'matric', label: 'matrik' },
                { key: 'usmUid', label: 'UID' },
                { key: 'serialNo', label: 'nombor kad' }
            ];
            const ids = new Set();
            const reasons = new Map();
            fields.forEach(field => {
                const buckets = new Map();
                (allMasterStudents || []).forEach(record => {
                    const value = normalizedDuplicateValue(record[field.key]);
                    if (!value) return;
                    if (!buckets.has(value)) buckets.set(value, []);
                    buckets.get(value).push(record);
                });
                buckets.forEach(records => {
                    if (records.length < 2) return;
                    records.forEach(record => {
                        const id = String(record.id);
                        ids.add(id);
                        if (!reasons.has(id)) reasons.set(id, new Set());
                        reasons.get(id).add(field.label);
                    });
                });
            });
            duplicateStudentIds = ids;
            window.usmsmartDuplicateReasons = reasons;
            const alertBox = document.getElementById('student-duplicate-alert');
            const summary = document.getElementById('student-duplicate-summary');
            if (alertBox && summary) {
                summary.textContent = ids.size + ' rekod terlibat dalam kemungkinan pendua berdasarkan matrik, UID atau nombor kad. Sila sahkan sebelum membuat perubahan.';
                alertBox.classList.toggle('hidden', ids.size === 0 || duplicateAlertDismissed);
            }
        }

        function dismissDuplicateAlert() {
            duplicateAlertDismissed = true;
            const el = document.getElementById('student-duplicate-alert');
            if (el) el.classList.add('hidden');
        }

        function toggleDuplicateFilter() {
            duplicateOnlyFilter = !duplicateOnlyFilter;
            const btn = document.getElementById('duplicate-filter-button');
            if (btn) btn.textContent = duplicateOnlyFilter ? 'Lihat semua rekod' : 'Lihat rekod pendua';
            if (duplicateOnlyFilter) {
                const status = document.getElementById('master-status-filter'); if (status) status.value = '';
                const search = document.getElementById('master-search'); if (search) search.value = '';
                const school = document.getElementById('master-school-filter'); if (school) school.value = '';
            }
            filterMasterTable();
        }

        window.usmsmartActiveProfileId = null;
        async function loadStudentCardHistory(id) {
            const list = document.getElementById('student-card-history-list');
            const summary = document.getElementById('student-card-history-summary');
            if (!id || !list || !summary) return;
            list.innerHTML = '<p class="text-xs text-slate-500 p-2">Memuatkan sejarah kad...</p>';
            try {
                const response = await fetch('index.php?action=get_student_card_history&id=' + encodeURIComponent(id), {credentials:'same-origin'});
                const payload = await response.json();
                if (!response.ok || payload.error) throw new Error(payload.error || 'Sejarah tidak tersedia.');
                const student = payload.student || {};
                const history = payload.history || [];
                summary.innerHTML = [
                    ['Rekod sejarah', history.length], ['UID semasa', student.usm_uid || '-'],
                    ['Nombor kad', student.serial_no || '-'], ['Luput', student.expiry_date || '-']
                ].map(([label,value]) => '<div class="rounded-lg bg-slate-50 p-2.5 dark:bg-zinc-900"><div class="text-[9px] font-bold uppercase tracking-wider text-slate-500">'+escapeHtml(label)+'</div><div class="mt-1 break-words text-xs font-semibold text-slate-900 dark:text-white">'+escapeHtml(value == null ? '-' : String(value))+'</div></div>').join('');
                if (!history.length) { list.innerHTML = '<p class="p-2 text-xs text-slate-500">Belum ada sejarah kad.</p>'; return; }
                const labels = {created:'Kad direkodkan',updated:'Butiran kad dikemaskini',baseline:'Snapshot awal',renewed:'Kad diperbaharui',reissued:'Kad diganti'};
                list.innerHTML = history.map(item => {
                    const title = labels[item.event_type] || String(item.event_type || 'Peristiwa');
                    const previous = item.previous_snapshot || null;
                    let details = '<div class="mt-2 grid grid-cols-1 gap-1 text-[11px] text-slate-600 dark:text-zinc-300 sm:grid-cols-2">' +
                        [['UID',item.uid_snapshot],['No. kad',item.serial_snapshot],['Polisi',item.policy_snapshot],['Tarikh luput',item.expiry_snapshot]].map(([k,v])=>'<div><span class="text-slate-400">'+k+':</span> '+escapeHtml(v || '-')+'</div>').join('') + '</div>';
                    if (previous) {
                        const changed = [['Nama','name'],['Matrik','matric'],['UID','usm_uid'],['No. kad','serial_no'],['Polisi','policy'],['Tarikh luput','expiry_date']].filter(pair => String(previous[pair[1]] ?? '') !== String(({name:item.name_snapshot,matric:item.matric,usm_uid:item.uid_snapshot,serial_no:item.serial_snapshot,policy:item.policy_snapshot,expiry_date:item.expiry_snapshot})[pair[1]] ?? ''));
                        if (changed.length) details += '<div class="mt-2 border-t border-slate-100 pt-2 text-[10px] text-slate-500 dark:border-zinc-800">Perubahan: '+changed.map(pair=>escapeHtml(pair[0])).join(', ')+'</div>';
                    }
                    return '<article class="rounded-lg border border-slate-200 p-3 dark:border-zinc-800"><div class="flex flex-wrap items-start justify-between gap-2"><div class="text-xs font-bold text-slate-900 dark:text-white">'+escapeHtml(title)+'</div><time class="text-[10px] text-slate-500">'+escapeHtml(item.created_at || '')+'</time></div><div class="mt-1 text-[10px] text-slate-500">Oleh '+escapeHtml(item.actor || 'system')+' · '+escapeHtml(item.matric || student.matric || '-')+'</div>'+details+'</article>';
                }).join('');
            } catch (error) {
                summary.innerHTML = '';
                list.innerHTML = '<p class="p-2 text-xs text-rose-600">'+escapeHtml(error.message || 'Sejarah kad gagal dimuatkan.')+'</p>';
            }
        }

        function openStudentProfile(id) {
            const student = (allMasterStudents || []).find(r => String(r.id) === String(id));
            if (!student) { showToast('Rekod pelajar tidak ditemui. Muat semula senarai dan cuba lagi.'); return; }
            window.usmsmartActiveProfileId = Number(id);
            const modal = document.getElementById('student-profile-modal');
            const title = document.getElementById('student-profile-title');
            const subtitle = document.getElementById('profile-matric-subtitle');
            if (!modal || !title || !subtitle) return;
            title.textContent = student.name || 'Profil pelajar';
            subtitle.textContent = student.matric || '-';
            const expiryRaw = student.expiryDate || '';
            const expiryDate = expiryRaw ? new Date(expiryRaw + (String(expiryRaw).length === 10 ? 'T23:59:59' : '')) : null;
            const today = new Date(); today.setHours(0, 0, 0, 0);
            const expiryDay = expiryDate && !Number.isNaN(expiryDate.getTime()) ? new Date(expiryDate.getFullYear(), expiryDate.getMonth(), expiryDate.getDate()) : null;
            const daysLeft = expiryDay ? Math.ceil((expiryDay.getTime() - today.getTime()) / 86400000) : null;
            const statusEl = document.getElementById('student-profile-status');
            const statusLabel = document.getElementById('student-profile-status-label');
            const expiryHint = document.getElementById('student-profile-expiry-hint');
            let statusText = 'Tarikh luput tiada';
            let statusClasses = 'border-slate-200 bg-slate-50 text-slate-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300';
            let hintText = 'Tiada tarikh luput yang sah untuk dipaparkan.';
            if (daysLeft !== null) {
                if (daysLeft < 0) { statusText = 'Tamat tempoh'; statusClasses = 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300'; hintText = 'Luput ' + Math.abs(daysLeft) + ' hari lalu.'; }
                else if (daysLeft <= 7) { statusText = 'Hampir luput'; statusClasses = 'border-orange-200 bg-orange-50 text-orange-700 dark:border-orange-900 dark:bg-orange-950/40 dark:text-orange-300'; hintText = daysLeft === 0 ? 'Luput hari ini.' : 'Baki ' + daysLeft + ' hari.'; }
                else if (daysLeft <= 30) { statusText = 'Luput tidak lama lagi'; statusClasses = 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'; hintText = 'Baki ' + daysLeft + ' hari.'; }
                else { statusText = 'Aktif'; statusClasses = 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'; hintText = 'Baki ' + daysLeft + ' hari.'; }
            }
            if (statusEl) statusEl.className = 'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-bold ' + statusClasses;
            if (statusLabel) statusLabel.textContent = statusText;
            if (expiryHint) expiryHint.textContent = hintText;
            const fields = [
                ['Nombor matrik', student.matric], ['Pusat pengajian', student.school],
                ['UID asal', student.usmUid], ['Nombor kad', student.serialNo],
                ['Polisi', student.policy], ['Tarikh daftar', student.dateJoined],
                ['Tarikh luput', student.expiryDate], ['ID rekod', student.id]
            ];
            document.getElementById('student-profile-fields').innerHTML = fields.map(([label, value]) =>
                '<div class="rounded-xl border border-slate-200 p-3 dark:border-zinc-800"><div class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 dark:text-zinc-400">' + escapeHtml(label) + '</div><div class="mt-1 break-words text-sm font-medium text-slate-900 dark:text-white">' + escapeHtml(value == null || value === '' ? '-' : value) + '</div></div>'
            ).join('');
            const duplicateNote = document.getElementById('student-profile-duplicate-note');
            const reasonSet = window.usmsmartDuplicateReasons && window.usmsmartDuplicateReasons.get(String(student.id));
            if (duplicateNote) {
                duplicateNote.classList.toggle('hidden', !reasonSet || !reasonSet.size);
                duplicateNote.textContent = reasonSet && reasonSet.size ? 'Semakan diperlukan: nilai ' + Array.from(reasonSet).join(', ') + ' sepadan dengan sekurang-kurangnya satu rekod lain.' : '';
            }
            // Build duplicate comparison directly from the loaded master records. This does not depend on the Data Quality scan having run.
            const comparison = document.getElementById('student-profile-duplicate-comparison');
            const comparisonCards = document.getElementById('student-profile-duplicate-comparison-cards');
            if (comparison && comparisonCards) {
                const normalize = v => String(v == null ? '' : v).trim().toUpperCase().replace(/\s+/g, '');
                const candidateFields = [
                    ['matric', 'matric', 'Nombor matrik'],
                    ['usmUid', 'usm_uid', 'UID asal'],
                    ['serialNo', 'serial_no', 'Nombor kad']
                ];
                const matched = new Map();
                (allMasterStudents || []).forEach(other => {
                    if (!other || String(other.id) === String(student.id)) return;
                    for (const [key, rawField, label] of candidateFields) {
                        const value = normalize(student[key]);
                        if (value && value === normalize(other[key])) {
                            if (!matched.has(String(other.id))) matched.set(String(other.id), {record: other, fields: []});
                            const entry = matched.get(String(other.id));
                            if (!entry.fields.some(f => f.key === key)) entry.fields.push({key, label, value: String(student[key]).trim()});
                        }
                    }
                });
                const profileFields = [['Nama','name'],['ID rekod','id'],['Nombor matrik','matric'],['Pusat pengajian','school'],['UID asal','usmUid'],['Nombor kad','serialNo'],['Polisi palang','policy'],['Tarikh daftar','dateJoined'],['Tarikh luput','expiryDate']];
                const renderComparisonCard = (record, heading, matchedFields, isMatch) => {
                    const rid = Number(record.id);
                    const matchedKeys = new Set((matchedFields || []).map(f => f.key));
                    const val = key => record[key] !== undefined && record[key] !== null && String(record[key]).trim() !== '' ? record[key] : '—';
                    const rows = profileFields.map(([label,key]) => {
                        const same = matchedKeys.has(key);
                        return '<div class="rounded-md p-2 '+(same?'bg-rose-100 ring-1 ring-rose-300 dark:bg-rose-950/50 dark:ring-rose-800':'bg-slate-50 dark:bg-zinc-900')+'"><div class="text-[10px] uppercase text-slate-500">'+label+(same?' · SEPADAN':'')+'</div><div class="mt-1 break-words text-xs font-semibold text-slate-900 dark:text-zinc-100">'+escapeHtml(val(key))+'</div></div>';
                    }).join('');
                    const action = USMSMART_IS_ADMIN && Number.isFinite(rid) && rid > 0 ? '<button type="button" onclick="closeStudentProfile(); editDataQualityRecord('+rid+')" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit rekod ini</button>' : '<span class="text-[10px] text-slate-500">Hubungi pentadbir untuk pembetulan.</span>';
                    return '<article class="min-w-0 overflow-hidden rounded-xl border '+(isMatch?'border-rose-300 dark:border-rose-900':'border-indigo-200 dark:border-indigo-900')+'"><header class="p-3 '+(isMatch?'bg-rose-50 dark:bg-rose-950/30':'bg-indigo-50 dark:bg-indigo-950/30')+'"><div class="text-[10px] font-bold uppercase tracking-wide">'+heading+'</div><div class="mt-1 text-sm font-bold">'+escapeHtml(val('name'))+'</div><div class="text-[10px] text-slate-500">ID '+escapeHtml(val('id'))+'</div></header><div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3">'+rows+'</div><div class="p-3 pt-0">'+action+'</div></article>';
                };
                const groups = Array.from(matched.values());
                if (groups.length) {
                    comparisonCards.innerHTML = groups.map((entry, idx) => {
                        const labels = entry.fields.map(f => f.label+': '+f.value).join(' · ');
                        return '<div class="mb-3 rounded-lg border border-rose-200 bg-rose-50 p-2 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200"><strong>Padanan '+(idx+1)+':</strong> '+escapeHtml(labels)+'</div><div class="grid grid-cols-1 lg:grid-cols-2 gap-3 items-start">'+renderComparisonCard(student,'Rekod semasa',entry.fields,false)+renderComparisonCard(entry.record,'Rekod sepadan',entry.fields,true)+'</div>';
                    }).join('');
                    comparison.classList.remove('hidden');
                } else {
                    comparisonCards.innerHTML = '<p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-900 dark:text-zinc-300">Tiada rekod sepadan ditemui dalam rekod yang sedang dimuatkan. Cuba muat semula data sebelum membuat keputusan.</p>';
                    comparison.classList.remove('hidden');
                }
            }
            const copyUidBtn = document.getElementById('student-profile-copy-uid');
            const copyCardBtn = document.getElementById('student-profile-copy-card');
            if (copyUidBtn) copyUidBtn.onclick = () => copyProfileValue(student.usmUid, 'UID');
            if (copyCardBtn) copyCardBtn.onclick = () => copyProfileValue(student.serialNo, 'Nombor kad');
            document.getElementById('student-profile-card-btn').onclick = () => { closeStudentProfile(); openCardModal(student); };
            const editBtn = document.getElementById('student-profile-edit-btn');
            if (editBtn) {
                editBtn.classList.toggle('hidden', !USMSMART_IS_ADMIN);
                editBtn.onclick = () => { closeStudentProfile(); navigateToEditPage(student); };
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            loadStudentCardHistory(id);
        }


        function copyProfileValue(value, label) {
            const text = String(value == null ? '' : value).trim();
            if (!text || text === '-') { showToast(label + ' tiada untuk disalin.'); return; }
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => showToast(label + ' disalin.')).catch(() => fallbackCopyProfileValue(text, label));
            } else fallbackCopyProfileValue(text, label);
        }

        function fallbackCopyProfileValue(text, label) {
            const field = document.createElement('textarea');
            field.value = text; field.setAttribute('readonly', '');
            field.style.position = 'fixed'; field.style.opacity = '0';
            document.body.appendChild(field); field.select();
            let copied = false;
            try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
            field.remove();
            showToast(copied ? label + ' disalin.' : 'Tidak dapat menyalin secara automatik.');
        }

        function closeStudentProfile() {
            const modal = document.getElementById('student-profile-modal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
        }

        function toggleMasterStudentSelection(id, checked) {
            const key = String(id);
            if (checked) masterSelectedIds.add(key); else masterSelectedIds.delete(key);
            updateMasterBulkToolbar();
            const selectAll = document.getElementById('master-select-all');
            const pageRecords = filteredMasterStudents.slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage);
            if (selectAll) {
                selectAll.checked = pageRecords.length > 0 && pageRecords.every(r => masterSelectedIds.has(String(r.id)));
                selectAll.indeterminate = pageRecords.some(r => masterSelectedIds.has(String(r.id))) && !selectAll.checked;
            }
        }

        function toggleMasterSelectAll(control) {
            const pageRecords = filteredMasterStudents.slice((currentPage - 1) * rowsPerPage, currentPage * rowsPerPage);
            pageRecords.forEach(record => {
                const id = String(record.id);
                if (control.checked) masterSelectedIds.add(id); else masterSelectedIds.delete(id);
            });
            renderMasterTable(filteredMasterStudents);
        }

        function selectEntireFilteredList() {
            if (filteredMasterStudents.length > 500) {
                showToast('Pilih maksimum 500 rekod untuk tindakan pukal. Gunakan penapisan untuk mengecilkan senarai.');
                return;
            }
            filteredMasterStudents.forEach(record => masterSelectedIds.add(String(record.id)));
            renderMasterTable(filteredMasterStudents);
        }

        function clearMasterSelection() {
            masterSelectedIds.clear();
            renderMasterTable(filteredMasterStudents);
        }

        function updateMasterBulkToolbar() {
            const bar = document.getElementById('master-bulk-bar');
            const badge = document.getElementById('bulk-selected-badge');
            const text = document.getElementById('bulk-selected-text');
            const allBtn = document.getElementById('bulk-select-all-filtered-btn');
            if (bar) bar.classList.toggle('hidden', masterSelectedIds.size === 0);
            if (badge) badge.textContent = String(masterSelectedIds.size);
            if (text) text.textContent = masterSelectedIds.size + ' pelajar dipilih';
            if (allBtn) allBtn.classList.toggle('hidden', filteredMasterStudents.length <= 0 || filteredMasterStudents.every(r => masterSelectedIds.has(String(r.id))));
        }

        function selectedMasterIdsArray() { return Array.from(masterSelectedIds).map(id => Number(id)).filter(id => Number.isInteger(id) && id > 0); }

        function bulkExportSelectedCSV() {
            const ids = new Set(Array.from(masterSelectedIds));
            const rows = (allMasterStudents || []).filter(r => ids.has(String(r.id)));
            if (!rows.length) { showToast('Sila pilih sekurang-kurangnya satu rekod.'); return; }
            const columns = [['Nama','name'],['Matrik','matric'],['Pusat Pengajian','school'],['UID','usmUid'],['Nombor Kad','serialNo'],['Polisi','policy'],['Tarikh Daftar','dateJoined'],['Tarikh Luput','expiryDate']];
            const csvCell = value => '"' + String(value == null ? '' : value).replace(/^[=+@\-\t\r]/, "'$&").replace(/"/g, '""') + '"';
            const csv = '\uFEFF' + columns.map(c => csvCell(c[0])).join(',') + '\r\n' + rows.map(r => columns.map(c => csvCell(r[c[1]])).join(',')).join('\r\n');
            const blob = new Blob([csv], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob); const a = document.createElement('a');
            a.href = url; a.download = 'usmsmart_pelajar_terpilih_' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
            showToast(rows.length + ' rekod dieksport ke CSV.');
        }

        async function confirmBulkDelete() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses pentadbir diperlukan.'); return; }
            const ids = selectedMasterIdsArray();
            if (!ids.length) { showToast('Pilih pelajar yang hendak dipadam.'); return; }
            if (ids.length > 500) { showToast('Had maksimum ialah 500 rekod bagi setiap tindakan.'); return; }
            if (!confirm('Padam ' + ids.length + ' rekod pelajar secara kekal? Tindakan ini tidak boleh dibuat asal.')) return;
            try {
                const response = await fetch('index.php?action=bulk_delete_students', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids})});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Pemadaman pukal gagal.');
                masterSelectedIds.clear(); showToast(result.message || 'Rekod dipadam.'); await loadMasterStudents();
            } catch (error) { showToast(error.message || 'Ralat memadam rekod secara pukal.'); }
        }

        function navigateToBulkUpdatePage() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses pentadbir diperlukan.'); return; }
            const ids = selectedMasterIdsArray();
            if (!ids.length) { showToast('Pilih sekurang-kurangnya seorang pelajar di Direktori dahulu.'); return; }
            if (ids.length > 500) { showToast('Had maksimum ialah 500 rekod bagi setiap tindakan.'); return; }
            hideAllMainViews();
            document.getElementById('view-bulk-update').classList.remove('hidden');
            document.getElementById('bulk-page-selected-count').innerHTML = '<i class="fa-solid fa-users"></i> ' + ids.length + ' pelajar dipilih';
            document.getElementById('bulk-page-submit').disabled = false;
            window.scrollTo({top:0, behavior:'smooth'});
        }

        function onBulkUpdatePageTypeChange() {
            const type = document.getElementById('bulk-page-type').value;
            const input = document.getElementById('bulk-page-value');
            const label = document.getElementById('bulk-page-value-label');
            const help = document.getElementById('bulk-page-help');
            const config = {
                extend_days: {label:'Bilangan hari untuk dilanjutkan', placeholder:'Contoh: 30', help:'Masukkan nombor bulat dari 1 hingga 1825.', type:'number', min:'1', max:'1825'},
                set_expiry: {label:'Tarikh luput baharu', placeholder:'Pilih tarikh luput', help:'Pilih tarikh dalam format YYYY-MM-DD.', type:'date'},
                set_policy: {label:'Nama polisi baharu', placeholder:'Contoh: LAB_ALPHA', help:'Masukkan nama polisi yang hendak digunakan.', type:'text'},
                set_school: {label:'Nama pusat pengajian baharu', placeholder:'Masukkan nama pusat pengajian', help:'Nama ini akan digunakan untuk semua rekod terpilih.', type:'text'}
            };
            const item = config[type];
            label.textContent = item ? item.label : 'Nilai baharu';
            input.type = item ? item.type : 'text'; input.placeholder = item ? item.placeholder : 'Pilih jenis kemas kini dahulu';
            input.min = item && item.min ? item.min : ''; input.max = item && item.max ? item.max : '';
            input.disabled = !item; input.value = ''; help.textContent = item ? item.help : 'Arahan dan format nilai akan dipaparkan selepas jenis kemas kini dipilih.';
        }

        async function submitBulkUpdatePage(event) {
            event.preventDefault();
            if (!USMSMART_IS_ADMIN) { showToast('Akses pentadbir diperlukan.'); return; }
            const ids = selectedMasterIdsArray();
            const updateType = document.getElementById('bulk-page-type').value;
            const input = document.getElementById('bulk-page-value');
            const value = input.value.trim();
            if (!ids.length) { showToast('Tiada rekod dipilih. Kembali ke direktori dan pilih pelajar.'); return; }
            if (ids.length > 500) { showToast('Had maksimum ialah 500 rekod bagi setiap tindakan.'); return; }
            if (!updateType || !value) { showToast('Lengkapkan jenis kemas kini dan nilai.'); return; }
            if (updateType === 'extend_days' && (!/^\d+$/.test(value) || Number(value) < 1 || Number(value) > 1825)) { showToast('Bilangan hari mesti antara 1 hingga 1825.'); return; }
            if (updateType === 'set_expiry' && !/^\d{4}-\d{2}-\d{2}$/.test(value)) { showToast('Pilih tarikh luput yang sah.'); return; }
            if (!confirm('Sahkan kemas kini ' + ids.length + ' rekod pelajar? Tindakan ini akan mengubah data dalam pangkalan data.')) return;
            const button = document.getElementById('bulk-page-submit'); button.disabled = true;
            try {
                const response = await fetch('index.php?action=bulk_update_students', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids, update_type:updateType, value})});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Kemas kini pukal gagal.');
                masterSelectedIds.clear();
                document.getElementById('bulk-update-page-form').reset(); onBulkUpdatePageTypeChange();
                showToast(result.message || 'Rekod berjaya dikemas kini.');
                await loadMasterStudents();
                navigateToDirectoryPage();
            } catch (error) { showToast(error.message || 'Ralat mengemas kini rekod secara pukal.'); }
            finally { button.disabled = selectedMasterIdsArray().length === 0; }
        }

        async function openBulkUpdateModal() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses pentadbir diperlukan.'); return; }
            const ids = selectedMasterIdsArray();
            if (!ids.length) { showToast('Pilih pelajar yang hendak dikemas kini.'); return; }
            if (ids.length > 500) { showToast('Had maksimum ialah 500 rekod bagi setiap tindakan.'); return; }
            const type = prompt('Pilih jenis kemas kini:\n1 = Lanjut tempoh (hari)\n2 = Tetapkan tarikh luput (YYYY-MM-DD)\n3 = Tukar polisi\n4 = Tukar pusat pengajian');
            if (type === null) return;
            const map = {'1':'extend_days','2':'set_expiry','3':'set_policy','4':'set_school'};
            const updateType = map[String(type).trim()];
            if (!updateType) { showToast('Pilihan kemas kini tidak sah.'); return; }
            const prompts = {extend_days:'Bilangan hari untuk dilanjutkan (1–1825)',set_expiry:'Tarikh luput baharu (YYYY-MM-DD)',set_policy:'Nama polisi baharu',set_school:'Nama pusat pengajian baharu'};
            const value = prompt(prompts[updateType]);
            if (value === null || !value.trim()) return;
            if (!confirm('Teruskan kemas kini ' + ids.length + ' rekod terpilih?')) return;
            try {
                const response = await fetch('index.php?action=bulk_update_students', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({ids,update_type:updateType,value:value.trim()})});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Kemas kini pukal gagal.');
                showToast(result.message || 'Rekod dikemas kini.'); await loadMasterStudents();
            } catch (error) { showToast(error.message || 'Ralat mengemas kini rekod secara pukal.'); }
        }

        function changePage(direction) {
            currentPage += direction;
            renderMasterTable(filteredMasterStudents);
        }

        function changeRowsPerPage() {
            const control = document.getElementById('master-page-size');
            const requested = parseInt(control ? control.value : '10', 10);
            rowsPerPage = [10, 25, 50, 100].includes(requested) ? requested : 10;
            try { localStorage.setItem('usmsmart_student_page_size', String(rowsPerPage)); } catch (e) {}
            currentPage = 1;
            renderMasterTable(filteredMasterStudents);
        }

        function getCardStatus(record, todayText) {
            const expiry = String(record.expiryDate || '').slice(0, 10);
            if (!/^\d{4}-\d{2}-\d{2}$/.test(expiry)) return 'unknown';
            if (expiry < todayText) return 'expired';
            const today = new Date(todayText + 'T00:00:00');
            const expiryDate = new Date(expiry + 'T00:00:00');
            const days = Math.round((expiryDate.getTime() - today.getTime()) / 86400000);
            return days <= 30 ? 'expiring:' + days : 'active';
        }

        function updateActiveFilterAlert(status, count) {
            const alertBox = document.getElementById('active-filter-alert');
            const alertText = document.getElementById('active-filter-text');
            if (!alertBox || !alertText) return;
            if (!status) {
                alertBox.classList.add('hidden');
                return;
            }
            let label = 'Semua Pelajar';
            if (status === '30') label = 'Pelajar Kad Hampir Luput (≤ 30 Hari)';
            else if (status === '7') label = 'Pelajar Kad Tamat Segera (≤ 7 Hari)';
            else if (status === '14') label = 'Pelajar Kad Tamat (≤ 14 Hari)';
            else if (status === 'expired') label = 'Pelajar Kad Telah Tamat Tempoh';
            else if (status === 'active') label = 'Pelajar Kad Masih Aktif';
            alertText.textContent = 'Menapis: ' + label + ' (' + count + ' rekod dijumpai)';
            alertBox.classList.remove('hidden');
        }

        function updatePillStyles(activeStatus) {
            const pills = [
                { id: 'pill-filter-all', val: '' },
                { id: 'pill-filter-expiring30', val: '30' },
                { id: 'pill-filter-expiring7', val: '7' },
                { id: 'pill-filter-expired', val: 'expired' },
                { id: 'pill-filter-active', val: 'active' }
            ];
            pills.forEach(p => {
                const btn = document.getElementById(p.id);
                if (!btn) return;
                if (p.val === (activeStatus || '')) {
                    btn.classList.add('ring-2', 'ring-indigo-600', 'ring-offset-1', 'dark:ring-offset-zinc-900', 'font-bold');
                } else {
                    btn.classList.remove('ring-2', 'ring-indigo-600', 'ring-offset-1', 'dark:ring-offset-zinc-900', 'font-bold');
                }
            });
        }

        function viewStudentsByExpiryStatus(status) {
            navigateToDirectoryPage();
            const statusSelect = document.getElementById('master-status-filter');
            if (statusSelect) {
                statusSelect.value = status || '';
            }
            const sortSelect = document.getElementById('master-sort');
            if (sortSelect && ['30', '7', '14', 'expired'].includes(status)) {
                sortSelect.value = 'expiry_date_asc';
            }
            filterMasterTable();
            const quickPills = document.getElementById('quick-expiry-pills');
            if (quickPills) {
                quickPills.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        const masterColumnSortDirection = { name: 1, school: 1, dateJoined: -1, expiryDate: 1 };

        function sortMasterByColumn(field) {
            const sortMap = {
                name: ['name_asc', 'name_desc'],
                school: ['school_asc', 'school_desc'],
                dateJoined: ['date_joined_asc', 'date_joined_desc'],
                expiryDate: ['expiry_date_asc', 'expiry_date_desc']
            };
            if (!sortMap[field]) return;
            const select = document.getElementById('master-sort');
            const current = select ? select.value : '';
            const pair = sortMap[field];
            const next = current === pair[0] ? pair[1] : pair[0];
            if (select) select.value = next;
            masterColumnSortDirection[field] = next === pair[0] ? 1 : -1;
            filterMasterTable();
        }

        function updateMasterSortIcons(sortBy) {
            const mappings = {
                name: ['name_asc', 'name_desc'],
                school: ['school_asc', 'school_desc'],
                dateJoined: ['date_joined_asc', 'date_joined_desc'],
                expiryDate: ['expiry_date_asc', 'expiry_date_desc']
            };
            Object.entries(mappings).forEach(([field, options]) => {
                const icon = document.getElementById('sort-icon-' + field);
                if (!icon) return;
                icon.className = 'fa-solid ' + (sortBy === options[0] ? 'fa-sort-up text-indigo-500' : sortBy === options[1] ? 'fa-sort-down text-indigo-500' : 'fa-sort text-slate-300');
            });
        }

        function filterMasterTable() {
            const keyword = String(document.getElementById('master-search')?.value || '').trim().toLocaleLowerCase();
            const selectedSchool = String(document.getElementById('master-school-filter')?.value || '').trim();
            // The dropdown offers both canonical and raw-as-stored names, and analytics hands over the
            // canonical one, so normalising both sides is what makes the list agree with the charts.
            const selectedSchoolKey = String(sanitizeSchoolName(selectedSchool) || '').trim();
            const selectedStatus = document.getElementById('master-status-filter')?.value || '';
            const selectedEducation = document.getElementById('master-education-filter')?.value || '';
            const selectedCategory = document.getElementById('master-category-filter')?.value || '';
            const sortBy = document.getElementById('master-sort')?.value || 'name_asc';
            const now = new Date();
            const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const filtered = (allMasterStudents || []).filter(r => {
                const searchable = [r.name, r.matric, r.usmUid, r.serialNo, r.school, r.policy, r.dateJoined, r.expiryDate, r.nationality, r.educationLevel, r.studentCategory, r.id]
                    .some(v => String(v || '').toLocaleLowerCase().includes(keyword));
                const schoolMatch = !selectedSchoolKey || String(sanitizeSchoolName(r.school) || '').trim() === selectedSchoolKey;
                const educationMatch = !selectedEducation || String(r.educationLevel || '') === selectedEducation;
                const categoryMatch = !selectedCategory || String(r.studentCategory || 'Unknown') === selectedCategory;
                const status = getCardStatus(r, today);
                let statusMatch = true;
                if (selectedStatus === 'expired') statusMatch = status === 'expired';
                else if (selectedStatus === 'active') statusMatch = status === 'active';
                else if (['7', '14', '30'].includes(selectedStatus)) {
                    if (status === 'expired' || status === 'unknown') statusMatch = false;
                    else {
                        const expiry = String(r.expiryDate || '').slice(0, 10);
                        const days = Math.round((new Date(expiry + 'T00:00:00').getTime() - new Date(today + 'T00:00:00').getTime()) / 86400000);
                        statusMatch = days >= 0 && days <= parseInt(selectedStatus, 10);
                    }
                }
                const duplicateMatch = !duplicateOnlyFilter || duplicateStudentIds.has(String(r.id));
                return searchable && schoolMatch && educationMatch && categoryMatch && statusMatch && duplicateMatch;
            });
            const sortConfig = {
                name_asc: ['name', 1], name_desc: ['name', -1],
                school_asc: ['school', 1], school_desc: ['school', -1],
                date_joined_asc: ['dateJoined', 1], date_joined_desc: ['dateJoined', -1],
                expiry_date_asc: ['expiryDate', 1], expiry_date_desc: ['expiryDate', -1]
            };
            const config = sortConfig[sortBy] || sortConfig.name_asc;
            updateMasterSortIcons(sortBy);
            filtered.sort((a, b) => {
                const av = String(a[config[0]] || '');
                const bv = String(b[config[0]] || '');
                const result = ['dateJoined', 'expiryDate'].includes(config[0])
                    ? av.localeCompare(bv)
                    : av.localeCompare(bv, undefined, { sensitivity: 'base' });
                return result * config[1] || String(a.matric || '').localeCompare(String(b.matric || ''), undefined, { sensitivity: 'base' });
            });
            currentPage = 1;
            renderMasterTable(filtered);
            updatePillStyles(selectedStatus);
            updateActiveFilterAlert(selectedStatus, filtered.length);
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') closeStudentProfile();
        });

        function openCardModal(student) {
            document.getElementById('card-modal-name').innerText = student.name;
            document.getElementById('card-modal-matric').innerText = student.matric;
            document.getElementById('card-modal-school').innerText = student.school;
            document.getElementById('card-modal-uid').innerText = student.usmUid;
            document.getElementById('card-modal-le').innerText = convertUidToLittleEndian(student.usmUid);
            document.getElementById('card-modal-policy').innerText = student.policy || 'LAB_ALPHA';
            const joinedEl = document.getElementById('card-modal-joined');
            if (joinedEl) joinedEl.innerText = student.dateJoined || '-';
            const expiryEl = document.getElementById('card-modal-expiry');
            if (expiryEl) {
                const exp = String(student.expiryDate || '').slice(0, 10);
                if (exp) {
                    const today = new Date(); today.setHours(0,0,0,0);
                    const expD = new Date(exp + 'T00:00:00');
                    const diff = Math.round((expD.getTime() - today.getTime()) / 86400000);
                    expiryEl.innerText = exp + (diff < 0 ? ' (Tamat tempoh)' : (diff <= 30 ? ' (' + diff + ' hari lagi)' : ' (Aktif)'));
                } else {
                    expiryEl.innerText = '-';
                }
            }
            document.getElementById('student-card-modal').classList.remove('hidden');
        }

        function closeCardModal() { document.getElementById('student-card-modal').classList.add('hidden'); }

        // The QR decoder is ~375 KB and most sessions never scan a card, so it is
        // fetched the first time the scanner opens and then kept for the session.
        function loadScannerLibrary() {
            if (window.Html5Qrcode) return Promise.resolve();
            if (window.__usmScannerLoading) return window.__usmScannerLoading;
            window.__usmScannerLoading = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = 'assets/vendor/html5-qrcode-2.3.8.min.js';
                script.onload = () => { window.__usmScannerLoading = null; resolve(); };
                script.onerror = () => {
                    window.__usmScannerLoading = null;
                    reject(new Error('Pustaka pengimbas tidak dapat dimuatkan'));
                };
                document.head.appendChild(script);
            });
            return window.__usmScannerLoading;
        }

        async function openScannerModal() {
            document.getElementById('scanner-modal').classList.remove('hidden');
            const resultBox = document.getElementById('scan-result-container');
            try {
                await loadScannerLibrary();
            } catch (err) {
                resultBox.innerText = 'Pustaka pengimbas tidak dapat dimuatkan. Semak sambungan dan cuba lagi.';
                return;
            }
            html5QrCode = new Html5Qrcode("reader");
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText) => {
                    document.getElementById('scan-result-container').innerHTML = '<span class="text-emerald-600 font-bold">Berjaya Imbas: ' + escapeHtml(decodedText) + '</span>';
                    closeScannerModal();
                    let masterSearch = document.getElementById('master-search');
                    if(masterSearch) {
                        masterSearch.value = decodedText;
                        filterMasterTable();
                        navigateToDirectoryPage();
                    }
                },
                (errorMessage) => { }
            ).catch((err) => {
                document.getElementById('scan-result-container').innerText = 'Ralat kamera: Sila pastikan kebenaran kamera diberikan.';
            });
        }

        function closeScannerModal() {
            // stop() rejects, and on some browsers throws outright, when the camera
            // never started (permission denied). Hiding first means the operator can
            // always dismiss the dialog, whatever state the scanner is in.
            document.getElementById('scanner-modal').classList.add('hidden');
            if (!html5QrCode) return;
            try {
                const stopping = html5QrCode.stop();
                if (stopping && typeof stopping.then === 'function') {
                    stopping.then(() => html5QrCode.clear()).catch(() => {});
                }
            } catch (err) { /* scanner was not running */ }
        }

        async function logoutUSMSmart() {
            if (!confirm('Log out from USMSmart?')) return;
            try {
                const response = await fetch('index.php?action=logout', {method:'POST', body:new FormData()});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Logout failed');
                window.location.href = 'index.php';
            } catch (err) { showToast('Logout failed: ' + err.message); }
        }

        // ===== NEW: My Profile JS =====
        let currentProfileData = null;
        /* Two values every avatar surface needs: whose picture this is, and the version string that
           makes its URL cacheable. The id comes from the session render, the version from
           get_my_profile, so nothing here has to guess or re-query. */
        const currentUserId = (typeof USMSMART_USER_ID === 'number' ? USMSMART_USER_ID : 0);
        let currentAvatarVersion = '';
        const AVATAR_HINT = 'JPG atau PNG. Gambar dipotong dan dikecilkan dalam pelayar anda, jadi foto asal tidak pernah dihantar ke pelayan.';

        function openMyProfile() {
            const modal = document.getElementById('my-profile-modal');
            if (!modal) return;
            modal.classList.remove('hidden');
            switchProfileTab('info');
            loadMyProfile();
        }

        function closeMyProfile() {
            document.getElementById('my-profile-modal')?.classList.add('hidden');
            document.getElementById('profile-password-form')?.reset();
            document.getElementById('profile-info-form')?.reset();
            const avatarInput = document.getElementById('profile-avatar-input');
            if (avatarInput) avatarInput.value = '';
            setAvatarStatus(AVATAR_HINT);
            updatePasswordStrength('');
            updatePasswordMatch();
        }

        function switchProfileTab(tab) {
            const infoTab = document.getElementById('profile-tab-info');
            const passTab = document.getElementById('profile-tab-password');
            const infoForm = document.getElementById('profile-info-form');
            const passForm = document.getElementById('profile-password-form');
            if (!infoTab || !passTab || !infoForm || !passForm) return;
            const activeCls = "flex-1 px-3 py-2 rounded-lg font-medium transition bg-white dark:bg-zinc-800 text-slate-900 dark:text-white shadow-clean";
            const inactiveCls = "flex-1 px-3 py-2 rounded-lg text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white transition";
            if (tab === 'password') {
                infoTab.className = inactiveCls;
                passTab.className = activeCls;
                infoForm.classList.add('hidden');
                passForm.classList.remove('hidden');
            } else {
                infoTab.className = activeCls;
                passTab.className = inactiveCls;
                infoForm.classList.remove('hidden');
                passForm.classList.add('hidden');
            }
        }

        /* The avatar pipeline. GD is off in this server's php.ini, so the crop and the shrink happen
           here on a canvas: the original photo, its EXIF and its GPS never leave the browser, and the
           bytes the server accepts are always a 256px JPEG it did not have to decode-and-rescale. */
        const AVATAR_EDGE = 256;
        /* The app names its session after the per-tab token (index.php reads usm_tab or X-USM-Tab),
           and only the fetch wrapper adds that header automatically. An img tag is a plain browser
           request, so a picture URL has to carry the token itself or PHP answers from the boot session
           and the request is unauthenticated. */
        function avatarUrl(id, version) {
            if (!id || !version) return '';
            let url = 'index.php?action=my_avatar&id=' + encodeURIComponent(id) + '&v=' + encodeURIComponent(version);
            const tab = sessionStorage.getItem('usmsmart_tab_token') || new URLSearchParams(location.search).get('usm_tab') || '';
            return tab ? url + '&usm_tab=' + encodeURIComponent(tab) : url;
        }
        function paintAvatar(chip, url, name) {
            if (!chip) return;
            const img = chip.querySelector('img');
            const initials = chip.querySelector('span');
            const two = String(name || '').trim().slice(0, 2);
            if (initials) initials.textContent = two || '--';
            if (!img) return;
            if (url) {
                img.removeAttribute('hidden');
                chip.classList.add('has-picture');
                if (img.getAttribute('src') !== url) img.setAttribute('src', url);
            } else {
                img.setAttribute('hidden', '');
                img.removeAttribute('src');
                chip.classList.remove('has-picture');
            }
        }
        /* A 401 or a file deleted under us must fall back to the initials instead of painting the
           browser's broken-image icon inside a chip. Registered once, for every chip. */
        document.addEventListener('error', function (event) {
            const img = event.target;
            if (!img || img.tagName !== 'IMG') return;
            const chip = img.closest('.usm-avatar');
            if (!chip || !chip.classList.contains('has-picture')) return;
            chip.classList.remove('has-picture');
            img.setAttribute('hidden', '');
            img.removeAttribute('src');
        }, true);
        /* The chip inside the Info tab, from whatever the profile fetch last said. Kept next to the
           pipeline it depends on so the modal and the chrome can never disagree. */
        function paintProfileAvatar() {
            const chip = document.getElementById('profile-avatar-wrap');
            if (!chip) return;
            const who = currentProfileData || {};
            paintAvatar(chip, avatarUrl(currentUserId, currentAvatarVersion), who.display_name || who.username || '');
        }

        function readAvatarAsJpeg(file) {
            return new Promise(function (resolve, reject) {
                if (!file) { reject(new Error('Pilih satu fail imej.')); return; }
                if (typeof file.type !== 'string' || file.type.indexOf('image/') !== 0) { reject(new Error('Pilih fail JPG atau PNG.')); return; }
                if (file.size > 8 * 1024 * 1024) { reject(new Error('Imej terlalu besar (maksimum 8 MB).')); return; }
                const reader = new FileReader();
                reader.onerror = function () { reject(new Error('Fail tidak dapat dibaca.')); };
                reader.onload = function () {
                    const image = new Image();
                    image.onerror = function () { reject(new Error('Imey tidak dapat dimuatkan dari fail itu.')); };
                    image.onload = function () {
                        const side = Math.max(1, Math.min(image.naturalWidth || image.width, image.naturalHeight || image.height));
                        const canvas = document.createElement('canvas');
                        canvas.width = AVATAR_EDGE; canvas.height = AVATAR_EDGE;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) { reject(new Error('Pelayar ini tidak menyokong pemprosesan imej.')); return; }
                        /* White underneath: a transparent PNG turns opaque instead of black. */
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, AVATAR_EDGE, AVATAR_EDGE);
                        ctx.drawImage(image,
                            ((image.naturalWidth || image.width) - side) / 2,
                            ((image.naturalHeight || image.height) - side) / 2,
                            side, side, 0, 0, AVATAR_EDGE, AVATAR_EDGE);
                        resolve(canvas.toDataURL('image/jpeg', 0.86));
                    };
                    image.src = String(reader.result || '');
                };
                reader.readAsDataURL(file);
            });
        }
        async function postAvatar(action, body) {
            const res = await fetch('index.php?action=' + action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': (typeof USMSMART_CSRF_TOKEN !== 'undefined' ? USMSMART_CSRF_TOKEN : '') },
                body: JSON.stringify(body || {})
            });
            const result = await res.json();
            if (!res.ok || !result.success) throw new Error(result.message || 'Gambar tidak dapat disimpan.');
            return result;
        }
        function setAvatarStatus(text, tone) {
            const status = document.getElementById('profile-avatar-status');
            if (!status) return;
            status.classList.remove('busy', 'failed');
            if (tone) status.classList.add(tone);
            status.textContent = text;
        }
        /* Every surface that shows a picture listens for this instead of re-fetching the page. */
        function announceAvatar(avatarUpdatedAt) {
            document.dispatchEvent(new CustomEvent('usm-avatar-changed', { detail: { avatarUpdatedAt: String(avatarUpdatedAt || '') } }));
        }
        /* The rail and header chips were rendered by PHP at page load, so an upload has to repaint
           them too - otherwise the rail keeps a stale picture until the next reload. The message list
           and thread re-render from their own fetches, so they need nothing here. */
        document.addEventListener('usm-avatar-changed', function (event) {
            const version = String((event.detail && event.detail.avatarUpdatedAt) || '');
            currentAvatarVersion = version;
            const who = (currentProfileData && (currentProfileData.display_name || currentProfileData.username)) || '';
            paintAvatar(document.getElementById('usm-rail-avatar'), avatarUrl(currentUserId, version), who);
            paintAvatar(document.getElementById('usm-header-avatar'), avatarUrl(currentUserId, version), who);
        });

        function wireAvatarControls() {
            const input = document.getElementById('profile-avatar-input');
            const removeBtn = document.getElementById('profile-avatar-remove');
            if (!input || input.dataset.wired === '1') return;
            input.dataset.wired = '1';
            input.addEventListener('change', async function () {
                const file = input.files && input.files[0];
                if (!file) return;
                const pick = document.querySelector('.usm-avatar-pick');
                if (pick) pick.classList.add('is-busy');
                setAvatarStatus('Menyimpan gambar...', 'busy');
                try {
                    const dataUrl = await readAvatarAsJpeg(file);
                    const result = await postAvatar('update_my_avatar', { image: dataUrl });
                    /* Written through the module value so the chip, the chrome and a later reload all
                       use the version the server actually stored. */
                    currentAvatarVersion = String(result.avatar_updated_at || '');
                    paintProfileAvatar();
                    announceAvatar(currentAvatarVersion);
                    setAvatarStatus('Gambar profil dikemas kini.');
                } catch (err) {
                    setAvatarStatus(err.message, 'failed');
                } finally {
                    if (pick) pick.classList.remove('is-busy');
                    input.value = '';
                }
            });
            if (removeBtn) removeBtn.addEventListener('click', async function () {
                removeBtn.disabled = true;
                try {
                    const result = await postAvatar('remove_my_avatar', {});
                    currentAvatarVersion = '';
                    paintProfileAvatar();
                    announceAvatar('');
                    setAvatarStatus(result.message || 'Gambar profil dibuang.');
                } catch (err) {
                    setAvatarStatus(err.message, 'failed');
                } finally {
                    removeBtn.disabled = false;
                }
            });
        }

        async function loadMyProfile() {
            try {
                const res = await fetch('index.php?action=get_my_profile');
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.message || 'Profil tidak dapat dimuatkan.');
                currentProfileData = result.profile;
                const p = result.profile;
                document.getElementById('profile-username-display').textContent = p.username || '—';
                document.getElementById('profile-role-display').textContent = (p.role || '').toUpperCase() || '—';
                document.getElementById('profile-display-name').value = p.display_name || '';
                document.getElementById('profile-email').value = p.email || '';
                document.getElementById('profile-created-display').textContent = p.created_at || '—';
                document.getElementById('profile-lastlogin-display').textContent = p.last_login_at || 'Belum pernah';
                currentAvatarVersion = String(p.avatar_updated_at || '');
                wireAvatarControls();
                paintProfileAvatar();
            } catch (err) {
                showToast('Gagal memuatkan profil: ' + err.message);
            }
        }

        async function submitProfileInfo(event) {
            event.preventDefault();
            const btn = document.getElementById('profile-info-submit');
            const displayName = document.getElementById('profile-display-name').value.trim();
            const email = document.getElementById('profile-email').value.trim();
            btn.disabled = true;
            btn.querySelector('span').textContent = 'Menyimpan…';
            try {
                const res = await fetch('index.php?action=update_my_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ display_name: displayName, email: email })
                });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.message || 'Profil tidak dapat disimpan.');
                showToast(result.message);
                currentProfileData = Object.assign({}, currentProfileData, {
                    display_name: displayName || null,
                    email: email || null
                });
            } catch (err) {
                showToast('Gagal menyimpan profil: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.querySelector('span').textContent = 'Simpan Maklumat';
            }
        }

        async function submitProfilePassword(event) {
            event.preventDefault();
            const btn = document.getElementById('profile-password-submit');
            const current = document.getElementById('profile-current-password').value;
            const newPass = document.getElementById('profile-new-password').value;
            const confirmPass = document.getElementById('profile-confirm-password').value;
            if (newPass.length < 12) { showToast('Kata laluan baharu mesti sekurang-kurangnya 12 aksara.'); return; }
            if (newPass !== confirmPass) { showToast('Kata laluan baharu tidak sepadan.'); return; }
            if (!window.confirm('Tukar kata laluan akan melog keluar anda daripada semua peranti. Teruskan?')) return;
            btn.disabled = true;
            btn.querySelector('span').textContent = 'Menukar…';
            try {
                const res = await fetch('index.php?action=change_my_password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: current,
                        new_password: newPass,
                        confirm_password: confirmPass
                    })
                });
                const result = await res.json();
                if (!res.ok || !result.success) throw new Error(result.message || 'Kata laluan tidak dapat ditukar.');
                showToast(result.message);
                setTimeout(() => { window.location.href = 'index.php'; }, 1200);
            } catch (err) {
                showToast('Gagal menukar kata laluan: ' + err.message);
                btn.disabled = false;
                btn.querySelector('span').textContent = 'Tukar Kata Laluan';
            }
        }

        function updatePasswordStrength(val) {
            const bar = document.getElementById('profile-password-strength');
            const label = document.getElementById('profile-password-strength-label');
            if (!bar || !label) return;
            let score = 0;
            if (val.length >= 12) score++;
            if (val.length >= 16) score++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;
            const pct = Math.min(100, score * 20);
            const colors = ['#dc2626', '#f97316', '#eab308', '#22c55e', '#16a34a'];
            const labels = ['Lemah', 'Sederhana', 'Baik', 'Kuat', 'Sangat kuat'];
            const idx = Math.max(0, Math.min(4, score - 1));
            bar.style.width = pct + '%';
            bar.style.backgroundColor = val ? colors[idx] : '#94a3b8';
            label.textContent = val ? labels[idx] : 'Kekuatan kata laluan';
            label.style.color = val ? colors[idx] : '';
        }

        function updatePasswordMatch() {
            const newPass = document.getElementById('profile-new-password')?.value || '';
            const confirm = document.getElementById('profile-confirm-password')?.value || '';
            const hint = document.getElementById('profile-password-match');
            if (!hint) return;
            if (!confirm) { hint.textContent = ''; return; }
            if (newPass === confirm) {
                hint.textContent = '✓ Kata laluan sepadan';
                hint.style.color = '#16a34a';
            } else {
                hint.textContent = '✗ Kata laluan tidak sepadan';
                hint.style.color = '#dc2626';
            }
        }
        // ===== END My Profile JS =====

        let userManagementRows = [];

        function navigateToUserManagement() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses Admin diperlukan.'); return; }
            hideAllMainViews();
            document.getElementById('view-user-management').classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            loadUserManagement();
        }

        async function createUserAccount() {
            navigateToUserManagement();
            document.getElementById('new-user-username')?.focus();
        }

        async function submitCreateUser(event) {
            event.preventDefault();
            if (!USMSMART_IS_ADMIN) { showToast('Akses Admin diperlukan.'); return; }
            const form = document.getElementById('create-user-form');
            const submit = document.getElementById('create-user-submit');
            const data = new FormData(form);
            submit.disabled = true; submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Mencipta...';
            try {
                const response = await fetch('index.php?action=create_user', { method: 'POST', body: data });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Akaun tidak dapat dibuat');
                form.reset(); showToast(result.message || 'Akaun berjaya dibuat'); await loadUserManagement();
            } catch (err) { showToast('Gagal mencipta akaun: ' + err.message); }
            finally { submit.disabled = false; submit.innerHTML = '<i class="fa-solid fa-plus mr-1.5"></i>Cipta akaun'; }
        }

        async function loadUserManagement() {
            if (!USMSMART_IS_ADMIN) return;
            const tbody = document.getElementById('user-management-tbody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Memuatkan akaun...</td></tr>';
            try {
                const response = await fetch('index.php?action=list_users');
                const result = await response.json();
                if (!response.ok || !result.success || !Array.isArray(result.users)) throw new Error(result.message || 'Senarai pengguna tidak tersedia');
                userManagementRows = result.users;
                const active = userManagementRows.filter(u => Number(u.is_active) === 1).length;
                const admins = userManagementRows.filter(u => Number(u.is_active) === 1 && u.role === 'admin').length;
                document.getElementById('users-total-count').textContent = userManagementRows.length;
                document.getElementById('users-active-count').textContent = active;
                document.getElementById('users-admin-count').textContent = admins;
                filterUserManagementTable();
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-rose-600">' + escapeHtml(err.message) + '</td></tr>';
                document.getElementById('user-management-count').textContent = 'Gagal memuatkan senarai akaun.';
                showToast('Pengurusan pengguna gagal: ' + err.message);
            }
        }

        function filterUserManagementTable() {
            const tbody = document.getElementById('user-management-tbody');
            if (!tbody) return;
            const query = String(document.getElementById('user-management-search')?.value || '').trim().toLowerCase();
            const rows = userManagementRows.filter(u => String(u.username || '').toLowerCase().includes(query) || String(u.role || '').toLowerCase().includes(query) || String(u.id).includes(query));
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-500 dark:text-zinc-400">Tiada akaun sepadan dengan carian.</td></tr>';
            } else {
                tbody.innerHTML = rows.map(u => {
                    const active = Number(u.is_active) === 1;
                    const roleClass = u.role === 'admin' ? 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/50 dark:text-violet-300 dark:border-violet-900' : 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-zinc-800 dark:text-zinc-300 dark:border-zinc-700';
                    const statusClass = active ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900' : 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900';
                    const lastLogin = u.last_login_at ? escapeHtml(String(u.last_login_at)) : 'Belum pernah';
                    return '<tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/70">' +
                        '<td class="p-3.5"><div class="flex items-center gap-3"><span class="w-9 h-9 shrink-0 rounded-xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center"><i class="fa-solid fa-user"></i></span><span><span class="block font-semibold text-slate-900 dark:text-white">' + escapeHtml(String(u.username)) + '</span><span class="block mt-1 text-[10px] text-slate-400">ID #' + escapeHtml(String(u.id)) + '</span></span></div></td>' +
                        '<td class="p-3.5"><span class="inline-flex rounded-lg border px-2.5 py-1 text-[10px] font-bold uppercase ' + roleClass + '">' + escapeHtml(String(u.role)) + '</span></td>' +
                        '<td class="p-3.5"><span class="inline-flex rounded-lg border px-2.5 py-1 text-[10px] font-semibold ' + statusClass + '">' + (active ? 'Aktif' : 'Nyahaktif') + '</span></td>' +
                        '<td class="p-3.5 text-[11px] text-slate-500 dark:text-zinc-400 whitespace-nowrap">' + lastLogin + '</td>' +
                        '<td class="p-3.5"><div class="flex justify-end gap-1.5"><button type="button" onclick="manageUserFromTable(' + Number(u.id) + ',\'role\')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 hover:bg-slate-100 dark:hover:bg-zinc-800 text-[10px]" title="Tukar peranan"><i class="fa-solid fa-shield-halved"></i></button><button type="button" onclick="manageUserFromTable(' + Number(u.id) + ',\'password\')" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-zinc-700 hover:bg-slate-100 dark:hover:bg-zinc-800 text-[10px]" title="Set semula kata laluan"><i class="fa-solid fa-key"></i></button><button type="button" onclick="manageUserFromTable(' + Number(u.id) + ',\'status\')" class="px-2.5 py-1.5 rounded-lg border ' + (active ? 'border-rose-200 text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50 dark:border-emerald-900 dark:hover:bg-emerald-950/40') + ' text-[10px]" title="' + (active ? 'Nyahaktifkan akaun' : 'Aktifkan akaun') + '"><i class="fa-solid ' + (active ? 'fa-user-slash' : 'fa-user-check') + '"></i></button><button type="button" onclick="manageUserFromTable(' + Number(u.id) + ',\'delete\')" class="px-2.5 py-1.5 rounded-lg border border-rose-200 dark:border-rose-900 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-[10px]" title="Padam akaun"><i class="fa-solid fa-trash"></i></button></div></td></tr>';
                }).join('');
            }
            document.getElementById('user-management-count').textContent = 'Menunjukkan ' + rows.length + ' daripada ' + userManagementRows.length + ' akaun';
        }

        async function manageUserFromTable(id, operation) {
            if (!USMSMART_IS_ADMIN) { showToast('Akses Admin diperlukan.'); return; }
            const target = userManagementRows.find(u => Number(u.id) === Number(id));
            if (!target) { showToast('Akaun tidak ditemui.'); return; }
            const data = new FormData(); data.append('user_id', String(id));
            if (operation === 'role') {
                const nextRole = target.role === 'admin' ? 'viewer' : 'admin';
                if (!confirm('Tukar peranan ' + target.username + ' kepada ' + nextRole.toUpperCase() + '?')) return;
                data.append('operation', 'update_role'); data.append('role', nextRole);
            } else if (operation === 'password') {
                const password = window.prompt('Kata laluan baharu untuk ' + target.username + ' (minimum 12 aksara):');
                if (password === null) return;
                if (password.length < 12) { showToast('Kata laluan mesti sekurang-kurangnya 12 aksara.'); return; }
                data.append('operation', 'reset_password'); data.append('password', password);
            } else if (operation === 'status') {
                const enable = Number(target.is_active) !== 1;
                if (!confirm((enable ? 'Aktifkan' : 'Nyahaktifkan') + ' akaun ' + target.username + '?')) return;
                data.append('operation', 'set_active'); data.append('is_active', enable ? '1' : '0');
            } else if (operation === 'delete') {
                const typed = window.prompt('Padam akaun ' + target.username + ' secara kekal. Taip nama pengguna untuk mengesahkan:');
                if (typed === null) return;
                if (typed.trim().toLowerCase() !== String(target.username).toLowerCase()) { showToast('Nama pengguna tidak sepadan. Pemadaman dibatalkan.'); return; }
                data.append('operation', 'delete_user'); data.append('confirm_username', typed.trim());
            } else return;
            try {
                const response = await fetch('index.php?action=manage_user', { method: 'POST', body: data });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Kemas kini gagal');
                showToast(result.message || 'Akaun berjaya dikemas kini'); await loadUserManagement();
            } catch (err) { showToast('Gagal mengurus akaun: ' + err.message); }
        }

        // Audit statistics reuse the analytics card language and chart theme. Everything here is built
        // from the FILTERED log, so these figures describe the rows the list is showing — unlike the four
        // counters above, which get_audit_log_page has always computed over the whole table.
        const AUDIT_CATEGORY_META = {
            update: { label: 'Kemas kini', color: '#6366F1' },
            create: { label: 'Tambah rekod', color: '#10B981' },
            delete: { label: 'Pemadaman', color: '#F43F5E' },
            import: { label: 'Import CSV', color: '#14B8A6' },
            export: { label: 'Eksport / UDA', color: '#F59E0B' },
            session: { label: 'Sesi log masuk', color: '#8B5CF6' },
            backup: { label: 'Sandaran', color: '#06B6D4' },
            restore: { label: 'Pemulihan', color: '#A855F7' },
            other: { label: 'Lain-lain', color: '#94A3B8' }
        };
        const AUDIT_SERIES_ORDER = ['update', 'create', 'delete', 'import', 'export', 'session', 'backup', 'restore', 'other'];
        let auditStatsPayload = null;
        let auditStatsRange = 30;
        let auditDailyChartInstance = null;
        let auditActionChartInstance = null;
        let auditActorChartInstance = null;

        function auditCategoryMeta(category) {
            return AUDIT_CATEGORY_META[String(category || '')] || AUDIT_CATEGORY_META.other;
        }

        function auditActionLabel(action) {
            const text = String(action || '').replace(/_/g, ' ').trim();
            return text ? text.charAt(0).toUpperCase() + text.slice(1) : 'Lain-lain';
        }

        function auditDayLabel(day) {
            const parsed = new Date(String(day || '') + 'T00:00:00');
            return Number.isNaN(parsed.getTime()) ? String(day) : new Intl.DateTimeFormat('ms-MY', { day: '2-digit', month: 'short' }).format(parsed);
        }

        function auditNumber(value) {
            return new Intl.NumberFormat('ms-MY').format(Math.round(Number(value) || 0));
        }

        // Numbers tick up as the figures land. The final value is written first and the animation only
        // ever overwrites it, with a watchdog after it, so a backgrounded tab (no requestAnimationFrame),
        // reduced motion, or a renderer that stalls still reports the real figure — the flourish is never
        // the payload, and a screen reader never announces a counting placeholder.
        function usmCountUp(node, target) {
            if (!node) return;
            const value = Number(target) || 0;
            const final = auditNumber(value);
            node.textContent = final;
            if (value <= 0 || document.hidden || !window.requestAnimationFrame
                || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) return;
            setTimeout(() => { node.textContent = final; }, 780);
            const startedAt = performance.now();
            const step = (now) => {
                const progress = Math.min(1, (Number(now) - startedAt) / 620);
                node.textContent = auditNumber(value * (1 - Math.pow(1 - progress, 3)));
                if (progress < 1) requestAnimationFrame(step); else node.textContent = final;
            };
            requestAnimationFrame(step);
        }

        function destroyAuditStatsCharts() {
            [auditDailyChartInstance, auditActionChartInstance, auditActorChartInstance].forEach(instance => {
                if (instance) instance.destroy();
            });
            auditDailyChartInstance = null;
            auditActionChartInstance = null;
            auditActorChartInstance = null;
        }

        function auditStatsWindow() {
            const payload = auditStatsPayload;
            const days = payload && Array.isArray(payload.days) ? payload.days : [];
            if (!days.length) return { days: [], series: [] };
            const keep = Math.max(1, Math.min(365, Number(auditStatsRange) || 30));
            const start = Math.max(0, days.length - keep);
            const slice = (payload.series || []).map(item => ({
                category: item.category,
                data: (item.data || []).slice(start)
            }));
            return { days: days.slice(start), series: slice };
        }

        async function loadAuditStats() {
            const panel = document.getElementById('audit-stats-panel');
            if (!panel) return;
            if (!USMSMART_IS_ADMIN) { panel.hidden = true; return; }
            const caption = document.getElementById('audit-stats-caption');
            const params = new URLSearchParams({
                search: (document.getElementById('audit-search-input')?.value || '').trim(),
                actionFilter: document.getElementById('audit-action-filter')?.value || '',
                startDate: document.getElementById('audit-start-date')?.value || '',
                endDate: document.getElementById('audit-end-date')?.value || ''
            });
            try {
                const res = await fetch('index.php?action=get_audit_stats&' + params.toString(), { cache: 'no-store' });
                const result = await res.json();
                if (!res.ok || result.error) throw new Error(result.error || 'Statistik log tidak tersedia.');
                auditStatsPayload = result;
                panel.hidden = false;
                renderAuditStats();
                // The panel had no box while it was hidden, so the armed pass could not see it; check the
                // pending list again now that it does.
                usmRevealCheck();
            } catch (err) {
                auditStatsPayload = null;
                panel.hidden = true;
                destroyAuditStatsCharts();
                if (caption) caption.textContent = 'Statistik log tidak dapat dimuatkan.';
            }
        }

        function renderAuditStats() {
            const payload = auditStatsPayload;
            if (!payload) return;
            const summary = payload.summary || {};
            const total = Number(summary.total) || 0;
            const slice = auditStatsWindow();
            const inRange = slice.series.reduce((sum, item) => sum + (item.data || []).reduce((a, b) => a + (Number(b) || 0), 0), 0);

            const caption = document.getElementById('audit-stats-caption');
            if (caption) {
                caption.textContent = total
                    ? auditNumber(total) + ' peristiwa dalam tapisan ini · ' + (Number(summary.actions) || 0) + ' jenis tindakan · ' + (Number(summary.actors) || 0) + ' pengguna'
                    : 'Tiada peristiwa bagi tapisan ini.';
            }
            const rangeLabel = document.getElementById('audit-stats-range-label');
            if (rangeLabel) rangeLabel.textContent = String(slice.days.length);
            usmCountUp(document.getElementById('audit-stats-in-range'), inRange);
            usmCountUp(document.getElementById('audit-stats-today'), summary.today);
            usmCountUp(document.getElementById('audit-stats-week'), summary.week);
            usmCountUp(document.getElementById('audit-stats-actors'), summary.actors);

            const week = Number(summary.week) || 0;
            const previous = Number(summary.prev_week) || 0;
            const delta = document.getElementById('audit-stats-week-delta');
            if (delta) {
                if (!previous) {
                    delta.textContent = week ? 'Tiada data minggu lepas' : '—';
                    delta.className = 'usm-stats-delta is-flat';
                } else {
                    const change = ((week - previous) / previous) * 100;
                    const rounded = Math.abs(change) >= 10 ? Math.round(change) : Math.round(change * 10) / 10;
                    const rising = change > 0;
                    delta.textContent = (rising ? '+' : change < 0 ? '-' : '') + Math.abs(rounded) + '% minggu ke minggu';
                    delta.className = 'usm-stats-delta ' + (rising ? 'is-up' : change < 0 ? 'is-down' : 'is-flat');
                }
            }

            const theme = usmChartTheme();
            const common = commonChartOptions(theme);
            destroyAuditStatsCharts();

            const dailyCanvas = document.getElementById('auditDailyChart');
            if (dailyCanvas && slice.days.length) {
                const ctx = dailyCanvas.getContext('2d');
                const ordered = AUDIT_SERIES_ORDER
                    .map(category => slice.series.find(item => item.category === category))
                    .filter(Boolean);
                const datasets = ordered.map(item => {
                    const meta = auditCategoryMeta(item.category);
                    const wash = ctx.createLinearGradient(0, 0, 0, 240);
                    wash.addColorStop(0, meta.color + '73');
                    wash.addColorStop(1, meta.color + '0A');
                    return {
                        label: meta.label,
                        data: item.data,
                        borderColor: meta.color,
                        backgroundColor: wash,
                        borderWidth: 2,
                        fill: true,
                        tension: .35,
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHoverBorderWidth: 2,
                        pointHoverBackgroundColor: meta.color,
                        pointHoverBorderColor: theme.tooltipBg,
                        stack: 'events'
                    };
                });
                auditDailyChartInstance = new Chart(ctx, {
                    type: 'line',
                    data: { labels: slice.days.map(auditDayLabel), datasets },
                    options: {
                        ...common,
                        plugins: {
                            ...common.plugins,
                            legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                            tooltip: {
                                ...common.plugins.tooltip,
                                callbacks: {
                                    title: items => slice.days[items[0] ? items[0].dataIndex : 0] || '',
                                    label: context => ' ' + context.dataset.label + ': ' + context.raw + ' peristiwa'
                                }
                            }
                        },
                        scales: {
                            x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } },
                            y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } }
                        }
                    }
                });
            }

            const actionCanvas = document.getElementById('auditActionChart');
            const actions = Array.isArray(payload.actions) ? payload.actions : [];
            if (actionCanvas && actions.length) {
                const listed = actions.reduce((sum, item) => sum + (Number(item.total) || 0), 0);
                const remainder = Math.max(0, total - listed);
                const labels = actions.map(item => auditActionLabel(item.action));
                const values = actions.map(item => Number(item.total) || 0);
                const colors = actions.map(item => auditCategoryMeta(item.category).color);
                if (remainder > 0) { labels.push('Lain-lain'); values.push(remainder); colors.push('#CBD5E1'); }
                auditActionChartInstance = new Chart(actionCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: { labels, datasets: [{ data: values, backgroundColor: colors, borderColor: theme.border, borderWidth: 3, hoverOffset: 7, spacing: 2 }] },
                    options: {
                        ...common,
                        cutout: '62%',
                        onClick: (event, elements) => {
                            if (!elements.length) return;
                            if (elements[0].index >= actions.length) { auditDrillAction(''); return; }
                            auditDrillAction(actions[elements[0].index].action);
                        },
                        onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                        plugins: {
                            ...common.plugins,
                            legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 12 } },
                            tooltip: {
                                ...common.plugins.tooltip,
                                callbacks: {
                                    title: items => actions[items[0] ? items[0].dataIndex : 0] ? actions[items[0].dataIndex].action : 'Lain-lain',
                                    label: context => {
                                        const value = Number(context.raw) || 0;
                                        return ' ' + value + ' peristiwa (' + (total ? ((value / total) * 100).toFixed(1) : '0.0') + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }

            const actorCanvas = document.getElementById('auditActorChart');
            const actors = Array.isArray(payload.actors) ? payload.actors : [];
            if (actorCanvas && actors.length) {
                auditActorChartInstance = new Chart(actorCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: actors.map(item => item.actor),
                        datasets: [{
                            label: 'Peristiwa',
                            data: actors.map(item => Number(item.total) || 0),
                            backgroundColor: actors.map((_, index) => ANALYTICS_COLORS[index % ANALYTICS_COLORS.length] + 'D9'),
                            borderColor: actors.map((_, index) => ANALYTICS_COLORS[index % ANALYTICS_COLORS.length]),
                            borderWidth: 1,
                            borderRadius: 7,
                            borderSkipped: false,
                            maxBarThickness: 20
                        }]
                    },
                    options: {
                        ...common,
                        indexAxis: 'y',
                        animation: { ...common.animation, delay: (context) => context.mode === 'default' ? context.dataIndex * 45 : 0 },
                        onClick: (event, elements) => {
                            if (!elements.length) return;
                            auditDrillActor(actors[elements[0].index] ? actors[elements[0].index].actor : '');
                        },
                        onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                        plugins: { ...common.plugins, legend: { display: false }, tooltip: { ...common.plugins.tooltip, callbacks: { title: items => items[0] ? items[0].label : '', label: context => ' ' + context.raw + ' peristiwa' } } },
                        scales: {
                            x: { beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 6, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } },
                            y: { grid: { display: false }, border: { display: false }, ticks: { color: theme.text, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 }, crossAlign: 'far' } }
                        }
                    }
                });
            }
        }

        function setAuditStatsRange(days, button) {
            auditStatsRange = Math.max(1, Number(days) || 30);
            document.querySelectorAll('#audit-stats-panel .usm-stats-range').forEach(btn => btn.classList.toggle('is-on', btn === button));
            if (auditStatsPayload) renderAuditStats();
        }

        function auditDrillAction(action) {
            const value = String(action || '');
            const select = document.getElementById('audit-action-filter');
            if (select && value) {
                // The select lists the common actions; the log can hold any token. Without an option for
                // it, quickFilterAuditAction() lands back on "Semua Tindakan" and the list is unfiltered.
                let found = false;
                for (let i = 0; i < select.options.length; i++) {
                    if (select.options[i].value === value) { select.selectedIndex = i; found = true; break; }
                }
                if (!found) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = 'Carta: ' + auditActionLabel(value);
                    select.appendChild(option);
                    select.value = value;
                }
            }
            quickFilterAuditAction(value);
            showToast(value ? 'Menapis statistik log: ' + auditActionLabel(value) : 'Menunjukkan semua tindakan');
        }

        function auditDrillActor(actor) {
            const value = String(actor || '').trim();
            if (value === '(tanpa nama)') { showToast('Rekod tanpa nama pengguna tidak boleh dicari.'); return; }
            const search = document.getElementById('audit-search-input');
            if (search) search.value = value;
            loadAuditHistoryPage(1);
            showToast(value ? 'Menapis log pengguna: ' + value : 'Menunjukkan semua pengguna');
        }

        async function loadAuditHistoryPage(page = 1) {
            auditCurrentPage = Math.max(1, parseInt(page, 10) || 1);
            const tbody = document.getElementById('audit-log-tbody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Memuatkan sejarah rekod...</td></tr>';
            }
            const search = (document.getElementById('audit-search-input')?.value || '').trim();
            const actionFilter = document.getElementById('audit-action-filter')?.value || '';
            const startDate = document.getElementById('audit-start-date')?.value || '';
            const endDate = document.getElementById('audit-end-date')?.value || '';

            const params = new URLSearchParams({
                page: String(auditCurrentPage),
                perPage: String(auditPerPage),
                search: search,
                actionFilter: actionFilter,
                startDate: startDate,
                endDate: endDate
            });

            try {
                const res = await fetch('index.php?action=get_audit_log_page&' + params.toString());
                const result = await res.json();
                if (!res.ok || result.error) throw new Error(result.error || 'Gagal memuatkan rekod');

                const data = result.data || [];
                data.forEach(row => { if (row && row.id !== undefined) auditRowsById[String(row.id)] = row; });
                const total = result.total || 0;
                auditTotalPages = result.totalPages || 1;

                // Update summary stats
                if (result.stats) {
                    const setText = (id, val) => { usmCountUp(document.getElementById(id), val); };
                    setText('audit-stat-total', result.stats.total ?? total);
                    setText('audit-stat-updates', result.stats.updates ?? 0);
                    setText('audit-stat-inserts', result.stats.inserts ?? 0);
                    setText('audit-stat-deletes', result.stats.deletes ?? 0);
                }

                // Render table rows
                if (!tbody) return;
                if (!data.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-slate-400">Tiada log sejarah rekod dijumpai bagi tapisan semasa.</td></tr>';
                } else {
                    tbody.innerHTML = data.map(r => renderAuditRowHtml(r)).join('');
                }

                // Update pagination
                const startIdx = total === 0 ? 0 : (auditCurrentPage - 1) * auditPerPage + 1;
                const endIdx = Math.min(total, auditCurrentPage * auditPerPage);
                const infoEl = document.getElementById('audit-pagination-info');
                if (infoEl) infoEl.textContent = 'Menunjukkan ' + startIdx + ' - ' + endIdx + ' daripada ' + total + ' rekod';

                const indEl = document.getElementById('audit-page-indicator');
                if (indEl) indEl.textContent = auditCurrentPage + ' / ' + auditTotalPages;

                const prevBtn = document.getElementById('audit-prev-page-btn');
                if (prevBtn) prevBtn.disabled = (auditCurrentPage <= 1);
                const nextBtn = document.getElementById('audit-next-page-btn');
                if (nextBtn) nextBtn.disabled = (auditCurrentPage >= auditTotalPages);

                // Filters reach the charts through the same call that re-reads the list, so a filter
                // change and a chart click can never show two different pictures. Page turns skip it.
                if (auditCurrentPage === 1) loadAuditStats();

            } catch (err) {
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="5" class="p-8 text-center text-rose-500">Ralat memuatkan log: ' + escapeHtml(err.message) + '</td></tr>';
                }
                showToast('Ralat log: ' + err.message);
            }
        }

        function getActionBadgeHtml(action) {
            const act = String(action || '').toLowerCase();
            if (act === 'update') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-950/70 dark:text-blue-300"><i class="fa-solid fa-pen-to-square text-[9px]"></i> KEMASKINI</span>';
            } else if (act === 'create' || act === 'save_student') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-300"><i class="fa-solid fa-user-plus text-[9px]"></i> TAMBAH</span>';
            } else if (act === 'delete' || act === 'delete_user' || act === 'soft_delete_session') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-300"><i class="fa-solid fa-trash-can text-[9px]"></i> PADAM</span>';
            } else if (act === 'import_csv') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-teal-100 text-teal-800 dark:bg-teal-950/70 dark:text-teal-300"><i class="fa-solid fa-file-arrow-up text-[9px]"></i> IMPORT CSV</span>';
            } else if (act === 'export_csv' || act === 'export_audit_csv') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-indigo-100 text-indigo-800 dark:bg-indigo-950/70 dark:text-indigo-300"><i class="fa-solid fa-file-arrow-down text-[9px]"></i> EKSPORT CSV</span>';
            } else if (act === 'dump_uda') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-300"><i class="fa-solid fa-code-merge text-[9px]"></i> SIMPAN UDA</span>';
            } else if (act === 'uda_ccadmin_push') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-violet-100 text-violet-800 dark:bg-violet-950/70 dark:text-violet-300"><i class="fa-solid fa-id-card text-[9px]"></i> CCADMIN</span>';
            } else if (act.includes('backup')) {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-300"><i class="fa-solid fa-database text-[9px]"></i> SANDARAN</span>';
            } else if (act.includes('restore')) {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-purple-100 text-purple-800 dark:bg-purple-950/70 dark:text-purple-300"><i class="fa-solid fa-rotate-left text-[9px]"></i> PULIHKAN</span>';
            } else if (act === 'login') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-emerald-800 dark:bg-zinc-800 dark:text-emerald-300"><i class="fa-solid fa-right-to-bracket text-[9px]"></i> LOGIN</span>';
            } else if (act === 'logout') {
                return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-400"><i class="fa-solid fa-right-from-bracket text-[9px]"></i> LOGOUT</span>';
            }
            return '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-300">' + escapeHtml(act.toUpperCase()) + '</span>';
        }

        function renderAuditRowDetailsHtml(detailsStr) {
            if (!detailsStr) return '<span class="text-slate-400 text-xs italic">Tiada perincian perubahan.</span>';
            try {
                const parsed = JSON.parse(detailsStr);
                if (parsed && parsed.type === 'record_update' && parsed.changes) {
                    const entries = Object.entries(parsed.changes);
                    if (!entries.length) return '<span class="text-slate-600 dark:text-zinc-300 text-xs">' + escapeHtml(parsed.summary || 'Rekod dikemas kini tanpa perubahan medan.') + '</span>';
                    const changeBadges = entries.map(([field, c]) => {
                        return '<div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md bg-slate-100 dark:bg-zinc-800/80 border border-slate-200 dark:border-zinc-700 text-[11px]">' +
                            '<span class="font-bold text-slate-700 dark:text-zinc-200">' + escapeHtml(field) + ':</span> ' +
                            '<span class="text-rose-600 dark:text-rose-400 line-through">' + escapeHtml(c.before || '(kosong)') + '</span> ' +
                            '<i class="fa-solid fa-arrow-right text-[9px] text-slate-400"></i> ' +
                            '<span class="text-emerald-600 dark:text-emerald-400 font-semibold">' + escapeHtml(c.after || '(kosong)') + '</span>' +
                        '</div>';
                    }).join(' ');
                    return '<div class="flex flex-wrap gap-1.5">' + changeBadges + '</div>';
                }
            } catch (_) {}
            return '<span class="text-slate-700 dark:text-zinc-300 text-xs">' + escapeHtml(detailsStr) + '</span>';
        }

        function renderAuditRowHtml(r) {
            const timeStr = escapeHtml(r.created_at || '-');
            const entityStr = (r.matric ? '<span class="font-bold text-indigo-600 dark:text-indigo-400">' + escapeHtml(r.matric) + '</span>' : '') +
                             (r.entity_id ? ' <span class="text-slate-400 text-[10px]">(#' + escapeHtml(String(r.entity_id)) + ')</span>' : '') +
                             (r.entity_type && !r.matric ? '<span class="text-slate-600 dark:text-zinc-300 text-xs font-semibold capitalize">' + escapeHtml(r.entity_type) + '</span>' : '');

            return '<tr class="hover:bg-slate-50/70 dark:hover:bg-zinc-900/60 transition-colors">' +
                '<td class="p-3.5 whitespace-nowrap text-slate-500 dark:text-zinc-400 font-mono text-[11px]"><i class="fa-regular fa-clock mr-1 text-[10px]"></i>' + timeStr + '</td>' +
                '<td class="p-3.5 whitespace-nowrap">' + getActionBadgeHtml(r.action) + '</td>' +
                '<td class="p-3.5 font-mono">' + (entityStr || '<span class="text-slate-400">-</span>') + '</td>' +
                '<td class="p-3.5 whitespace-nowrap"><span class="inline-flex items-center gap-1 font-medium text-slate-800 dark:text-zinc-200"><i class="fa-solid fa-user-shield text-[10px] text-violet-500"></i>' + escapeHtml(r.actor || 'system') + '</span></td>' +
                '<td class="p-3.5"><div class="space-y-2">' + renderAuditRowDetailsHtml(r.details) + '<button type="button" onclick="openAuditDetail(' + Number(r.id) + ')" class="inline-flex items-center gap-1 text-[10px] font-semibold text-violet-600 dark:text-violet-400 hover:underline"><i class="fa-solid fa-arrow-up-right-from-square"></i> Lihat butiran penuh</button></div></td>' +
            '</tr>';
        }

        function openAuditDetail(id) {
            const r = auditRowsById[String(id)];
            if (!r) { showToast('Butiran audit tidak ditemui. Muat semula senarai.'); return; }
            const host = document.getElementById('audit-detail-content');
            const modal = document.getElementById('audit-detail-modal');
            if (!host || !modal) return;
            const field = (label, value) => '<div class="rounded-xl border border-slate-200 dark:border-zinc-800 p-3"><div class="text-[10px] uppercase tracking-wider text-slate-500 dark:text-zinc-400 font-bold mb-1">' + label + '</div><div class="text-sm text-slate-900 dark:text-zinc-100 break-words">' + escapeHtml(value == null || value === '' ? '—' : String(value)) + '</div></div>';
            let detailContent = '';
            try {
                const parsed = JSON.parse(r.details || '');
                if (parsed && typeof parsed === 'object') detailContent = '<pre class="whitespace-pre-wrap break-words text-xs bg-slate-50 dark:bg-zinc-900 rounded-xl border border-slate-200 dark:border-zinc-800 p-4">' + escapeHtml(JSON.stringify(parsed, null, 2)) + '</pre>';
            } catch (_) {}
            if (!detailContent) detailContent = '<div class="whitespace-pre-wrap break-words text-sm text-slate-700 dark:text-zinc-300 rounded-xl border border-slate-200 dark:border-zinc-800 p-4">' + escapeHtml(r.details || 'Tiada perincian tambahan direkodkan.') + '</div>';
            host.innerHTML = '<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">' + field('ID Audit', r.id) + field('Tarikh & Masa', r.created_at) + field('Tindakan', r.action) + field('Jenis Entiti', r.entity_type) + field('ID Entiti', r.entity_id) + field('No. Matrik', r.matric) + field('Pengguna', r.actor || 'system') + '</div><div><div class="text-xs font-bold text-slate-600 dark:text-zinc-300 mb-2">Perincian penuh</div>' + detailContent + '</div>';
            modal.classList.remove('hidden'); modal.classList.add('flex');
            document.addEventListener('keydown', auditDetailEscapeHandler);
        }
        function auditDetailEscapeHandler(event) { if (event.key === 'Escape') closeAuditDetail(); }
        function closeAuditDetail() {
            const modal = document.getElementById('audit-detail-modal');
            if (modal) { modal.classList.add('hidden'); modal.classList.remove('flex'); }
            document.removeEventListener('keydown', auditDetailEscapeHandler);
        }

        function debounceAuditSearch() {
            clearTimeout(auditSearchDebounceTimer);
            auditSearchDebounceTimer = setTimeout(() => {
                loadAuditHistoryPage(1);
            }, 300);
        }

        function resetAuditFilters() {
            const s = document.getElementById('audit-search-input'); if (s) s.value = '';
            const a = document.getElementById('audit-action-filter'); if (a) a.value = '';
            const d1 = document.getElementById('audit-start-date'); if (d1) d1.value = '';
            const d2 = document.getElementById('audit-end-date'); if (d2) d2.value = '';
            loadAuditHistoryPage(1);
        }

        function quickFilterAuditAction(actionVal) {
            const a = document.getElementById('audit-action-filter');
            if (a) {
                let found = false;
                for (let i = 0; i < a.options.length; i++) {
                    if (a.options[i].value === actionVal) { a.selectedIndex = i; found = true; break; }
                }
                if (!found) a.value = actionVal;
            }
            loadAuditHistoryPage(1);
        }

        function changeAuditPageSize() {
            const select = document.getElementById('audit-page-size');
            if (select) {
                auditPerPage = parseInt(select.value, 10) || 20;
                loadAuditHistoryPage(1);
            }
        }

        function changeAuditPage(direction) {
            const targetPage = auditCurrentPage + direction;
            if (targetPage >= 1 && targetPage <= auditTotalPages) {
                loadAuditHistoryPage(targetPage);
            }
        }

        function exportAuditLogCSV() {
            window.location.href = 'index.php?action=export_audit_csv';
        }

        function viewAuditHistory() {
            navigateToAuditHistory('directory');
        }

        async function manageServerBackups() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses ditolak — hanya Admin boleh mengurus sandaran.'); return; }
            try {
                const response = await fetch('index.php?action=backup_status');
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Status sandaran tidak tersedia.');
                const list = (result.backups || []).map((b, i) => `${i + 1}. ${b.file}\n   ${b.created_at} | ${(b.size / 1024).toFixed(1)} KB | ${b.automatic ? 'Automatik' : 'Manual'}`).join('\n\n');
                const promptText = `SANDARAN PELAYAN\nAutomatik: sandaran harian pada permintaan pertama selepas tarikh baharu. Simpanan: 14 hari. Fail baharu disahkan dengan struktur JSON, bilangan rekod dan checksum SHA-256.\n\n${list || 'Belum ada sandaran tersimpan.'}\n\nOK untuk cipta sandaran manual sekarang; Cancel untuk tutup.`;
                if (confirm(promptText)) {
                    const response2 = await fetch('index.php?action=create_server_backup', {method:'POST'});
                    const result2 = await response2.json();
                    if (!response2.ok || !result2.success) throw new Error(result2.message || 'Sandaran gagal.');
                    showToast(result2.message + ' Fail: ' + result2.backup.file);
                    if (confirm('Sandaran telah disimpan pada pelayan. Muat turun salinan juga?')) {
                        window.location.href = 'index.php?action=download_server_backup&file=' + encodeURIComponent(result2.backup.file);
                    }
                }
            } catch (err) { showToast('Ralat sandaran: ' + err.message); }
        }

        function triggerDatabaseBackup() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses ditolak — hanya Admin boleh membuat sandaran pangkalan data.'); return; }
            showToast('Menjana fail sandaran pangkalan data...');
            window.location.href = 'index.php?action=backup_database';
        }

        async function uploadRestoreDatabase(inputElement) {
            if (!USMSMART_IS_ADMIN) { showToast('Akses ditolak — hanya Admin boleh memulihkan sandaran.'); inputElement.value = ''; return; }
            const file = inputElement.files[0];
            if (!file) return;
            if (!/\.json$/i.test(file.name) || file.size > 15 * 1024 * 1024) { showToast('Pilih fail sandaran .JSON yang sah (maksimum 15 MB).'); inputElement.value = ''; return; }
            try {
                showToast('Memeriksa fail sandaran dahulu...');
                const preflightData = new FormData(); preflightData.append('backup_file', file);
                const preflightResponse = await fetch('index.php?action=validate_restore_backup', {method:'POST', body:preflightData, headers:{'X-CSRF-Token':USMSMART_CSRF_TOKEN,'Accept':'application/json'}, credentials:'same-origin'});
                const preflight = await preflightResponse.json();
                if (!preflightResponse.ok || !preflight.success) throw new Error(preflight.message || 'Pemeriksaan fail sandaran gagal.');
                const c = preflight.counts || {};
                const summary = `\n\nRekod yang ditemui:\n• Pelajar: ${c.students ?? 0}\n• UDA: ${c.uda_records ?? 0}\n• Sesi pengangkutan: ${c.sessions ?? 0}\n• Audit dalam fail: ${c.audit_events ?? 0}\n\nPemeriksaan awal lulus, tetapi ini BUKAN ujian pemulihan sebenar. Pemulihan akan menggantikan rekod pelajar/UDA/sesi semasa. Teruskan hanya jika anda mempunyai sandaran semasa.`;
                if (!confirm('AMARAN: Pulihkan pangkalan data daripada fail ini?' + summary)) { inputElement.value = ''; return; }
                const formData = new FormData(); formData.append('backup_file', file);
                showToast('Sedang memulihkan pangkalan data...');
                const response = await fetch('index.php?action=restore_database', {method:'POST', body:formData, headers:{'X-CSRF-Token':USMSMART_CSRF_TOKEN,'Accept':'application/json'}, credentials:'same-origin'});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Pemulihan gagal.');
                showToast(result.message); inputElement.value = ''; loadMasterStudents(); loadSessionHistory();
            } catch (err) {
                showToast('Sandaran/pemulihan: ' + (err.message || 'Ralat tidak diketahui'));
                inputElement.value = '';
            }
        }

        function downloadMasterCSV() {
            if (!USMSMART_IS_ADMIN) { showToast('Akses ditolak — hanya Admin boleh mengeksport CSV.'); return; }
            window.location.href = 'index.php?action=export_csv';
        }

        let csvImportFile = null;
        let csvImportPreview = null;
        function closeCsvImportModal() {
            document.getElementById('csv-import-modal')?.classList.add('hidden');
            csvImportFile = null; csvImportPreview = null;
            const input = document.getElementById('csv-file-input'); if (input) input.value = '';
        }
        function renderCsvImportPreview(result) {
            csvImportPreview = result;
            document.getElementById('csv-import-subtitle').textContent = result.filename + ' — semak baris sah dan ralat sebelum import.';
            document.getElementById('csv-stat-total').textContent = 'Jumlah: ' + result.total;
            document.getElementById('csv-stat-valid').textContent = 'Sedia: ' + result.valid;
            document.getElementById('csv-stat-errors').textContent = 'Ralat: ' + result.errors;
            document.getElementById('csv-download-errors').disabled = !result.errors;
            document.getElementById('csv-commit-button').disabled = !result.valid;
            const esc = value => String(value == null ? '' : value).replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
            const rows = result.rows.slice(0,250).map(r => '<tr class="border-t border-slate-100 dark:border-zinc-800"><td class="p-2 whitespace-nowrap">'+r.line+'</td><td class="p-2">'+esc(r.name)+'</td><td class="p-2">'+esc(r.matric)+'</td><td class="p-2">'+esc(r.uid)+'</td><td class="p-2">'+esc(r.serial)+'</td><td class="p-2 min-w-48"><span class="font-medium '+(r.status==='ready'?'text-emerald-700':'text-rose-700')+'">'+(r.status==='ready'?'Sedia':'Ralat')+'</span>'+(r.issues.length?'<div class="text-xs text-rose-700 mt-1">'+r.issues.map(esc).join('; ')+'</div>':'')+'</td></tr>').join('');
            document.getElementById('csv-import-preview').innerHTML = '<p class="text-xs text-slate-500 mb-2">Paparan maksimum 250 baris. Laporan ralat memuatkan semua baris bermasalah.</p><table class="w-full text-xs text-left"><thead class="sticky top-0 bg-slate-50 dark:bg-zinc-800"><tr><th class="p-2">Baris</th><th class="p-2">Nama</th><th class="p-2">Matrik</th><th class="p-2">UID</th><th class="p-2">Siri</th><th class="p-2">Status / sebab</th></tr></thead><tbody>'+rows+'</tbody></table>';
        }
        async function uploadCsvStudents(inputElement) {
            if (!USMSMART_IS_ADMIN) { showToast('Akses ditolak — hanya Admin boleh mengimport CSV.'); inputElement.value = ''; return; }
            const file = inputElement.files[0]; if (!file) return;
            if (!file.name.toLowerCase().endsWith('.csv') || file.size > 5 * 1024 * 1024) { showToast('Pilih fail .csv yang tidak melebihi 5 MB.'); inputElement.value=''; return; }
            csvImportFile = file;
            const formData = new FormData(); formData.append('csv_file', file); formData.append('mode','preview');
            try {
                showToast('Menganalisis CSV dan mengesan pendua...');
                const res = await fetch('index.php?action=import_csv', {method:'POST', body:formData, headers:{'X-CSRF-Token':USMSMART_CSRF_TOKEN}});
                const result = await res.json(); if (!res.ok || !result.success) throw new Error(result.message || 'Pratonton gagal.');
                renderCsvImportPreview(result); document.getElementById('csv-import-modal').classList.remove('hidden');
            } catch (err) { showToast('Ralat pratonton CSV: '+err.message); inputElement.value=''; csvImportFile=null; }
        }
        function downloadCsvImportErrors() {
            if (!csvImportPreview) return;
            const bad = csvImportPreview.rows.filter(r => r.status !== 'ready'); if (!bad.length) { showToast('Tiada ralat untuk dimuat turun.'); return; }
            const cell = v => '"'+String(v == null ? '' : v).replace(/^[=+@\-\t\r]/,"'$&").replace(/"/g,'""')+'"';
            const lines = [['CSV row','Name','Matric','School','UID','Serial Number','Policy','Date Joined','Expiry Date','Errors'], ...bad.map(r=>[r.line,r.name,r.matric,r.school,r.uid,r.serial,r.policy,r.joined,r.expiry,r.issues.join('; ')])];
            const blob = new Blob(['\uFEFF'+lines.map(row=>row.map(cell).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8;'});
            const url=URL.createObjectURL(blob), a=document.createElement('a'); a.href=url; a.download='usmsmart_csv_import_errors_'+new Date().toISOString().slice(0,10)+'.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
        }
        async function commitCsvImport() {
            if (!csvImportFile || !csvImportPreview || !csvImportPreview.valid) return;
            if (!confirm('Import '+csvImportPreview.valid+' baris sah? Baris ralat akan dilangkau.')) return;
            const button=document.getElementById('csv-commit-button'); button.disabled=true; button.textContent='Mengimport...';
            try {
                const formData=new FormData(); formData.append('csv_file',csvImportFile); formData.append('mode','commit');
                const res=await fetch('index.php?action=import_csv',{method:'POST',body:formData,headers:{'X-CSRF-Token':USMSMART_CSRF_TOKEN}});
                const result=await res.json(); if(!res.ok || !result.success) throw new Error(result.message || 'Import gagal.');
                closeCsvImportModal(); showToast(result.message); loadMasterStudents();
            } catch(err) { showToast('Ralat import CSV: '+err.message); button.disabled=false; button.textContent='Cuba import semula'; }
        }

        function setLivePullStatus(state, title, detail) {
            const box = document.getElementById('live-pull-status');
            const titleEl = document.getElementById('live-pull-status-title');
            const detailEl = document.getElementById('live-pull-status-detail');
            if (titleEl) titleEl.textContent = title;
            if (detailEl) detailEl.textContent = detail;
            if (box) {
                box.className = 'rounded-xl border p-3 ' + (state === 'error' ? 'border-rose-200 dark:border-rose-900/60 bg-rose-50/70 dark:bg-rose-950/20' : state === 'loading' ? 'border-indigo-200 dark:border-indigo-900/60 bg-indigo-50/70 dark:bg-indigo-950/20' : state === 'success' ? 'border-emerald-200 dark:border-emerald-900/60 bg-emerald-50/70 dark:bg-emerald-950/20' : 'border-slate-200 dark:border-zinc-800 bg-slate-50/70 dark:bg-zinc-900/50');
            }
        }

        function setLiveDateRange(range) {
            const now = new Date();
            const localIso = d => { const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), day=String(d.getDate()).padStart(2,'0'); return `${y}-${m}-${day}`; };
            const start = new Date(now.getFullYear(), range === 'month' ? now.getMonth() : 0, 1);
            const from = document.getElementById('start-date'), to = document.getElementById('end-date');
            if (from) from.value = localIso(start);
            if (to) to.value = localIso(now);
        }

        function resetLiveFilters() {
            const search = document.getElementById('uda-live-search');
            const status = document.getElementById('uda-live-status');
            const school = document.getElementById('uda-live-school');
            if (search) search.value = '';
            if (status) status.value = '';
            if (school) school.value = '';
            setLiveDateRange('year');
            setLivePullStatus('idle', 'Penapis dikosongkan', 'Semak julat tarikh dan tekan “Tarik untuk Transporter UDA” untuk memuatkan semula data.');
        }

        function renderLivePullSummary(records) {
            const summary = document.getElementById('live-pull-summary');
            if (!summary) return;
            summary.classList.remove('hidden');
            const total = records.length;
            const missingPolicy = records.filter(r => !String(r.policy || '').trim()).length;
            const invalidUid = records.filter(r => !/^(?:[0-9A-F]{2})+$/i.test(String(r.usmUid || '').replace(/\s+/g, ''))).length;
            const invalidSerial = records.filter(r => String(r.serialNo || '').trim() && !/^[A-Z0-9][A-Z0-9 ._\/-]{0,99}$/i.test(String(r.serialNo).trim())).length;
            const issueCount = records.filter(r => !String(r.policy || '').trim() || !/^(?:[0-9A-F]{2})+$/i.test(String(r.usmUid || '').replace(/\s+/g, '')) || (String(r.serialNo || '').trim() && !/^[A-Z0-9][A-Z0-9 ._\/-]{0,99}$/i.test(String(r.serialNo).trim()))).length;
            const ready = Math.max(0, total - issueCount);
            document.getElementById('live-summary-total').textContent = total;
            document.getElementById('live-summary-ready').textContent = ready;
            document.getElementById('live-summary-policy').textContent = missingPolicy;
            document.getElementById('live-summary-invalid').textContent = records.filter(r => !/^(?:[0-9A-F]{2})+$/i.test(String(r.usmUid || '').replace(/\s+/g, '')) || (String(r.serialNo || '').trim() && !/^[A-Z0-9][A-Z0-9 ._\/-]{0,99}$/i.test(String(r.serialNo).trim()))).length;
        }

        async function readFromUSMSmart() {
            let startDate = document.getElementById('start-date').value;
            let endDate = document.getElementById('end-date').value;
            const recordLimitInput = document.getElementById('uda-live-record-limit');
            const recordLimit = Number(recordLimitInput?.value);
            if (!Number.isSafeInteger(recordLimit) || recordLimit < 1) { setLivePullStatus('error', 'Bilangan rekod tidak sah', 'Masukkan nombor bulat yang lebih besar daripada sifar.'); showToast('Masukkan bilangan rekod yang sah.'); recordLimitInput?.focus(); return; }
            if (!startDate || !endDate || startDate > endDate) { setLivePullStatus('error', 'Julat tarikh tidak sah', 'Pilih tarikh mula dan tarikh tamat yang sah.'); showToast('Pilih julat tarikh yang sah.'); return; }
            const triggerButton = document.querySelector('button[onclick="readFromUSMSmart()"]');
            if (triggerButton) { triggerButton.disabled = true; triggerButton.classList.add('opacity-60'); }
            setLivePullStatus('loading', 'Sedang menarik data…', 'Meminta sehingga ' + recordLimit + ' rekod mengikut penapis, kemudian menyemak rekod UDA sedia ada dan polisi palang.');
            try {
                showToast('Menghubungkan ke Live Database...');
                const cardholderType = document.getElementById('uda-cardholder-type')?.value || 'student';
                if (USM_SMART_IS_ADMIN) { try { await loadExistingPolicyOptions(); } catch (policyError) { console.error('Policy list load failed:', policyError); } }
                const liveSearch = document.getElementById('uda-live-search')?.value.trim() || '';
                const liveStatus = document.getElementById('uda-live-status')?.value || '';
                const liveSchool = document.getElementById('uda-live-school')?.value.trim() || '';
                const params = new URLSearchParams({action:'get_records', cardholderType, startDate, endDate, recordLimit:String(recordLimit), recordSearch:liveSearch, recordStatus:liveStatus, recordSchool:liveSchool});
                let res = await fetch('index.php?' + params.toString(), {credentials:'same-origin', headers:{'Accept':'application/json'}});
                let data = await res.json();
                if (!res.ok || data.error) { setLivePullStatus('error', 'Gagal menarik data', data.error || 'Permintaan tidak berjaya.'); showToast('Ralat DB: ' + (data.error || 'Permintaan gagal.')); return; }
                if (!Array.isArray(data)) throw new Error('Format respons SQL Server tidak sah.');
                currentFetchedRecords = data.map(r => Object.assign({}, r, { school: sanitizeSchoolName(r.school), cardholderType: r.cardholderType || cardholderType, policy: String(r.policy || '').trim(), usmUid: String(r.usmUid || '').trim().toUpperCase() }));
                renderLivePullSummary(currentFetchedRecords);
                if (currentFetchedRecords.length === 0) { setLivePullStatus('success', 'Sambungan berjaya — tiada rekod baharu', 'SQL Server berjaya dihubungi, tetapi tiada rekod baharu sepadan dengan tarikh dan penapis yang dipilih. Rekod yang telah berada dalam UDA dikecualikan.'); showToast('Tiada rekod baharu untuk julat tarikh ini.'); document.getElementById('section-step-2').classList.remove('hidden'); renderRawTable(currentFetchedRecords); return; }
                setLivePullStatus('success', 'Data berjaya dimuatkan', currentFetchedRecords.length + ' rekod baharu diterima. Semak ringkasan kualiti data sebelum memilih rekod untuk penukaran UDA.');
                selectedRecordIds = new Set(currentFetchedRecords.map(r => r.id));
                udaCurrentPage = 1;
                renderStatistics();
                renderRawTable(currentFetchedRecords);
                updateMetricsOverview();
                document.getElementById('dashboard-metrics').classList.remove('hidden');
                document.getElementById('section-stats').classList.remove('hidden');
                document.getElementById('section-step-2').classList.remove('hidden');
                const missingPolicy = currentFetchedRecords.filter(r => !String(r.policy || '').trim()).length;
                showToast('Berjaya ambil ' + currentFetchedRecords.length + ' rekod ' + (cardholderType === 'staff' ? 'staf' : 'pelajar') + (missingPolicy ? '. ' + missingPolicy + ' rekod tiada polisi palang dan akan disekat.' : '.'));
            } catch (err) {
                setLivePullStatus('error', 'Tidak dapat memuatkan data', err && err.message ? err.message : 'Ralat rangkaian atau format respons. Semak sambungan SQL Server dan cuba semula.');
                showToast('Ralat mengambil data: ' + (err && err.message ? err.message : 'Ralat rangkaian.'));
            } finally {
                if (triggerButton) { triggerButton.disabled = false; triggerButton.classList.remove('opacity-60'); }
            }
        }

        function updateMetricsOverview() {
            let activeSchools = new Set(currentFetchedRecords.map(r => r.school));
            document.getElementById('metric-total-records').innerText = currentFetchedRecords.length;
            document.getElementById('metric-total-schools').innerText = activeSchools.size;
        }

        function renderStatistics() {
            let container = document.getElementById('school-stats-container');
            container.innerHTML = '';
            let map = {};
            OFFICIAL_SCHOOLS.forEach(s => { map[s] = 0; });
            currentFetchedRecords.forEach(r => { map[sanitizeSchoolName(r.school)]++; });
            document.getElementById('total-student-badge').innerText = currentFetchedRecords.length + ' Pelajar';
            OFFICIAL_SCHOOLS.forEach(school => {
                let count = map[school] || 0;
                let card = document.createElement('div');
                card.className = "bg-slate-50/80 dark:bg-zinc-900 border border-slate-200/60 dark:border-zinc-800 p-4 rounded-xl flex flex-col justify-between gap-3";
                card.innerHTML = '<div class="flex items-center justify-between"><span class="text-[10px] uppercase tracking-wider text-slate-400 font-bold">Pusat Pengajian</span></div>' +
                                 '<div class="text-xs font-semibold text-slate-900 dark:text-white leading-relaxed">' + escapeHtml(school) + '</div>' +
                                 '<div class="flex items-baseline justify-between pt-2 border-t border-slate-200/50 dark:border-zinc-800"><span class="text-[10px] text-slate-500">Jumlah</span><span class="font-bold text-base text-indigo-600 dark:text-indigo-400">' + count + ' orang</span></div>';
                container.appendChild(card);
            });
        }

        const ANALYTICS_COLORS = ['#6366F1', '#10B981', '#F59E0B', '#F43F5E', '#8B5CF6', '#06B6D4', '#84CC16'];
        const SCHOOL_SHORT_NAMES = ['Aeroangkasa', 'Awam', 'Elektrik & Elektronik', 'Bahan & Mineral', 'Kimia', 'Mekanikal', 'Bahasa & Terjemahan'];

        function downloadAnalyticsCharts() {
            const charts = [
                { id: 'pieChart', name: 'USMSmart-taburan-pelajar.png' },
                { id: 'barChart', name: 'USMSmart-jumlah-mengikut-pusat.png' },
                { id: 'monthlyComparisonChart', name: 'USMSmart-trend-pendaftaran-bulanan.png' }
            ];
            let downloaded = 0;
            charts.forEach(chart => {
                const canvas = document.getElementById(chart.id);
                if (!canvas || !canvas.width || !canvas.height) return;
                const link = document.createElement('a');
                link.download = chart.name;
                link.href = canvas.toDataURL('image/png');
                document.body.appendChild(link);
                link.click();
                link.remove();
                downloaded++;
            });
            if (!downloaded) alert('Carta belum tersedia. Buka tab Analisis Statistik dan cuba lagi.');
        }

        function printAnalyticsReport() {
            if (document.getElementById('view-analytics')?.classList.contains('hidden')) {
                switchMainTab('analytics');
                setTimeout(() => window.print(), 350);
                return;
            }
            window.print();
        }

        function usmChartTheme() {
            const dark = document.documentElement.classList.contains('dark');
            return {
                text: dark ? '#A1A1AA' : '#64748B',
                grid: dark ? 'rgba(161, 161, 170, 0.12)' : 'rgba(148, 163, 184, 0.18)',
                tooltipBg: dark ? '#18181B' : '#FFFFFF',
                tooltipText: dark ? '#F4F4F5' : '#0F172A',
                border: dark ? '#27272A' : '#FFFFFF'
            };
        }

        function commonChartOptions(theme) {
            return {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 550 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        labels: {
                            color: theme.text,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            boxWidth: 8,
                            boxHeight: 8,
                            padding: 16,
                            font: { family: "'Plus Jakarta Sans', sans-serif", size: 11 }
                        }
                    },
                    tooltip: {
                        backgroundColor: theme.tooltipBg,
                        titleColor: theme.tooltipText,
                        bodyColor: theme.text,
                        borderColor: theme.grid,
                        borderWidth: 1,
                        padding: 12,
                        cornerRadius: 10,
                        titleFont: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' },
                        bodyFont: { family: "'Plus Jakarta Sans', sans-serif", size: 11 },
                        callbacks: {
                            title: (items) => {
                                if (!items.length) return '';
                                const idx = items[0].dataIndex;
                                return OFFICIAL_SCHOOLS[idx] || items[0].label;
                            }
                        }
                    }
                }
            };
        }

        function renderCardStatusStatistics() {
            const today = new Date(); today.setHours(0,0,0,0);
            const todayMs = today.getTime();
            const soonLimit = todayMs + (30 * 86400000);
            const weekLimit = todayMs + (7 * 86400000);
            let active = 0, expired = 0, expiring = 0, expiring7 = 0;
            allMasterStudents.forEach(r => {
                const expiryText = String(r.expiryDate || '').slice(0, 10);
                if (!/^\d{4}-\d{2}-\d{2}$/.test(expiryText)) return;
                const expiry = new Date(expiryText + 'T00:00:00');
                const expiryMs = expiry.getTime();
                if (Number.isNaN(expiryMs)) return;
                if (expiryMs < todayMs) {
                    expired++;
                } else {
                    active++;
                    if (expiryMs <= soonLimit) expiring++;
                    if (expiryMs <= weekLimit) expiring7++;
                }
            });
            const total = allMasterStudents.length;
            const activeRate = total ? ((active / total) * 100).toFixed(1) : '0.0';
            const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = String(value); };
            setText('card-stat-total', total);
            setText('card-stat-active', active);
            setText('card-stat-expiring', expiring);
            setText('card-stat-expired', expired);
            setText('card-stat-active-rate', activeRate + '% daripada semua rekod');

            // Synchronize Quick Filter Pills counts
            setText('pill-count-all', '(' + total + ')');
            setText('pill-count-active', '(' + active + ')');
            setText('pill-count-expiring30', expiring);
            setText('pill-count-expiring7', expiring7);
            setText('pill-count-expired', expired);

            // Synchronize Transporter Page Expiry Alert Banner
            const banner = document.getElementById('transporter-expiry-alert-banner');
            const expBadge = document.getElementById('transporter-expiring-badge');
            const expText = document.getElementById('transporter-expiry-alert-text');
            const expExpiredBtn = document.getElementById('transporter-expired-btn');
            if (banner) {
                if (expiring > 0 || expired > 0) {
                    banner.classList.remove('hidden');
                    if (expBadge) expBadge.textContent = String(expiring + expired);
                    if (expExpiredBtn) expExpiredBtn.classList.toggle('hidden', expired === 0);
                    if (expText) {
                        if (expired > 0 && expiring > 0) {
                            expText.textContent = expired + ' kad telah tamat tempoh dan ' + expiring + ' kad akan luput dalam 30 hari akan datang.';
                        } else if (expired > 0) {
                            expText.textContent = expired + ' kad pelajar telah tamat tempoh dan memerlukan tindakan.';
                        } else {
                            expText.textContent = expiring + ' kad pelajar akan tamat tempoh dalam tempoh 30 hari akan datang.';
                        }
                    }
                } else {
                    banner.classList.add('hidden');
                }
            }
        }

        function getAnalyticsSchoolNames() {
            const names = new Set(OFFICIAL_SCHOOLS);
            (allMasterStudents || []).forEach(record => {
                const school = String(sanitizeSchoolName(record.school) || '').trim();
                if (school) names.add(school);
            });
            return Array.from(names).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
        }

        // A chart click is the user saying "show me this school's numbers", not "leave the segment",
        // so it retargets the filter that the segment already owns. Reaching the student list stays a
        // separate, deliberate action on the button below.
        function analyticsDrillToSchool(school, monthKey = '') {
            const targetSchool = String(school || '').trim();
            if (!targetSchool) return;
            const targetMonth = /^\d{4}-\d{2}$/.test(String(monthKey || '')) ? String(monthKey) : '';
            if (targetMonth) {
                const start = document.getElementById('analytics-start-month');
                const end = document.getElementById('analytics-end-month');
                if (start) start.value = targetMonth;
                if (end) end.value = targetMonth;
            }
            setAnalyticsSchoolFilter(targetSchool);
            const status = document.getElementById('analytics-chart-status');
            if (status) status.textContent = targetMonth
                ? 'Carta ditapis untuk ' + targetSchool + ' — bulan ' + targetMonth + '.'
                : 'Carta ditapis untuk ' + targetSchool + '.';
            showToast('Menapis statistik: ' + targetSchool);
            document.getElementById('analytics-school-filter')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Sections rise into place as the segment is scrolled. Armed on every entry to the tab, so
        // arriving and scrolling both animate; without IntersectionObserver, or when the visitor asks
        // for reduced motion, the arm class is never added and everything simply stays visible.
        let usmRevealItems = [];
        let usmRevealObserver = null;
        let usmRevealArmedView = '';
        // Only these tags can be a card. Every other element in a segment is a control, a cell or a label,
        // and a segment that animates its own toolbar or list reads as a page that is still loading.
        const USM_REVEAL_TAGS = { SECTION: 1, DIV: 1, FORM: 1, DETAILS: 1 };
        const USM_REVEAL_SKIP = /(search|filter|toolbar|bulk|alert|banner|status|action|form-panel|message|empty|selected|cursor-pointer|inline-flex)/i;
        function usmRevealIsCard(node) {
            if (!USM_REVEAL_TAGS[node.tagName]) return false;
            if (node.tagName === 'SECTION') return true;
            const cls = String(node.getAttribute('class') || '');
            return /rounded-(xl|2xl|3xl)/.test(cls) && /border/.test(cls);
        }
        // Scoped to the view so an inactive segment - which is itself .hidden - does not count as hidden
        // content while its own cards are being judged.
        function usmRevealHidden(node, root) {
            for (let el = node; el && el !== root; el = el.parentElement) {
                if (el.hasAttribute('hidden') || el.classList.contains('hidden')) return true;
            }
            return false;
        }
        function usmRevealDiscover(root) {
            const candidates = Array.from(root.querySelectorAll('*')).filter(node =>
                usmRevealIsCard(node) && !usmRevealHidden(node, root)
                && !USM_REVEAL_SKIP.test((node.id || '') + ' ' + String(node.getAttribute('class') || '')));
            // Outermost first: document order always meets a frame before the cards inside it, so one pass
            // decides both the shells to drop and the single-card panels that move as one unit.
            const chosen = [];
            candidates.forEach(node => {
                if (chosen.some(kept => kept.contains(node))) return;
                const inside = candidates.filter(other => other !== node && node.contains(other)).length;
                if (inside > 1 || chosen.length >= 10) return;
                chosen.push(node);
            });
            return chosen;
        }
        function usmRevealCheck() {
            // Rect maths, not the observer alone: a renderer that is hidden or has not painted yet
            // delivers no IntersectionObserver callbacks, and the segment must never wait on one to
            // show its content.
            const limit = window.innerHeight || document.documentElement.clientHeight || 0;
            // No height to measure against — a hidden frame, a print pass, this sandbox's unpainted
            // renderer — means reveal-all, because content must never sit behind an animation that
            // cannot be triggered.
            if (!(limit > 0)) {
                usmRevealItems.forEach(el => el.classList.add('usm-reveal-in'));
                if (usmRevealObserver) { usmRevealObserver.disconnect(); usmRevealObserver = null; }
                usmRevealItems = [];
                return;
            }
            usmRevealItems = usmRevealItems.filter(el => {
                const box = el.getBoundingClientRect();
                // No box at all means it is not laid out yet (the statistics card before its figures
                // arrive), so stay armed and let the next pass judge it.
                if (!box.width && !box.height) return true;
                // Above the viewport means the visitor has already passed it, so it must simply be
                // visible. Treating that as "not in view yet" left content at opacity 0 forever whenever
                // a segment was entered while the document was already scrolled (measured: scrollY 1432 —
                // the first two blocks of the log segment never appeared, and scrolling back could not
                // help because the condition never released them).
                if (box.bottom > 0 && box.top >= limit * 0.92) return true;
                el.classList.add('usm-reveal-in');
                if (usmRevealObserver) usmRevealObserver.unobserve(el);
                return false;
            });
        }
        function usmRevealArm(viewId) {
            const root = document.getElementById(viewId);
            if (!root) return;
            if (usmRevealArmedView === viewId && root.classList.contains('usm-reveal-arm')) return;
            // Only blocks that are laid out right now. Nothing but an observer callback ever runs again for
            // a block that appears later, so collecting a hidden one is how content ends up stuck invisible.
            const items = Array.from(root.querySelectorAll('[data-usm-reveal]')).filter(el => !usmRevealHidden(el, root));
            const calm = !('IntersectionObserver' in window)
                || (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
            if (usmRevealObserver) { usmRevealObserver.disconnect(); usmRevealObserver = null; }
            if (calm) { usmRevealArmedView = ''; root.classList.remove('usm-reveal-arm'); usmRevealItems = []; return; }
            if (!items.length) {
                const found = usmRevealDiscover(root);
                if (found.length < 2) {
                    usmRevealArmedView = '';
                    root.classList.remove('usm-reveal-arm');
                    usmRevealItems = [];
                    return;
                }
                found.forEach(el => {
                    el.setAttribute('data-usm-reveal', '');
                    items.push(el);
                });
            }
            items.forEach((el, index) => {
                el.classList.remove('usm-reveal-in');
                el.style.setProperty('--usm-reveal-delay', Math.min(index * 70, 420) + 'ms');
            });
            root.classList.add('usm-reveal-arm');
            usmRevealItems = items;
            usmRevealArmedView = viewId;
            usmRevealObserver = new IntersectionObserver(usmRevealCheck, { threshold: 0.08, rootMargin: '0px 0px -6% 0px' });
            items.forEach(el => usmRevealObserver.observe(el));
            usmRevealCheck();
            setTimeout(usmRevealCheck, 1200);
        }
        window.addEventListener('scroll', () => { if (usmRevealItems.length) usmRevealCheck(); }, { passive: true });
        // The landing view is not always opened through setMainTabState, so it arms itself once the inline
        // script at the foot of the body has run: blocks already on screen settle immediately and only the
        // ones below the fold wait for the scroll.
        queueMicrotask(usmRevealVisible);

        function openAnalyticsStudentRecords(school, monthKey = '') {
            const targetSchool = String(school || '').trim();
            const targetMonth = /^\d{4}-\d{2}$/.test(String(monthKey || '')) ? String(monthKey) : '';
            const search = document.getElementById('master-search');
            const schoolFilter = document.getElementById('master-school-filter');
            const statusFilter = document.getElementById('master-status-filter');
            if (search) search.value = '';
            if (schoolFilter) {
                // A school that only ever appears in its raw variant has no option yet; without this the
                // assignment below silently lands on "Semua Pusat Pengajian" and the list is unfiltered.
                if (targetSchool && !Array.from(schoolFilter.options).some(o => o.value === targetSchool)) {
                    const injected = document.createElement('option');
                    injected.value = targetSchool;
                    injected.textContent = targetSchool;
                    schoolFilter.appendChild(injected);
                }
                schoolFilter.value = targetSchool;
            }
            if (statusFilter) statusFilter.value = '';
            duplicateOnlyFilter = false;
            const sort = document.getElementById('master-sort');
            if (sort) sort.value = 'name_asc';
            filterMasterTable();
            if (targetMonth) {
                const monthRecords = filteredMasterStudents.filter(record => {
                    const joined = String(record.dateJoined || record.date_joined || record.joined || '').trim();
                    return joined.slice(0, 7) === targetMonth;
                });
                currentPage = 1;
                renderMasterTable(monthRecords);
                const status = document.getElementById('analytics-chart-status');
                if (status) status.textContent = 'Senarai dibuka untuk ' + targetSchool + ' — bulan ' + targetMonth + ': ' + monthRecords.length + ' rekod.';
            } else {
                const status = document.getElementById('analytics-chart-status');
                if (status) status.textContent = 'Senarai dibuka untuk ' + (targetSchool || 'semua pusat pengajian') + ': ' + filteredMasterStudents.length + ' rekod.';
            }
            switchMainTab('transporter');
            const table = document.getElementById('master-student-tbody');
            if (table) table.closest('section, .overflow-x-auto, div')?.scrollIntoView({behavior:'smooth', block:'start'});
            showToast(targetMonth ? 'Memaparkan rekod ' + targetSchool + ' bagi ' + targetMonth : 'Memaparkan rekod pelajar: ' + (targetSchool || 'semua pusat pengajian'));
        }

        function setAnalyticsSchoolFilter(school) {
            selectedAnalyticsSchool = String(school || '').trim();
            const select = document.getElementById('analytics-school-filter');
            const reset = document.getElementById('analytics-school-reset');
            if (select && select.value !== selectedAnalyticsSchool) select.value = selectedAnalyticsSchool;
            if (reset) reset.hidden = !selectedAnalyticsSchool && !document.getElementById('analytics-start-month')?.value && !document.getElementById('analytics-end-month')?.value;
            const records = document.getElementById('analytics-open-records');
            if (records) records.hidden = !selectedAnalyticsSchool;
            const activeLabel = document.getElementById('analytics-active-filter');
            if (activeLabel) activeLabel.innerHTML = selectedAnalyticsSchool ? '<i class="fa-solid fa-filter"></i> Paparan: ' + escapeHtml(selectedAnalyticsSchool) : ' <i class="fa-solid fa-circle-check"></i> Paparan: semua pusat pengajian';
            renderAnalyticsCharts();
            renderMonthlyComparisonChart();
        }

        function resetAnalyticsFilters() {
            const start = document.getElementById('analytics-start-month');
            const end = document.getElementById('analytics-end-month');
            if (start) start.value = '';
            if (end) end.value = '';
            setAnalyticsSchoolFilter('');
        }

        function resetAnalyticsMonthFilter() {
            const start = document.getElementById('analytics-start-month');
            const end = document.getElementById('analytics-end-month');
            if (start) start.value = '';
            if (end) end.value = '';
            renderMonthlyComparisonChart();
            const reset = document.getElementById('analytics-school-reset');
            if (reset) reset.hidden = !selectedAnalyticsSchool;
        }

        function renderAnalyticsCharts() {
            const selector = document.getElementById('analytics-school-filter');
            if (selector) {
                const current = selectedAnalyticsSchool;
                const names = getAnalyticsSchoolNames();
                const options = ['<option value="">Semua pusat pengajian</option>'].concat(names.map(name => '<option value="' + escapeHtml(name).replace(/"/g, '&quot;') + '">' + escapeHtml(name) + '</option>'));
                selector.innerHTML = options.join('');
                selector.value = current;
            }
            const sourceRecords = selectedAnalyticsSchool ? allMasterStudents.filter(record => String(sanitizeSchoolName(record.school) || '').trim() === selectedAnalyticsSchool) : allMasterStudents;
            const schoolNames = selectedAnalyticsSchool ? [selectedAnalyticsSchool] : getAnalyticsSchoolNames();
            const map = {};
            schoolNames.forEach(school => { map[school] = 0; });
            sourceRecords.forEach(record => {
                const school = String(sanitizeSchoolName(record.school) || '').trim();
                if (school) map[school] = (map[school] || 0) + 1;
            });
            const labels = schoolNames.map(school => {
                const knownIndex = OFFICIAL_SCHOOLS.indexOf(school);
                return knownIndex >= 0 ? SCHOOL_SHORT_NAMES[knownIndex] : school;
            });
            const values = schoolNames.map(school => map[school] || 0);
            const total = values.reduce((sum, value) => sum + value, 0);
            // KPIs use the same records already loaded for the existing charts.
            const schoolCount = new Set(sourceRecords.map(record => String(sanitizeSchoolName(record.school) || '').trim()).filter(Boolean)).size;
            const topSchool = schoolNames.map((school, index) => ({school, count: values[index] || 0})).sort((a,b) => b.count-a.count)[0];
            const latestDateRaw = sourceRecords.reduce((latest, record) => {
                const raw = record.dateJoined || record.date_joined || record.joined || '';
                const normalized = String(raw).trim();
                return normalized && normalized > latest ? normalized : latest;
            }, '');
            const fmtNumber = value => new Intl.NumberFormat('ms-MY').format(Number(value) || 0);
            const setKpi = (id, value) => { const node = document.getElementById(id); if (node) node.textContent = value; };
            setKpi('analytics-kpi-total', fmtNumber(sourceRecords.length));
            setKpi('analytics-kpi-schools', fmtNumber(schoolCount));
            setKpi('analytics-kpi-top-school', topSchool && topSchool.count > 0 ? topSchool.school : '—');
            setKpi('analytics-kpi-top-school-count', topSchool && topSchool.count > 0 ? fmtNumber(topSchool.count) + ' pelajar' : 'Tiada rekod mengikut pusat');
            let latestDateLabel = 'Tiada tarikh';
            if (latestDateRaw) { const parsedDate = new Date(latestDateRaw.length === 10 ? latestDateRaw + 'T00:00:00' : latestDateRaw); if (!Number.isNaN(parsedDate.getTime())) latestDateLabel = new Intl.DateTimeFormat('ms-MY', {day:'2-digit',month:'short',year:'numeric'}).format(parsedDate); }
            setKpi('analytics-kpi-latest', latestDateLabel);
            const totalCaption = document.querySelector('#analytics-kpi-total')?.parentElement?.querySelector('.analytics-kpi-caption');
            if (totalCaption) totalCaption.textContent = selectedAnalyticsSchool ? 'Rekod bagi pusat yang dipilih' : 'Jumlah rekod dalam senarai semasa';
            const theme = usmChartTheme();
            const common = commonChartOptions(theme);
            if (pieChartInstance) pieChartInstance.destroy();
            if (barChartInstance) barChartInstance.destroy();
            pieChartInstance = new Chart(document.getElementById('pieChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: schoolNames.map((_, index) => ANALYTICS_COLORS[index % ANALYTICS_COLORS.length]), borderColor: theme.border, borderWidth: 3, hoverOffset: 7, spacing: 2 }]
                },
                options: {
                    ...common,
                    onClick: (event, elements, chart) => { if (!elements.length) return; const index = elements[0].index; analyticsDrillToSchool(schoolNames[index] || '', ''); },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    cutout: '64%',
                    plugins: {
                        ...common.plugins,
                        legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 14 } },
                        tooltip: { ...common.plugins.tooltip, callbacks: { title: items => schoolNames[items[0].dataIndex] || '', label: context => { const value = Number(context.raw) || 0; const percentage = total ? ((value / total) * 100).toFixed(1) : '0.0'; return ` ${value} pelajar (${percentage}%)`; } } }
                    }
                }
            });
            barChartInstance = new Chart(document.getElementById('barChart').getContext('2d'), {
                type: 'bar',
                data: { labels: labels, datasets: [{ label: 'Pelajar', data: values, backgroundColor: schoolNames.map((_, index) => ANALYTICS_COLORS[index % ANALYTICS_COLORS.length] + 'D9'), borderColor: schoolNames.map((_, index) => ANALYTICS_COLORS[index % ANALYTICS_COLORS.length]), borderWidth: 1, borderRadius: 7, borderSkipped: false, maxBarThickness: 34 }] },
                options: {
                    ...common,
                    onClick: (event, elements, chart) => { if (!elements.length) return; const index = elements[0].index; analyticsDrillToSchool(schoolNames[index] || '', ''); },
                    onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                    plugins: { ...common.plugins, legend: { display: false }, tooltip: { ...common.plugins.tooltip, callbacks: { title: items => schoolNames[items[0].dataIndex] || '', label: context => ` ${context.raw} pelajar` } } },
                    scales: { x: { grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 35, minRotation: 0, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } }, y: { beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } } }
                }
            });
        }

        async function renderMonthlyComparisonChart() {
            try {
                const response = await fetch('index.php?action=get_monthly_comparison');
                const rows = await response.json();
                const monthsSet = new Set();
                const lookup = {};
                if (Array.isArray(rows)) {
                    rows.forEach(record => {
                        if (!record.month_key) return;
                        monthsSet.add(record.month_key);
                        const school = sanitizeSchoolName(record.school);
                        if (!lookup[school]) lookup[school] = {};
                        lookup[school][record.month_key] = (lookup[school][record.month_key] || 0) + (parseInt(record.total, 10) || 0);
                    });
                }
                let months = Array.from(monthsSet).sort();
                const startMonth = document.getElementById('analytics-start-month')?.value || '';
                const endMonth = document.getElementById('analytics-end-month')?.value || '';
                const resetButton = document.getElementById('analytics-school-reset');
                if (resetButton) resetButton.hidden = !selectedAnalyticsSchool && !startMonth && !endMonth;
                if (startMonth && endMonth && startMonth > endMonth) { document.getElementById('analytics-chart-status').textContent = 'Bulan mula mesti sebelum atau sama dengan bulan akhir.'; return; }
                months = months.filter(month => (!startMonth || month >= startMonth) && (!endMonth || month <= endMonth));
                document.getElementById('analytics-chart-status').textContent = months.length ? ('Memaparkan ' + months.length + ' bulan.') : 'Tiada data bagi julat bulan dipilih.';
                if (!months.length) months = [];
                const theme = usmChartTheme();
                const common = commonChartOptions(theme);
                const monthlySchools = selectedAnalyticsSchool ? [selectedAnalyticsSchool] : OFFICIAL_SCHOOLS;
                const datasets = monthlySchools.map(school => { const index = Math.max(0, OFFICIAL_SCHOOLS.indexOf(school)); const schoolIndex = OFFICIAL_SCHOOLS.indexOf(school); return { label: SCHOOL_SHORT_NAMES[schoolIndex] || school, data: months.map(month => (lookup[school] && lookup[school][month]) || 0), backgroundColor: ANALYTICS_COLORS[index % ANALYTICS_COLORS.length] + 'D9', borderColor: ANALYTICS_COLORS[index % ANALYTICS_COLORS.length], borderWidth: 1, borderRadius: 5, borderSkipped: false, maxBarThickness: 24 }; });
                if (monthlyComparisonChartInstance) monthlyComparisonChartInstance.destroy();
                monthlyComparisonChartInstance = new Chart(document.getElementById('monthlyComparisonChart').getContext('2d'), {
                    type: 'bar',
                    data: { labels: months, datasets },
                    options: {
                        ...common,
                        onClick: (event, elements, chart) => { if (!elements.length) return; const point = elements[0]; const school = chart.data.datasets[point.datasetIndex]?.label || ''; const officialIndex = SCHOOL_SHORT_NAMES.indexOf(school); const fullSchool = officialIndex >= 0 ? OFFICIAL_SCHOOLS[officialIndex] : school; analyticsDrillToSchool(fullSchool, chart.data.labels[point.index] || ''); },
                        onHover: (event, elements) => { if (event.native && event.native.target) event.native.target.style.cursor = elements.length ? 'pointer' : 'default'; },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            ...common.plugins,
                            legend: { position: 'bottom', labels: { ...common.plugins.legend.labels, padding: 18 } },
                            tooltip: { ...common.plugins.tooltip, callbacks: { title: items => items[0]?.label || '', label: context => ` ${context.dataset.label}: ${context.raw} pelajar` } }
                        },
                        scales: {
                            x: { stacked: false, grid: { display: false }, border: { display: false }, ticks: { color: theme.text, maxRotation: 0, autoSkip: true, maxTicksLimit: 12, font: { family: "'Plus Jakarta Sans', sans-serif", size: 10 } } },
                            y: { beginAtZero: true, ticks: { precision: 0, color: theme.text, padding: 8, font: { size: 10 } }, grid: { color: theme.grid, drawTicks: false }, border: { display: false } }
                        }
                    }
                });
            } catch (err) {
                console.error('Gagal memaparkan carta perbandingan bulanan:', err);
            }
        }

        async function deleteStudentRecord(id) {
            if (!confirm('Padam rekod pelajar ini secara kekal?')) return;
            try {
                let res = await fetch('index.php?action=delete_student', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) });
                let result = await res.json();
                if (!result.success) throw new Error(result.message);
                showToast(result.message);
                loadMasterStudents();
            } catch (err) { showToast('Ralat memadam rekod.'); }
        }

        async function loadExistingPolicyOptions() {
            if (existingPolicyOptions.length) return existingPolicyOptions;
            const response = await fetch('index.php?action=get_card_policies', {credentials:'same-origin', headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Senarai polisi gagal dimuatkan.');
            existingPolicyOptions = Array.isArray(data.policies) ? data.policies : [];
            return existingPolicyOptions;
        }

        async function assignLiveCardPolicy(recordId) {
            const record = currentFetchedRecords.find(r => String(r.id) === String(recordId));
            const selectId = 'policy-select-' + String(recordId).replace(/[^A-Za-z0-9_-]/g, '');
            const select = document.getElementById(selectId);
            if (!record || !select || !select.value) { showToast('Pilih polisi sedia ada dahulu.'); return; }
            if (!confirm('Tetapkan polisi ' + select.value + ' untuk ' + record.matric + '?')) return;
            try {
                const response = await fetch('index.php?action=assign_card_policy', {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json'}, body:JSON.stringify({cardholderType:record.cardholderType,sourceId:record.sourceId,identifier:record.matric,policy:select.value})});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Polisi tidak dapat disimpan.');
                record.policy = data.policy;
                record.policyAvailable = true;
                renderRawTable(currentFetchedRecords);
                showToast(data.message);
            } catch (error) { showToast('Ralat menetapkan polisi: ' + error.message); }
        }

        function liveRecordQualityBadge(record) {
            const issues = [];
            if (!String(record.policy || '').trim()) issues.push('Polisi tiada');
            if (!/^(?:[0-9A-F]{2})+$/i.test(String(record.usmUid || '').replace(/\s+/g, ''))) issues.push('UID tidak sah');
            if (String(record.serialNo || '').trim() && !/^[A-Z0-9][A-Z0-9 ._\/-]{0,99}$/i.test(String(record.serialNo).trim())) issues.push('Format nombor kad tidak sah');
            if (!issues.length) return '<span class="inline-flex rounded-full px-2 py-1 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 font-semibold">Lulus semakan awal</span>';
            return '<span class="inline-flex rounded-full px-2 py-1 bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 font-semibold">Perlu tindakan</span><div class="mt-1 text-rose-600 dark:text-rose-300">' + issues.map(escapeHtml).join(' · ') + '</div>';
        }

        function renderRawTable(records) {
            const all = Array.isArray(records) ? records : currentFetchedRecords;
            const keyword = (document.getElementById('table-search')?.value || '').trim().toLowerCase();
            const filtered = keyword ? all.filter(r => [r.name,r.matric,r.school,r.usmUid,r.cardStatus].some(v => String(v || '').toLowerCase().includes(keyword))) : all;
            const total = filtered.length;
            const pages = Math.max(1, Math.ceil(total / udaPageSize));
            udaCurrentPage = Math.min(Math.max(1, udaCurrentPage), pages);
            const start = (udaCurrentPage - 1) * udaPageSize;
            const visible = filtered.slice(start, start + udaPageSize);
            let tbody = document.getElementById('usm-raw-tbody');
            tbody.innerHTML = '';
            visible.forEach((r, idx) => {
                let isChecked = selectedRecordIds.has(r.id) ? 'checked' : '';
                let tr = document.createElement('tr');
                tr.className = "hover:bg-slate-50/50 dark:hover:bg-zinc-900/50 transition-colors";
                tr.innerHTML = '<td class="p-3.5 text-center"><input type="checkbox" ' + isChecked + ' onchange="toggleSelect(&quot;' + escapeHtml(String(r.id)) + '&quot;)" class="rounded accent-indigo-600"></td>' +
                               '<td class="p-3.5">' + (start + idx + 1) + '</td>' +
                               '<td class="p-3.5 font-medium text-slate-900 dark:text-white">' + escapeHtml(r.name) + '</td>' +
                               '<td class="p-3.5">' + escapeHtml(r.matric) + '</td>' +
                               '<td class="p-3.5">' + escapeHtml(r.school) + '</td>' +
                               '<td class="p-3.5">' + escapeHtml(r.cardholderType === 'staff' ? 'Staf' : 'Pelajar') + '</td>' +
                               '<td class="p-3.5 font-bold text-indigo-600 dark:text-indigo-400">' + escapeHtml(r.usmUid) + '</td>' +
                               '<td class="p-3.5 text-[10px] min-w-[150px]">' + liveRecordQualityBadge(r) + '</td>' +
                               '<td class="p-3.5 text-xs"><div class="flex flex-col gap-2 min-w-[180px]"><span>' + (r.policy ? '<span class="text-emerald-600 font-semibold">' + escapeHtml(r.policy) + '</span>' : '<span class="text-rose-600 font-semibold">Tiada polisi — disekat</span>') + '</span>' + (USM_SMART_IS_ADMIN ? '<div class="flex gap-1"><select id="policy-select-' + escapeHtml(String(r.id).replace(/[^A-Za-z0-9_-]/g,'')) + '" class="max-w-[145px] border rounded-lg px-2 py-1 bg-white dark:bg-zinc-900 text-xs"><option value="">Pilih polisi…</option>' + existingPolicyOptions.map(p => '<option value="' + escapeHtml(p) + '" ' + (p === r.policy ? 'selected' : '') + '>' + escapeHtml(p) + '</option>').join('') + '</select><button type="button" onclick="assignLiveCardPolicy(\'' + escapeHtml(String(r.id)) + '\')" class="px-2 py-1 rounded-lg bg-indigo-600 text-white text-[10px] font-semibold">Simpan</button></div>' : '') + '</div></td>';
                tbody.appendChild(tr);
            });
            const summary = document.getElementById('uda-pagination-summary');
            if (summary) summary.textContent = total ? `Memaparkan ${start + 1}–${Math.min(start + udaPageSize, total)} daripada ${total} rekod` : 'Tiada rekod sepadan';
            const indicator = document.getElementById('uda-page-indicator'); if (indicator) indicator.textContent = `Halaman ${udaCurrentPage} / ${pages}`;
            const prev = document.getElementById('uda-page-prev'); if (prev) prev.disabled = udaCurrentPage <= 1;
            const next = document.getElementById('uda-page-next'); if (next) next.disabled = udaCurrentPage >= pages;
        }

        function filterTableRecords() { udaCurrentPage = 1; renderRawTable(currentFetchedRecords); }
        function changeUdaPage(delta) { udaCurrentPage += Number(delta) || 0; renderRawTable(currentFetchedRecords); }
        function changeUdaPageSize(value) { udaPageSize = Math.max(1, Math.min(100, parseInt(value, 10) || 25)); udaCurrentPage = 1; renderRawTable(currentFetchedRecords); }

        function toggleSelect(id) {
            if (selectedRecordIds.has(id)) selectedRecordIds.delete(id);
            else selectedRecordIds.add(id);
        }

        function toggleSelectAll(master) {
            if (master.checked) { currentFetchedRecords.forEach(r => selectedRecordIds.add(r.id)); }
            else { selectedRecordIds.clear(); }
            renderRawTable(currentFetchedRecords);
        }

        function exportToUDA() {
            if (selectedRecordIds.size === 0) { showToast('Sila pilih sekurang-kurangnya satu rekod.'); return; }
            const selectedRecords = currentFetchedRecords.filter(r => selectedRecordIds.has(r.id));
            const missingPolicy = selectedRecords.filter(r => !String(r.policy || '').trim());
            if (missingPolicy.length) { showToast('Eksport disekat: ' + missingPolicy.length + ' rekod tiada polisi palang. Tetapkan polisi palang dahulu.'); return; }
            const invalidUid = selectedRecords.find(r => !/^(?:[0-9a-fA-F]{2})+$/.test(String(r.usmUid || '').replace(/\s+/g,'')));
            if (invalidUid) { showToast('Eksport disekat: UID tidak sah untuk ' + (invalidUid.matric || 'rekod terpilih') + '. UID mesti hexadecimal dengan bilangan aksara genap.'); return; }
            const invalidSerial = selectedRecords.find(r => String(r.serialNo || '').trim() && !/^[A-Z0-9][A-Z0-9 ._\/-]{0,99}$/i.test(String(r.serialNo).trim()));
            if (invalidSerial) { showToast('Eksport disekat: nombor kad mengandungi aksara tidak disokong untuk ' + (invalidSerial.matric || 'rekod terpilih') + '.'); return; }
            convertedUdaRecords = selectedRecords
                .map(r => Object.assign({}, r, { udaConvertedUid: convertUidToLittleEndian(r.usmUid), statusTag: 'READY' }));
            renderUdaTable(convertedUdaRecords);
            document.getElementById('section-step-3').classList.remove('hidden');
            document.getElementById('section-step-3').scrollIntoView({ behavior: 'smooth' });
        }

        function renderUdaTable(records) {
            let tbody = document.getElementById('uda-converted-tbody');
            tbody.innerHTML = '';
            records.forEach((r, idx) => {
                let tr = document.createElement('tr');
                tr.className = "hover:bg-slate-50/50 dark:hover:bg-zinc-900/50 transition-colors";
                tr.innerHTML = '<td class="p-3.5">' + (idx + 1) + '</td>' +
                               '<td class="p-3.5 text-slate-900 dark:text-white font-medium">' + escapeHtml(r.name) + '<br><span class="text-[10px] text-slate-400 font-normal">' + escapeHtml(r.cardholderType === 'staff' ? 'Staf · ' : 'Pelajar · ') + escapeHtml(r.matric) + '</span></td>' +
                               '<td class="p-3.5">' + escapeHtml(r.usmUid) + '</td>' +
                               '<td class="p-3.5 font-bold text-emerald-600 dark:text-emerald-400">' + escapeHtml(r.udaConvertedUid) + '</td>' +
                               '<td class="p-3.5 flex items-center gap-2">' +
                                   '<input type="text" value="' + escapeHtml(r.serialNo) + '" class="border border-slate-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 px-2.5 py-1 rounded-lg text-xs" onchange="updateSerial(&quot;' + escapeHtml(String(r.id)) + '&quot;, this.value)">' +
                                   '<select onchange="updateStatus(&quot;' + escapeHtml(String(r.id)) + '&quot;, this.value)" class="text-[10px] font-bold px-2.5 py-1 rounded-lg border border-slate-200 dark:border-zinc-700 bg-emerald-50 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400">' +
                                       '<option value="READY" ' + (r.statusTag === 'READY' ? 'selected' : '') + '>READY</option>' +
                                       '<option value="PENDING" ' + (r.statusTag === 'PENDING' ? 'selected' : '') + '>PENDING</option>' +
                                   '</select>' +
                               '</td>';
                tbody.appendChild(tr);
            });
        }

        function filterUdaTable() {
            let statusVal = document.getElementById('status-filter').value;
            let query = document.getElementById('uda-search').value.toLowerCase();
            let filtered = convertedUdaRecords.filter(r => (statusVal === 'ALL' || r.statusTag === statusVal) && (r.name.toLowerCase().includes(query) || r.matric.toLowerCase().includes(query)));
            renderUdaTable(filtered);
        }

        function updateSerial(id, val) { let item = convertedUdaRecords.find(r => r.id === id); if (item) item.serialNo = val; }
        function updateStatus(id, val) { let item = convertedUdaRecords.find(r => r.id === id); if (item) item.statusTag = val; }

        function openConfirmModal() { document.getElementById('confirm-modal').classList.remove('hidden'); }
        function closeConfirmModal() { document.getElementById('confirm-modal').classList.add('hidden'); }

        async function confirmDumpToUDA() {
            closeConfirmModal();
            try {
                let res = await fetch('index.php?action=dump_uda', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ records: convertedUdaRecords }) });
                let result = await res.json();
                if (!result.success) throw new Error(result.message);
                const verification = result.verification_report || null;
                if (verification) {
                    showUdaVerificationReport(verification);
                    console.info('USMSmart UDA Export Verification Report', verification);
                }
                document.getElementById('dashboard-metrics').classList.add('hidden');
                document.getElementById('section-stats').classList.add('hidden');
                document.getElementById('section-step-2').classList.add('hidden');
                document.getElementById('section-step-3').classList.add('hidden');
                document.getElementById('section-success').classList.remove('hidden');
                enableSyswebAfterSuccessfulUdaExport(convertedUdaRecords);
                loadSessionHistory();
                showToast('Sesi berjaya direkodkan! Langkah seterusnya: Sysweb sedia dibuka.');
            } catch (err) { showToast('Ralat: ' + err.message); }
        }

        function showUdaVerificationReport(report) {
            const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
            let panel = document.getElementById('usm-uda-verification-modal');
            if (!panel) {
                panel = document.createElement('div');
                panel.id = 'usm-uda-verification-modal';
                panel.className = 'fixed inset-0 z-[2000] flex items-center justify-center bg-slate-950/60 p-3';
                panel.innerHTML = '<section role="dialog" aria-modal="true" aria-labelledby="usm-uda-report-title" class="w-full max-w-6xl max-h-[92vh] overflow-hidden rounded-2xl bg-white text-slate-800 shadow-2xl dark:bg-zinc-950 dark:text-zinc-100"><header class="flex items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-zinc-800"><div><h2 id="usm-uda-report-title" class="text-lg font-extrabold">UDA Export Verification Report</h2><p class="text-xs opacity-70">Read-back verification from the configured MySQL database</p></div><button type="button" id="usm-uda-report-close" class="rounded-lg px-3 py-2 text-xl hover:bg-slate-100 dark:hover:bg-zinc-800" aria-label="Close report">×</button></header><div id="usm-uda-report-content" class="max-h-[calc(92vh-135px)] overflow-auto p-4"></div><footer class="flex flex-wrap justify-between items-center gap-2 border-t border-slate-200 p-4 dark:border-zinc-800"><div class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5"><i class="fa-solid fa-circle-check"></i> Eksport UDA Selesai & Diverifikasi</div><div class="flex flex-wrap items-center gap-2"><button type="button" id="usm-uda-report-csv" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700">Download CSV</button><button type="button" id="usm-uda-report-close-bottom" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold dark:border-zinc-700">Tutup</button><button type="button" id="usm-uda-report-goto-sysweb" class="rounded-lg bg-emerald-700 hover:bg-emerald-800 px-4 py-2 text-sm font-bold text-white shadow-sm flex items-center gap-2 cursor-pointer"><i class="fa-solid fa-network-wired"></i> Teruskan ke Sysweb (Langkah Seterusnya) <i class="fa-solid fa-arrow-right"></i></button></div></footer></section>';
                document.body.appendChild(panel);
                const close = () => panel.remove();
                panel.querySelector('#usm-uda-report-close').addEventListener('click', close);
                panel.querySelector('#usm-uda-report-close-bottom').addEventListener('click', close);
                panel.querySelector('#usm-uda-report-goto-sysweb')?.addEventListener('click', () => {
                    close();
                    openSyswebDemo();
                });
                panel.addEventListener('click', event => { if (event.target === panel) close(); });
                panel.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
            }
            panel.dataset.report = JSON.stringify(report);
            const passed = report.result === 'PASS';
            const cc = report.ccadmin || null;
            const ccBanner = !cc ? '' : (() => {
                const written = cc.status === 'written';
                const failed = cc.status === 'failed';
                const tone = written ? 'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/30' : failed ? 'border-rose-300 bg-rose-50 dark:bg-rose-950/30' : 'border-amber-300 bg-amber-50 dark:bg-amber-950/30';
                const title = written ? 'ccadmin: ' + cc.written + ' of ' + cc.total + ' cards written' : failed ? 'ccadmin: write failed — no card rows were changed' : 'ccadmin: not configured';
                const detail = written ? String(cc.table || '') + ' · UID stored as ' + String(cc.uid || 'source') : String(cc.message || '');
                return '<div class="mb-4 rounded-xl border px-4 py-3 text-sm ' + tone + '"><div class="font-bold">' + esc(title) + '</div>' + (detail ? '<div class="mt-1 text-xs">' + esc(detail) + '</div>' : '') + '</div>';
            })();
            const metric = (label, value) => '<div class="rounded-xl border border-slate-200 p-3 dark:border-zinc-800"><div class="text-xs opacity-70">'+esc(label)+'</div><div class="mt-1 text-xl font-extrabold">'+esc(value)+'</div></div>';
            const items = Array.isArray(report.items) ? report.items : [];
            const rows = items.map(item => '<tr class="border-t border-slate-100 dark:border-zinc-800"><td class="p-2 font-semibold">'+esc(item.matric)+'</td><td class="p-2">'+(item.saved ? 'PASS' : 'FAIL')+'</td><td class="p-2">'+esc(item.uid)+'</td><td class="p-2">'+esc(item.policy)+'</td><td class="p-2">'+esc(item.cardholder_type)+'</td><td class="p-2 font-bold '+(item.result==='PASS'?'text-emerald-600':'text-rose-600')+'">'+esc(item.result)+'</td></tr>').join('');
            panel.querySelector('#usm-uda-report-content').innerHTML = '<div class="mb-4 rounded-xl border p-4 '+(passed?'border-emerald-300 bg-emerald-50 dark:bg-emerald-950/30':'border-amber-300 bg-amber-50 dark:bg-amber-950/30')+'"><div class="text-lg font-black">'+(passed?'PASS — Verification completed':'WARNING — Verification found mismatches')+'</div><div class="mt-1 text-sm">'+esc(report.saved_and_verified_count)+' of '+esc(report.requested_count)+' records fully verified.</div></div>'+ccBanner+'<div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-4">'+metric('Requested',report.requested_count)+metric('Saved & verified',report.saved_and_verified_count)+metric('Failed records',report.failed_count)+metric('UID checks',report.uid_checks_passed+'/'+report.requested_count)+'</div><div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">'+metric('Policy checks',report.policy_checks_passed+'/'+report.requested_count)+metric('Cardholder checks',report.cardholder_type_checks_passed+'/'+report.requested_count)+metric('Session #'+(report.session?.id ?? '?'),(report.session?.result ?? 'FAIL')+' · '+(report.session?.saved_count ?? 0)+'/'+(report.session?.expected_count ?? report.requested_count))+'</div><div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-zinc-800"><table class="w-full min-w-[680px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase dark:bg-zinc-900"><tr><th class="p-2">Matric / ID</th><th class="p-2">Saved</th><th class="p-2">UID</th><th class="p-2">Policy</th><th class="p-2">Cardholder</th><th class="p-2">Result</th></tr></thead><tbody>'+(rows || '<tr><td colspan="6" class="p-4 text-center opacity-70">No record details returned.</td></tr>')+'</tbody></table></div><p class="mt-3 text-xs opacity-70">'+esc(report.scope_note || 'This report checks saved records in the configured MySQL database only.')+'</p>';
            panel.querySelector('#usm-uda-report-csv').onclick = () => {
                const csvCell = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
                const lines = [['Matric / ID','Saved','UID check','Policy check','Cardholder check','Result'], ...items.map(item => [item.matric,item.saved?'PASS':'FAIL',item.uid,item.policy,item.cardholder_type,item.result])];
                lines.unshift(['UDA Export Verification Report','Result',report.result],['Requested',report.requested_count,'Verified',report.saved_and_verified_count,'Failed',report.failed_count],['Session ID',report.session?.id ?? '', 'Session result',report.session?.result ?? 'FAIL']);
                if (cc) lines.unshift(['ccadmin', cc.status, (cc.written ?? 0) + '/' + (cc.total ?? 0), cc.table || '', cc.uid || '']);
                const blob = new Blob(['\ufeff'+lines.map(row => row.map(csvCell).join(',')).join('\r\n')], {type:'text/csv;charset=utf-8;'});
                const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href=url; a.download='USMSmart_UDA_Verification_'+(report.session?.id ?? 'report')+'.csv'; document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
            };
            panel.querySelector('#usm-uda-report-close').focus();
        }

        async function loadSessionHistory() {
            if (!USMSMART_IS_ADMIN) return; // get_sessions is admin-only; avoid a guaranteed 403 for viewers
            try {
                let res = await fetch('index.php?action=get_sessions');
                if (!res.ok) { allSessionsData = []; renderSessionHistory(); return; }
                const data = await res.json();
                allSessionsData = Array.isArray(data) ? data : [];
                renderSessionHistory();
            } catch (e) { console.error("Gagal muat sejarah sesi"); }
        }

        function renderSessionHistoryBase() {
            const tbody = document.getElementById('session-history-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';
            const rows = sessionHistoryFiltered();
            if (!rows.length) { tbody.innerHTML = '<tr><td colspan="4" class="p-5 text-center text-slate-500 dark:text-zinc-400">Tiada sesi sepadan dengan julat, tapisan atau carian ini.</td></tr>'; return; }
            rows.forEach(s => {
                let tr = document.createElement('tr');
                tr.className = "hover:bg-slate-50/50 dark:hover:bg-zinc-900/50 transition-colors";
                const safeName = escapeHtml(s.session_name || ('Sesi #' + s.id));
                tr.innerHTML = '<td class="p-3.5"><div class="font-bold text-indigo-600 dark:text-indigo-400">#' + escapeHtml(s.id) + '</div><div class="mt-1 font-semibold text-slate-700 dark:text-zinc-200">' + safeName + '</div></td>' +
                    '<td class="p-3.5 text-slate-500 dark:text-zinc-400">' + escapeHtml(s.session_date || '-') + '</td>' +
                    '<td class="p-3.5 text-center"><span class="bg-slate-100 dark:bg-zinc-800 px-2.5 py-1 rounded-lg font-bold">' + escapeHtml(s.total_records) + ' orang</span></td>' +
                    '<td class="p-3.5 text-center"><div class="flex flex-wrap items-center justify-center gap-1.5">' +
                    '<button onclick="renameSession(' + Number(s.id) + ')" class="bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 hover:bg-indigo-100 px-2.5 py-1 rounded-lg text-[11px] cursor-pointer"><i class="fa-solid fa-pen mr-1"></i> Namakan</button>' +
                    '<button onclick="viewSessionDetail(' + Number(s.id) + ')" class="bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 px-2.5 py-1 rounded-lg text-[11px] cursor-pointer"><i class="fa-solid fa-eye mr-1"></i> Detail</button>' +
                    '<button onclick="softDeleteSession(' + Number(s.id) + ')" class="bg-rose-500 hover:bg-rose-600 text-white px-2.5 py-1 rounded-lg text-[11px] cursor-pointer">Padam</button></div></td>';
                tbody.appendChild(tr);
            });
        }

        function renderSessionHistory() {
            renderSessionHistoryBase();
            scheduleSessionHistoryStats();
        }

        async function renameSession(sessionId) {
            const session = allSessionsData.find(s => Number(s.id) === Number(sessionId));
            if (!session) return;
            const suggested = session.session_name || ('Sesi #' + session.id + ' - ' + String(session.session_date || '').slice(0, 10));
            const sessionName = prompt('Masukkan nama sesi (maksimum 120 aksara):', suggested);
            if (sessionName === null) return;
            const cleanName = sessionName.trim();
            if (!cleanName || cleanName.length > 120) { showToast('Nama sesi wajib diisi dan maksimum 120 aksara.'); return; }
            try {
                const response = await fetch('index.php?action=rename_session', {method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token': typeof USMSMART_CSRF_TOKEN !== 'undefined' ? USMSMART_CSRF_TOKEN : ''}, body:JSON.stringify({id:Number(sessionId),session_name:cleanName})});
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Nama sesi gagal disimpan.');
                showToast(result.message || 'Nama sesi berjaya dikemas kini.');
                await loadSessionHistory();
            } catch (err) { showToast(err.message || 'Nama sesi gagal disimpan.'); }
        }

        function viewSessionDetail(sessionId) {
            let session = allSessionsData.find(s => s.id == sessionId);
            if (!session) return;
            document.getElementById('modal-session-title').innerText = 'Senarai Pelajar - Sesi #' + session.id;
            let tbody = document.getElementById('modal-session-tbody');
            tbody.innerHTML = '';
            try {
                JSON.parse(session.details).forEach((st, idx) => {
                    tbody.innerHTML += '<tr class="hover:bg-slate-50/50 dark:hover:bg-zinc-900/50"><td class="p-3">' + (idx + 1) + '</td><td class="p-3 font-medium">' + escapeHtml(st.name) + '</td><td class="p-3 font-mono">' + escapeHtml(st.matric) + '</td></tr>';
                });
            } catch (_) {}
            document.getElementById('session-detail-modal').classList.remove('hidden');
        }

        function closeDetailModal() { document.getElementById('session-detail-modal').classList.add('hidden'); }

        async function softDeleteSession(sessionId) {
            try {
                let res = await fetch('index.php?action=soft_delete_session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: sessionId }) });
                let result = await res.json();
                if (!result.success) throw new Error(result.message);
                showToast('Sesi dipadam dan pelajar dilepaskan.');
                loadSessionHistory();
            } catch (err) { showToast('Ralat memadam sesi.'); }
        }

        function downloadCSV() {
            if (currentFetchedRecords.length === 0) { showToast('Tiada data.'); return; }
            let csv = 'ID,Name,Matric,School,DateJoined,ExpiryDate,USMUid,SerialNo,Policy\n';
            currentFetchedRecords.forEach(r => { csv += '"' + r.id + '","' + r.name + '","' + r.matric + '","' + r.school + '","' + r.dateJoined + '","' + r.expiryDate + '","' + r.usmUid + '","' + r.serialNo + '","' + r.policy + '"\n'; });
            let blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.setAttribute('download', 'usmsmart_records.csv');
            a.click();
        }

        function downloadReportExcel() {
            const filter = (document.getElementById('session-history-filter')?.value || '').trim().toLowerCase();
            const source = Array.isArray(allSessionsData) ? allSessionsData : [];
            const filteredSessions = source.filter(s => [s.id, s.session_name, s.session_date, s.total_records]
                .some(value => String(value ?? '').toLowerCase().includes(filter)));
            if (filteredSessions.length === 0) { showToast(filter ? 'Tiada sesi sepadan dengan penapis untuk dieksport.' : 'Tiada data sejarah untuk dieksport.'); return; }
            const csvCell = value => '"' + String(value ?? '').replace(/"/g, '""') + '"';
            const rows = [['Session ID', 'Session Name', 'Session Date', 'Total Records']];
            filteredSessions.forEach(session => rows.push([
                session.id,
                session.session_name || ('Sesi #' + session.id),
                session.session_date || '',
                session.total_records ?? 0
            ]));
            const csv = '\uFEFF' + rows.map(row => row.map(csvCell).join(',')).join('\r\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.setAttribute('download', 'USMSmart_UDA_Sessions_Filtered_' + new Date().toISOString().slice(0, 10) + '.csv');
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            showToast('Eksport berjaya: ' + filteredSessions.length + ' sesi.');
        }

        function showToast(msg) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const message = String(msg ?? 'Something went wrong. Please try again.');
            const t = document.createElement('div');
            const isError = /error|ralat|gagal|failed|denied|ditolak|tidak sah|invalid|unauthorized|forbidden|cannot|could not|tidak dapat|tidak dibenarkan/i.test(message);
            t.className = 'pointer-events-auto flex items-start gap-3 w-full max-w-lg rounded-2xl border px-5 py-4 text-sm font-medium shadow-2xl page-transition ' +
                (isError
                    ? 'border-red-200 bg-white text-slate-900 dark:border-red-900 dark:bg-[#18181b] dark:text-white'
                    : 'border-indigo-200 bg-white text-slate-900 dark:border-indigo-900 dark:bg-[#18181b] dark:text-white');
            t.setAttribute('role', isError ? 'alert' : 'status');
            const icon = document.createElement('span');
            icon.className = 'mt-0.5 shrink-0 flex h-9 w-9 items-center justify-center rounded-full ' +
                (isError ? 'bg-red-100 text-red-600 dark:bg-red-950 dark:text-red-300' : 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300');
            icon.innerHTML = isError ? '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>' : '<i class="fa-solid fa-circle-info" aria-hidden="true"></i>';
            const textWrap = document.createElement('div');
            textWrap.className = 'min-w-0 flex-1 pt-1';
            const heading = document.createElement('div');
            heading.className = 'mb-1 font-bold';
            heading.textContent = isError ? 'Notice / Error' : 'Notice';
            const body = document.createElement('div');
            body.className = 'font-normal leading-relaxed break-words';
            body.textContent = message;
            textWrap.append(heading, body);
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'ml-1 rounded-lg px-2 py-1 text-lg leading-none text-slate-500 hover:bg-slate-100 dark:hover:bg-zinc-800';
            close.setAttribute('aria-label', 'Dismiss notice');
            close.textContent = '×';
            close.addEventListener('click', () => t.remove());
            t.append(icon, textWrap, close);
            container.appendChild(t);
            setTimeout(() => { if (t.isConnected) t.remove(); }, isError ? 6000 : 4000);
        }
    
        /* --- Dialog keyboard layer ---------------------------------------
           Every in-page dialog carries data-modal-close="<close function>".
           The observer reacts to the same classList toggle the open and close
           functions already use, so Escape, focus entry, the Tab trap and focus
           return are added once instead of in each of those handlers. The user
           manual manages its own focus and has no data-modal-close, so it is
           deliberately left out. */
        (function () {
            if (window.__usmDialogKeys) return;
            window.__usmDialogKeys = true;
            const stack = [];
            const FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]):not([type="hidden"]),select:not([disabled]),textarea:not([disabled]),[contenteditable="true"],[tabindex]:not([tabindex="-1"])';

            function visible(node) {
                return node.getClientRects().length > 0 && !node.closest('[hidden]') && node.getAttribute('aria-hidden') !== 'true';
            }
            function focusable(dialog) {
                return Array.prototype.filter.call(dialog.querySelectorAll(FOCUSABLE), visible);
            }
            function onShow(dialog) {
                if (stack.indexOf(dialog) !== -1) return;
                dialog.__usmReturnFocus = stack.length ? null : document.activeElement;
                stack.push(dialog);
                const first = focusable(dialog)[0];
                if (first) first.focus({ preventScroll: true });
                else {
                    dialog.setAttribute('tabindex', '-1');
                    dialog.focus({ preventScroll: true });
                }
            }
            function onHide(dialog) {
                const index = stack.indexOf(dialog);
                if (index === -1) return;
                stack.splice(index, 1);
                const back = dialog.__usmReturnFocus;
                dialog.__usmReturnFocus = null;
                if (back && back.isConnected && typeof back.focus === 'function') back.focus({ preventScroll: true });
            }
            function close(dialog) {
                const name = dialog.getAttribute('data-modal-close');
                const fn = name ? window[name] : null;
                if (typeof fn === 'function') return fn();
                dialog.classList.add('hidden');
                onHide(dialog);
            }
            document.addEventListener('keydown', function (event) {
                const dialog = stack[stack.length - 1];
                if (!dialog) return;
                if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    close(dialog);
                    return;
                }
                if (event.key !== 'Tab') return;
                const list = focusable(dialog);
                if (!list.length) { event.preventDefault(); return; }
                const first = list[0];
                const last = list[list.length - 1];
                const active = document.activeElement;
                if (event.shiftKey && (active === first || !dialog.contains(active))) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && (active === last || !dialog.contains(active))) {
                    event.preventDefault();
                    first.focus();
                }
            }, true);

            Array.prototype.forEach.call(document.querySelectorAll('[data-modal-close]'), function (dialog) {
                new MutationObserver(function () {
                    const shown = !dialog.classList.contains('hidden') && !dialog.hasAttribute('hidden');
                    if (shown) onShow(dialog); else onHide(dialog);
                }).observe(dialog, { attributes: true, attributeFilter: ['class', 'hidden', 'style'] });
            });
        })();
