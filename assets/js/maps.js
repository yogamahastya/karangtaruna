document.addEventListener('DOMContentLoaded', function() {
    // --- Variabel Global & Elemen DOM ---
    const editAnggotaModal = document.getElementById('editAnggotaModal');
    const editKegiatanModal = document.getElementById('editKegiatanModal');
    const editKeuanganModal = document.getElementById('editKeuanganModal');
    const editIuranModal = document.getElementById('editIuranModal');
    const editUsersModal = document.getElementById('editUsersModal');

    // Elemen terkait lokasi absensi
    const addLokasiModal = document.getElementById('addLokasiModal');
    const detectDeviceLocationBtn = document.getElementById('detect-device-location-btn');
    const latitudeInput = document.getElementById('lokasi_latitude');
    const longitudeInput = document.getElementById('lokasi_longitude');
    const gmapsIframe = document.getElementById('gmaps-iframe');
    const toastContainer = document.getElementById('toast-container');
    
    // --- Fungsi Bantuan ---

    // Fungsi untuk menampilkan Toast
    function showToast(message, isSuccess, customBgClass = null) {
        if (!toastContainer) {
            console.error("Elemen toast-container tidak ditemukan.");
            return;
        }

        const toast = document.createElement('div');
        const bgClass = customBgClass || (isSuccess ? 'bg-success' : 'bg-danger');

        toast.className = `toast align-items-center text-white ${bgClass} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        const bootstrapToast = new bootstrap.Toast(toast, { delay: 5000 });
        bootstrapToast.show();

        toast.addEventListener('hidden.bs.toast', () => toast.remove());
    }

    // Fungsi untuk memperbarui peta Google Maps
    function updateMap(latitude, longitude) {
        if (gmapsIframe) {
            // Perbaikan URL: Menggunakan alamat yang benar dan variabel dinamis
            gmapsIframe.src = `https://maps.google.com/maps?q=${latitude},${longitude}&z=15&output=embed`;
        }
    }

    // Fungsi untuk mendeteksi lokasi perangkat dan memperbarui peta
    function detectAndShowLocation() {
        if (navigator.geolocation) {
            showToast("Mencari lokasi perangkat...", true, 'bg-secondary');

            navigator.geolocation.getCurrentPosition(position => {
                const lat = position.coords.latitude;
                const lon = position.coords.longitude;
                
                // Isi input form dengan koordinat baru
                latitudeInput.value = lat;
                longitudeInput.value = lon;
                
                // Panggil fungsi updateMap untuk memperbarui peta
                updateMap(lat, lon);
                
                showToast("Lokasi perangkat berhasil dideteksi dan diisi.", true);
            }, error => {
                let errorMessage = "Gagal mendeteksi lokasi perangkat: ";
                switch(error.code) {
                    case error.PERMISSION_DENIED: errorMessage += "Pengguna menolak permintaan Geolocation."; break;
                    case error.POSITION_UNAVAILABLE: errorMessage += "Informasi lokasi tidak tersedia."; break;
                    case error.TIMEOUT: errorMessage += "Permintaan lokasi habis waktu."; break;
                    case error.UNKNOWN_ERROR: errorMessage += "Terjadi kesalahan tidak dikenal."; break;
                }
                showToast(errorMessage, false);
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            });
        } else {
            showToast("Browser Anda tidak mendukung Geolocation API.", false);
        }
    }

    // --- Logika Event Listeners ---
    
    // Logika Modal Lokasi
    if (addLokasiModal) {
        // Event listener yang memicu update peta saat modal terbuka
        addLokasiModal.addEventListener('shown.bs.modal', function () {
            const currentLatitude = parseFloat(latitudeInput.value);
            const currentLongitude = parseFloat(longitudeInput.value);
            
            if (!isNaN(currentLatitude) && !isNaN(currentLongitude)) {
                updateMap(currentLatitude, currentLongitude);
            } else {
                // Tampilkan lokasi default jika input kosong
                updateMap(-6.2088, 106.8456); // Lokasi default: Jakarta
            }
        });
    }

    // Event listener untuk tombol "Gunakan Lokasi Perangkat Sekarang"
    if (detectDeviceLocationBtn) {
        detectDeviceLocationBtn.addEventListener('click', detectAndShowLocation);
    }
    
    // Logika untuk mengisi data modal Edit Anggota
    if (editAnggotaModal) {
        editAnggotaModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#edit-anggota-id').value = button.getAttribute('data-id');
            this.querySelector('#edit-nama-lengkap').value = button.getAttribute('data-nama');
            this.querySelector('#edit-jabatan').value = button.getAttribute('data-jabatan');
            this.querySelector('#edit-bergabung-sejak').value = button.getAttribute('data-sejak');
        });
    }

    // Logika untuk mengisi data modal Edit Kegiatan
    if (editKegiatanModal) {
        editKegiatanModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#edit-kegiatan-id').value = button.getAttribute('data-id');
            this.querySelector('#edit-nama-kegiatan').value = button.getAttribute('data-nama');
            this.querySelector('#edit-deskripsi').value = button.getAttribute('data-deskripsi');
            this.querySelector('#edit-lokasi').value = button.getAttribute('data-lokasi');
            this.querySelector('#edit-tanggal-mulai').value = button.getAttribute('data-tanggal');
        });
    }

    // Logika untuk mengisi data modal Edit Keuangan
    if (editKeuanganModal) {
        editKeuanganModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#edit-keuangan-id').value = button.getAttribute('data-id');
            this.querySelector('#edit-jenis-transaksi').value = button.getAttribute('data-jenis');
            this.querySelector('#edit-jumlah-keuangan').value = button.getAttribute('data-jumlah');
            this.querySelector('#edit-deskripsi-keuangan').value = button.getAttribute('data-deskripsi');
            this.querySelector('#edit-tanggal-transaksi').value = button.getAttribute('data-tanggal');
        });
    }

    // Logika untuk mengisi data modal Edit Iuran
    if (editIuranModal) {
        editIuranModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#edit-iuran-id').value = button.getAttribute('data-id');
            this.querySelector('#edit-anggota-id').value = button.getAttribute('data-anggota-id');
            this.querySelector('#edit-tanggal-bayar').value = button.getAttribute('data-tanggal');
            this.querySelector('#edit-jumlah-iuran').value = button.getAttribute('data-jumlah');
            this.querySelector('#edit-keterangan').value = button.getAttribute('data-keterangan');
        });
    }

    // Logika untuk mengisi data modal Edit Users
    if (editUsersModal) {
        editUsersModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            this.querySelector('#edit-users-id').value = button.getAttribute('data-id');
            this.querySelector('#edit-username').value = button.getAttribute('data-username');
            this.querySelector('#edit-role').value = button.getAttribute('data-role');
            this.querySelector('#edit-anggota-id-user').value = button.getAttribute('data-anggota-id');
            this.querySelector('#edit-password').value = ''; 
        });
    }

});