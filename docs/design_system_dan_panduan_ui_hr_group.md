# Dokumen Panduan Desain Sistem & UI/UX (Design System Guide)
## HR Group — Human Resource, Attendance, & Payroll System

---

| Atribut Dokumen | Informasi |
| :--- | :--- |
| **Nama Sistem** | HR Group Enterprise HRIS |
| **Repositori Sumber** | [`hrghrd`](file:///Users/lrmcorporation/Documents/Website/hrghrd) |
| **Versi Dokumen** | 1.0.0 (Baseline Design System) |
| **Framework CSS** | Tailwind CSS v4 (`@tailwindcss/vite` ^4.3.3) |
| **Filosofi Desain** | Modern Enterprise, Kerapian Data-Centric, Strictly "No AI Slop" |
| **Zona Waktu Acuan** | `Asia/Jakarta` (WIB / UTC+7) |

---

## 1. Pendahuluan & Filosofi Desain

### 1.1 Tujuan Panduan
Dokumen ini menjadi standar acuan tunggal (*Single Source of Truth*) bagi arsitektur antarmuka, token visual, tata letak, dan konvensi penulisan komponen pada sistem informasi HR Group. Tujuannya adalah memastikan setiap modul baru yang dikembangkan di masa mendatang tetap konsisten, berkinerja tinggi, mudah dirawat, dan memiliki standar visual enterprise berkelas dunia.

### 1.2 Prinsip "No AI Slop"
Sistem HR Group mengadopsi prinsip desain korporat modern (terinspirasi dari platform seperti Linear, Stripe, dan Vercel) yang menjunjung tinggi efisiensi kerja tim HR:
1. **Hirarki Visual yang Disengaja (*Purposeful Hierarchy*)**: Tidak ada elemen dekoratif tanpa fungsi. Penekanan visual difokuskan pada data esensial: jam kerja, titik koordinat cabang, status persetujuan, dan nominal akuntansi.
2. **Eliminasi Gradien Berlebihan**: Menghindari gradien pelangi atau neon mencolok. Penggunaan gradien dibatasi pada elemen branding halus (*subtle mesh / dark slate to teal*).
3. **Struktur Kartu yang Terkendali (*No Nested Card Hell*)**: Menghindari penumpukan kotak di dalam kotak tanpa batasan yang jelas. Kontainer data menggunakan batas tipis (*hairline border*) `border-slate-200/80` dengan bayangan mikro `shadow-xs`.
4. **Tipografi Berorientasi Angka (*Data-Centric Typography*)**: Wajib menggunakan font tabular (`tabular-nums`) untuk jam, tanggal, persentase, dan nominal mata uang Rupiah agar tersusun lurus secara vertikal.

---

## 2. Fondasi Teknologi & Arsitektur Styling

### 2.1 Stack Frontend
* **Core Engine**: Vite v8 + `@tailwindcss/vite` v4 + `laravel-vite-plugin` v3.
* **Entry Point CSS**: [`resources/css/app.css`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/css/app.css).
* **Entry Point JS**: [`resources/js/app.js`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/js/app.js).
* **Konfigurasi Vite**: [`vite.config.js`](file:///Users/lrmcorporation/Documents/Website/hrghrd/vite.config.js).

### 2.2 Konfigurasi Tailwind CSS v4
Tailwind CSS v4 menggunakan engine native berkinerja tinggi berbasis CSS native tanpa membutuhkan file konfigurasi `tailwind.config.js` atau `postcss.config.js`. Konfigurasi tema didefinisikan langsung pada blok `@theme` di dalam file `resources/css/app.css`:

```css
@import "tailwindcss";

@theme {
  --font-sans: "Plus Jakarta Sans", "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --color-brand-50: #f0fdf9;
  --color-brand-100: #ccfbee;
  --color-brand-200: #99f6e0;
  --color-brand-300: #5eead0;
  --color-brand-400: #2dd4b9;
  --color-brand-500: #14b89f;
  --color-brand-600: #0d9484;
  --color-brand-700: #0f766e;
  --color-brand-800: #115e58;
  --color-brand-900: #134e4a;
  --color-brand-950: #042f2c;
}
```

---

## 3. Sistem Desain Token (Design Tokens)

### 3.1 Palet Warna & Penggunaan Semantik

| Kategori | Token Tailwind | Kode Hex Acuan | Penggunaan Utama |
| :--- | :--- | :--- | :--- |
| **Primary Brand** | `teal-700` / `teal-800` | `#0f766e` / `#115e58` | Tombol utama, header brand, tautan aktif, focus ring. |
| **Accent Emerald** | `emerald-500` / `emerald-600` | `#10b981` / `#059669` | Monogram logo, pulse indicator live WIB, tombol persetujuan cepat. |
| **Neutral Background** | `slate-50` | `#f8fafc` | Latar belakang seluruh halaman aplikasi dan panel input. |
| **Surface Cards** | `white` | `#ffffff` | Kontainer kartu, modal, popover, dan sel data table. |
| **Border Line** | `slate-200/80` | `#e2e8f0` | Garis pembatas kartu, separator baris tabel, input border. |
| **Text Primary** | `slate-900` | `#0f172a` | Judul halaman, nama karyawan, angka total netto, teks penting. |
| **Text Muted** | `slate-500` / `slate-400` | `#64748b` / `#94a3b8` | Label sekunder, tanggal posting, timestamp, petunjuk input. |

#### Status Semantik (Operational Status Badges):
* **Sukses / Disetujui / Selesai**:
  * Kelas: `bg-emerald-50 text-emerald-700 border border-emerald-200`
  * Titik Indikator: `bg-emerald-500`
* **Peringatan / Draf / Menunggu Persetujuan**:
  * Kelas: `bg-amber-50 text-amber-700 border border-amber-200`
  * Titik Indikator: `bg-amber-500`
* **Bahaya / Ditolak / Alfa (Mangkir) / Pelanggaran**:
  * Kelas: `bg-rose-50 text-rose-700 border border-rose-200`
  * Titik Indikator: `bg-rose-500`
* **Informasi / Sebagian Disetujui**:
  * Kelas: `bg-sky-50 text-sky-700 border border-sky-200`

### 3.2 Tipografi & Angka Tabular
* **Font Family**: `Plus Jakarta Sans` dengan fallback `Inter` dan `system-ui`.
* **Font Features**: Diaktifkan secara global di tag `html`:
  ```css
  font-feature-settings: "cv02", "cv03", "cv04", "cv11";
  ```
* **Kelas Utilitas `.tabular-nums`**: Wajib diterapkan pada:
  * Waktu shift kerja: `09:00 – 17:00`
  * Nominal Rupiah: `Rp12.000.000`
  * Koordinat Geofence: `-6.200000, 106.816666`
  * Durasi keterlambatan & lembur: `15 Menit / 1 Unit`

---

## 4. Standar Komponen Antarmuka (UI Components)

### 4.1 Shell Layout Global ([`layouts/app.blade.php`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/views/layouts/app.blade.php))
Sistem menggunakan pola **Enterprise Dual-Mode Shell**:
1. **Mode Tamu (`@guest`)**:
   * Header ringkas dengan logo HR Group dan status live WIB.
   * Konten terpusat vertikal untuk kenyamanan autentikasi ([`login.blade.php`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/views/login.blade.php)).
2. **Mode Terautentikasi (`@auth`)**:
   * **Desktop Sidebar (Kiri)**: Lebar `w-72` berwarna dark slate (`bg-slate-900 text-slate-300 border-r border-slate-800`), terbagi menjadi 3 grup menu peran (*Operasional*, *Manajemen Operasi*, *Administrasi & Finansial*), dilengkapi avatar profil inisial 2 huruf dan tombol logout aman.
   * **Mobile Drawer**: Mendukung toggle drawer hamburger untuk tablet/ponsel pintar via script di `resources/js/app.js`.
   * **Top Bar**: Memuat breadcrumb lokasi aktif (`HR Group / Nama Halaman`) dan indikator sinkronisasi waktu `Asia/Jakarta (WIB)`.
   * **Flash Toast Notifications**:
     * Sukses (`session('ok')`): Kontainer `bg-emerald-50 border-emerald-200` dengan ikon checkmark dan tombol dismiss.
     * Error (`$errors`): Kontainer `bg-rose-50 border-rose-200` dengan ikon peringatan dan daftar masukan.

### 4.2 Kartu Data & Kontainer (*Cards & Bento Grids*)
* **Kelas Standar**:
  ```html
  <section class="bg-white rounded-2xl border border-slate-200/80 shadow-xs p-5 sm:p-6 space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100">
          <div class="flex items-center gap-2.5">
              <span class="w-8 h-8 rounded-lg bg-teal-500/10 text-teal-700 flex items-center justify-center font-bold">
                  <!-- SVG Icon -->
              </span>
              <h2 class="text-base font-bold text-slate-900 tracking-tight m-0">Judul Kartu</h2>
          </div>
      </div>
      <!-- Konten Kartu -->
  </section>
  ```

### 4.3 Formulir & Kontrol Masukan (*Form Controls*)
* **Input Field Teks, Angka, Tanggal**:
  ```html
  <input class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-medium focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-600 transition-all">
  ```
* **Dropdown Select**:
  ```html
  <select class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 text-sm font-semibold focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-teal-500/20 focus:border-teal-600 cursor-pointer">
  ```
* **Tombol Aksi Utama (CTA Button)**:
  ```html
  <button class="px-5 py-2.5 rounded-xl bg-teal-700 hover:bg-teal-800 text-white font-bold text-xs sm:text-sm shadow-sm transition-colors cursor-pointer flex items-center gap-2">
      <!-- SVG Icon -->
      <span>Simpan Data</span>
  </button>
  ```

### 4.4 Tabel Data (*Enterprise Data Tables*)
* **Standar Pembungkus**:
  * Seluruh tabel dibungkus dalam `overflow-x-auto` di dalam kartu ber-border halus.
  * Header tabel (`<thead>`) menggunakan latar `bg-slate-50/80`, huruf kapital kecil (`text-[11px] font-bold text-slate-500 tracking-wider`).
  * Baris tabel (`<tbody>`) memiliki pemisah `divide-y divide-slate-100` dan efek hover halus `hover:bg-slate-50/60`.

### 4.5 Katalog Komponen Blade Bersama (*Reusable Components*)
Komponen modular terletak di [`resources/views/components/`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/views/components):

| Nama Komponen | Tag Panggilan | Deskripsi & Penggunaan |
| :--- | :--- | :--- |
| **Button Terpadu** | `<x-button variant="primary\|secondary\|success\|destructive\|ghost" size="sm\|md\|lg">` | Standarisasi tombol aksi dengan varian dan padding terukur, mendukung tag `<button>` dan `<a>`. |
| **Input Kata Sandi** | `<x-password-input name="password" label="Kata Sandi" required>` | Input kata sandi dengan toggle intip (*Show/Hide Password*) SVG dan deteksi error validasi bawaan. |
| **Toolbar Tabel** | `<x-table-toolbar searchPlaceholder="Cari..." resetUrl="...">` | Wadah pencarian, filter dropdown dinamis, tombol filter, dan tombol reset filter. |
| **Navigasi Paginasi** | `<x-pagination :paginator="$data" />` | Kontrol paginasi 10 data per halaman dengan counter teks ringkasan dan retensi parameter query. |
| **Modal Detail Relasi** | `<x-detail-modal id="modal-id" title="..." maxWidth="2xl">` | Kontainer dialog overlay untuk menampilkan informasi detail lengkap beserta relasinya. |
| **Field Input Formulir** | `<x-form-field name="..." label="..." type="text\|date\|number">` | Pembungkus label bintang merah, focus ring teal, dan pesan error validasi langsung di bawah input. |

---

## 5. Kontrak Kritis JavaScript Client-Side ([`resources/js/app.js`](file:///Users/lrmcorporation/Documents/Website/hrghrd/resources/js/app.js))

Untuk mencegah kerusakan fungsional saat melakukan penyesuaian markup, pengembang wajib mematuhi pemetaan selektor berikut:

| Modul | Selektor DOM Wajib | Elemen / Perilaku yang Diharapkan |
| :--- | :--- | :--- |
| **Presensi Check-in/out** | `[data-open-attendance="{id}"]` | Tombol pemicu yang membuka kontainer `#attendance-{id}` secara halus. |
| **Formulir Geofence Presensi** | `.attendance-form` | Form penangkap submit event untuk meminta `navigator.geolocation` ke browser. |
| **Koordinat Tersembunyi** | `input[name="latitude"]`<br>`input[name="longitude"]`<br>`input[name="accuracy"]` | Field hidden yang otomatis diisi nilai lintang, bujur, dan akurasi meter oleh JS. |
| **Kode Cabang & Challenge** | `input[name="qr_code"]`<br>`input[name="challenge"]` | Input 8 karakter kode aktif dan 3 digit angka tantangan anti-bot. |
| **Pemindai Kamera QR** | `[data-scan-qr]`<br>`<video class="qr-scanner">` | Tombol pembuka kamera dan elemen video stream yang memindai QR via `BarcodeDetector`. |
| **Status Pengiriman Form** | `.form-status` | Elemen penampil status real-time ("Mengambil lokasi...", "Mengirim..."). |
| **Kiosk Display Cabang** | `[data-qr-url]` | Kontainer utama pemanggil endpoint JSON kode QR rotasi. |
| **Elemen Kiosk** | `<canvas>`<br>`.qr-code`<br>`.qr-timer` | Kanvas perender gambar QR, teks 8 karakter monospace, dan timer hitung mundur 30 detik. |
| **Validasi Surat Dokter** | `#leave-type`<br>`input[name="certificate"]` | Dropdown yang secara dinamis mewajibkan upload surat dokter saat jenis pengajuan bernilai `'sick'`. |
| **Mobile Drawer Navigasi** | `#open-mobile-sidebar`<br>`#close-mobile-sidebar`<br>`#mobile-sidebar-backdrop`<br>`#app-sidebar` | Tombol toggle hamburger, tombol silang, backdrop blur, dan wadah sidebar mobile. |
| **Show/Hide Password** | `[data-toggle-password]`<br>`.eye-icon-show`<br>`.eye-icon-hide` | Tombol intip yang men-toggle tipe input antara `password` dan `text` secara dinamis. |
| **Modal Dialog Detail** | `[data-open-modal="{id}"]`<br>`[data-modal-container]`<br>`[data-close-modal]` | Tombol pembuka, kontainer dialog, dan tombol penutup (juga ditutup via tombol keyboard ESC). |

---

## 6. Prosedur Build, Pengujian & Kualitas

Sebelum mengajukan perubahan antarmuka ke lingkungan produksi (*production*), lakukan serangkaian validasi berikut:

1. **Kompilasi Aset Produksi**:
   ```bash
   npm run build
   ```
   *Pastikan tidak ada peringatan CSS atau modul tidak ditemukan.*
2. **Uji Validasi Render UI End-to-End**:
   ```bash
   php scripts/verify-ui-e2e.php
   ```
   *Memvalidasi perenderan seluruh 12 template Blade dengan session autentikasi nyata.*
3. **Uji Regresi Backend Otomatis**:
   ```bash
   /opt/homebrew/opt/php@8.4/bin/php ./vendor/bin/phpunit
   ```
   *Memastikan 49 unit dan feature test cases lulus 100% tanpa regresi.*
4. **Verifikasi Aturan Bisnis & Service**:
   ```bash
   /opt/homebrew/opt/php@8.4/bin/php scripts/verify-business.php
   /opt/homebrew/opt/php@8.4/bin/php scripts/verify-services.php
   ```
