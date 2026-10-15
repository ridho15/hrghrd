@push('scripts')
<script>
(function() {
    if (window._shiftExchangeModalInitialized) return;
    window._shiftExchangeModalInitialized = true;

    function initModal(container) {
        if (!container || container._hasInitExchange) return;
        container._hasInitExchange = true;

        const form = container.querySelector('.form-exchange-container');
        if (!form) return;

        const isStandalone = form.dataset.isStandalone === '1';
        const defaultAction = form.dataset.defaultAction || '#';

        // Elements
        const btnOff = container.querySelector('.btn-intent-off');
        const btnWork = container.querySelector('.btn-intent-work');
        const methodsOff = container.querySelector('.methods-off-container');
        const methodsWork = container.querySelector('.methods-work-container');
        const radios = container.querySelectorAll('.radio-method');

        const boxRep = container.querySelector('.box-replacement');
        const boxSwap = container.querySelector('.box-swap');
        const boxOpen = container.querySelector('.box-open-slot');
        const boxOff = container.querySelector('.box-off-date');

        const selectRepUser = container.querySelector('.select-replacement-user');
        const selectSwapColleague = container.querySelector('.select-swap-colleague');
        const selectSwapShift = container.querySelector('.select-swap-shift');
        const selectOpenSlot = container.querySelector('.select-open-slot');
        const inputOffDate = container.querySelector('.input-off-date');
        const inputReason = container.querySelector('.input-reason');
        const charCount = container.querySelector('.reason-char-count');

        // Standalone elements
        const selectStandalone = container.querySelector('.select-standalone-shift');
        const infoShiftCard = container.querySelector('.info-shift-card');
        const shiftDisplayDate = container.querySelector('.shift-display-date');
        const shiftDisplayTime = container.querySelector('.shift-display-time');
        const shiftDisplayPos = container.querySelector('.shift-display-pos');
        const shiftDisplayBranch = container.querySelector('.shift-display-branch');

        // Preview elements
        const previewBadge = container.querySelector('.preview-badge');
        const previewMyShift = container.querySelector('.preview-my-shift');
        const previewMyStatus = container.querySelector('.preview-my-status');
        const previewTargetName = container.querySelector('.preview-target-name');
        const previewTargetStatus = container.querySelector('.preview-target-status');
        const previewStepper = container.querySelector('.preview-stepper');

        let currentIntent = 'off';

        function updateIntent(intent) {
            currentIntent = intent;
            if (intent === 'off') {
                if (btnOff) {
                    btnOff.className = 'btn-intent btn-intent-off group text-left p-3 rounded-xl border-2 border-emerald-600 bg-emerald-50/30 hover:bg-emerald-50/50 transition-all cursor-pointer relative shadow-2xs';
                    const chk = btnOff.querySelector('.badge-intent-check');
                    if (chk) {
                        chk.classList.remove('hidden');
                        chk.classList.add('flex');
                    }
                }
                if (btnWork) {
                    btnWork.className = 'btn-intent btn-intent-work group text-left p-3 rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50 transition-all cursor-pointer relative';
                    const chk = btnWork.querySelector('.badge-intent-check');
                    if (chk) {
                        chk.classList.add('hidden');
                        chk.classList.remove('flex');
                    }
                }
                if (methodsOff) methodsOff.classList.remove('hidden');
                if (methodsWork) methodsWork.classList.add('hidden');

                // Select first radio in methodsOff
                const r = methodsOff ? methodsOff.querySelector('input[type="radio"]') : null;
                if (r) {
                    r.checked = true;
                    updateMethod(r.value);
                }
            } else {
                if (btnWork) {
                    btnWork.className = 'btn-intent btn-intent-work group text-left p-3 rounded-xl border-2 border-emerald-600 bg-emerald-50/30 hover:bg-emerald-50/50 transition-all cursor-pointer relative shadow-2xs';
                    const chk = btnWork.querySelector('.badge-intent-check');
                    if (chk) {
                        chk.classList.remove('hidden');
                        chk.classList.add('flex');
                    }
                }
                if (btnOff) {
                    btnOff.className = 'btn-intent btn-intent-off group text-left p-3 rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/50 transition-all cursor-pointer relative';
                    const chk = btnOff.querySelector('.badge-intent-check');
                    if (chk) {
                        chk.classList.add('hidden');
                        chk.classList.remove('flex');
                    }
                }
                if (methodsWork) methodsWork.classList.remove('hidden');
                if (methodsOff) methodsOff.classList.add('hidden');

                // Select first radio in methodsWork
                const r = methodsWork ? methodsWork.querySelector('input[type="radio"]') : null;
                if (r) {
                    r.checked = true;
                    updateMethod(r.value);
                }
            }
        }

        function updateMethod(type) {
            // Update styling of method option cards
            radios.forEach(radio => {
                const parent = radio.closest('.method-option-card');
                if (!parent) return;
                if (radio.value === type) {
                    if (type === 'replacement' || type === 'swap') {
                        parent.className = 'method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border-2 border-emerald-500 bg-emerald-50/60 shadow-xs cursor-pointer hover:bg-emerald-50 transition-all';
                    } else if (type === 'change_shift') {
                        parent.className = 'method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border-2 border-amber-400 bg-amber-50/70 shadow-xs cursor-pointer hover:bg-amber-50 transition-all';
                    } else {
                        parent.className = 'method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border-2 border-indigo-400 bg-indigo-50/70 shadow-xs cursor-pointer hover:bg-indigo-50 transition-all';
                    }
                } else {
                    parent.className = 'method-option-card flex items-start gap-2.5 p-2.5 rounded-xl border border-slate-200 bg-white cursor-pointer hover:bg-slate-50 transition-all';
                }
            });

            // Toggle input boxes
            if (boxRep) boxRep.classList.add('hidden');
            if (boxSwap) boxSwap.classList.add('hidden');
            if (boxOpen) boxOpen.classList.add('hidden');
            if (boxOff) boxOff.classList.add('hidden');

            if (selectRepUser) selectRepUser.removeAttribute('required');
            if (selectSwapShift) selectSwapShift.removeAttribute('required');
            if (selectOpenSlot) selectOpenSlot.removeAttribute('required');
            if (inputOffDate) inputOffDate.removeAttribute('required');

            if (type === 'replacement') {
                if (boxRep) boxRep.classList.remove('hidden');
                if (selectRepUser) selectRepUser.setAttribute('required', 'required');
            } else if (type === 'swap') {
                if (boxSwap) boxSwap.classList.remove('hidden');
                if (selectSwapShift) selectSwapShift.setAttribute('required', 'required');
            } else if (type === 'change_shift') {
                if (boxOpen) boxOpen.classList.remove('hidden');
                if (selectOpenSlot) selectOpenSlot.setAttribute('required', 'required');
            } else if (type === 'change_off_day') {
                if (boxOpen) boxOpen.classList.remove('hidden');
                if (boxOff) boxOff.classList.remove('hidden');
                if (selectOpenSlot) selectOpenSlot.setAttribute('required', 'required');
                if (inputOffDate) inputOffDate.setAttribute('required', 'required');
            }

            updateLivePreview(type);
        }

        // Smart Cascading Filter for Swap
        function filterSwapShifts() {
            if (!selectSwapColleague || !selectSwapShift) return;
            const colleagueId = selectSwapColleague.value;
            const options = Array.from(selectSwapShift.querySelectorAll('option[data-user-id]'));
            const initialOpt = selectSwapShift.querySelector('option:not([data-user-id])');

            if (!colleagueId) {
                selectSwapShift.disabled = true;
                options.forEach(opt => {
                    opt.classList.add('hidden');
                    opt.disabled = true;
                });
                if (initialOpt) {
                    initialOpt.textContent = '-- Pilih rekan pada langkah 3A terlebih dahulu --';
                    initialOpt.selected = true;
                }
                updateLivePreview('swap');
                return;
            }

            selectSwapShift.disabled = false;
            let matchCount = 0;
            let firstMatch = null;

            options.forEach(opt => {
                if (opt.dataset.userId === colleagueId) {
                    opt.classList.remove('hidden');
                    opt.disabled = false;
                    matchCount++;
                    if (!firstMatch) firstMatch = opt;
                } else {
                    opt.classList.add('hidden');
                    opt.disabled = true;
                }
            });

            if (matchCount === 0) {
                if (initialOpt) {
                    initialOpt.textContent = '(Rekan ini belum memiliki jadwal shift kerja aktif mendatang)';
                    initialOpt.selected = true;
                }
                selectSwapShift.disabled = true;
            } else if (matchCount === 1 && firstMatch) {
                firstMatch.selected = true;
                if (initialOpt) initialOpt.textContent = '-- Pilih Jadwal Shift Rekan --';
            } else {
                if (initialOpt) {
                    initialOpt.textContent = `-- Pilih salah satu jadwal shift rekan (${matchCount} jadwal tersedia) --`;
                    initialOpt.selected = true;
                }
            }

            updateLivePreview('swap');
        }

        function updateLivePreview(type) {
            if (!type) {
                const checked = container.querySelector('.radio-method:checked');
                type = checked ? checked.value : 'replacement';
            }

            const stepArrow = '<svg class="w-3 h-3 text-slate-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>';

            // My shift info
            let myShiftText = '';
            if (isStandalone) {
                const opt = selectStandalone && selectStandalone.selectedOptions[0];
                if (opt && opt.value) {
                    myShiftText = `${opt.dataset.date} (${opt.dataset.time})`;
                } else {
                    myShiftText = 'Pilih jadwal kerja Anda';
                }
            } else {
                const dateEl = container.querySelector('.shift-display-date');
                const timeEl = container.querySelector('.shift-display-time');
                myShiftText = (dateEl ? dateEl.textContent.trim() : '') + (timeEl ? ' ' + timeEl.textContent.trim() : '');
            }

            if (previewMyShift) previewMyShift.textContent = myShiftText;

            if (type === 'replacement') {
                if (previewBadge) {
                    previewBadge.textContent = 'Searah: Saya Libur';
                    previewBadge.className = 'preview-badge px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[10px] font-bold uppercase tracking-wider';
                }
                if (previewMyStatus) {
                    previewMyStatus.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200/60"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Menjadi Libur (Off)</span>';
                }

                let targetUser = '(Belum Memilih Rekan)';
                if (selectRepUser && selectRepUser.selectedOptions[0] && selectRepUser.value) {
                    targetUser = selectRepUser.selectedOptions[0].dataset.name || selectRepUser.selectedOptions[0].textContent.trim();
                }
                if (previewTargetName) previewTargetName.textContent = targetUser;
                if (previewTargetStatus) {
                    previewTargetStatus.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200/60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Masuk Menggantikan</span>';
                }

                if (previewStepper) {
                    previewStepper.innerHTML = `
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">1. Rekan Setuju</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">2. Review Manajer</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-slate-900 text-white font-semibold">3. Jadwal Berpindah</span>
                    `;
                }
            } else if (type === 'swap') {
                if (previewBadge) {
                    previewBadge.textContent = '2 Arah: Saling Bertukar';
                    previewBadge.className = 'preview-badge px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[10px] font-bold uppercase tracking-wider';
                }

                let targetUser = '(Belum Memilih Rekan)';
                if (selectSwapColleague && selectSwapColleague.selectedOptions[0] && selectSwapColleague.value) {
                    targetUser = selectSwapColleague.selectedOptions[0].dataset.name || selectSwapColleague.selectedOptions[0].textContent.trim();
                }

                let targetShiftLabel = '(Pilih Jadwal Rekan)';
                if (selectSwapShift && selectSwapShift.selectedOptions[0] && selectSwapShift.value) {
                    targetShiftLabel = selectSwapShift.selectedOptions[0].dataset.label || selectSwapShift.selectedOptions[0].textContent.trim();
                }

                if (previewMyStatus) {
                    previewMyStatus.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200/60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Masuk: ${targetShiftLabel}</span>`;
                }
                if (previewTargetName) previewTargetName.textContent = targetUser;
                if (previewTargetStatus) {
                    previewTargetStatus.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-emerald-800 bg-emerald-50 border border-emerald-200/60"><span class="w-1.5 h-1.5 rounded-full bg-emerald-600"></span>Mengambil Jadwal Anda</span>';
                }

                if (previewStepper) {
                    const peerName = targetUser !== '(Belum Memilih Rekan)' ? targetUser : 'Rekan';
                    previewStepper.innerHTML = `
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">1. ${peerName} Setuju</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">2. Review Manajer</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-slate-900 text-white font-semibold">3. Jadwal Bertukar</span>
                    `;
                }
            } else if (type === 'change_shift') {
                if (previewBadge) {
                    previewBadge.textContent = 'Slot Kosong: Geser Libur';
                    previewBadge.className = 'preview-badge px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[10px] font-bold uppercase tracking-wider';
                }
                if (previewMyStatus) {
                    previewMyStatus.innerHTML = '<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-rose-700 bg-rose-50 border border-rose-200/60"><span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>Jadwal Ini Menjadi Libur</span>';
                }

                let slotLabel = '(Pilih Slot Kosong)';
                if (selectOpenSlot && selectOpenSlot.selectedOptions[0] && selectOpenSlot.value) {
                    const opt = selectOpenSlot.selectedOptions[0];
                    slotLabel = `${opt.dataset.date || ''} (${opt.dataset.time || ''})`;
                }
                if (previewTargetName) previewTargetName.textContent = 'Slot Kosong Cabang';
                if (previewTargetStatus) {
                    previewTargetStatus.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200/60"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Anda Masuk: ${slotLabel}</span>`;
                }

                if (previewStepper) {
                    previewStepper.innerHTML = `
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">1. Diteruskan ke HRD</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">2. Disahkan HRD / Admin</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-slate-900 text-white font-semibold">3. Jadwal Aktif</span>
                    `;
                }
            } else if (type === 'change_off_day') {
                if (previewBadge) {
                    previewBadge.textContent = 'Slot Kosong: Ganti Libur';
                    previewBadge.className = 'preview-badge px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-700 text-[10px] font-bold uppercase tracking-wider';
                }

                const offDateVal = inputOffDate && inputOffDate.value ? inputOffDate.value : '(Pilih Tanggal)';
                if (previewMyStatus) {
                    previewMyStatus.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-indigo-800 bg-indigo-50 border border-indigo-200/60"><span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>Hari Libur Resmi: ${offDateVal}</span>`;
                }

                let slotLabel = '(Pilih Slot Kosong)';
                if (selectOpenSlot && selectOpenSlot.selectedOptions[0] && selectOpenSlot.value) {
                    const opt = selectOpenSlot.selectedOptions[0];
                    slotLabel = `${opt.dataset.date || ''} (${opt.dataset.time || ''})`;
                }
                if (previewTargetName) previewTargetName.textContent = 'Slot Kosong Cabang';
                if (previewTargetStatus) {
                    previewTargetStatus.innerHTML = `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200/60"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Anda Masuk: ${slotLabel}</span>`;
                }

                if (previewStepper) {
                    previewStepper.innerHTML = `
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-semibold text-slate-700 shadow-2xs">1. Diteruskan ke HRD</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 text-slate-600">2. Disahkan HRD / Admin</span>
                        ${stepArrow}
                        <span class="px-2 py-0.5 rounded-md bg-slate-900 text-white font-semibold">3. Jadwal & Libur Aktif</span>
                    `;
                }
            }
        }

        // Event Listeners
        if (btnOff) {
            btnOff.addEventListener('click', () => updateIntent('off'));
        }
        if (btnWork) {
            btnWork.addEventListener('click', () => updateIntent('work'));
        }

        radios.forEach(radio => {
            radio.addEventListener('change', () => updateMethod(radio.value));
        });

        if (selectRepUser) {
            selectRepUser.addEventListener('change', () => updateLivePreview('replacement'));
        }

        if (selectSwapColleague) {
            selectSwapColleague.addEventListener('change', filterSwapShifts);
        }

        if (selectSwapShift) {
            selectSwapShift.addEventListener('change', () => updateLivePreview('swap'));
        }

        if (selectOpenSlot) {
            selectOpenSlot.addEventListener('change', () => {
                const checked = container.querySelector('.radio-method:checked');
                updateLivePreview(checked ? checked.value : 'change_shift');
            });
        }

        if (inputOffDate) {
            inputOffDate.addEventListener('input', () => updateLivePreview('change_off_day'));
        }

        if (inputReason && charCount) {
            inputReason.addEventListener('input', () => {
                charCount.textContent = `${inputReason.value.length} / 500`;
            });
        }

        if (isStandalone && selectStandalone) {
            selectStandalone.addEventListener('change', () => {
                const opt = selectStandalone.selectedOptions[0];
                if (opt && opt.value) {
                    if (infoShiftCard) infoShiftCard.classList.remove('hidden');
                    if (shiftDisplayDate) shiftDisplayDate.textContent = opt.dataset.date || '';
                    if (shiftDisplayTime) shiftDisplayTime.textContent = opt.dataset.time || '';
                    if (shiftDisplayPos) shiftDisplayPos.textContent = opt.dataset.position || '';
                    if (shiftDisplayBranch) shiftDisplayBranch.textContent = opt.dataset.branch || '';
                    form.action = `/shifts/${opt.value}/exchange`;
                } else {
                    if (infoShiftCard) infoShiftCard.classList.add('hidden');
                    form.action = defaultAction;
                }
                const checked = container.querySelector('.radio-method:checked');
                updateLivePreview(checked ? checked.value : 'replacement');
            });
        }

        // Initialize state
        updateIntent('off');
    }

    // Initialize all modals currently in DOM
    document.querySelectorAll('.exchange-modal-box').forEach(box => {
        initModal(box.closest('[data-modal-container]') || box);
    });

    // Also observe modal open clicks
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-modal]');
        if (btn) {
            const targetId = btn.getAttribute('data-open-modal');
            const targetModal = document.getElementById(targetId);
            if (targetModal && targetModal.querySelector('.exchange-modal-box')) {
                initModal(targetModal);
            }
        }
    });

    // Backward-compatibility bridge
    window.toggleExchangeBoxes = function(shiftId, type) {
        const modal = document.getElementById('modal-exchange-shift-' + shiftId);
        if (modal) {
            const radio = modal.querySelector(`.radio-method[value="${type}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        }
    };
})();
</script>
@endpush
