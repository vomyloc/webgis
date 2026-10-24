<?php
require_once __DIR__ . '/ketnoi.php';
requireLogin();

if (!isAdmin()) {
    header('Location: index.php');
    exit;
}

$currentUserId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
$currentUser = $currentUserId ? db_fetch_one('SELECT role FROM users WHERE id = $1', [$currentUserId]) : null;
if (!$currentUser || $currentUser === false || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit('Bạn không có quyền truy cập trang quản lý tài khoản.');
}

$fullName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Quản trị viên';
?>
<!DOCTYPE html>
<html lang="vi" class="accounts-document">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý tài khoản - WebGIS Địa chính Đông Thạnh</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="demo.css">
</head>
<body class="accounts-page">
    <nav class="navbar navbar-dark bg-topbar px-3 px-lg-4 shadow-sm">
        <a class="navbar-brand d-flex align-items-center fw-bold fs-6" href="index.php">
            <i class="bi bi-layers-half text-info me-2 fs-5"></i>
            <span>WEBGIS ĐỊA CHÍNH ĐÔNG THẠNH</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-3">
            <a class="btn btn-sm btn-outline-light" href="index.php"><i class="bi bi-map me-1"></i><span class="d-none d-sm-inline">Quay lại bản đồ</span></a>
            <div class="d-flex align-items-center text-light small">
                <i class="bi bi-person-circle fs-5 me-2 text-info"></i>
                <span class="fw-semibold d-none d-sm-inline"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <a href="logout.php" class="btn btn-sm btn-outline-light" title="Đăng xuất"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </nav>

    <main class="container-fluid accounts-container px-3 px-lg-5 py-4 py-lg-5">
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3 mb-4">
            <div>
                <div class="accounts-eyebrow"><i class="bi bi-shield-lock me-1"></i>KHU VỰC QUẢN TRỊ</div>
                <h1 class="h2 fw-bold text-dark mb-1">Quản lý tài khoản</h1>
                <p class="text-secondary mb-0">Tạo tài khoản, cập nhật thông tin và phân quyền truy cập WebGIS.</p>
            </div>
            <button class="btn btn-primary px-3 py-2 fw-semibold" type="button" id="btnCreateAccount">
                <i class="bi bi-person-plus me-1"></i>Tạo tài khoản
            </button>
        </div>

        <div id="pageAlert" class="alert d-none" role="alert"></div>

        <section class="row g-3 mb-4" aria-label="Thống kê tài khoản">
            <div class="col-12 col-md-4">
                <div class="card account-stat-card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="account-stat-icon stat-total"><i class="bi bi-people-fill"></i></div>
                        <div><div class="small text-secondary">Tổng tài khoản</div><div class="fs-3 fw-bold" id="statTotal">—</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card account-stat-card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="account-stat-icon stat-admin"><i class="bi bi-shield-check"></i></div>
                        <div><div class="small text-secondary">Quản trị viên</div><div class="fs-3 fw-bold" id="statAdmins">—</div></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <div class="card account-stat-card h-100 border-0 shadow-sm">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="account-stat-icon stat-user"><i class="bi bi-person-check-fill"></i></div>
                        <div><div class="small text-secondary">Người dùng</div><div class="fs-3 fw-bold" id="statUsers">—</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm account-list-card">
            <div class="card-header bg-white border-0 p-3 p-lg-4 pb-lg-3">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div>
                        <h2 class="h5 fw-bold mb-1">Danh sách tài khoản</h2>
                        <p class="small text-secondary mb-0">Tài khoản admin luôn được hiển thị trước.</p>
                    </div>
                    <div class="input-group account-search">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-secondary"></i></span>
                        <input type="search" class="form-control border-start-0 ps-0" id="accountSearch" placeholder="Tìm tên, email, tài khoản..." aria-label="Tìm tài khoản">
                        <button class="btn btn-outline-secondary" type="button" id="btnRefresh" title="Làm mới danh sách"><i class="bi bi-arrow-clockwise"></i></button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 account-table">
                    <thead><tr><th>Tài khoản</th><th>Họ và tên</th><th>Email</th><th>Quyền</th><th>Ngày tạo</th><th class="text-end">Thao tác</th></tr></thead>
                    <tbody id="accountRows">
                        <tr><td colspan="6" class="text-center py-5 text-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Đang tải tài khoản...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 px-lg-4 py-3 border-top small text-secondary">
                <span id="resultSummary">Đang tải...</span>
                <span><i class="bi bi-lock-fill me-1"></i>Chỉ admin có thể thực hiện thao tác</span>
            </div>
        </section>
    </main>

    <div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="accountForm" novalidate>
                    <div class="modal-header border-0 pb-0">
                        <div>
                            <div class="accounts-eyebrow mb-1">THÔNG TIN TÀI KHOẢN</div>
                            <h2 class="modal-title fs-5 fw-bold" id="accountModalTitle">Tạo tài khoản</h2>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <input type="hidden" name="id" id="accountId">
                        <div class="mb-3"><label class="form-label small fw-semibold" for="accountUsername">Tên đăng nhập</label><input class="form-control" id="accountUsername" name="username" minlength="3" maxlength="50" autocomplete="username" required></div>
                        <div class="mb-3"><label class="form-label small fw-semibold" for="accountFullName">Họ và tên</label><input class="form-control" id="accountFullName" name="full_name" maxlength="100" autocomplete="name" required></div>
                        <div class="mb-3"><label class="form-label small fw-semibold" for="accountEmail">Email <span class="text-secondary fw-normal">(không bắt buộc)</span></label><input class="form-control" id="accountEmail" name="email" type="email" maxlength="255" autocomplete="email"></div>
                        <div class="mb-3"><label class="form-label small fw-semibold" for="accountRole">Quyền truy cập</label><select class="form-select" id="accountRole" name="role"><option value="user">Người dùng tra cứu</option><option value="admin">Quản trị viên</option></select></div>
                        <div class="mb-1"><label class="form-label small fw-semibold" for="accountPassword">Mật khẩu <span class="text-secondary fw-normal" id="passwordHint">(ít nhất 8 ký tự)</span></label><input class="form-control" id="accountPassword" name="password" type="password" minlength="8" autocomplete="new-password"><div class="form-text" id="passwordHelp">Mật khẩu được bảo vệ an toàn trước khi lưu.</div></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary fw-semibold" id="btnSaveAccount"><i class="bi bi-check2-circle me-1"></i>Lưu tài khoản</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF_TOKEN = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const CURRENT_USER_ID = <?= json_encode((int)$currentUserId) ?>;
        const modal = new bootstrap.Modal(document.getElementById('accountModal'));
        const form = document.getElementById('accountForm');
        const rows = document.getElementById('accountRows');
        let searchTimer;
        let userCache = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char]);
        }

        function showAlert(message, type = 'success') {
            const alert = document.getElementById('pageAlert');
            alert.className = `alert alert-${type} shadow-sm`;
            alert.textContent = message;
            window.scrollTo({ top: 0, behavior: 'smooth' });
            window.setTimeout(() => alert.classList.add('d-none'), 5000);
        }

        async function request(url, options = {}) {
            const response = await fetch(url, { ...options, headers: { 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {}) } });
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Có lỗi xảy ra.');
            return result;
        }

        function formatDate(value) {
            if (!value) return '—';
            const parsed = new Date(String(value).replace(' ', 'T'));
            return Number.isNaN(parsed.getTime()) ? escapeHtml(value) : new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parsed);
        }

        function renderUsers(users) {
            userCache = users;
            if (!users.length) {
                rows.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="empty-accounts-icon mx-auto mb-2"><i class="bi bi-person-x"></i></div><div class="fw-semibold">Không tìm thấy tài khoản</div><div class="small text-secondary mt-1">Thử thay đổi từ khóa hoặc tạo tài khoản mới.</div></td></tr>';
                document.getElementById('resultSummary').textContent = 'Không có kết quả';
                return;
            }

            rows.innerHTML = users.map(user => {
                const isCurrentUser = Number(user.id) === CURRENT_USER_ID;
                const role = user.role === 'admin';
                const initials = String(user.full_name || user.username).trim().slice(0, 1).toUpperCase();
                return `<tr>
                    <td><div class="d-flex align-items-center gap-2"><div class="account-avatar ${role ? 'avatar-admin' : ''}">${escapeHtml(initials)}</div><div><div class="fw-semibold text-dark">${escapeHtml(user.username)} ${isCurrentUser ? '<span class="badge text-bg-light border ms-1">Bạn</span>' : ''}</div><div class="small text-secondary">ID #${escapeHtml(user.id)}</div></div></div></td>
                    <td class="fw-medium">${escapeHtml(user.full_name)}</td>
                    <td class="text-secondary">${escapeHtml(user.email || '—')}</td>
                    <td><span class="badge rounded-pill ${role ? 'role-admin' : 'role-user'}"><i class="bi ${role ? 'bi-shield-fill-check' : 'bi-person-fill'} me-1"></i>${role ? 'Admin' : 'Người dùng'}</span></td>
                    <td class="text-secondary text-nowrap">${formatDate(user.created_at)}</td>
                    <td><div class="d-flex justify-content-end gap-1"><button class="btn btn-sm btn-light border account-action" type="button" data-edit-id="${escapeHtml(user.id)}" title="Sửa tài khoản" aria-label="Sửa tài khoản ${escapeHtml(user.username)}"><i class="bi bi-pencil-square"></i></button>${isCurrentUser ? '' : `<button class="btn btn-sm btn-light border text-danger account-action" type="button" data-delete-id="${escapeHtml(user.id)}" title="Xóa tài khoản" aria-label="Xóa tài khoản ${escapeHtml(user.username)}"><i class="bi bi-trash3"></i></button>`}</div></td>
                </tr>`;
            }).join('');
            document.getElementById('resultSummary').textContent = `Hiển thị ${users.length} tài khoản`;
        }

        async function loadAccounts() {
            rows.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-secondary"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Đang tải tài khoản...</td></tr>';
            try {
                const query = new URLSearchParams({ action: 'list', q: document.getElementById('accountSearch').value.trim() });
                const result = await request(`account_api.php?${query.toString()}`);
                document.getElementById('statTotal').textContent = result.stats.total;
                document.getElementById('statAdmins').textContent = result.stats.admins;
                document.getElementById('statUsers').textContent = result.stats.users;
                renderUsers(result.data || []);
            } catch (error) {
                rows.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-danger"><i class="bi bi-exclamation-circle me-1"></i>${escapeHtml(error.message)}</td></tr>`;
                document.getElementById('resultSummary').textContent = 'Không tải được dữ liệu';
            }
        }

        function openCreateModal() {
            form.reset();
            document.getElementById('accountId').value = '';
            document.getElementById('accountModalTitle').textContent = 'Tạo tài khoản';
            document.getElementById('accountPassword').required = true;
            document.getElementById('passwordHint').textContent = '(ít nhất 8 ký tự)';
            document.getElementById('passwordHelp').textContent = 'Mật khẩu được bảo vệ an toàn trước khi lưu.';
            modal.show();
        }

        function openEditModal(user) {
            form.reset();
            document.getElementById('accountId').value = user.id;
            document.getElementById('accountUsername').value = user.username || '';
            document.getElementById('accountFullName').value = user.full_name || '';
            document.getElementById('accountEmail').value = user.email || '';
            document.getElementById('accountRole').value = user.role || 'user';
            document.getElementById('accountModalTitle').textContent = 'Cập nhật tài khoản';
            document.getElementById('accountPassword').required = false;
            document.getElementById('passwordHint').textContent = '(để trống nếu không đổi)';
            document.getElementById('passwordHelp').textContent = 'Nhập mật khẩu mới nếu cần đặt lại; mật khẩu cũ không được hiển thị.';
            if (Number(user.id) === CURRENT_USER_ID) document.getElementById('accountRole').disabled = true;
            else document.getElementById('accountRole').disabled = false;
            modal.show();
        }

        document.getElementById('btnCreateAccount').addEventListener('click', openCreateModal);
        document.getElementById('btnRefresh').addEventListener('click', loadAccounts);
        document.getElementById('accountSearch').addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(loadAccounts, 250);
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const submit = document.getElementById('btnSaveAccount');
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Đang lưu...';
            const formData = new FormData(form);
            formData.set('_csrf', CSRF_TOKEN);
            const id = document.getElementById('accountId').value;
            const action = id ? 'update' : 'create';
            // Không gửi quyền bị khóa khi admin đang sửa chính tài khoản của mình.
            if (document.getElementById('accountRole').disabled) formData.set('role', 'admin');
            try {
                const result = await request(`account_api.php?action=${action}`, { method: 'POST', body: formData });
                modal.hide();
                showAlert(result.message);
                await loadAccounts();
            } catch (error) {
                showAlert(error.message, 'danger');
            } finally {
                submit.disabled = false;
                submit.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Lưu tài khoản';
            }
        });

        rows.addEventListener('click', async event => {
            const editButton = event.target.closest('[data-edit-id]');
            if (editButton) {
                const user = userCache.find(item => Number(item.id) === Number(editButton.dataset.editId));
                if (user) openEditModal(user);
                return;
            }
            const deleteButton = event.target.closest('[data-delete-id]');
            if (!deleteButton) return;
            const user = userCache.find(item => Number(item.id) === Number(deleteButton.dataset.deleteId));
            if (!user || !window.confirm(`Bạn có chắc muốn xóa tài khoản “${user.username}” không? Thao tác này không thể hoàn tác.`)) return;
            const body = new FormData();
            body.set('id', user.id);
            body.set('_csrf', CSRF_TOKEN);
            deleteButton.disabled = true;
            try {
                const result = await request('account_api.php?action=delete', { method: 'POST', body });
                showAlert(result.message);
                await loadAccounts();
            } catch (error) {
                showAlert(error.message, 'danger');
                deleteButton.disabled = false;
            }
        });

        document.getElementById('accountModal').addEventListener('hidden.bs.modal', () => {
            document.getElementById('accountRole').disabled = false;
        });

        loadAccounts();
    </script>
</body>
</html>
