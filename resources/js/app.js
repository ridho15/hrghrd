document.querySelectorAll('[data-open-attendance]').forEach(button => {
  button.addEventListener('click', () => {
    const panel = document.getElementById(`attendance-${button.dataset.openAttendance}`);
    panel.hidden = !panel.hidden;
    if (!panel.hidden) panel.scrollIntoView({behavior: 'smooth', block: 'center'});
  });
});

document.querySelectorAll('.attendance-form').forEach(form => {
  const status = form.querySelector('.form-status');
  form.addEventListener('submit', event => {
    event.preventDefault();
    if (!navigator.geolocation) { status.textContent = 'Lokasi tidak didukung. Gunakan pengajuan pengecualian.'; return; }
    const button = form.querySelector('button:not([type])');
    button.disabled = true; status.textContent = 'Mengambil lokasi...';
    navigator.geolocation.getCurrentPosition(position => {
      form.elements.latitude.value = position.coords.latitude;
      form.elements.longitude.value = position.coords.longitude;
      form.elements.accuracy.value = position.coords.accuracy;
      status.textContent = 'Mengirim...'; form.submit();
    }, () => { button.disabled = false; status.textContent = 'Lokasi gagal diambil. Aktifkan izin lokasi atau ajukan pengecualian.'; },
    {enableHighAccuracy: true, timeout: 12000, maximumAge: 0});
  });
  const scan = form.querySelector('[data-scan-qr]');
  if (scan) scan.addEventListener('click', async () => {
    if (!('BarcodeDetector' in window) || !navigator.mediaDevices?.getUserMedia) {
      status.textContent = 'Pemindai tidak didukung. Ketik kode delapan karakter yang tampil di cabang.'; return;
    }
    let stream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
      const video = form.querySelector('video'); video.hidden = false; video.srcObject = stream; await video.play();
      const detector = new BarcodeDetector({formats: ['qr_code']});
      const until = Date.now() + 15000;
      while (Date.now() < until) {
        const codes = await detector.detect(video);
        if (codes.length) {
          const code = codes[0].rawValue.trim().toUpperCase();
          if (/^[A-F0-9]{8}$/.test(code)) { form.elements.qr_code.value = code; status.textContent = 'Kode terbaca.'; break; }
        }
        await new Promise(resolve => setTimeout(resolve, 250));
      }
      if (!form.elements.qr_code.value) status.textContent = 'QR belum terbaca. Ketik kode secara manual.';
      video.hidden = true;
    } catch { status.textContent = 'Kamera tidak tersedia. Ketik kode secara manual.'; }
    finally { stream?.getTracks().forEach(track => track.stop()); }
  });
});

const qrDisplay = document.querySelector('[data-qr-url]');
if (qrDisplay) {
  const codeNode = qrDisplay.querySelector('.qr-code');
  const timerNode = qrDisplay.querySelector('.qr-timer');
  let expiry = 0;
  async function refresh() {
    try {
      const response = await fetch(qrDisplay.dataset.qrUrl, {credentials: 'same-origin', cache: 'no-store'});
      if (!response.ok) throw new Error();
      const value = await response.json();
      if (codeNode.textContent !== value.code) {
        const {default: QRCode} = await import('qrcode');
        await QRCode.toCanvas(qrDisplay.querySelector('canvas'), value.code, {width: 256, margin: 2, color: {dark: '#102434', light: '#ffffff'}});
        codeNode.textContent = value.code;
      }
      expiry = value.expires_at;
    } catch { codeNode.textContent = 'Koneksi terputus'; }
  }
  refresh();
  setInterval(() => { timerNode.textContent = expiry ? `Berganti dalam ${Math.max(0, expiry - Math.floor(Date.now()/1000))} detik` : ''; if (expiry && Date.now()/1000 >= expiry) refresh(); }, 1000);
}

const leaveType = document.getElementById('leave-type');
if (leaveType) {
  const file = document.querySelector('input[name="certificate"]');
  const update = () => { file.required = leaveType.value === 'sick'; };
  leaveType.addEventListener('change', update); update();
}

// Mobile sidebar drawer toggle
const openSidebarBtn = document.getElementById('open-mobile-sidebar');
const closeSidebarBtn = document.getElementById('close-mobile-sidebar');
const mobileBackdrop = document.getElementById('mobile-sidebar-backdrop');
const appSidebar = document.getElementById('app-sidebar');

if (openSidebarBtn && appSidebar) {
  const toggleSidebar = (show) => {
    if (show) {
      appSidebar.classList.remove('-translate-x-full');
      mobileBackdrop?.classList.remove('hidden');
    } else {
      appSidebar.classList.add('-translate-x-full');
      mobileBackdrop?.classList.add('hidden');
    }
  };

  openSidebarBtn.addEventListener('click', () => toggleSidebar(true));
  closeSidebarBtn?.addEventListener('click', () => toggleSidebar(false));
  mobileBackdrop?.addEventListener('click', () => toggleSidebar(false));
}

// Show / Hide Password Toggle Handler
document.addEventListener('click', (e) => {
  const toggleBtn = e.target.closest('[data-toggle-password]');
  if (!toggleBtn) return;
  const wrapper = toggleBtn.closest('.password-input-wrapper');
  const input = wrapper ? wrapper.querySelector('input') : toggleBtn.parentElement.querySelector('input');
  if (!input) return;
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  toggleBtn.setAttribute('aria-label', isPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
  toggleBtn.querySelector('.eye-icon-show')?.classList.toggle('hidden', isPassword);
  toggleBtn.querySelector('.eye-icon-hide')?.classList.toggle('hidden', !isPassword);
});

// Generic Detail Modal Dialog Handler
document.addEventListener('click', (e) => {
  const openBtn = e.target.closest('[data-open-modal]');
  if (openBtn) {
    const modalId = openBtn.dataset.openModal;
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('hidden');
      document.body.classList.add('overflow-hidden');
    }
  }
  const closeBtn = e.target.closest('[data-close-modal]');
  if (closeBtn) {
    const modal = closeBtn.closest('[data-modal-container]');
    if (modal) {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    }
  }
});

// Penutup Modal via Keyboard ESC
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('[data-modal-container]:not(.hidden)').forEach(modal => {
      modal.classList.add('hidden');
      document.body.classList.remove('overflow-hidden');
    });
  }
});

