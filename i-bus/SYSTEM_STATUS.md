# e-campusBus System Status (Features & Fixes)

_Version: e-campusBus v0.1.0_

## Apa Sistem Boleh Buat Sekarang
- ✅ Tempahan Pelajar: Pilih laluan, tarikh (30 hari), masa, grid tempat duduk, ringkasan harga, semakan ketersediaan pemandu.
- ✅ Notifikasi Pemandu Masa Nyata: Dashboard dengan penapis Pending/Accepted/Rejected/All, kad butiran lengkap, terima/tolak dengan alasan, polling pantas (~5s) tanpa muat semula penuh.
- ✅ Tugasan Bas Pemandu: Pemandu mesti tetapkan satu bas sebelum menerima notifikasi; boleh tukar bas melalui halaman Assign Bus.
- ✅ Statistik & Audit: Kad kiraan status pada notifikasi; log tindakan di `activity_logs` untuk jejak terima/tolak.
- ✅ Reset Data (Ujian): Admin boleh kosongkan bookings + notifications + activity_logs serentak dari Reset Data.
- ✅ Pengurusan Bas Admin: Tambah/kemas kini/buang bas dengan semakan nombor unik, kapasiti > 0, satu pemandu satu bas; statistik bas dipaparkan.
- ✅ Navigasi Konsisten: Pautan notifikasi wujud di semua halaman pemandu; navbar pelajar diperkemas.

## Senarai Baikan Utama (Smooth Operation)
- ✅ Unik Nombor Bas: Semakan pendua + tangkap ralat DB; normalisasi nombor (uppercase/trim); elak undefined warnings dengan nilai lalai.
- ✅ Validasi Input Bas: Kapasiti mesti >0; pemandu tunggal per bas; mesej ralat mesra dipaparkan dalam alert.
- ✅ Reset Gabungan: Satu tindakan untuk padam bookings, notifications, activity_logs (khas ujian/pembersihan data).
- ✅ Notifikasi Pantas: Tukar auto-refresh kepada polling ~5s dengan kemas kini seksyen sahaja (tanpa hilang scroll); stat dikemas kini serentak.
- ✅ Kunci Notifikasi Untuk Pemandu Tanpa Bas: Halaman notifikasi dikunci sehingga bas ditetapkan, elak “ghost drivers”.
- ✅ Navigasi Pemandu: Link Notifications ditambah pada semua sidebar pemandu untuk akses pantas.
- ✅ Testing Helpers: Skrip ujian/diagnostik tersedia (check_database, check_users, test_login_detailed, test_bus_assignment, dll.).
- ✅ Sanitasi & Keselamatan: Prepared statements, htmlspecialchars pada paparan, semakan role pada halaman, input divalidasi di API.

## Fail Rujukan Penting
- 📘 Penggunaan: [USAGE_GUIDE.md](USAGE_GUIDE.md)
- 🛠️ Penyelenggaraan Admin: [ADMIN_MAINTENANCE_GUIDE.md](ADMIN_MAINTENANCE_GUIDE.md)
- 🧾 Ringkasan Sistem: [NOTIFICATION_SYSTEM_SUMMARY.txt](NOTIFICATION_SYSTEM_SUMMARY.txt)

## Nota Ringkas
- ⚙️ Jalankan [public/setup/setup_notifications.php](public/setup/setup_notifications.php) jika jadual notifikasi belum wujud.
- 🚌 Pastikan setiap pemandu ada bas ditugaskan supaya notifikasi dihantar.
- 🧹 Gunakan Reset Data hanya di persekitaran ujian kerana ia memadam data utama.
