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

// Floating Toast Notification System
function setupToastItem(toast) {
  if (!toast || toast.dataset.toastInitialized) return;
  toast.dataset.toastInitialized = 'true';

  const duration = parseInt(toast.dataset.duration || '5000', 10);
  const progressBar = toast.querySelector('.toast-progress');
  if (progressBar) {
    progressBar.style.animationDuration = `${duration}ms`;
    progressBar.classList.add('animate-toast-progress');
  }

  let startTime = Date.now();
  let remaining = duration;
  let timerId = null;

  function dismiss() {
    toast.classList.add('translate-x-full', 'opacity-0');
    setTimeout(() => {
      if (toast.parentElement) toast.remove();
    }, 320);
  }

  function startTimer() {
    startTime = Date.now();
    timerId = setTimeout(dismiss, remaining);
  }

  function pauseTimer() {
    clearTimeout(timerId);
    remaining -= (Date.now() - startTime);
  }

  toast.addEventListener('mouseenter', () => {
    pauseTimer();
    if (progressBar) progressBar.style.animationPlayState = 'paused';
  });

  toast.addEventListener('mouseleave', () => {
    if (remaining > 0) {
      startTimer();
      if (progressBar) progressBar.style.animationPlayState = 'running';
    } else {
      dismiss();
    }
  });

  const closeBtn = toast.querySelector('[data-toast-close]');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      clearTimeout(timerId);
      dismiss();
    });
  }

  startTimer();
}

function initToastNotifications() {
  document.querySelectorAll('#toast-container .toast-item').forEach(toast => {
    setupToastItem(toast);
  });
}

window.showToast = function({ type = 'success', title = '', message = '', duration = 5000 }) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.className = 'fixed top-5 right-5 z-50 flex flex-col gap-3 max-w-sm sm:max-w-md w-full pointer-events-none px-4 sm:px-0';
    container.setAttribute('aria-live', 'polite');
    document.body.appendChild(container);
  }

  const isSuccess = type === 'success';
  const isError = type === 'error';
  const borderColor = isSuccess ? 'border-emerald-200/90' : (isError ? 'border-rose-200/90' : 'border-slate-200');
  const shadowColor = isSuccess ? 'shadow-emerald-950/10' : (isError ? 'shadow-rose-950/10' : 'shadow-slate-900/10');
  const iconBg = isSuccess ? 'bg-emerald-500' : (isError ? 'bg-rose-500' : 'bg-slate-700');
  const titleColor = isSuccess ? 'text-emerald-800' : (isError ? 'text-rose-800' : 'text-slate-800');
  const progressBg = isSuccess ? 'bg-gradient-to-r from-emerald-500 to-emerald-400' : (isError ? 'bg-gradient-to-r from-rose-500 to-rose-400' : 'bg-slate-400');
  
  const iconSvg = isSuccess
    ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>'
    : (isError
      ? '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>'
      : '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>');

  const toast = document.createElement('div');
  toast.className = `toast-item pointer-events-auto relative overflow-hidden rounded-2xl bg-white/95 backdrop-blur-md border ${borderColor} p-4 shadow-xl ${shadowColor} flex items-start justify-between gap-3 transform transition-all duration-300 translate-x-0 opacity-100`;
  toast.setAttribute('data-toast-type', type);
  toast.setAttribute('data-duration', duration);

  toast.innerHTML = `
    <div class="flex items-start gap-3">
      <span class="w-8 h-8 rounded-xl ${iconBg} text-white flex items-center justify-center shrink-0 shadow-xs">
        ${iconSvg}
      </span>
      <div class="space-y-0.5">
        <h4 class="text-xs font-bold ${titleColor} uppercase tracking-wider m-0">${title || (isSuccess ? 'Operasi Berhasil' : (isError ? 'Terjadi Kesalahan' : 'Pemberitahuan'))}</h4>
        <p class="text-xs font-medium text-slate-700 m-0 leading-relaxed">${message}</p>
      </div>
    </div>
    <button type="button" data-toast-close class="p-1 text-slate-400 hover:text-slate-700 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer shrink-0" aria-label="Tutup notifikasi">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
    <div class="toast-progress absolute bottom-0 left-0 h-1 ${progressBg} w-full transition-all duration-100"></div>
  `;

  container.appendChild(toast);
  setupToastItem(toast);
  return toast;
};

// Keyboard Shortcuts Engine (/, Escape, ?)
function initKeyboardShortcuts() {
  document.addEventListener('keydown', (e) => {
    const isTyping = ['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable;

    // Pintasan: "/" untuk fokus input pencarian tabel
    if (e.key === '/' && !isTyping) {
      const searchInput = document.querySelector('input[data-table-search], input[type="search"][name="search"]');
      if (searchInput) {
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
      }
      return;
    }

    // Pintasan: "?" (Shift + /) untuk buka/tutup modal cheat sheet keyboard shortcuts
    if (e.key === '?' && !isTyping) {
      e.preventDefault();
      const shortcutModal = document.getElementById('modal-keyboard-shortcuts');
      if (shortcutModal) {
        const isHidden = shortcutModal.classList.contains('hidden');
        if (isHidden) {
          shortcutModal.classList.remove('hidden');
          document.body.classList.add('overflow-hidden');
        } else {
          shortcutModal.classList.add('hidden');
          document.body.classList.remove('overflow-hidden');
        }
      }
      return;
    }

    // Pintasan: "Escape" untuk tutup dropdown, tutup confirm-modal, tutup modal umum, atau blur input
    if (e.key === 'Escape') {
      // 1. Tutup searchable select jika terbuka
      const openSelects = document.querySelectorAll('[data-searchable-select] [data-select-dropdown]:not(.hidden)');
      if (openSelects.length > 0) {
        openSelects.forEach(d => {
          d.classList.add('hidden');
          d.closest('[data-searchable-select]')?.querySelector('.chevron-icon')?.classList.remove('rotate-180');
          d.closest('[data-searchable-select]')?.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
        });
        return;
      }

      // 2. Tutup confirm modal jika terbuka
      const confirmModal = document.querySelector('[data-confirm-modal]:not(.hidden)');
      if (confirmModal) {
        confirmModal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        return;
      }

      // 3. Tutup modal umum
      const openModals = document.querySelectorAll('[data-modal-container]:not(.hidden)');
      if (openModals.length > 0) {
        openModals.forEach(modal => {
          modal.classList.add('hidden');
        });
        document.body.classList.remove('overflow-hidden');
        return;
      }

      if (document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName)) {
        document.activeElement.blur();
      }
    }
  });
}

// Global Confirm Modal Engine
function initConfirmModal() {
  const modal = document.getElementById('global-confirm-modal');
  if (!modal) return;

  const titleEl = modal.querySelector('[data-confirm-title]');
  const messageEl = modal.querySelector('[data-confirm-message]');
  const submitBtn = modal.querySelector('[data-confirm-submit]');
  const btnTextEl = modal.querySelector('[data-confirm-btn-text]');
  const iconDanger = modal.querySelector('[data-icon-danger]');
  const iconPrimary = modal.querySelector('[data-icon-primary]');
  const iconWarning = modal.querySelector('[data-icon-warning]');

  let pendingAction = null;

  function closeModal() {
    modal.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    pendingAction = null;
  }

  modal.querySelectorAll('[data-close-confirm]').forEach(btn => {
    btn.addEventListener('click', closeModal);
  });

  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-confirm]');
    if (!trigger) return;
    e.preventDefault();

    const title = trigger.dataset.confirmTitle || 'Konfirmasi Tindakan';
    const message = trigger.dataset.confirm || 'Apakah Anda yakin ingin melanjutkan tindakan ini?';
    const btnText = trigger.dataset.confirmBtn || 'Ya, Lanjutkan';
    const variant = trigger.dataset.confirmVariant || 'danger';

    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message;
    if (btnTextEl) btnTextEl.textContent = btnText;

    // Set icon variant
    if (iconDanger) iconDanger.classList.toggle('hidden', variant !== 'danger');
    if (iconPrimary) iconPrimary.classList.toggle('hidden', variant !== 'primary');
    if (iconWarning) iconWarning.classList.toggle('hidden', variant !== 'warning');

    // Set submit button visual variant
    if (submitBtn) {
      if (variant === 'primary') {
        submitBtn.className = 'h-10 px-5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1.5';
      } else if (variant === 'warning') {
        submitBtn.className = 'h-10 px-5 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1.5';
      } else {
        submitBtn.className = 'h-10 px-5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold uppercase tracking-wider transition-colors cursor-pointer shadow-xs inline-flex items-center gap-1.5';
      }
    }

    const parentForm = trigger.closest('form');
    if (parentForm) {
      pendingAction = () => {
        parentForm.submit();
      };
    } else if (trigger.dataset.confirmAction) {
      pendingAction = () => {
        let actionForm = document.getElementById('global-confirm-dynamic-form');
        if (!actionForm) {
          actionForm = document.createElement('form');
          actionForm.id = 'global-confirm-dynamic-form';
          actionForm.method = 'POST';
          actionForm.className = 'hidden';
          const tokenInput = document.createElement('input');
          tokenInput.type = 'hidden';
          tokenInput.name = '_token';
          tokenInput.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value || '';
          actionForm.appendChild(tokenInput);
          document.body.appendChild(actionForm);
        }
        actionForm.action = trigger.dataset.confirmAction;
        actionForm.submit();
      };
    } else if (trigger.tagName === 'A' && trigger.href) {
      pendingAction = () => {
        window.location.href = trigger.href;
      };
    }

    modal.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
  });

  submitBtn?.addEventListener('click', () => {
    if (typeof pendingAction === 'function') {
      const action = pendingAction;
      closeModal();
      action();
    } else {
      closeModal();
    }
  });
}

// Searchable Select Combobox Engine
function initSearchableSelects() {
  document.querySelectorAll('[data-searchable-select]').forEach(wrapper => {
    if (wrapper.dataset.selectInitialized) return;
    wrapper.dataset.selectInitialized = 'true';

    const trigger = wrapper.querySelector('[data-select-trigger]');
    const dropdown = wrapper.querySelector('[data-select-dropdown]');
    const searchInput = wrapper.querySelector('[data-select-search-input]');
    const hiddenInput = wrapper.querySelector('[data-select-hidden-input]');
    const labelEl = wrapper.querySelector('[data-select-label]');
    const noResultsEl = wrapper.querySelector('[data-select-no-results]');
    const chevronIcon = trigger?.querySelector('.chevron-icon');

    if (!trigger || !dropdown) return;

    function openDropdown() {
      // Tutup dropdown lain yang sedang terbuka
      document.querySelectorAll('[data-searchable-select] [data-select-dropdown]:not(.hidden)').forEach(d => {
        if (d !== dropdown) {
          d.classList.add('hidden');
          d.closest('[data-searchable-select]')?.querySelector('.chevron-icon')?.classList.remove('rotate-180');
          d.closest('[data-searchable-select]')?.querySelector('[data-select-trigger]')?.setAttribute('aria-expanded', 'false');
        }
      });

      dropdown.classList.remove('hidden');
      trigger.setAttribute('aria-expanded', 'true');
      chevronIcon?.classList.add('rotate-180');
      if (searchInput) {
        searchInput.value = '';
        filterOptions('');
        setTimeout(() => searchInput.focus(), 50);
      }
    }

    function closeDropdown() {
      dropdown.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
      chevronIcon?.classList.remove('rotate-180');
    }

    function toggleDropdown() {
      if (dropdown.classList.contains('hidden')) {
        openDropdown();
      } else {
        closeDropdown();
      }
    }

    function filterOptions(query) {
      const q = query.toLowerCase().trim();
      let matchCount = 0;
      wrapper.querySelectorAll('[data-select-option]').forEach(opt => {
        const text = (opt.dataset.label || opt.textContent).toLowerCase();
        const matches = text.includes(q);
        opt.classList.toggle('hidden', !matches);
        if (matches) matchCount++;
      });
      if (noResultsEl) {
        noResultsEl.classList.toggle('hidden', matchCount > 0);
      }
    }

    function selectOption(opt) {
      const val = opt.dataset.value;
      const label = opt.dataset.label;

      if (hiddenInput) {
        hiddenInput.value = val;
        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
      }

      if (labelEl) {
        labelEl.textContent = label;
        labelEl.className = 'truncate text-slate-900 font-semibold';
      }

      wrapper.querySelectorAll('[data-select-option]').forEach(o => {
        const isCurrent = o === opt;
        o.setAttribute('aria-selected', isCurrent ? 'true' : 'false');
        o.className = isCurrent 
          ? 'px-3 py-2 rounded-xl flex items-center justify-between gap-2 cursor-pointer transition-colors bg-emerald-50 text-emerald-900 font-bold'
          : 'px-3 py-2 rounded-xl flex items-center justify-between gap-2 cursor-pointer transition-colors text-slate-700 hover:bg-slate-100';
      });

      closeDropdown();
      trigger.focus();
    }

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleDropdown();
    });

    searchInput?.addEventListener('input', (e) => {
      filterOptions(e.target.value);
    });

    searchInput?.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeDropdown();
        trigger.focus();
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        const firstVisible = wrapper.querySelector('[data-select-option]:not(.hidden)');
        firstVisible?.focus();
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const visibleOptions = wrapper.querySelectorAll('[data-select-option]:not(.hidden)');
        if (visibleOptions.length > 0) {
          selectOption(visibleOptions[0]);
        }
      }
    });

    wrapper.querySelectorAll('[data-select-option]').forEach(opt => {
      opt.setAttribute('tabindex', '0');
      opt.addEventListener('click', (e) => {
        e.stopPropagation();
        selectOption(opt);
      });
      opt.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          selectOption(opt);
        } else if (e.key === 'ArrowDown') {
          e.preventDefault();
          let next = opt.nextElementSibling;
          while (next && next.classList.contains('hidden')) next = next.nextElementSibling;
          next?.focus();
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          let prev = opt.previousElementSibling;
          while (prev && prev.classList.contains('hidden')) prev = prev.previousElementSibling;
          if (prev) {
            prev.focus();
          } else {
            searchInput?.focus();
          }
        } else if (e.key === 'Escape') {
          closeDropdown();
          trigger.focus();
        }
      });
    });

    // Tutup jika klik di luar dropdown
    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target)) {
        closeDropdown();
      }
    });
  });
}

// Inisialisasi Fitur saat DOM siap
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    initToastNotifications();
    initKeyboardShortcuts();
    initConfirmModal();
    initSearchableSelects();
  });
} else {
  initToastNotifications();
  initKeyboardShortcuts();
  initConfirmModal();
  initSearchableSelects();
}

