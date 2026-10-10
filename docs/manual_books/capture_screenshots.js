const { chromium } = require('/Users/lrmcorporation/Documents/Website/Duta-Tunggal-ERP/node_modules/playwright');
const path = require('path');
const fs = require('fs');

const BASE_URL = 'http://127.0.0.1:8003';
const ASSETS_DIR = path.join(__dirname, 'assets');

async function login(page, email, password) {
  console.log(`Logging in as ${email}...`);
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  
  // Fill credentials
  await page.fill('input[name="login"]', email);
  await page.fill('input[name="password"]', password);
  
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('button[type="submit"]')
  ]);
  console.log(`Logged in successfully as ${email}, current url: ${page.url()}`);
}

async function capture(page, urlPath, savePath, title = '') {
  try {
    const fullUrl = urlPath.startsWith('http') ? urlPath : `${BASE_URL}${urlPath}`;
    console.log(`Navigating to ${fullUrl} [${title}]...`);
    await page.goto(fullUrl, { waitUntil: 'networkidle', timeout: 15000 });
    // Wait a brief moment for dynamic elements/animations
    await page.waitForTimeout(1000);
    
    await page.screenshot({ path: savePath, fullPage: false });
    console.log(`Saved screenshot to: ${savePath}`);
  } catch (err) {
    console.error(`Failed to capture ${urlPath}:`, err.message);
  }
}

async function run() {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--font-render-hinting=none']
  });

  const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2 // High resolution Retina quality
  });

  const page = await context.newPage();

  // 0. Login Page
  console.log('=== CAPTURING LOGIN PAGE ===');
  await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: path.join(ASSETS_DIR, 'admin', '01_login_page.png') });

  // 1. ADMIN
  console.log('=== CAPTURING SUPER ADMIN PAGES ===');
  await login(page, 'admin@example.test', 'Demo12345!');

  const adminPages = [
    { url: '/', file: '02_dashboard.png', title: 'Dashboard Eksekutif' },
    { url: '/people', file: '03_people_list.png', title: 'Direktori Karyawan' },
    { url: '/people/create', file: '04_people_create.png', title: 'Form Tambah Karyawan' },
    { url: '/people/3', file: '05_people_detail.png', title: 'Detail Karyawan' },
    { url: '/people/3/edit', file: '06_people_edit.png', title: 'Edit Karyawan' },
    { url: '/branches', file: '07_branches_list.png', title: 'Daftar Cabang & Geofence' },
    { url: '/branches/create', file: '08_branches_create.png', title: 'Form Tambah Cabang' },
    { url: '/branches/1', file: '09_branches_detail.png', title: 'Detail Cabang' },
    { url: '/branches/1/edit', file: '10_branches_edit.png', title: 'Edit Cabang' },
    { url: '/positions', file: '11_positions_list.png', title: 'Daftar Jabatan' },
    { url: '/positions/create', file: '12_positions_create.png', title: 'Form Tambah Jabatan' },
    { url: '/positions/1/edit', file: '13_positions_edit.png', title: 'Edit Jabatan' },
    { url: '/import', file: '14_import_people.png', title: 'Impor Massal Karyawan' },
    { url: '/shifts', file: '15_shifts_list.png', title: 'Manajemen Jadwal Shift' },
    { url: '/shifts/create', file: '16_shifts_create.png', title: 'Form Buat Shift' },
    { url: '/attendance-review', file: '17_attendance_review.png', title: 'Review Presensi & Exception' },
    { url: '/leave', file: '18_leave_list.png', title: 'Manajemen Cuti & Sakit' },
    { url: '/leave/create', file: '19_leave_create.png', title: 'Form Pengajuan Cuti' },
    { url: '/payroll', file: '20_payroll_console.png', title: 'Konsol Penggajian Payroll' },
    { url: '/settings', file: '21_settings.png', title: 'Pengaturan Sistem' },
    { url: '/audit', file: '22_audit_trail.png', title: 'Jejak Audit Keamanan' }
  ];

  for (const p of adminPages) {
    await capture(page, p.url, path.join(ASSETS_DIR, 'admin', p.file), p.title);
  }

  // Try capturing detail leave & shift if exists
  try {
    await capture(page, '/leave/1', path.join(ASSETS_DIR, 'admin', '19b_leave_detail.png'), 'Detail & Otorisasi Cuti');
  } catch(e) {}
  try {
    await capture(page, '/payroll/slip/3', path.join(ASSETS_DIR, 'admin', '20b_payroll_slip.png'), 'Slip Gaji Karyawan');
  } catch(e) {}

  // Logout admin
  console.log('Logging out Admin...');
  await page.goto(`${BASE_URL}/`, { waitUntil: 'networkidle' });
  const logoutForm = await page.$('form[action*="logout"]');
  if (logoutForm) {
    await logoutForm.evaluate(f => f.submit());
    await page.waitForNavigation({ waitUntil: 'networkidle' });
  } else {
    await page.context().clearCookies();
  }

  // 2. MANAGER
  console.log('=== CAPTURING MANAGER PAGES ===');
  await login(page, 'manager@example.test', 'Demo12345!');

  const managerPages = [
    { url: '/', file: '01_dashboard_manager.png', title: 'Dashboard Manager Cabang' },
    { url: '/branch-qr', file: '02_branch_qr_dynamic.png', title: 'Display QR Code Dinamis Cabang' },
    { url: '/shifts', file: '03_shifts_cabang.png', title: 'Jadwal Shift Staf Cabang' },
    { url: '/shifts/create', file: '04_shifts_create_cabang.png', title: 'Form Buat Shift Cabang' },
    { url: '/attendance-review', file: '05_attendance_review_cabang.png', title: 'Review Presensi & Exception Cabang' },
    { url: '/leave', file: '06_leave_cabang.png', title: 'Review Pengajuan Cuti Cabang' },
    { url: '/leave/create', file: '07_leave_create_cabang.png', title: 'Form Pengajuan Izin/Cuti Cabang' }
  ];

  for (const p of managerPages) {
    await capture(page, p.url, path.join(ASSETS_DIR, 'manager', p.file), p.title);
  }

  // Logout manager
  console.log('Logging out Manager...');
  await page.context().clearCookies();

  // 3. KARYAWAN
  console.log('=== CAPTURING KARYAWAN PAGES ===');
  await login(page, 'karyawan@example.test', 'Demo12345!');

  const employeePages = [
    { url: '/', file: '01_dashboard_karyawan.png', title: 'Dashboard Beranda Karyawan' },
    { url: '/shifts', file: '02_shifts_karyawan.png', title: 'Jadwal Shift Kerja Pribadi' },
    { url: '/leave', file: '03_leave_karyawan.png', title: 'Riwayat & Status Cuti Pribadi' },
    { url: '/leave/create', file: '04_leave_create_karyawan.png', title: 'Form Permohonan Cuti/Izin' }
  ];

  for (const p of employeePages) {
    await capture(page, p.url, path.join(ASSETS_DIR, 'employee', p.file), p.title);
  }

  // Capture slip if accessible
  try {
    await capture(page, '/payroll/slip/3', path.join(ASSETS_DIR, 'employee', '05_slip_gaji_karyawan.png'), 'Slip Gaji Elektronik');
  } catch(e) {}

  await browser.close();
  console.log('=== ALL SCREENSHOTS CAPTURED SUCCESSFULLY! ===');
}

run().catch(err => {
  console.error('Fatal error during screenshot capture:', err);
  process.exit(1);
});
