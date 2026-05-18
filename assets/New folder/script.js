/* ============================================
   ALDY BARBERSHOP - Main JavaScript
   ============================================ */

document.addEventListener('DOMContentLoaded', function() {

    // === Toggle Password Visibility ===
    const togglePwd = document.getElementById('togglePwd');
    if (togglePwd) {
        togglePwd.addEventListener('click', function() {
            const pwdInput = document.getElementById('password');
            const icon = this.querySelector('i');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                pwdInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    }

    // === Login Form Validation ===
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            if (!email || !password) {
                e.preventDefault();
                showToast('warning', 'Email dan password wajib diisi.');
            }
        });
    }

    // === Register Form Validation ===
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;

            if (!name || !phone || !password || !confirm) {
                e.preventDefault();
                showToast('warning', 'Semua field wajib diisi.');
                return;
            }
            if (password.length < 6) {
                e.preventDefault();
                showToast('warning', 'Password minimal 6 karakter.');
                return;
            }
            if (password !== confirm) {
                e.preventDefault();
                showToast('warning', 'Konfirmasi password tidak cocok.');
                return;
            }
            const phoneRegex = /^[0-9+\-\s]{10,20}$/;
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                showToast('warning', 'Nomor HP tidak valid (10-20 digit).');
                return;
            }
        });
    }

    // === Booking Form: Info Preview ===
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        const serviceSelect = document.getElementById('service_id');
        const dateInput = document.getElementById('booking_date');
        const timeInput = document.getElementById('booking_time');
        const infoDiv = document.getElementById('bookingInfo');
        const infoText = document.getElementById('bookingInfoText');

        function updateBookingInfo() {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const date = dateInput.value;
            const time = timeInput.value;

            if (serviceSelect.value && date && time) {
                const serviceName = selectedOption.textContent;
                // Format tanggal ke Indonesia
                const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                const d = new Date(date);
                const formatted = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
                infoText.textContent = 'Anda akan booking ' + serviceName + ' pada ' + formatted + ' pukul ' + time + ' WIB.';
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }

        serviceSelect.addEventListener('change', updateBookingInfo);
        dateInput.addEventListener('change', updateBookingInfo);
        timeInput.addEventListener('change', updateBookingInfo);

        bookingForm.addEventListener('submit', function(e) {
            if (!serviceSelect.value || !dateInput.value || !timeInput.value) {
                e.preventDefault();
                showToast('warning', 'Lengkapi semua field booking.');
            }
        });
    }

    // === Payment Modal: Populate Data ===
    const paymentModal = document.getElementById('paymentModal');
    if (paymentModal) {
        paymentModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            if (!button) return;
            document.getElementById('payBookingId').value = button.dataset.bookingId;
            document.getElementById('payCustomer').textContent = button.dataset.customer;
            document.getElementById('payService').textContent = button.dataset.service;
            document.getElementById('payTotal').value = button.dataset.price;
            // Reset radio
            document.querySelectorAll('input[name="metode_pembayaran"]').forEach(r => r.checked = false);
        });
    }

    // === Service Modal: Add / Edit Mode ===
    const serviceModal = document.getElementById('serviceModal');
    if (serviceModal) {
        serviceModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const mode = button ? button.dataset.mode : 'add';
            const title = document.getElementById('serviceModalTitle');
            const action = document.getElementById('serviceAction');
            const editId = document.getElementById('serviceEditId');
            const nameInput = document.getElementById('svcName');
            const priceInput = document.getElementById('svcPrice');

            if (mode === 'edit') {
                title.innerHTML = '<i class="bi bi-pencil me-2 text-accent"></i>Edit Layanan';
                action.value = 'edit_service';
                editId.value = button.dataset.id;
                nameInput.value = button.dataset.name;
                priceInput.value = button.dataset.price;
            } else {
                title.innerHTML = '<i class="bi bi-scissors me-2 text-accent"></i>Tambah Layanan';
                action.value = 'add_service';
                editId.value = '0';
                nameInput.value = '';
                priceInput.value = '';
            }
        });
    }

    // === Initialize DataTables ===
    initDataTable('bookingsTable');
    initDataTable('paymentsTable');
    initDataTable('servicesTable');
    initDataTable('historyTable');
    initDataTable('visitTable');
    initDataTable('detailPayTable');
});

// === DataTable Initializer ===
function initDataTable(tableId) {
    const table = document.getElementById(tableId);
    if (table && !table.classList.contains('dataTable')) {
        try {
            $(table).DataTable({
                pageLength: 10,
                lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Semua"]],
                language: {
                    search: "Cari:",
                    lengthMenu: "Tampilkan _MENU_ data",
                    info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
                    infoEmpty: "Tidak ada data",
                    infoFiltered: "(disaring dari _MAX_ total data)",
                    zeroRecords: "Tidak ditemukan data yang cocok",
                    paginate: { first: "Pertama", last: "Terakhir", next: ">>", previous: "<<" }
                },
                responsive: true,
                columnDefs: [{ orderable: false, targets: -1 }]
            });
        } catch (e) {
            // DataTables mungkin tidak tersedia, abaikan
        }
    }
}

// === Toast Notification ===
function showToast(type, message) {
    // Hapus toast lama jika ada
    const existing = document.querySelector('.custom-toast');
    if (existing) existing.remove();

    const bgClass = type === 'error' ? 'danger' : (type === 'success' ? 'success' : 'warning');
    const iconClass = type === 'error' ? 'exclamation-circle' : (type === 'success' ? 'check-circle' : 'info-circle');

    const toast = document.createElement('div');
    toast.className = 'toast-container position-fixed top-0 end-0 p-3 custom-toast';
    toast.style.zIndex = '9999';
    toast.innerHTML = `
        <div class="toast align-items-center text-bg-${bgClass} border-0 show" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-${iconClass} me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.custom-toast').remove()"></button>
            </div>
        </div>
    `;
    document.body.appendChild(toast);

    // Auto-hide 4 detik
    setTimeout(function() {
        const t = document.querySelector('.custom-toast');
        if (t) t.remove();
    }, 4000);
}