# 🚌 e-campusBus System

_Version: e-campusBus v0.1.0_

> Sistem pengurusan bas kampus yang komprehensif dengan notifikasi masa nyata, tempahan pelajar, dan pentadbiran lengkap.

---

## 📖 Tentang Projek

**e-campusBus** adalah sistem pengurusan pengangkutan kampus yang menyediakan penyelesaian lengkap untuk tempahan bas, notifikasi pemandu masa nyata, dan pentadbiran armada. Sistem ini direka untuk memudahkan komunikasi antara pelajar, pemandu, dan pentadbir kampus.

### ✨ Ciri-ciri Utama

#### 🎓 Untuk Pelajar
- ✅ Tempahan perjalanan interaktif dengan kalendar (30 hari hadapan)
- ✅ Pemilihan masa dan tempat duduk secara visual (grid A01-E10)
- ✅ Semakan ketersediaan pemandu secara langsung
- ✅ Ringkasan tambang dan laluan secara automatik
- ✅ Papan pemuka peribadi dengan sejarah tempahan

#### 🚗 Untuk Pemandu
- ✅ Dashboard notifikasi masa nyata (~5 saat polling)
- ✅ Terima/tolak tempahan dengan satu klik
- ✅ Penapis status (Pending/Accepted/Rejected/All)
- ✅ Butiran lengkap pelajar (nama, telefon, e-mel)
- ✅ Statistik prestasi dan aktiviti
- ✅ Sistem tugasan bas (one-driver-one-bus)

#### 👨‍💼 Untuk Admin
- ✅ Pengurusan pengguna (pelajar, pemandu, admin)
- ✅ Pengurusan bas dengan validasi keunikan
- ✅ Pengurusan laluan dan jadual
- ✅ Laporan dan analitik sistem
- ✅ Reset data untuk ujian/pembersihan
- ✅ Log audit lengkap semua aktiviti

---

## 🛠️ Teknologi

### Backend
- **PHP 8.2.12** - Server-side logic
- **MySQL 5.7+** - Database management
- **PDO** - Database abstraction layer

### Frontend
- **HTML5, CSS3, JavaScript** - Core technologies
- **Bootstrap 5.3.0** - Responsive framework
- **Font Awesome 6.4.0** - Icon library
- **Chart.js** - Data visualization (ready)

### Keselamatan
- ✅ Prepared statements (SQL injection proof)
- ✅ Session-based authentication
- ✅ Role-based access control (RBAC)
- ✅ Input validation & output sanitization
- ✅ Audit trail logging

---

## 📦 Pemasangan

### Prasyarat
- XAMPP/WAMP (Apache + MySQL)
- PHP 8.2 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Browser moden (Chrome, Firefox, Edge, Safari)

### Langkah Pemasangan

1. **Clone repositori ini**
```bash
git clone https://github.com/username/e-campusbus.git
cd e-campusbus
```

2. **Setup database**
```bash
# Login ke MySQL
mysql -u root -p

# Cipta database
CREATE DATABASE bus_management CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

# Import skema
mysql -u root -p bus_management < public/sql/bus_management.sql
```

3. **Konfigurasi sambungan database**
   
   Edit `public/config/database.php`:
```php
$host = "localhost";
$dbname = "bus_management";
$username = "root";
$password = ""; // Tambah kata laluan jika ada
```

4. **Setup jadual notifikasi**
   
   Layari: `http://localhost/i-bus/public/setup/setup_notifications.php`
   
   Atau import manual:
```bash
mysql -u root -p bus_management < public/sql/create_notification_system.sql
```

5. **Akses sistem**
   
   Buka browser dan layari: `http://localhost/i-bus/public/login.php`

### Akaun Default
```
Student:
Username: student1
Password: password123

Driver:
Username: driver1
Password: password123

Admin:
Username: admin1
Password: password123
```

> ⚠️ **Penting**: Tukar kata laluan default selepas log masuk pertama!

---

## 📚 Dokumentasi

Projek ini dilengkapi dengan dokumentasi lengkap:

- 📘 **[USAGE_GUIDE.md](USAGE_GUIDE.md)** - Panduan penggunaan untuk pelajar & pemandu
- 🛠️ **[ADMIN_MAINTENANCE_GUIDE.md](ADMIN_MAINTENANCE_GUIDE.md)** - Panduan penyelenggaraan admin
- 🗂️ **[PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md)** - Struktur folder dan organisasi
- 📊 **[SYSTEM_STATUS.md](SYSTEM_STATUS.md)** - Status sistem dan senarai baikan
- 💾 **[DATABASE_SETUP.md](DATABASE_SETUP.md)** - Setup database dan migrasi
- 🎉 **[NOTIFICATION_SYSTEM_SUMMARY.txt](NOTIFICATION_SYSTEM_SUMMARY.txt)** - Ringkasan sistem notifikasi

---

## 🚀 Penggunaan Pantas

### Buat Tempahan (Pelajar)
1. Log masuk sebagai pelajar
2. Pergi ke **Make Booking**
3. Pilih laluan, tarikh, masa, dan tempat duduk
4. Klik **Confirm Booking**
5. Notifikasi akan dihantar ke pemandu

### Terima Tempahan (Pemandu)
1. Log masuk sebagai pemandu
2. Pastikan bas sudah ditetapkan di **Assign Bus**
3. Pergi ke **Notifications**
4. Lihat tempahan baharu dalam kad notifikasi
5. Klik **Accept** atau **Reject** (dengan alasan)

### Urus Sistem (Admin)
1. Log masuk sebagai admin
2. **Manage Users** - Tambah/edit/buang pengguna
3. **Manage Buses** - Urus armada bas
4. **Manage Routes** - Tetapkan laluan dan jadual
5. **Reports** - Lihat laporan dan analitik
6. **Reset Data** - Kosongkan data ujian (guna berhati-hati!)

---

## 📁 Struktur Projek

```
i-bus/
├── public/
│   ├── admin/              # Halaman pentadbir
│   ├── student/            # Halaman pelajar
│   ├── driver/             # Halaman pemandu
│   ├── api/                # API endpoints
│   ├── config/             # Konfigurasi sistem
│   ├── includes/           # Helper functions & auth
│   ├── assets/             # CSS, JS, images
│   ├── sql/                # Database schemas
│   ├── tests/              # Skrip diagnostik
│   └── setup/              # Setup scripts
├── README.md               # Fail ini
├── USAGE_GUIDE.md          # Panduan pengguna
├── ADMIN_MAINTENANCE_GUIDE.md
├── SYSTEM_STATUS.md
├── PROJECT_STRUCTURE.md
└── DATABASE_SETUP.md
```

---

## 🔧 Penyelesaian Masalah

### Notifikasi tidak muncul?
- ✅ Pastikan jadual `booking_notifications` dan `activity_logs` wujud
- ✅ Semak pemandu ada bas ditetapkan di **Assign Bus**
- ✅ Pastikan polling API aktif (lihat Console browser)

### Ralat database?
- ✅ Semak sambungan di `public/config/database.php`
- ✅ Jalankan skrip diagnostik: `public/tests/check_database.php`
- ✅ Import semula skema jika perlu

### Ralat "Duplicate bus number"?
- ✅ Nombor bas mesti unik (case-insensitive)
- ✅ Sistem akan paparkan mesej ralat mesra
- ✅ Cuba nombor bas yang berbeza

---

## 🧪 Testing

Skrip ujian tersedia di folder `public/tests/`:

- 🔍 `check_database.php` - Semak sambungan database
- 👥 `check_users.php` - Sahkan akaun pengguna
- 🧾 `test_login_detailed.php` - Uji proses log masuk
- 🚌 `test_bus_assignment.php` - Semak tugasan bas pemandu

---

## 🎯 Roadmap & Peningkatan Masa Depan

- [ ] Notifikasi e-mel/SMS untuk tempahan
- [ ] Integrasi pembayaran dalam talian
- [ ] Aplikasi mobile (iOS & Android)
- [ ] Penjejakan GPS bas secara langsung
- [ ] Analitik lanjutan dan laporan eksport
- [ ] Multi-bahasa (BM/EN)
- [ ] API awam untuk integrasi pihak ketiga

---

## 📝 Changelog

### v0.1.0 (Januari 2026)
- ✅ Sistem tempahan asas dengan kalendar & grid tempat duduk
- ✅ Notifikasi masa nyata (polling ~5s)
- ✅ Sistem terima/tolak pemandu dengan alasan
- ✅ Tugasan bas one-driver-one-bus
- ✅ Pengurusan admin lengkap (users/buses/routes)
- ✅ Audit trail & activity logs
- ✅ Reset data gabungan untuk ujian
- ✅ Validasi input & keselamatan
- ✅ Dokumentasi lengkap

---

## 🤝 Kontribusi

Sumbangan dan cadangan dialu-alukan! Sila:

1. Fork repositori ini
2. Cipta branch baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buka Pull Request

---

## 📄 Lesen

Projek ini adalah **assignment kerja kumpulan** untuk tujuan akademik.

- 🎓 Institusi: [Nama Universiti/Kolej]
- 📚 Kursus: [Nama Kursus/Kod]
- 👥 Kumpulan: 3 Ahli
- 📅 Semester: [Semester/Tahun]

---

## 👨‍💻 Pembangunan

Dibangunkan oleh **Kumpulan 3 Ahli** untuk Assignment:

### Ahli Pasukan

**Ahli 1: [Nama Ahli 1]**
- 📧 Email: [email1@example.com]
- 🐙 GitHub: [github.com/member1]

**Ahli 2: [Nama Ahli 2]**
- 📧 Email: [email2@example.com]
- 🐙 GitHub: [github.com/member2]

**Ahli 3: [Nama Ahli 3]**
- 📧 Email: [email3@example.com]
- 🐙 GitHub: [github.com/member3]

---

## 🙏 Penghargaan

- Bootstrap untuk framework responsive
- Font Awesome untuk ikon-ikon
- Chart.js untuk visualisasi data
- Komuniti PHP & MySQL

---

## 📞 Sokongan

Untuk sokongan teknikal atau pertanyaan:

1. Lihat dokumentasi terlebih dahulu
2. Semak isu sedia ada di GitHub Issues
3. Cipta isu baharu dengan butiran lengkap
4. Hubungi [aimanyf06@gmail.com] untuk sokongan segera

---

<div align="center">

**Dibuat dengan ❤️ oleh Kumpulan 3 Ahli untuk Assignment Akademik**

🎓 Projek Assignment | 👥 Kerja Kumpulan | 💻 e-campusBus System

⭐ Jika projek ini membantu anda, sila beri bintang!

</div>
