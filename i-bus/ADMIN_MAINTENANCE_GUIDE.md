# e-campusBus Admin Maintenance Guide

_Version: e-campusBus v0.1.0_

## Skop & Prasyarat
- Untuk pentadbir sistem yang menyelenggara aplikasi, data, dan pengguna.
- Persekitaran: XAMPP Apache + MySQL. Pangkalan data: `bus_management` dari [sql/i_bus_system.sql](sql/i_bus_system.sql).
- Konfigurasi DB berada di [public/config/database.php](public/config/database.php) dan laluan asas di [public/config/paths.php](public/config/paths.php).

## Persediaan / Pulih Semula
1) Import skema asas: [sql/i_bus_system.sql](sql/i_bus_system.sql).
2) Cipta jadual notifikasi (jika belum): jalankan [public/setup/setup_notifications.php](public/setup/setup_notifications.php) dan semak respons `success:true`.
3) Semak akaun admin/driver/pelajar di [public/admin/manage_users.php](public/admin/manage_users.php). Gunakan hash bcrypt untuk kata laluan.
4) Sahkan sambungan DB melalui [public/tests/check_database.php](public/tests/check_database.php) dan pengguna contoh melalui [public/tests/check_users.php](public/tests/check_users.php).

## Tugas Rutin Admin
- **Pengguna**: [public/admin/manage_users.php](public/admin/manage_users.php)
  - Cipta/kemas kini peranan (admin/driver/student), aktifkan/nyahaktifkan akaun, pastikan pemandu unik.
- **Bas**: [public/admin/manage_buses.php](public/admin/manage_buses.php)
  - Nombor bas mestilah unik (case-insensitive); kapasiti > 0; satu pemandu hanya satu bas. Ralat pendua dipaparkan dengan jelas. Statistik tersedia pada halaman sama.
- **Laluan**: [public/admin/manage_routes.php](public/admin/manage_routes.php)
  - Kekalkan laluan aktif agar tempahan boleh dibuat. Pastikan laluan memaut ke pemandu yang mempunyai bas.
- **Laporan**: [public/admin/reports.php](public/admin/reports.php)
  - Guna untuk semakan aktiviti/booking (bergantung pada implementasi semasa).
- **Reset Data**: [public/admin/reset_data.php](public/admin/reset_data.php)
  - Butang gabungan untuk kosongkan bookings, notifications, activity_logs. Guna hanya pada persekitaran ujian kerana operasi ini destruktif.

## Penyelenggaraan Sistem Notifikasi
- Jadual penting: `booking_notifications`, `activity_logs`, `bookings`, `users`, `buses`.
- Peraturan operasi:
  - Pemandu mesti mempunyai bas (lihat [public/driver/assign_bus.php](public/driver/assign_bus.php)); jika tidak, mereka tidak menerima notifikasi.
  - Terima tempahan → `bookings.status = confirmed`, `booking_notifications.status = accepted`, `responded_at` direkod.
  - Tolak tempahan → `bookings.status = pending`, `booking_notifications.status = rejected`, `response_reason` wajib, `responded_at` direkod.
- Jika kad notifikasi tidak muncul:
  - Sahkan driver aktif dan mempunyai bas.
  - Semak API [public/api/check_notifications.php](public/api/check_notifications.php) boleh dicapai tanpa ralat.
  - Semak jadual `booking_notifications` untuk rekod baharu.

## Diagnostik & Ujian
- Skrip ujian di [public/tests](public/tests):
  - [public/tests/check_database.php](public/tests/check_database.php) – ping DB dan sambungan.
  - [public/tests/check_users.php](public/tests/check_users.php) – semak akaun contoh.
  - [public/tests/test_login_detailed.php](public/tests/test_login_detailed.php) – laluan login.
  - [public/tests/test_bus_assignment.php](public/tests/test_bus_assignment.php) – status tugasan bas pemandu.
  - [public/tests/generate_demo_passwords.php](public/tests/generate_demo_passwords.php) – jana hash bcrypt untuk kata laluan demo.
- Jangan tinggalkan folder `tests` terbuka pada produksi; lindungi dengan .htaccess atau pindahkan keluar dari akar web.

## Sandaran & Pemulihan Data
- Sandaran pangkalan data:
```bash
mysqldump -u root bus_management > backup_bus_management.sql
```
- Pulih semula sandaran:
```bash
mysql -u root bus_management < backup_bus_management.sql
```
- Lakukan sandaran sebelum menjalankan reset data atau kemas kini skema.

## Amalan Operasi Baik
- Gunakan kata laluan kuat untuk semua akaun; tukar lalai `student1/driver1` selepas ujian.
- Kekalkan hak akses minimum: hanya admin boleh akses halaman `admin/`.
- Semak log aktiviti dengan menapis `activity_logs` bagi tindakan `booking_accepted` atau `booking_rejected` jika audit diperlukan.
- Pastikan fail konfigurasi tidak boleh diakses secara umum; semak tetapan pelayan produksi.
- Jadualkan semakan berkala pada statistik bas/route untuk mengelak pemandu tanpa bas.
