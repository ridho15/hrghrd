@props([
    'id' => 'map-picker-' . uniqid(),
    'latitude' => null,
    'longitude' => null,
    'radius' => 100,
    'latInputId' => 'latitude',
    'lngInputId' => 'longitude',
    'radiusInputId' => 'radius_m',
    'readonly' => false,
    'height' => '360px',
    'zoom' => 16,
    'title' => 'Peta Interaktif Geofence',
    'branchName' => 'Cabang Perusahaan',
])

@php
    $defaultLat = $latitude !== null && is_numeric($latitude) ? (float) $latitude : -6.2088;
    $defaultLng = $longitude !== null && is_numeric($longitude) ? (float) $longitude : 106.8456;
    $defaultRadius = $radius !== null && is_numeric($radius) ? (int) $radius : 100;
@endphp

@pushOnce('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        .leaflet-container {
            font-family: inherit;
        }
        .leaflet-popup-content-wrapper {
            border-radius: 1rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            padding: 4px;
        }
        .leaflet-popup-content {
            margin: 12px 14px;
            font-size: 12px;
            line-height: 1.4;
        }
        .custom-pin-marker {
            background: transparent;
            border: none;
        }
    </style>
@endpushOnce

<div 
    id="{{ $id }}-container"
    class="w-full rounded-2xl border border-slate-200/90 bg-white overflow-hidden shadow-xs flex flex-col space-y-0"
    data-map-picker
    data-map-id="{{ $id }}"
    data-lat="{{ $defaultLat }}"
    data-lng="{{ $defaultLng }}"
    data-radius="{{ $defaultRadius }}"
    data-lat-input="{{ $latInputId }}"
    data-lng-input="{{ $lngInputId }}"
    data-radius-input="{{ $radiusInputId }}"
    data-readonly="{{ $readonly ? 'true' : 'false' }}"
    data-zoom="{{ $zoom }}"
    data-branch-name="{{ $branchName }}"
>
    {{-- Header Bilah Kontrol Peta --}}
    <div class="p-3.5 sm:px-4 sm:py-3 bg-slate-50 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
        <div class="flex items-center gap-2">
            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
            </span>
            <div>
                <h4 class="text-xs font-bold text-slate-800 tracking-tight m-0">{{ $title }}</h4>
                <p class="text-[11px] text-slate-500 m-0">
                    @if($readonly)
                        Visualisasi jangkauan absensi presensi cabang
                    @else
                        Klik atau geser penanda pin untuk menentukan koordinat presensi
                    @endif
                </p>
            </div>
        </div>

        @if(!$readonly)
            {{-- Toolbar Interaktif: Pencarian Lokasi & Tombol GPS --}}
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                {{-- Input Pencarian Alamat Cepat --}}
                <div class="relative w-full sm:w-56">
                    <input 
                        type="text" 
                        id="{{ $id }}-search" 
                        placeholder="Cari lokasi / kota..." 
                        class="w-full pl-7 pr-7 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-medium text-slate-800 placeholder-slate-400 focus:outline-hidden focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition-all"
                    >
                    <span class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </span>
                    <button 
                        type="button" 
                        id="{{ $id }}-search-btn" 
                        class="absolute inset-y-0 right-0 pr-2 flex items-center text-slate-400 hover:text-emerald-700 transition-colors cursor-pointer"
                        title="Cari Alamat"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>

                {{-- Tombol Gunakan Lokasi GPS Saya --}}
                <button 
                    type="button" 
                    id="{{ $id }}-locate-btn"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 text-xs font-bold transition-all shadow-2xs cursor-pointer shrink-0"
                    title="Gunakan posisi GPS perangkat saat ini"
                >
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span id="{{ $id }}-locate-text">Lokasi Saya</span>
                </button>

                {{-- Tombol Fokus ke Pin --}}
                <button 
                    type="button" 
                    id="{{ $id }}-recenter-btn"
                    class="p-1.5 rounded-lg bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 transition-colors shadow-2xs cursor-pointer shrink-0"
                    title="Pusatkan Peta ke Pin"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                </button>
            </div>
        @else
            <div class="flex items-center gap-2">
                <a 
                    href="https://maps.google.com/?q={{ $defaultLat }},{{ $defaultLng }}" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 hover:text-emerald-700 hover:bg-slate-50 text-[11px] font-bold transition-all shadow-2xs"
                >
                    <span>Buka Google Maps</span>
                    <svg class="w-3 h-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                </a>
            </div>
        @endif
    </div>

    {{-- Kanvas Peta Leaflet --}}
    <div class="relative w-full" style="height: {{ $height }};">
        <div id="{{ $id }}-canvas" class="w-full h-full z-10 bg-slate-100"></div>

        {{-- Overlay Keterangan Geofence Status --}}
        <div class="absolute bottom-2.5 left-2.5 z-20 pointer-events-none">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/95 backdrop-blur-xs border border-slate-200/90 shadow-md text-[11px] font-semibold text-slate-700">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-emerald-200"></span>
                <span>Radius Geofence: <strong id="{{ $id }}-radius-badge" class="text-emerald-800 font-extrabold">{{ $defaultRadius }}m</strong></span>
                <span class="text-slate-300">|</span>
                <span id="{{ $id }}-coord-badge" class="font-mono text-slate-500 font-medium">{{ number_format($defaultLat, 4) }}, {{ number_format($defaultLng, 4) }}</span>
            </div>
        </div>
    </div>
</div>

@pushOnce('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            initLeafletMapPickers();
        });

        function initLeafletMapPickers() {
            if (typeof L === 'undefined') {
                console.warn('Leaflet JS belum siap, mencoba memuat ulang dalam 300ms...');
                setTimeout(initLeafletMapPickers, 300);
                return;
            }

            document.querySelectorAll('[data-map-picker]').forEach(container => {
                if (container.dataset.mapInitialized === 'true') return;
                container.dataset.mapInitialized = 'true';

                const mapId = container.dataset.mapId;
                const canvas = document.getElementById(`${mapId}-canvas`);
                if (!canvas) return;

                const isReadonly = container.dataset.readonly === 'true';
                const latInput = document.getElementById(container.dataset.latInput);
                const lngInput = document.getElementById(container.dataset.lngInput);
                const radiusInput = document.getElementById(container.dataset.radiusInput);
                const radiusBadge = document.getElementById(`${mapId}-radius-badge`);
                const coordBadge = document.getElementById(`${mapId}-coord-badge`);
                const branchName = container.dataset.branchName || 'Lokasi Cabang';

                let initialLat = parseFloat(container.dataset.lat);
                let initialLng = parseFloat(container.dataset.lng);
                let initialRadius = parseInt(container.dataset.radius, 10) || 100;
                let zoomLevel = parseInt(container.dataset.zoom, 10) || 16;

                // Sinkronisasi dengan nilai awal input jika ada
                if (latInput && !isNaN(parseFloat(latInput.value))) initialLat = parseFloat(latInput.value);
                if (lngInput && !isNaN(parseFloat(lngInput.value))) initialLng = parseFloat(lngInput.value);
                if (radiusInput && !isNaN(parseInt(radiusInput.value, 10))) initialRadius = parseInt(radiusInput.value, 10);

                // Inisialisasi Peta Leaflet
                const map = L.map(canvas, {
                    center: [initialLat, initialLng],
                    zoom: zoomLevel,
                    zoomControl: true,
                    attributionControl: false
                });

                // Tambahkan Tile OpenStreetMap
                L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap contributors'
                }).addTo(map);

                // Tambahkan attribution kontrol kecil di pojok kanan bawah
                L.control.attribution({ position: 'bottomright', prefix: false })
                    .addAttribution('&copy; <a href="https://openstreetmap.org" target="_blank" class="text-slate-400">OSM</a>')
                    .addTo(map);

                // Custom Pin Marker Icon (Modern Emerald SVG Pin)
                const pinIcon = L.divIcon({
                    className: 'custom-pin-marker',
                    html: `
                        <div style="position: relative; width: 34px; height: 34px; transform: translate(-17px, -34px); cursor: ${isReadonly ? 'default' : 'grab'};">
                            <div style="width: 34px; height: 34px; background: #047857; border: 2px solid #ffffff; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); display: flex; items-center; justify-content: center; box-shadow: 0 4px 12px rgba(4,120,87,0.45);">
                                <svg style="transform: rotate(45deg); width: 18px; height: 18px; margin: auto; color: white;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                        </div>
                    `,
                    iconSize: [34, 34],
                    iconAnchor: [0, 0]
                });

                // Buat Marker
                const marker = L.marker([initialLat, initialLng], {
                    icon: pinIcon,
                    draggable: !isReadonly
                }).addTo(map);

                // Buat Geofence Circle
                const circle = L.circle([initialLat, initialLng], {
                    radius: initialRadius,
                    color: '#059669',
                    weight: 2,
                    fillColor: '#10b981',
                    fillOpacity: 0.22,
                    dashArray: '5, 5'
                }).addTo(map);

                // Popup Penjelas
                const popupContent = `
                    <div style="font-family: inherit;">
                        <h4 style="margin: 0; font-size: 13px; font-weight: 800; color: #0f172a;">${branchName}</h4>
                        <p style="margin: 3px 0 0; font-size: 11px; color: #475569;">
                            Radius Validasi Absensi: <strong style="color: #047857;">${initialRadius} meter</strong>
                        </p>
                        <p style="margin: 2px 0 0; font-size: 10px; font-family: monospace; color: #64748b;">
                            ${initialLat.toFixed(6)}, ${initialLng.toFixed(6)}
                        </p>
                    </div>
                `;
                marker.bindPopup(popupContent);
                if (isReadonly) {
                    marker.openPopup();
                }

                // Fungsi Update State Koordinat & Peta
                function updateCoordinates(lat, lng, pan = false) {
                    const roundedLat = parseFloat(lat.toFixed(6));
                    const roundedLng = parseFloat(lng.toFixed(6));

                    marker.setLatLng([roundedLat, roundedLng]);
                    circle.setLatLng([roundedLat, roundedLng]);

                    if (latInput) latInput.value = roundedLat;
                    if (lngInput) lngInput.value = roundedLng;

                    if (coordBadge) {
                        coordBadge.textContent = `${roundedLat.toFixed(4)}, ${roundedLng.toFixed(4)}`;
                    }

                    if (pan) {
                        map.panTo([roundedLat, roundedLng]);
                    }
                }

                // Fungsi Update Radius Circle
                function updateRadius(radiusMeters) {
                    const r = Math.max(10, Math.min(2000, parseInt(radiusMeters, 10) || 100));
                    circle.setRadius(r);
                    if (radiusBadge) radiusBadge.textContent = `${r}m`;
                }

                // Interaksi Map (Jika bukan mode Readonly)
                if (!isReadonly) {
                    // Geser Marker
                    marker.on('dragend', () => {
                        const pos = marker.getLatLng();
                        updateCoordinates(pos.lat, pos.lng, true);
                    });

                    // Klik Peta
                    map.on('click', (e) => {
                        updateCoordinates(e.latlng.lat, e.latlng.lng, true);
                    });

                    // Sinkronisasi Balik: Input Form -> Peta
                    if (latInput && lngInput) {
                        const syncFromInputs = () => {
                            const parsedLat = parseFloat(latInput.value);
                            const parsedLng = parseFloat(lngInput.value);
                            if (!isNaN(parsedLat) && !isNaN(parsedLng)) {
                                updateCoordinates(parsedLat, parsedLng, true);
                            }
                        };
                        latInput.addEventListener('change', syncFromInputs);
                        lngInput.addEventListener('change', syncFromInputs);
                    }

                    if (radiusInput) {
                        radiusInput.addEventListener('input', () => {
                            updateRadius(radiusInput.value);
                        });
                        radiusInput.addEventListener('change', () => {
                            updateRadius(radiusInput.value);
                        });
                    }

                    // Tombol Pusatkan ke Pin
                    const recenterBtn = document.getElementById(`${mapId}-recenter-btn`);
                    if (recenterBtn) {
                        recenterBtn.addEventListener('click', () => {
                            const pos = marker.getLatLng();
                            map.setView(pos, zoomLevel);
                        });
                    }

                    // Tombol Gunakan Lokasi GPS Saya
                    const locateBtn = document.getElementById(`${mapId}-locate-btn`);
                    const locateText = document.getElementById(`${mapId}-locate-text`);
                    if (locateBtn) {
                        locateBtn.addEventListener('click', () => {
                            if (!navigator.geolocation) {
                                alert('Peramban web Anda tidak mendukung deteksi lokasi Geolocation.');
                                return;
                            }

                            if (locateText) locateText.textContent = 'Mencari...';
                            locateBtn.disabled = true;

                            navigator.geolocation.getCurrentPosition(
                                (pos) => {
                                    const userLat = pos.coords.latitude;
                                    const userLng = pos.coords.longitude;
                                    updateCoordinates(userLat, userLng, true);
                                    map.setView([userLat, userLng], 17);
                                    if (locateText) locateText.textContent = 'Lokasi Terpasang';
                                    setTimeout(() => {
                                        if (locateText) locateText.textContent = 'Lokasi Saya';
                                        locateBtn.disabled = false;
                                    }, 2000);
                                },
                                (err) => {
                                    alert('Gagal mengambil lokasi GPS: ' + (err.message || 'Izin akses lokasi ditolak oleh browser.'));
                                    if (locateText) locateText.textContent = 'Lokasi Saya';
                                    locateBtn.disabled = false;
                                },
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                            );
                        });
                    }

                    // Pencarian Lokasi via OpenStreetMap Nominatim
                    const searchInput = document.getElementById(`${mapId}-search`);
                    const searchBtn = document.getElementById(`${mapId}-search-btn`);

                    const performSearch = async () => {
                        const query = searchInput?.value.trim();
                        if (!query) return;

                        const originalPlaceholder = searchInput.placeholder;
                        searchInput.placeholder = 'Mencari lokasi...';
                        searchInput.disabled = true;

                        try {
                            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`;
                            const res = await fetch(url, {
                                headers: {
                                    'Accept-Language': 'id,en'
                                }
                            });
                            const data = await res.json();
                            if (data && data.length > 0) {
                                const foundLat = parseFloat(data[0].lat);
                                const foundLng = parseFloat(data[0].lon);
                                updateCoordinates(foundLat, foundLng, true);
                                map.setView([foundLat, foundLng], 16);
                            } else {
                                alert(`Lokasi "${query}" tidak ditemukan. Coba gunakan nama kota atau jalan yang lebih spesifik.`);
                            }
                        } catch (err) {
                            console.error('Gagal mencari alamat via Nominatim:', err);
                            alert('Gagal menghubungi layanan pencarian peta. Periksa koneksi internet Anda.');
                        } finally {
                            searchInput.placeholder = originalPlaceholder;
                            searchInput.disabled = false;
                        }
                    };

                    searchBtn?.addEventListener('click', performSearch);
                    searchInput?.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            performSearch();
                        }
                    });
                }

                // Invalidate size jika modal atau container baru saja dimuat
                setTimeout(() => {
                    map.invalidateSize();
                }, 250);
            });
        }
    </script>
@endpushOnce
