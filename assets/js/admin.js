<<<<<<< HEAD

    document.addEventListener('DOMContentLoaded', function() {
        // Logika untuk mengisi data modal Edit Anggota
        var editAnggotaModal = document.getElementById('editAnggotaModal');
        editAnggotaModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var jabatan = button.getAttribute('data-jabatan');
            var sejak = button.getAttribute('data-sejak');
            var modal = this;
            modal.querySelector('#edit-anggota-id').value = id;
            modal.querySelector('#edit-nama-lengkap').value = nama;
            modal.querySelector('#edit-jabatan').value = jabatan;
            modal.querySelector('#edit-bergabung-sejak').value = sejak;
        });

        // Logika untuk mengisi data modal Edit Kegiatan
        var editKegiatanModal = document.getElementById('editKegiatanModal');
        editKegiatanModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var deskripsi = button.getAttribute('data-deskripsi');
            var lokasi = button.getAttribute('data-lokasi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-kegiatan-id').value = id;
            modal.querySelector('#edit-nama-kegiatan').value = nama;
            modal.querySelector('#edit-deskripsi').value = deskripsi;
            modal.querySelector('#edit-lokasi').value = lokasi;
            modal.querySelector('#edit-tanggal-mulai').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Keuangan
        var editKeuanganModal = document.getElementById('editKeuanganModal');
        editKeuanganModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var jenis = button.getAttribute('data-jenis');
            var jumlah = button.getAttribute('data-jumlah');
            var deskripsi = button.getAttribute('data-deskripsi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-keuangan-id').value = id;
            modal.querySelector('#edit-jenis-transaksi').value = jenis;
            modal.querySelector('#edit-jumlah-keuangan').value = jumlah;
            modal.querySelector('#edit-deskripsi-keuangan').value = deskripsi;
            modal.querySelector('#edit-tanggal-transaksi').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Iuran
        var editIuranModal = document.getElementById('editIuranModal');
        editIuranModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var anggotaId = button.getAttribute('data-anggota-id');
            var tanggal = button.getAttribute('data-tanggal');
            var jumlah = button.getAttribute('data-jumlah');
            var keterangan = button.getAttribute('data-keterangan');
            var modal = this;
            modal.querySelector('#edit-iuran-id').value = id;
            modal.querySelector('#edit-anggota-id').value = anggotaId;
            modal.querySelector('#edit-tanggal-bayar').value = tanggal;
            modal.querySelector('#edit-jumlah-iuran').value = jumlah;
            modal.querySelector('#edit-keterangan').value = keterangan;
        });

        // Logika untuk mengisi data modal Edit Users
        var editUsersModal = document.getElementById('editUsersModal');
        editUsersModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var role = button.getAttribute('data-role');
            var anggotaId = button.getAttribute('data-anggota-id');
            var modal = this;
            modal.querySelector('#edit-users-id').value = id;
            modal.querySelector('#edit-username').value = username;
            modal.querySelector('#edit-role').value = role;
            modal.querySelector('#edit-anggota-id-user').value = anggotaId;
            modal.querySelector('#edit-password').value = ''; // Kosongkan password untuk keamanan
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const detectBtn = document.getElementById('detect-coords-btn');
        const gmapsUrlInput = document.getElementById('gmaps-url');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');

        detectBtn.addEventListener('click', function() {
            const gmapsUrl = gmapsUrlInput.value;
            const regex = /@(-?\d+\.\d+),(-?\d+\.\d+)/;
            const match = gmapsUrl.match(regex);
            
            let message = "";
            let isSuccess = false;

            if (match && match.length >= 3) {
                const lat = match[1];
                const lon = match[2];

                latitudeInput.value = lat;
                longitudeInput.value = lon;
                
                message = "Koordinat berhasil dideteksi dan diisi.";
                isSuccess = true;
            } else {
                message = "Tautan Google Maps tidak valid. Pastikan tautan memiliki format yang benar.";
                isSuccess = false;
            }

            // Tampilkan notifikasi toast
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0`;
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
            const bootstrapToast = new bootstrap.Toast(toast);
            bootstrapToast.show();
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const detectDeviceLocationBtn = document.getElementById('detect-device-location-btn');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');
        const toastContainer = document.getElementById('toast-container'); // Pastikan ini ada di HTML Anda

        detectDeviceLocationBtn.addEventListener('click', function() {
            // Periksa apakah browser mendukung Geolocation API
            if (navigator.geolocation) {
                // Tampilkan pesan loading
                showToast("Mencari lokasi perangkat...", false, 'bg-secondary');

                // Dapatkan lokasi saat ini
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Callback sukses
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        latitudeInput.value = lat;
                        longitudeInput.value = lon;
                        
                        showToast("Lokasi perangkat berhasil dideteksi dan diisi.", true);
                    },
                    function(error) {
                        // Callback error
                        let errorMessage = "Gagal mendeteksi lokasi perangkat: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Pengguna menolak permintaan Geolocation.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Permintaan lokasi habis waktu.";
                                break;
                            case error.UNKNOWN_ERROR:
                                errorMessage += "Terjadi kesalahan tidak dikenal.";
                                break;
                        }
                        showToast(errorMessage, false);
                    },
                    {
                        // Opsi konfigurasi Geolocation
                        enableHighAccuracy: true, // Mencoba mendapatkan lokasi seakurat mungkin
                        timeout: 10000,           // Waktu maksimum (ms) untuk menunggu hasil
                        maximumAge: 0             // Jangan gunakan cache lokasi yang sudah tua
                    }
                );
            } else {
                showToast("Browser Anda tidak mendukung Geolocation API.", false);
            }
        });

        // Fungsi bantu untuk menampilkan Toast
        function showToast(message, isSuccess, customBgClass = null) {
            const toast = document.createElement('div');
            let bgClass = customBgClass ? customBgClass : (isSuccess ? 'bg-success' : 'bg-danger');

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
            const bootstrapToast = new bootstrap.Toast(toast, { delay: 5000 }); // Toast hilang setelah 5 detik
            bootstrapToast.show();
            // Hapus toast dari DOM setelah disembunyikan untuk menjaga kebersihan
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
    });
=======

    document.addEventListener('DOMContentLoaded', function() {
        // Logika untuk mengisi data modal Edit Anggota
        var editAnggotaModal = document.getElementById('editAnggotaModal');
        editAnggotaModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var jabatan = button.getAttribute('data-jabatan');
            var sejak = button.getAttribute('data-sejak');
            var modal = this;
            modal.querySelector('#edit-anggota-id').value = id;
            modal.querySelector('#edit-nama-lengkap').value = nama;
            modal.querySelector('#edit-jabatan').value = jabatan;
            modal.querySelector('#edit-bergabung-sejak').value = sejak;
        });

        // Logika untuk mengisi data modal Edit Kegiatan
        var editKegiatanModal = document.getElementById('editKegiatanModal');
        editKegiatanModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var deskripsi = button.getAttribute('data-deskripsi');
            var lokasi = button.getAttribute('data-lokasi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-kegiatan-id').value = id;
            modal.querySelector('#edit-nama-kegiatan').value = nama;
            modal.querySelector('#edit-deskripsi').value = deskripsi;
            modal.querySelector('#edit-lokasi').value = lokasi;
            modal.querySelector('#edit-tanggal-mulai').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Keuangan
        var editKeuanganModal = document.getElementById('editKeuanganModal');
        editKeuanganModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var jenis = button.getAttribute('data-jenis');
            var jumlah = button.getAttribute('data-jumlah');
            var deskripsi = button.getAttribute('data-deskripsi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-keuangan-id').value = id;
            modal.querySelector('#edit-jenis-transaksi').value = jenis;
            modal.querySelector('#edit-jumlah-keuangan').value = jumlah;
            modal.querySelector('#edit-deskripsi-keuangan').value = deskripsi;
            modal.querySelector('#edit-tanggal-transaksi').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Iuran
        var editIuranModal = document.getElementById('editIuranModal');
        editIuranModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var anggotaId = button.getAttribute('data-anggota-id');
            var tanggal = button.getAttribute('data-tanggal');
            var jumlah = button.getAttribute('data-jumlah');
            var keterangan = button.getAttribute('data-keterangan');
            var modal = this;
            modal.querySelector('#edit-iuran-id').value = id;
            modal.querySelector('#edit-anggota-id').value = anggotaId;
            modal.querySelector('#edit-tanggal-bayar').value = tanggal;
            modal.querySelector('#edit-jumlah-iuran').value = jumlah;
            modal.querySelector('#edit-keterangan').value = keterangan;
        });

        // Logika untuk mengisi data modal Edit Users
        var editUsersModal = document.getElementById('editUsersModal');
        editUsersModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var role = button.getAttribute('data-role');
            var anggotaId = button.getAttribute('data-anggota-id');
            var modal = this;
            modal.querySelector('#edit-users-id').value = id;
            modal.querySelector('#edit-username').value = username;
            modal.querySelector('#edit-role').value = role;
            modal.querySelector('#edit-anggota-id-user').value = anggotaId;
            modal.querySelector('#edit-password').value = ''; // Kosongkan password untuk keamanan
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const detectBtn = document.getElementById('detect-coords-btn');
        const gmapsUrlInput = document.getElementById('gmaps-url');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');

        detectBtn.addEventListener('click', function() {
            const gmapsUrl = gmapsUrlInput.value;
            const regex = /@(-?\d+\.\d+),(-?\d+\.\d+)/;
            const match = gmapsUrl.match(regex);
            
            let message = "";
            let isSuccess = false;

            if (match && match.length >= 3) {
                const lat = match[1];
                const lon = match[2];

                latitudeInput.value = lat;
                longitudeInput.value = lon;
                
                message = "Koordinat berhasil dideteksi dan diisi.";
                isSuccess = true;
            } else {
                message = "Tautan Google Maps tidak valid. Pastikan tautan memiliki format yang benar.";
                isSuccess = false;
            }

            // Tampilkan notifikasi toast
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0`;
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
            const bootstrapToast = new bootstrap.Toast(toast);
            bootstrapToast.show();
        });
    });


    document.addEventListener('DOMContentLoaded', function() {
        const detectDeviceLocationBtn = document.getElementById('detect-device-location-btn');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');
        const toastContainer = document.getElementById('toast-container'); // Pastikan ini ada di HTML Anda

        detectDeviceLocationBtn.addEventListener('click', function() {
            // Periksa apakah browser mendukung Geolocation API
            if (navigator.geolocation) {
                // Tampilkan pesan loading
                showToast("Mencari lokasi perangkat...", false, 'bg-secondary');

                // Dapatkan lokasi saat ini
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Callback sukses
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        latitudeInput.value = lat;
                        longitudeInput.value = lon;
                        
                        showToast("Lokasi perangkat berhasil dideteksi dan diisi.", true);
                    },
                    function(error) {
                        // Callback error
                        let errorMessage = "Gagal mendeteksi lokasi perangkat: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Pengguna menolak permintaan Geolocation.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Permintaan lokasi habis waktu.";
                                break;
                            case error.UNKNOWN_ERROR:
                                errorMessage += "Terjadi kesalahan tidak dikenal.";
                                break;
                        }
                        showToast(errorMessage, false);
                    },
                    {
                        // Opsi konfigurasi Geolocation
                        enableHighAccuracy: true, // Mencoba mendapatkan lokasi seakurat mungkin
                        timeout: 10000,           // Waktu maksimum (ms) untuk menunggu hasil
                        maximumAge: 0             // Jangan gunakan cache lokasi yang sudah tua
                    }
                );
            } else {
                showToast("Browser Anda tidak mendukung Geolocation API.", false);
            }
        });

        // Fungsi bantu untuk menampilkan Toast
        function showToast(message, isSuccess, customBgClass = null) {
            const toast = document.createElement('div');
            let bgClass = customBgClass ? customBgClass : (isSuccess ? 'bg-success' : 'bg-danger');

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
            const bootstrapToast = new bootstrap.Toast(toast, { delay: 5000 }); // Toast hilang setelah 5 detik
            bootstrapToast.show();
            // Hapus toast dari DOM setelah disembunyikan untuk menjaga kebersihan
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
    });
>>>>>>> 5f6158f27cf4d32e7de55a6ebe447ba9b45dd307
