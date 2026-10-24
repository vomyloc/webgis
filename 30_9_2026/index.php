<?php
require_once "ketnoi.php";
requireLogin();

$is_admin   = isAdmin();
$full_name  = $_SESSION['full_name'] ?? $_SESSION['username'];
$role_text  = $is_admin ? 'Quản trị viên (Admin)' : 'Tra cứu viên (User)';
$role_badge = $is_admin ? 'bg-danger' : 'bg-success';
$geoserverUrl = rtrim(getenv('WEBGIS_GEOSERVER_URL') ?: 'http://localhost:8181/geoserver', '/');
$geoserverWorkspace = getenv('WEBGIS_GEOSERVER_WORKSPACE') ?: 'dong_thanh';
$geoserverLayer = getenv('WEBGIS_GEOSERVER_LAYER') ?: 'thua_dat_dong_thanh';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ thống WebGIS Quản lý Đất đai & Hồ sơ Địa chính</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="L.basemapControl.min.css" />
    <link rel="stylesheet" href="demo.css" />
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-topbar px-3 shadow-sm">
        <div class="d-flex align-items-center">
            <button class="btn btn-sm btn-outline-light me-2 d-lg-none" id="btnToggleSidebar">
                <i class="bi bi-list fs-5"></i>
            </button>
            <a class="navbar-brand d-flex align-items-center fw-bold mb-0 h1 fs-6" href="#">
                <i class="bi bi-layers-half text-info me-2 fs-5"></i>
                <span>WEBGIS ĐỊA CHÍNH ĐÔNG THẠNH</span>
            </a>
        </div>

        <div class="ms-auto d-flex align-items-center gap-3">
            <div class="form-check form-switch text-light small mb-0 d-none d-md-flex align-items-center gap-2">
                <input class="form-check-input mt-0" type="checkbox" id="toggleWmsLayer" checked>
                <label class="form-check-label" for="toggleWmsLayer">Lớp Thửa đất (WMS)</label>
            </div>

            <?php if ($is_admin): ?>
            <a href="accounts.php" class="btn btn-sm btn-outline-light" title="Quản lý tài khoản">
                <i class="bi bi-people"></i> <span class="d-none d-md-inline">Tài khoản</span>
            </a>
            <?php endif; ?>

            <div class="vr text-secondary d-none d-md-block"></div>

            <div class="d-flex align-items-center text-light small">
                <i class="bi bi-person-circle fs-5 me-2 text-info"></i>
                <div class="lh-1 me-2">
                    <div class="fw-semibold"><?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8') ?></div>
                    <span class="badge <?= $role_badge ?> mt-1" style="font-size: 0.68rem;"><?= $role_text ?></span>
                </div>
            </div>

            <a href="logout.php" class="btn btn-sm btn-outline-light" title="Đăng xuất">
                <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Đăng xuất</span>
            </a>
        </div>
    </nav>

    <div class="workspace-container">
        <aside class="sidebar-panel shadow" id="sidebarPanel">
            <div class="p-3 border-bottom bg-white">
                <ul class="nav nav-pills nav-fill custom-tabs mb-3" id="searchTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#tab-thua" type="button">
                            <i class="bi bi-bounding-box me-1"></i>Thửa đất
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-gcn" type="button">
                            <i class="bi bi-journal-bookmark-fill me-1"></i>Số GCN
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" data-bs-toggle="pill" data-bs-target="#tab-chu" type="button">
                            <i class="bi bi-person-vcard me-1"></i>Chủ đất
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-thua">
                        <form id="formSearchThua" class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Số tờ bản đồ</label>
                                <input type="number" name="so_to" class="form-control form-control-sm" placeholder="VD: 12">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Số thứ tự thửa</label>
                                <input type="number" name="so_thua" class="form-control form-control-sm" placeholder="VD: 105">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1 fw-semibold">Loại đất (Mục đích sử dụng)</label>
                                <input type="text" name="ma_loai_dat" class="form-control form-control-sm" placeholder="VD: ODT, ONT, CLN, LUC...">
                            </div>
                            <div class="col-12 mt-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-semibold">
                                    <i class="bi bi-search me-1"></i>Tra cứu Thửa đất
                                </button>
                                <button type="reset" class="btn btn-light btn-sm border btn-reset-map" title="Làm mới">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="tab-gcn">
                        <form id="formSearchGCN" class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Số phát hành (Seri)</label>
                                <input type="text" name="so_phat_hanh" class="form-control form-control-sm" placeholder="VD: DP 123456">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Số vào sổ cấp GCN</label>
                                <input type="text" name="so_vao_so" class="form-control form-control-sm" placeholder="VD: CH00125">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1 fw-semibold">Trạng thái cấp GCN</label>
                                <select name="trang_thai_gcn" class="form-select form-select-sm">
                                    <option value="">-- Tất cả trạng thái --</option>
                                    <option value="Đã cấp GCN">Đã cấp GCN</option>
                                    <option value="Đang chờ cấp">Đang chờ cấp</option>
                                    <option value="Chưa cấp GCN">Chưa cấp GCN</option>
                                    <option value="Đang thế chấp">Đang thế chấp</option>
                                </select>
                            </div>
                            <div class="col-12 mt-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-semibold">
                                    <i class="bi bi-award me-1"></i>Tra cứu Giấy chứng nhận
                                </button>
                                <button type="reset" class="btn btn-light btn-sm border btn-reset-map">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="tab-pane fade" id="tab-chu">
                        <form id="formSearchChu" class="row g-2">
                            <div class="col-12">
                                <label class="form-label small mb-1 fw-semibold">Họ và tên chủ sử dụng đất</label>
                                <input type="text" name="ten_chu" class="form-control form-control-sm" placeholder="Nhập họ tên chủ sử dụng...">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Số CCCD / Định danh</label>
                                <input type="text" name="so_cccd" class="form-control form-control-sm" placeholder="VD: 079205...">
                            </div>
                            <div class="col-6">
                                <label class="form-label small mb-1 fw-semibold">Địa chỉ / Điện thoại</label>
                                <input type="text" name="dia_chi" class="form-control form-control-sm" placeholder="Ấp, xã, SĐT...">
                            </div>
                            <div class="col-12 mt-2 d-flex gap-2">
                                <button type="submit" class="btn btn-primary btn-sm flex-grow-1 fw-semibold">
                                    <i class="bi bi-person-search me-1"></i>Tra cứu Chủ sử dụng
                                </button>
                                <button type="reset" class="btn btn-light btn-sm border btn-reset-map">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="sidebar-content p-3" id="resultContainer">
                <div class="text-center text-muted py-5" id="emptyState">
                    <i class="bi bi-geo-alt fs-1 text-secondary opacity-50"></i>
                    <p class="small mt-2 mb-0">
                        Nhấp trực tiếp vào một <strong>thửa đất trên bản đồ</strong> hoặc sử dụng <strong>bộ lọc tra cứu</strong> phía trên để xem hồ sơ địa chính chi tiết.
                    </p>
                </div>

                <div id="searchListWrapper" class="d-none mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-secondary text-uppercase">Kết quả tìm thấy (<span id="resultCount">0</span>)</span>
                        <button class="btn btn-link btn-sm p-0 text-decoration-none small" id="btnClearResults">Xóa kết quả</button>
                    </div>
                    <div class="list-group list-group-flush border rounded-2 overflow-auto" id="searchResultList" style="max-height: 220px;"></div>
                </div>

                <div id="parcelDetailCard" class="d-none"></div>
            </div>
        </aside>

        <main class="map-wrapper">
            <div id="map"></div>
        </main>
    </div>

    <?php if ($is_admin): ?>
    <div class="modal fade" id="modalEditParcel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="formUpdateParcel">
                    <div class="modal-header bg-primary text-white py-2">
                        <h6 class="modal-title fw-bold">
                            <i class="bi bi-pencil-square me-2"></i>Cập nhật Hồ sơ Thửa đất & Giấy chứng nhận
                        </h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="gid" id="edit_gid">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="section-label"><i class="bi bi-bounding-box me-1"></i>1. Thông tin kỹ thuật Thửa đất</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Số tờ bản đồ</label>
                                <input type="number" name="so_to" id="edit_so_to" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Số thứ tự thửa</label>
                                <input type="number" name="so_thua" id="edit_so_thua" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Diện tích (m²)</label>
                                <input type="number" step="0.01" name="dien_tich" id="edit_dien_tich" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Mã loại đất</label>
                                <input type="text" name="ma_loai_dat" id="edit_ma_loai_dat" class="form-control form-control-sm">
                            </div>

                            <div class="col-12 mt-3">
                                <div class="section-label"><i class="bi bi-person-vcard me-1"></i>2. Thông tin Chủ sử dụng đất</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Họ và tên chủ sử dụng</label>
                                <input type="text" name="ten_chu" id="edit_ten_chu" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Số CCCD / Định danh</label>
                                <input type="text" name="so_cccd" id="edit_so_cccd" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Địa chỉ thường trú</label>
                                <input type="text" name="dia_chi_thuong_tru" id="edit_dia_chi" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Số điện thoại</label>
                                <input type="text" name="so_dien_thoai" id="edit_sdt" class="form-control form-control-sm">
                            </div>

                            <div class="col-12 mt-3">
                                <div class="section-label"><i class="bi bi-journal-bookmark-fill me-1"></i>3. Thông tin Giấy chứng nhận (GCNQSDĐ)</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Số phát hành (Seri sổ)</label>
                                <input type="text" name="so_phat_hanh" id="edit_so_phat_hanh" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Số vào sổ cấp GCN</label>
                                <input type="text" name="so_vao_so" id="edit_so_vao_so" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Ngày cấp GCN</label>
                                <input type="date" name="ngay_cap" id="edit_ngay_cap" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Trạng thái pháp lý GCN</label>
                                <select name="trang_thai_gcn" id="edit_trang_thai_gcn" class="form-select form-select-sm">
                                    <option value="Đã cấp GCN">Đã cấp GCN</option>
                                    <option value="Đang chờ cấp">Đang chờ cấp</option>
                                    <option value="Chưa cấp GCN">Chưa cấp GCN</option>
                                    <option value="Đang thế chấp">Đang thế chấp</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy bỏ</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                            <i class="bi bi-check2-circle me-1"></i>Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="L.basemapControl.min.js"></script>
    <script src="leaflet-simple-locate.min.js"></script>

    <script>
        const IS_ADMIN = <?= json_encode($is_admin) ?>;
        const CSRF_TOKEN = <?= json_encode(csrfToken(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const GEOSERVER_WMS_URL = <?= json_encode($geoserverUrl . '/' . rawurlencode($geoserverWorkspace) . '/wms', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        const GEOSERVER_LAYER = <?= json_encode($geoserverWorkspace . ':' . $geoserverLayer, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let currentParcelData = null;
        let currentAdjacentParcels = [];

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function(char) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char];
            });
        }

        const map = L.map('map', {
            // Tâm dữ liệu thửa đất lấy từ tọa độ hiển thị trong GeoServer.
            center: [10.87528, 106.63050],
            zoom: 16,
            zoomControl: false
        });

        L.control.zoom({ position: 'topright' }).addTo(map);
        L.control.scale({ imperial: false, position: 'bottomright' }).addTo(map);

        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 22,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        const satelliteLayer = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            maxZoom: 22,
            attribution: '&copy; Google Satellite'
        });

        L.control.layers(
            { "Bản đồ Đường phố (OSM)": osmLayer, "Ảnh vệ tinh (Google Hybrid)": satelliteLayer },
            null,
            { position: 'topright' }
        ).addTo(map);

        if (typeof L.Control.SimpleLocate !== 'undefined') {
            new L.Control.SimpleLocate({
                position: 'topright',
                className: 'button-locate',
                afterClick: function(result) {
                    if (result && result.latlng) map.setView(result.latlng, 18);
                }
            }).addTo(map);
        }

        const wmsThuaDat = L.tileLayer.wms(GEOSERVER_WMS_URL, {
            layers: GEOSERVER_LAYER,
            format: 'image/png',
            transparent: true,
            version: '1.1.1',
            maxZoom: 22
        }).addTo(map);

        wmsThuaDat.on('tileerror', function(event) {
            console.error('GeoServer không trả tile WMS:', event.error || event.tile);
        });

        document.getElementById('toggleWmsLayer').addEventListener('change', function() {
            if (this.checked) {
                map.addLayer(wmsThuaDat);
            } else {
                map.removeLayer(wmsThuaDat);
            }
        });

        const highlightLayer = L.geoJSON(null, {
            style: {
                color: '#ff3b30',
                weight: 3,
                fillColor: '#ffcc00',
                fillOpacity: 0.45
            }
        }).addTo(map);
        const adjacentLayer = L.geoJSON(null, {
            style: {
                color: '#0057d9',
                weight: 3,
                fillColor: '#00a8ff',
                fillOpacity: 0.42
            }
        }).addTo(map);

        let mapClickRequestId = 0;
        let adjacentRequestId = 0;
        map.on('click', async function(e) {
            const requestId = ++mapClickRequestId;
            const formData = new FormData();
            formData.append('action', 'click_map');
            formData.append('lat', e.latlng.lat);
            formData.append('lng', e.latlng.lng);

            currentParcelData = null;
            highlightLayer.clearLayers();
            adjacentLayer.clearLayers();
            adjacentRequestId += 1;
            document.getElementById('parcelDetailCard').classList.add('d-none');
            document.getElementById('emptyState').classList.remove('d-none');
            map.closePopup();

            try {
                const res = await fetch('xu_ly_click.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (requestId !== mapClickRequestId) return;

                if (json.status === 'success' && json.data) {
                    selectAndHighlightParcel(json.data, true);
                } else {
                    L.popup()
                        .setLatLng(e.latlng)
                        .setContent('<div class="small text-secondary p-1"><i class="bi bi-info-circle me-1"></i>' + escapeHtml(json.message || 'Không có dữ liệu thửa đất.') + '</div>')
                        .openOn(map);
                }
            } catch (err) {
                if (requestId !== mapClickRequestId) return;
                console.error("Lỗi truy vấn click bản đồ:", err);
                L.popup()
                    .setLatLng(e.latlng)
                    .setContent('<div class="small text-danger p-1"><i class="bi bi-exclamation-circle me-1"></i>Không thể nhận thông tin thửa đất. Vui lòng thử lại.</div>')
                    .openOn(map);
            }
        });

        function selectAndHighlightParcel(item, fromMapClick) {
            if (!fromMapClick) mapClickRequestId += 1;
            const selectionId = ++adjacentRequestId;
            currentParcelData = item;
            currentAdjacentParcels = [];
            document.getElementById('emptyState').classList.add('d-none');

            highlightLayer.clearLayers();
            adjacentLayer.clearLayers();
            if (item.geojson) {
                highlightLayer.addData(item.geojson);
                if (!fromMapClick) {
                    map.flyToBounds(highlightLayer.getBounds(), { maxZoom: 19, padding: [40, 40] });
                }
            }

            const gcnBadgeClass = item.trang_thai_gcn === 'Đã cấp GCN' ? 'bg-success' : 'bg-warning text-dark';

            let adminButtonsHtml = '';
            const safeGid = Number.parseInt(item.gid, 10) || 0;
            if (IS_ADMIN) {
                adminButtonsHtml = '<div class="d-flex gap-2 mt-3 pt-3 border-top">' +
                    '<button class="btn btn-sm btn-primary flex-grow-1 fw-semibold" onclick="openEditModal()">' +
                        '<i class="bi bi-pencil-square me-1"></i>Cập nhật / Cấp GCN' +
                    '</button>' +
                    '<button class="btn btn-sm btn-outline-danger" onclick="deleteParcel(' + safeGid + ')">' +
                        '<i class="bi bi-trash3"></i>' +
                    '</button>' +
                '</div>';
            }

            const dienTichText = item.dien_tich ? Number(item.dien_tich).toLocaleString('vi-VN') + ' m²' : 'Chưa cập nhật';

            const detailContainer = document.getElementById('parcelDetailCard');
            detailContainer.classList.remove('d-none');
            detailContainer.innerHTML =
                '<div class="card border-0 shadow-sm detail-card">' +
                    '<div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">' +
                        '<span class="fw-bold small"><i class="bi bi-pin-map-fill me-1"></i>Tờ ' + escapeHtml(item.so_to || '-') + ' — Thửa ' + escapeHtml(item.so_thua || '-') + '</span>' +
                        '<span class="badge ' + gcnBadgeClass + '">' + escapeHtml(item.trang_thai_gcn || 'Chưa cập nhật') + '</span>' +
                    '</div>' +
                    '<div class="card-body p-3 small">' +
                        '<div class="detail-group-title"><i class="bi bi-bounding-box me-1"></i>Thông tin Thửa đất</div>' +
                        '<div class="row g-2 mb-3">' +
                            '<div class="col-6">' +
                                '<span class="text-muted d-block">Tờ bản đồ / Số thửa:</span>' +
                                '<strong>Tờ ' + escapeHtml(item.so_to || '-') + ' / Thửa ' + escapeHtml(item.so_thua || '-') + '</strong>' +
                            '</div>' +
                            '<div class="col-6">' +
                                '<span class="text-muted d-block">Diện tích pháp lý:</span>' +
                                '<strong class="text-primary">' + dienTichText + '</strong>' +
                            '</div>' +
                            '<div class="col-12">' +
                                '<span class="text-muted d-block">Loại đất (Mục đích sử dụng):</span>' +
                                '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 mt-1">' + escapeHtml(item.ma_loai_dat || 'Chưa phân loại') + '</span>' +
                            '</div>' +
                        '</div>' +

                        '<div class="detail-group-title"><i class="bi bi-person-vcard me-1"></i>Chủ sử dụng đất</div>' +
                        '<div class="mb-3">' +
                            '<div class="d-flex justify-content-between py-1 border-bottom border-light">' +
                                '<span class="text-muted">Họ và tên:</span>' +
                                '<strong class="text-end">' + escapeHtml(item.ten_chu || 'Chưa kê khai') + '</strong>' +
                            '</div>' +
                            '<div class="d-flex justify-content-between py-1 border-bottom border-light">' +
                                '<span class="text-muted">Số CCCD/Định danh:</span>' +
                                '<span>' + escapeHtml(item.so_cccd || '-') + '</span>' +
                            '</div>' +
                            '<div class="d-flex justify-content-between py-1 border-bottom border-light">' +
                                '<span class="text-muted">Số điện thoại:</span>' +
                                '<span>' + escapeHtml(item.so_dien_thoai || '-') + '</span>' +
                            '</div>' +
                            '<div class="py-1">' +
                                '<span class="text-muted d-block">Địa chỉ thường trú:</span>' +
                                '<span>' + escapeHtml(item.dia_chi_thuong_tru || '-') + '</span>' +
                            '</div>' +
                        '</div>' +

                        '<div class="detail-group-title"><i class="bi bi-journal-bookmark-fill me-1"></i>Giấy chứng nhận (GCNQSDĐ)</div>' +
                        '<div>' +
                            '<div class="d-flex justify-content-between py-1 border-bottom border-light">' +
                                '<span class="text-muted">Số phát hành (Seri):</span>' +
                                '<strong class="text-danger">' + escapeHtml(item.so_phat_hanh || 'Chưa có') + '</strong>' +
                            '</div>' +
                            '<div class="d-flex justify-content-between py-1 border-bottom border-light">' +
                                '<span class="text-muted">Số vào sổ cấp GCN:</span>' +
                                '<strong>' + escapeHtml(item.so_vao_so || '-') + '</strong>' +
                            '</div>' +
                            '<div class="d-flex justify-content-between py-1">' +
                                '<span class="text-muted">Ngày cấp:</span>' +
                                '<span>' + escapeHtml(item.ngay_cap || '-') + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary w-100 my-3" id="btnFindAdjacent"><i class="bi bi-bounding-box-circles me-1"></i>Tìm thửa ráp ranh</button>' +
                        '<div class="d-none mb-3" id="adjacentParcelResults">' +
                            '<div class="detail-group-title"><i class="bi bi-bounding-box-circles me-1"></i>Thửa ráp ranh <span class="badge bg-primary-subtle text-primary-emphasis ms-1" id="adjacentParcelCount">...</span></div>' +
                            '<div id="adjacentParcelsContent"><div class="text-muted py-2"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang tìm thửa có chung ranh...</div></div>' +
                        '</div>' +
                        adminButtonsHtml +
                    '</div>' +
                '</div>';

            const adjacentButton = document.getElementById('btnFindAdjacent');
            const adjacentResults = document.getElementById('adjacentParcelResults');
            adjacentButton.addEventListener('click', function() {
                if (!adjacentResults.classList.contains('d-none')) {
                    adjacentResults.classList.add('d-none');
                    adjacentLayer.clearLayers();
                    adjacentButton.innerHTML = adjacentButton.dataset.loaded === 'true'
                        ? '<i class="bi bi-bounding-box-circles me-1"></i>Bật thửa ráp ranh'
                        : '<i class="bi bi-bounding-box-circles me-1"></i>Tìm thửa ráp ranh';
                    return;
                }

                adjacentResults.classList.remove('d-none');
                adjacentButton.innerHTML = '<i class="bi bi-eye-slash me-1"></i>Tắt thửa ráp ranh';
                if (adjacentButton.dataset.loaded === 'true') {
                    drawAdjacentParcels(currentAdjacentParcels);
                    fitSelectedAndAdjacentParcels();
                    return;
                }

                adjacentButton.dataset.loaded = 'true';
                document.getElementById('adjacentParcelCount').textContent = '...';
                document.getElementById('adjacentParcelsContent').innerHTML = '<div class="text-muted py-2"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Đang tìm thửa có chung ranh...</div>';
                loadAdjacentParcels(item.gid, selectionId).then(function(loaded) {
                    if (!loaded && selectionId === adjacentRequestId) {
                        adjacentButton.dataset.loaded = 'false';
                        if (adjacentResults.classList.contains('d-none')) {
                            adjacentButton.innerHTML = '<i class="bi bi-bounding-box-circles me-1"></i>Tìm thửa ráp ranh';
                        }
                    }
                });
            });
        }

        async function loadAdjacentParcels(gid, selectionId) {
            const params = new URLSearchParams({ action: 'adjacent_parcels', gid: String(gid ?? '') });
            const countEl = document.getElementById('adjacentParcelCount');
            const contentEl = document.getElementById('adjacentParcelsContent');
            if (!countEl || !contentEl) return false;

            try {
                const res = await fetch('xu_ly_click.php?' + params.toString());
                const json = await res.json();
                if (selectionId !== adjacentRequestId) return;

                if (json.status !== 'success') {
                    countEl.textContent = '—';
                    contentEl.innerHTML = '<div class="text-danger py-2">' + escapeHtml(json.message || 'Không thể tìm thửa ráp ranh.') + '</div>';
                    return false;
                }

                const parcels = json.data || [];
                countEl.textContent = String(parcels.length);
                currentAdjacentParcels = parcels;

                if (parcels.length === 0) {
                    adjacentLayer.clearLayers();
                    contentEl.innerHTML = '<div class="text-muted py-2">Không tìm thấy thửa có chung đoạn ranh với thửa này.</div>';
                    if (!document.getElementById('adjacentParcelResults').classList.contains('d-none')) {
                        fitSelectedAndAdjacentParcels();
                    }
                    return true;
                }

                const list = document.createElement('div');
                list.className = 'list-group list-group-flush border rounded-2';
                parcels.forEach(function(parcel) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action py-2 px-3 small';
                    button.innerHTML =
                        '<div class="d-flex justify-content-between align-items-center">' +
                            '<strong class="text-primary">Tờ ' + escapeHtml(parcel.so_to || '-') + ' — Thửa ' + escapeHtml(parcel.so_thua || '-') + '</strong>' +
                            '<span class="badge bg-info-subtle text-info-emphasis">' + escapeHtml(parcel.ma_loai_dat || 'Chưa phân loại') + '</span>' +
                        '</div>' +
                        '<div class="text-muted mt-1"><i class="bi bi-person me-1"></i>' + escapeHtml(parcel.ten_chu || 'Chưa cập nhật chủ đất') + '</div>';
                    button.addEventListener('click', function() { selectAndHighlightParcel(parcel, false); });
                    list.appendChild(button);
                });
                contentEl.innerHTML = '';
                contentEl.appendChild(list);

                if (!document.getElementById('adjacentParcelResults').classList.contains('d-none')) {
                    drawAdjacentParcels(parcels);
                    fitSelectedAndAdjacentParcels();
                }
                return true;
            } catch (err) {
                if (selectionId !== adjacentRequestId) return;
                countEl.textContent = '—';
                contentEl.innerHTML = '<div class="text-danger py-2">Không thể tải danh sách thửa ráp ranh. Vui lòng thử lại.</div>';
                console.error('Lỗi tìm thửa ráp ranh:', err);
                return false;
            }
        }

        function drawAdjacentParcels(parcels) {
            adjacentLayer.clearLayers();
            parcels.forEach(function(parcel) {
                if (parcel.geojson) adjacentLayer.addData(parcel.geojson);
            });
            highlightLayer.bringToFront();
        }

        function fitSelectedAndAdjacentParcels() {
            map.invalidateSize({ pan: false });
            requestAnimationFrame(function() {
                const bounds = highlightLayer.getBounds();
                const adjacentBounds = adjacentLayer.getBounds();
                if (adjacentBounds.isValid()) bounds.extend(adjacentBounds);
                if (bounds.isValid()) map.fitBounds(bounds, { maxZoom: 18, padding: [40, 40] });
            });
        }

        function bindSearchForm(formId, actionName) {
            const form = document.getElementById(formId);
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const params = new URLSearchParams(new FormData(form));
                params.append('action', actionName);

                try {
                    const res = await fetch('xu_ly_click.php?' + params.toString());
                    const json = await res.json();

                    if (json.status === 'error') {
                        alert(json.message);
                        return;
                    }
                    renderSearchResults(json.data || []);
                } catch (err) {
                    console.error("Lỗi tìm kiếm:", err);
                }
            });
        }

        bindSearchForm('formSearchThua', 'search_thua');
        bindSearchForm('formSearchGCN', 'search_gcn');
        bindSearchForm('formSearchChu', 'search_chu');

        function renderSearchResults(items) {
            document.getElementById('emptyState').classList.add('d-none');
            const wrapper = document.getElementById('searchListWrapper');
            const listEl  = document.getElementById('searchResultList');
            const countEl = document.getElementById('resultCount');

            wrapper.classList.remove('d-none');
            countEl.textContent = items.length;
            listEl.innerHTML = '';

            if (items.length === 0) {
                listEl.innerHTML = '<div class="p-3 text-center text-muted small">Không tìm thấy hồ sơ phù hợp.</div>';
                return;
            }

            items.forEach(function(item, index) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action py-2 px-3 small';
                btn.innerHTML =
                    '<div class="d-flex justify-content-between align-items-center">' +
                        '<strong class="text-primary">Tờ ' + escapeHtml(item.so_to || '-') + ' - Thửa ' + escapeHtml(item.so_thua || '-') + '</strong>' +
                        '<span class="badge bg-secondary-subtle text-secondary-emphasis">' + escapeHtml(item.ma_loai_dat || '-') + '</span>' +
                    '</div>' +
                    '<div class="text-truncate text-dark mt-1">' +
                        '<i class="bi bi-person me-1 text-muted"></i>' + escapeHtml(item.ten_chu || 'Chưa cập nhật chủ đất') +
                    '</div>' +
                    '<div class="d-flex justify-content-between text-muted mt-1" style="font-size: 0.75rem;">' +
                        '<span>GCN: ' + escapeHtml(item.so_phat_hanh || 'Chưa cấp') + '</span>' +
                        '<span>' + (item.dien_tich ? item.dien_tich + ' m²' : '') + '</span>' +
                    '</div>';
                btn.addEventListener('click', function() { selectAndHighlightParcel(item, false); });
                listEl.appendChild(btn);

                if (index === 0) {
                    selectAndHighlightParcel(item, false);
                }
            });
        }

        function clearAllResults() {
            mapClickRequestId += 1;
            adjacentRequestId += 1;
            currentParcelData = null;
            currentAdjacentParcels = [];
            highlightLayer.clearLayers();
            adjacentLayer.clearLayers();
            document.getElementById('searchListWrapper').classList.add('d-none');
            document.getElementById('parcelDetailCard').classList.add('d-none');
            document.getElementById('emptyState').classList.remove('d-none');
        }

        document.getElementById('btnClearResults').addEventListener('click', clearAllResults);
        document.querySelectorAll('.btn-reset-map').forEach(function(btn) {
            btn.addEventListener('click', clearAllResults);
        });

        <?php if ($is_admin): ?>
        const editModal = new bootstrap.Modal(document.getElementById('modalEditParcel'));

        window.openEditModal = function() {
            if (!currentParcelData) return;
            document.getElementById('edit_gid').value            = currentParcelData.gid;
            document.getElementById('edit_so_to').value          = currentParcelData.so_to || '';
            document.getElementById('edit_so_thua').value        = currentParcelData.so_thua || '';
            document.getElementById('edit_dien_tich').value      = currentParcelData.dien_tich || '';
            document.getElementById('edit_ma_loai_dat').value    = currentParcelData.ma_loai_dat || '';
            document.getElementById('edit_ten_chu').value        = currentParcelData.ten_chu || '';
            document.getElementById('edit_so_cccd').value        = currentParcelData.so_cccd || '';
            document.getElementById('edit_dia_chi').value        = currentParcelData.dia_chi_thuong_tru || '';
            document.getElementById('edit_sdt').value            = currentParcelData.so_dien_thoai || '';
            document.getElementById('edit_so_phat_hanh').value   = currentParcelData.so_phat_hanh || '';
            document.getElementById('edit_so_vao_so').value      = currentParcelData.so_vao_so || '';
            document.getElementById('edit_ngay_cap').value       = currentParcelData.ngay_cap || '';
            document.getElementById('edit_trang_thai_gcn').value = currentParcelData.trang_thai_gcn || 'Đã cấp GCN';
            editModal.show();
        };

        document.getElementById('formUpdateParcel').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'update_parcel');
            formData.append('_csrf', CSRF_TOKEN);

            const res  = await fetch('xu_ly_click.php', { method: 'POST', body: formData });
            const json = await res.json();

            alert(json.message);
            if (json.status === 'success') {
                editModal.hide();
                Object.assign(currentParcelData, Object.fromEntries(new FormData(this).entries()));
                selectAndHighlightParcel(currentParcelData, true);
                wmsThuaDat.setParams({ _ts: Date.now() });
            }
        });

        window.deleteParcel = async function(gid) {
            if (!confirm("Bạn có chắc chắn muốn xóa bản ghi thửa đất này khỏi cơ sở dữ liệu?")) return;
            const formData = new FormData();
            formData.append('action', 'delete_parcel');
            formData.append('gid', gid);
            formData.append('_csrf', CSRF_TOKEN);

            const res  = await fetch('xu_ly_click.php', { method: 'POST', body: formData });
            const json = await res.json();

            alert(json.message);
            if (json.status === 'success') {
                clearAllResults();
                wmsThuaDat.setParams({ _ts: Date.now() });
            }
        };
        <?php endif; ?>

        document.getElementById('btnToggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebarPanel').classList.toggle('show-mobile');
        });
    </script>
</body>
</html>