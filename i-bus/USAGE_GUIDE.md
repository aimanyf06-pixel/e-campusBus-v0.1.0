# e-campusBus Usage Guide (Users)

_Version: e-campusBus v0.1.0_

## Audience & Prerequisites
- Untuk pelajar dan pemandu yang menggunakan sistem harian.
- Server lokal: XAMPP Apache + MySQL. Database: `bus_management` dari [sql/i_bus_system.sql](sql/i_bus_system.sql).
- Akaun contoh: student1/password123, driver1/password123. Admin perlu cipta akaun tambahan melalui [public/admin/manage_users.php](public/admin/manage_users.php).
- Pastikan jadual notifikasi wujud (lihat seksyen Notification Flow). Jika belum, jalankan [public/setup/setup_notifications.php](public/setup/setup_notifications.php).

## Ciri Utama
- Pelajar: tempah perjalanan dengan pemilih tarikh (30 hari), masa, grid tempat duduk, ringkasan harga, dan semakan ketersediaan pemandu.
- Pemandu: papan pemuka notifikasi dengan penapis Pending/Accepted/Rejected/All, kad butiran lengkap, terima atau tolak dengan alasan.
- Notifikasi masa nyata: polling 5s tanpa muat semula penuh; statistik dikemas kini secara automatik.
- Audit: semua tindakan terima/tolak direkod dalam `activity_logs`.
- Ketersambungan: hanya pemandu yang ada bas ditetapkan akan menerima notifikasi.

## Aliran Cepat Penggunaan
1) Import skema: import [sql/i_bus_system.sql](sql/i_bus_system.sql) melalui phpMyAdmin atau `mysql -u root < sql/i_bus_system.sql`.
2) Jalankan setup notifikasi sekali (jika jadual belum ada): lawat [public/setup/setup_notifications.php](public/setup/setup_notifications.php) dan pastikan respons `success:true`.
3) Log masuk di [public/login.php](public/login.php) menggunakan peranan masing-masing.

## Aliran Pelajar (Tempahan)
1) Buka [public/student/make_booking.php](public/student/make_booking.php).
2) Pilih laluan, tarikh (dalam 30 hari), masa, dan tempat duduk pada grid.
3) Semak ringkasan (harga, laluan, pemandu tersedia) kemudian sahkan tempahan.
4) Sistem mencipta tempahan `pending` dan notifikasi untuk pemandu yang memiliki bas.
5) Semak status tempahan di [public/student/view_booking.php](public/student/view_booking.php); status berubah kepada `confirmed` selepas pemandu terima, atau kekal/semula `pending` jika ditolak.

## Aliran Pemandu (Notifikasi)
1) Pastikan bas ditetapkan di [public/driver/assign_bus.php](public/driver/assign_bus.php). Tanpa bas, notifikasi dikunci.
2) Buka [public/driver/notifications.php](public/driver/notifications.php).
3) Gunakan penapis status; kad menunjukkan laluan, pelajar, masa, tempat duduk, tambang, dan status.
4) Terima tempahan → tempahan menjadi `confirmed`, kad bertukar hijau, statistik dikemas kini.
5) Tolak tempahan → masukkan alasan; tempahan kembali `pending` untuk pemandu lain, kad bertukar merah dengan alasan dipaparkan.
6) Laman dikemas kini setiap ~5 saat tanpa muat semula penuh; statistik (Pending/Accepted/Rejected/Total) dikemas kini serentak.

## URL Penting
- Log masuk: [public/login.php](public/login.php)
- Pelajar: [public/student/dashboard.php](public/student/dashboard.php), [public/student/make_booking.php](public/student/make_booking.php), [public/student/view_booking.php](public/student/view_booking.php)
- Pemandu: [public/driver/dashboard.php](public/driver/dashboard.php), [public/driver/notifications.php](public/driver/notifications.php), [public/driver/assign_bus.php](public/driver/assign_bus.php)

## Nota Penggunaan
- Hanya satu bas per pemandu; tugasan semula bas dibenarkan melalui halaman Assign Bus.
- Notifikasi memerlukan sambungan pangkalan data yang sihat; jika tiada kad muncul, semak jadual `booking_notifications` dan status bas pemandu.
- Semua input telah dipra-sanitasi, tetapi pengguna patut elak medan kosong/panjang melampau.
- Untuk paparan mudah alih, UI responsif; gunakan mod potret jika ada isu ruang.
