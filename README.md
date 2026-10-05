# HR Group — MVP browser

Aplikasi Laravel untuk data karyawan, shift, absensi, izin/sakit, koreksi, dan payroll. Antarmuka memakai Blade (render server), CSS sistem, serta JavaScript kecil yang dibangun dengan Vite/Node.js. Paket `qrcode` hanya dimuat pada halaman QR manager untuk menggambar kode yang berubah; halaman karyawan tidak memuatnya. Data aturan dan perhitungan berada di PHP agar kelak dapat dipakai API Flutter. Flutter belum dibuat.

## Keadaan awal dan data

Repositori awal hanya berisi README. Tidak ada Excel karyawan di workspace saat pemeriksaan. File asli yang kelak diunggah tidak diubah: impor membaca salinan sementara, menampilkan lima baris contoh dan pemetaan kolom, lalu melaporkan baris yang gagal. Data seeder bersifat sintetis dan hanya dapat dijalankan di lingkungan `local`.

## Menjalankan MVP

Cara termudah untuk melihat aplikasi **secara interaktif di localhost komputer Anda** hanya memerlukan Docker:

```bash
git clone -b codex/hrd-browser-mvp https://github.com/lrmcorp/hrghrd.git
cd hrghrd
./scripts/run-local.sh
```

Buka `http://localhost:8000/login`. Skrip ini memasang dependensi PHP/Node, membangun aset, membuat database SQLite dan akun demo pada pemakaian pertama, lalu menjalankan server sampai Anda menekan Ctrl+C. Jika port 8000 terpakai, jalankan `HRD_PORT=8001 ./scripts/run-local.sh` dan buka port tersebut. `localhost` dari lingkungan cloud Codex tidak sama dengan `localhost` komputer Anda.

Untuk menyiapkan manual pada mesin dengan PHP 8.4+, Composer, Node 24+, dan SQLite:

```bash
composer install --no-dev
cp .env.example .env
touch database/database.sqlite
php artisan key:generate
php artisan migrate --seed
npm ci && npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Jika memakai Docker, image `composer:2` menyediakan PHP dan Composer. Jalankan perintah `composer`/`php` di atas dengan volume proyek ke `/app` dan working directory `/app`. Jangan jalankan seeder demo di produksi. Server bawaan Artisan hanya untuk pengembangan.

Akun **demo lokal saja** (sandi tiap akun `Demo12345!`):

| Peran | Email |
| --- | --- |
| Super admin | `admin@example.test` |
| Manager | `manager@example.test` |
| Karyawan | `karyawan@example.test` |

Ganti seluruh sandi dan gunakan HTTPS sebelum aplikasi diakses pengguna nyata. Cabang demo berada di Monas. Ubah koordinat dan radius di **Karyawan → Lokasi cabang** sebelum menguji absensi dari lokasi fisik lain.

## Urutan uji di browser ponsel

Buka alamat server yang dapat dicapai ponsel. Tampilan, impor, jadwal, izin, dan payroll dapat diuji melalui LAN. **GPS dan kamera browser membutuhkan HTTPS** pada ponsel; gunakan alamat HTTPS tepercaya untuk uji absensi sesungguhnya. `localhost` pada ponsel menunjuk ke ponsel, bukan ke komputer/server ini.

1. **Impor:** masuk sebagai admin → **Karyawan** buat cabang → **Impor** unggah CSV/XLSX (sheet pertama) → periksa pratinjau, petakan 7 kolom, impor. Baris tidak valid dilaporkan. Sandi akun impor acak; admin setel sandi sementara di **Karyawan → Ubah**.
2. **Shift:** admin → **Shift** buat draf dan setujui. Coba jam 23:00–01:00 untuk shift lintas tengah malam. Revisi shift yang disetujui membutuhkan alasan dan persetujuan ulang.
3. **Absensi:** manager → **QR cabang** tampilkan QR/kode yang berganti tiap 30 detik. Karyawan → **Beranda** pilih shift yang disetujui → pindai/ketik kode, jawab angka acak, dan izinkan GPS. Check-in lewat 60 menit menjadi alfa; lokasi salah, perangkat berbeda, kode lama, dan absen ganda ditolak. **Tinjau absensi** menampilkan hasil pemeriksaan dan bukti. Jika perangkat/lokasi/jaringan bermasalah, kirim pengecualian dengan alasan; manager harus menyetujui sebelum tercatat.
4. **Izin/sakit:** karyawan → **Izin & sakit**. Izin biasa H-7. Sakit wajib unggah PDF/JPG/PNG (maks. 5 MB). Manager memilih tanggal yang disetujui; dua hari sakit pertama yang disetujui dalam satu kejadian dibayar, sisanya tidak. Pembuat pengajuan tidak dapat menyetujuinya sendiri. Surat hanya dapat diunduh pemilik, manager cabangnya, atau admin.
5. **Payroll:** admin → **Payroll** pilih bulan/cabang, lihat sumber perhitungan setiap karyawan, tambahkan koreksi manual beralasan, simpan draf. Setelah bulan berakhir dan tidak ada pekerjaan tertunda, setujui, ekspor CSV, lalu kunci periode. **Audit** menampilkan identitas dan alasan perubahan.

## Aturan bisnis yang disetujui untuk MVP

- Izin biasa minimal H-7.
- Sakit dibayar paling banyak dua hari **per kejadian**, dengan surat dan persetujuan.
- Keterlambatan sampai 15 menit tanpa potongan; menit 16–30 satu unit Rp10.000, 31–45 dua unit, 46–60 tiga unit. Lebih dari 60 menit ditolak dan ditandai alfa.
- Lembur perlu persetujuan manager. Hanya menit **setelah** ambang 90 menit yang dihitung.
- Tarif harian = gaji bulanan ÷ jumlah hari kalender bulan. Tarif per jam = tarif harian ÷ 24. Contoh gaji Rp3.000.000 pada bulan 30 hari: Rp100.000/hari dan sekitar Rp4.166,67/jam. Admin dapat mengubah ambang, potongan, tenggat, dan pembagi pada **Aturan**. Opsi pembagi tetap 30 hari tersedia untuk kebijakan mendatang; gunakan hanya setelah kebijakan baru disetujui.
- Prorata memakai tanggal mulai dan akhir kerja. Tanggal izin/alfa tidak dibayar dihitung unik agar satu hari tidak terpotong ganda. Karyawan nonaktif tanpa tanggal akhir kerja menghalangi persetujuan payroll.

## Model dan kontrol

Tabel inti: `users` (peran, cabang, jabatan, masa kerja, gaji), `branches` (area), `shifts`, `attendances`, `attendance_attempts`, `attendance_exceptions`, `leave_requests`/`leave_days`, `payroll_runs`/`payroll_lines`, `payroll_adjustments`, `settings`, dan `audit_logs`. File surat disimpan di disk privat, bukan di folder publik. Payroll dan dokumen sakit dijaga lewat pemeriksaan peran/cabang di server; CSRF dan pembatasan percobaan login/absensi aktif.

Lapisan absensi: jam server, shift disetujui, jendela waktu, QR HMAC cabang 30 detik, GPS dengan radius dan batas akurasi, cookie perangkat yang diikat saat absensi pertama, tantangan acak, deteksi beberapa absensi dalam satu jam, audit, dan pengecualian berpersetujuan. QR, GPS, dan cookie masih dapat disalin atau dipalsukan oleh penyerang yang menguasai perangkat/akun; browser tidak dapat membuktikan identitas fisik seseorang. Foto tidak diwajibkan karena tambahan data pribadi dan biaya jaringan tanpa jaminan anti fraud yang kuat. Sebelum produksi, tetapkan kebijakan retensi bukti lokasi/IP, uji perangkat nyata di tiap cabang, dan tentukan respons untuk pola mencurigakan.

**Batas MVP:** belum mencakup pajak, BPJS, THR, cuti saldo, notifikasi, reset sandi mandiri, tanda tangan persetujuan terpisah, atau integrasi bank. Cocok untuk menguji alur dan rumus yang telah disetujui; lakukan validasi kebijakan perusahaan serta aturan hukum yang berlaku sebelum payroll nyata. Tidak ada URL HTTPS publik pada lingkungan ini, sehingga uji GPS di ponsel memerlukan staging/hosting HTTPS terpisah.

## Verifikasi

Jalankan `php scripts/verify-business.php` untuk memeriksa batas 15 menit, prorata tanggal mulai/akhir, hari tidak dibayar, lembur yang disetujui, dan total payroll dalam transaksi yang dibatalkan setelah tes. Uji HTTP lokal juga telah mencakup login tiga peran, penolakan akses gaji/dokumen, impor CSV/XLSX, persetujuan shift, QR/GPS/perangkat/absen ganda, alfa >60 menit, shift lintas tengah malam, surat sakit wajib, persetujuan per tanggal, serta siklus draf–setujui–kunci payroll. Database demo dikembalikan ke data sintetis awal setelah pengujian.
