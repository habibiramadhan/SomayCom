# SomayCom - POS & Ecommerce Distributor Somay

**Dibuat oleh: Habibi Ramadhan**

## Deskripsi
SomayCom adalah aplikasi Point of Sale (POS) dan Ecommerce untuk distributor Somay, dirancang untuk penjualan B2C dengan fitur guest checkout dan area pengiriman khusus Tangerang Selatan. Aplikasi ini mendukung manajemen produk, kategori, stok, pesanan, area pengiriman, serta dashboard analitik dan laporan penjualan.

---

## Fitur Utama

### Ecommerce (Frontend)
- **Beranda**: Menampilkan produk unggulan, statistik, dan informasi toko.
- **Katalog Produk**: Filter berdasarkan kategori, pencarian, dan urutkan produk.
- **Detail Produk**: Informasi lengkap, gambar, harga, dan stok.
- **Keranjang Belanja**: Tambah, edit, dan hapus produk.
- **Checkout**: Formulir pengiriman, pilih area, metode pembayaran (COD/Transfer), validasi minimum order.
- **Lacak Pesanan**: Cek status pesanan dengan nomor order.
- **Notifikasi**: Pesan sukses/gagal, validasi form, dan info stok.

### Admin Panel (Backend)
- **Dashboard**: Statistik penjualan, produk terlaris, pelanggan, grafik harian/mingguan.
- **Manajemen Produk**: CRUD produk, upload gambar, stok, kategori.
- **Manajemen Kategori**: CRUD kategori produk.
- **Manajemen Stok**: Riwayat pergerakan stok (in/out/adjustment), laporan stok menipis.
- **Manajemen Pesanan**: Lihat, proses, update status, konfirmasi pembayaran, cetak invoice.
- **Manajemen Area Pengiriman**: CRUD area, ongkir, estimasi waktu.
- **Laporan**: Penjualan, produk, pelanggan, ekspor data.
- **Pengaturan Aplikasi**: Info toko, kontak, minimal order, gratis ongkir, dsb.
- **Manajemen Admin**: Multi user, role & permission (super admin, admin, operator).
- **Login Admin**: Sistem login, session, remember me, timeout.

---

## Struktur Direktori

```
├── index.php                # Halaman utama (frontend)
├── config.php               # Konfigurasi aplikasi & koneksi database
├── deskrpsi_aplikasi.md     # Skema database & sample data
├── pages/                   # Halaman frontend (produk, checkout, lacak pesanan, dsb)
├── includes/                # Fungsi cart, ajax, dsb
├── models/                  # Model PHP (Product, Order, Category, dsb)
├── controllers/             # Controller untuk produk & kategori
├── helpers/                 # Helper untuk stok & kategori
├── uploads/                 # Upload gambar produk, pembayaran, kategori
├── assets/                  # CSS, JS, gambar statis
├── api/                     # Endpoint API (settings, dsb)
├── admin/                   # Panel admin (dashboard, produk, pesanan, laporan, dsb)
```

---

## Instalasi & Setup

1. **Clone repository**
   ```bash
   git clone <repo-url>
   cd SomayCom
   ```
2. **Setup Database**
   - Import skema dan data awal dari `deskrpsi_aplikasi.md` ke MySQL.
   - Pastikan database bernama `pos_somay_ecommerce`.
3. **Konfigurasi**
   - Edit `config.php` untuk set host, user, password, dan nama database.
   - Atur path upload jika perlu.
4. **Akses Aplikasi**
   - Frontend: buka `http://localhost/SomayCom/index.php`
   - Admin: buka `http://localhost/SomayCom/admin/login.php`
   - Login admin default: 
     - Username: `admin`
     - Password: `admin123` *(ubah password setelah login)*
5. **Folder Upload**
   - Pastikan folder `uploads/` dan subfoldernya (`products/`, `categories/`, `payments/`) writable (CHMOD 755/775).

---

## Dependensi
- PHP >= 7.4
- MySQL/MariaDB
- TailwindCSS (CDN)
- FontAwesome (CDN)
- XAMPP/LAMPP/WAMP (untuk lokal)

---

## Skema Database
Lihat file `deskrpsi_aplikasi.md` untuk detail tabel, relasi, dan sample data.

---

## Fitur Keamanan
- Validasi input & sanitasi data
- Hash password (bcrypt)
- Session timeout & remember me
- Role & permission admin
- Proteksi akses admin

---

## Pengembangan & Customisasi
- Struktur MVC sederhana (models, controllers, views)
- Mudah dikembangkan untuk fitur baru (voucher, notifikasi, dsb)
- API endpoint untuk integrasi eksternal

---

## Kontak & Lisensi
- Email: habibiramadhan.dev@gmail.com
- WhatsApp: -
- Hak cipta © 2024 Habibi Ramadhan

Lisensi: MIT 