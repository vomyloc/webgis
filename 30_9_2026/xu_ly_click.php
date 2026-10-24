<?php
header('Content-Type: application/json; charset=utf-8');
require_once 'ketnoi.php';

$connection = webgisPgConnection();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'click_map';
$action = is_string($action) ? $action : '';

function requestString(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_scalar($value) ? trim((string)$value) : '';
}

if (in_array($action, ['update_parcel', 'delete_parcel'], true)) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Phương thức không được hỗ trợ.']);
        exit;
    }
    if (!verifyCsrfToken($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Phiên thao tác không hợp lệ. Vui lòng tải lại trang.']);
        exit;
    }
}

// Bảng nhập từ bộ dữ liệu địa chính dùng to_so/thua_so/loai_dat/thua_id.
// Các bản cài mới hơn có thể đã dùng tên so_to/so_thua/ma_loai_dat/gid.
$columnCandidates = [
    'gid' => ['thua_id', 'gid', '_gid'],
    'so_to' => ['to_so', 'so_to'],
    'so_thua' => ['thua_so', 'so_thua'],
    'dien_tich' => ['dien_tich'],
    'ma_loai_dat' => ['loai_dat', 'ma_loai_dat'],
    'ten_chu' => ['ten_chu'],
    'so_cccd' => ['so_cccd'],
    'dia_chi_thuong_tru' => ['dia_chi_thuong_tru'],
    'so_dien_thoai' => ['so_dien_thoai'],
    'so_phat_hanh' => ['so_phat_hanh'],
    'so_vao_so' => ['so_vao_so'],
    'ngay_cap' => ['ngay_cap'],
    'trang_thai_gcn' => ['trang_thai_gcn'],
    'geom' => ['geom']
];
$schemaResult = @pg_query($connection, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'thua_dat_dong_thanh'");
if (!$schemaResult) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Không đọc được cấu trúc bảng thửa đất.']);
    exit;
}
$availableColumns = [];
while ($columnRow = pg_fetch_assoc($schemaResult)) {
    $availableColumns[$columnRow['column_name']] = true;
}
pg_free_result($schemaResult);

// In the current dataset, certificate numbers live in their own table and
// point back to the parcel through thua_id. Keep the parcel-column fallback
// for installations that still store these values directly on the parcel.
$certificateColumns = [];
$personColumns = [];
$certificatePersonColumns = [];
$ownerForeignKeys = [];
if (in_array($action, ['search_gcn', 'search_chu'], true)) {
    $certificateSchemaResult = @pg_query($connection, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'giay_chung_nhan'");
    if ($certificateSchemaResult) {
        while ($columnRow = pg_fetch_assoc($certificateSchemaResult)) {
            $certificateColumns[$columnRow['column_name']] = true;
        }
        pg_free_result($certificateSchemaResult);
    }
}

$ownerColumnCandidates = [
    'ten_chu' => ['ten_chu', 'ho_ten', 'ho_va_ten', 'ten_ca_nhan', 'ho_ten_chu', 'ten_nguoi', 'ten', 'full_name', 'name'],
    'so_cccd' => ['so_cccd', 'cccd', 'so_dinh_danh', 'ma_dinh_danh', 'so_cmnd', 'cmnd', 'identity_number'],
    'dia_chi_thuong_tru' => ['dia_chi_thuong_tru', 'dia_chi', 'thuong_tru', 'dia_chi_nha', 'address'],
    'so_dien_thoai' => ['so_dien_thoai', 'dien_thoai', 'sdt', 'phone_number', 'phone', 'mobile']
];

if ($action === 'search_chu') {
    $personSchemaResult = @pg_query($connection, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'ca_nhan'");
    if ($personSchemaResult) {
        while ($columnRow = pg_fetch_assoc($personSchemaResult)) {
            $personColumns[$columnRow['column_name']] = true;
        }
        pg_free_result($personSchemaResult);
    }

    $certificatePersonSchemaResult = @pg_query($connection, "SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'gcn_ca_nhan'");
    if ($certificatePersonSchemaResult) {
        while ($columnRow = pg_fetch_assoc($certificatePersonSchemaResult)) {
            $certificatePersonColumns[$columnRow['column_name']] = true;
        }
        pg_free_result($certificatePersonSchemaResult);
    }

    // Prefer declared foreign keys for the normalized owner relation, while
    // retaining conventional column-name fallbacks for older imported datasets.
    $ownerForeignKeyResult = @pg_query($connection, "SELECT kcu.column_name AS local_column, ccu.table_name AS referenced_table, ccu.column_name AS referenced_column
    FROM information_schema.table_constraints tc
    JOIN information_schema.key_column_usage kcu
      ON tc.constraint_catalog = kcu.constraint_catalog
     AND tc.constraint_schema = kcu.constraint_schema
     AND tc.constraint_name = kcu.constraint_name
    JOIN information_schema.constraint_column_usage ccu
      ON tc.constraint_catalog = ccu.constraint_catalog
     AND tc.constraint_schema = ccu.constraint_schema
     AND tc.constraint_name = ccu.constraint_name
    WHERE tc.constraint_type = 'FOREIGN KEY'
      AND tc.table_schema = current_schema()
      AND tc.table_name = 'gcn_ca_nhan'
      AND ccu.table_schema = current_schema()");
    if ($ownerForeignKeyResult) {
        while ($foreignKeyRow = pg_fetch_assoc($ownerForeignKeyResult)) {
            if (in_array($foreignKeyRow['referenced_table'], ['ca_nhan', 'giay_chung_nhan'], true)
                && !isset($ownerForeignKeys[$foreignKeyRow['referenced_table']])) {
                $ownerForeignKeys[$foreignKeyRow['referenced_table']] = [
                    'local' => $foreignKeyRow['local_column'],
                    'referenced' => $foreignKeyRow['referenced_column']
                ];
            }
        }
        pg_free_result($ownerForeignKeyResult);
    }
}

function mappedColumns(string $field): array
{
    global $columnCandidates, $availableColumns;
    return array_values(array_filter($columnCandidates[$field] ?? [], function ($name) use ($availableColumns) {
        return isset($availableColumns[$name]);
    }));
}

function quotedColumn(string $name): string
{
    // All names are selected from the hard-coded allowlist above.
    return '"' . str_replace('"', '""', $name) . '"';
}

function fieldExpr(string $field, string $tableAlias = 't'): string
{
    $columns = mappedColumns($field);
    if (!$columns) return 'NULL';
    $parts = array_map(function ($name) use ($tableAlias) { return $tableAlias . '.' . quotedColumn($name); }, $columns);
    return count($parts) === 1 ? $parts[0] : 'COALESCE(' . implode(', ', $parts) . ')';
}

function certificateFieldExpr(string $field, string $tableAlias = 'g'): string
{
    global $certificateColumns;
    if (!in_array($field, ['so_phat_hanh', 'so_vao_so', 'ngay_cap'], true) || !isset($certificateColumns[$field])) return 'NULL';
    return $tableAlias . '.' . quotedColumn($field);
}

function ownerFieldExpr(string $field, string $tableAlias = 'cn'): string
{
    global $ownerColumnCandidates, $personColumns;
    foreach ($ownerColumnCandidates[$field] ?? [] as $column) {
        if (isset($personColumns[$column])) return $tableAlias . '.' . quotedColumn($column);
    }
    return 'NULL';
}

function ownerParcelSearchExpr(string $field): array
{
    $expressions = [];
    $ownerExpression = ownerFieldExpr($field);
    $parcelExpression = fieldExpr($field);
    if ($ownerExpression !== 'NULL') $expressions[] = $ownerExpression;
    if ($parcelExpression !== 'NULL') $expressions[] = $parcelExpression;
    return $expressions;
}

function ownerResultExpr(string $field): string
{
    $expressions = [];
    $ownerExpression = ownerFieldExpr($field);
    $parcelExpression = fieldExpr($field);
    if ($ownerExpression !== 'NULL') $expressions[] = $ownerExpression;
    if ($parcelExpression !== 'NULL') $expressions[] = $parcelExpression;
    if (!$expressions) return 'NULL';
    return count($expressions) === 1 ? $expressions[0] : 'COALESCE(' . implode(', ', $expressions) . ')';
}

function firstAvailableColumn(array $availableColumns, array $candidates): ?string
{
    foreach ($candidates as $column) {
        if (isset($availableColumns[$column])) return $column;
    }
    return null;
}

function selectedFields(string $tableAlias = 't', array $overrides = []): string
{
    $fields = ['gid', 'so_to', 'so_thua', 'dien_tich', 'ma_loai_dat', 'ten_chu', 'so_cccd',
        'dia_chi_thuong_tru', 'so_dien_thoai', 'so_phat_hanh', 'so_vao_so', 'ngay_cap', 'trang_thai_gcn'];
    $select = array_map(function ($field) use ($tableAlias, $overrides) {
        $expression = $overrides[$field] ?? fieldExpr($field, $tableAlias);
        return $expression . ' AS ' . quotedColumn($field);
    }, $fields);
    $select[] = 'ST_AsGeoJSON(ST_Transform(' . $tableAlias . '.' . quotedColumn('geom') . ', 4326)) AS geojson';
    return implode(",\n", $select);
}

function requireParcelSchema(): void
{
    if (!mappedColumns('gid') || !mappedColumns('so_to') || !mappedColumns('so_thua')
        || !mappedColumns('ma_loai_dat') || !mappedColumns('geom')) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Bảng thửa đất thiếu cột khóa, tờ/thửa, loại đất hoặc geom.']);
        exit;
    }
}

requireParcelSchema();
$selectFields = selectedFields();
$table = 'thua_dat_dong_thanh t';

switch ($action) {
    case 'adjacent_parcels':
        $gidRaw = requestString($_GET, 'gid');
        $gid = filter_var($gidRaw, FILTER_VALIDATE_INT);
        if ($gid === false || $gid < 1) {
            echo json_encode(['status' => 'error', 'message' => 'Mã thửa đất không hợp lệ.']);
            exit;
        }

        $geometryColumn = quotedColumn(mappedColumns('geom')[0]);
        $sourceGid = fieldExpr('gid', 'source');
        $neighborGid = fieldExpr('gid', 't');
        $sql = 'SELECT ' . selectedFields('t') . ' FROM thua_dat_dong_thanh source '
            . 'JOIN thua_dat_dong_thanh t ON ST_Touches(source.' . $geometryColumn . ', t.' . $geometryColumn . ') '
            . 'AND ST_Length(ST_Intersection(ST_Boundary(source.' . $geometryColumn . '), ST_Boundary(t.' . $geometryColumn . '))) > 0 '
            . 'WHERE ' . $sourceGid . ' = $1 '
            . 'AND ' . $neighborGid . ' <> ' . $sourceGid . ' '
            . 'ORDER BY ' . fieldExpr('so_to') . ' ASC, ' . fieldExpr('so_thua') . ' ASC';
        sendListResponse(@pg_query_params($connection, $sql, [$gid]));
        break;

    case 'click_map':
        $latRaw = $_POST['lat'] ?? null;
        $lngRaw = $_POST['lng'] ?? null;
        if (!is_scalar($latRaw) || !is_numeric((string)$latRaw) || !is_scalar($lngRaw) || !is_numeric((string)$lngRaw)) {
            echo json_encode(['status' => 'error', 'message' => 'Tọa độ không hợp lệ.']);
            exit;
        }
        $lat = (float)$latRaw; $lng = (float)$lngRaw;
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            echo json_encode(['status' => 'error', 'message' => 'Tọa độ không hợp lệ.']);
            exit;
        }
        $clickPoint = 'ST_Transform(ST_SetSRID(ST_MakePoint($1, $2), 4326), ST_SRID(t.' . quotedColumn('geom') . '))';
        $sql = 'SELECT ' . $selectFields . ' FROM ' . $table . ' WHERE ST_Intersects(t.' . quotedColumn('geom')
            . ', ' . $clickPoint . ')'
            . ' ORDER BY CASE WHEN ST_Contains(t.' . quotedColumn('geom') . ', ' . $clickPoint . ') THEN 0 ELSE 1 END ASC, '
            . 'CASE WHEN ST_Dimension(t.' . quotedColumn('geom') . ') = 2 THEN ST_Area(t.' . quotedColumn('geom') . ') ELSE 1.0e100 END ASC, '
            . fieldExpr('gid') . ' ASC LIMIT 1';
        $res = @pg_query_params($connection, $sql, [$lng, $lat]);
        if (!$res) {
            error_log('WebGIS click_map error: ' . pg_last_error($connection));
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Không thể truy vấn thửa đất tại vị trí vừa chọn.']);
        } elseif (pg_num_rows($res) > 0) {
            $data = pg_fetch_assoc($res);
            $data['geojson'] = json_decode($data['geojson']);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            echo json_encode(['status' => 'empty', 'message' => 'Không tìm thấy thửa đất tại vị trí vừa chọn.']);
        }
        break;

    case 'search_thua':
        $soTo = requestString($_GET, 'so_to');
        $soThua = requestString($_GET, 'so_thua');
        $loaiDat = requestString($_GET, 'ma_loai_dat');
        $conditions = []; $params = []; $idx = 1;
        foreach ([['value' => $soTo, 'field' => 'so_to', 'label' => 'Số tờ bản đồ'], ['value' => $soThua, 'field' => 'so_thua', 'label' => 'Số thửa']] as $item) {
            if ($item['value'] === '') continue;
            $parsed = filter_var($item['value'], FILTER_VALIDATE_INT);
            if ($parsed === false || $parsed < 0) {
                echo json_encode(['status' => 'error', 'message' => $item['label'] . ' phải là số nguyên không âm.']); exit;
            }
            $conditions[] = fieldExpr($item['field']) . ' = $' . $idx++;
            $params[] = $parsed;
        }
        if ($loaiDat !== '') { $conditions[] = fieldExpr('ma_loai_dat') . ' ILIKE $' . $idx++; $params[] = '%' . $loaiDat . '%'; }
        if (!$conditions) { echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập ít nhất 1 tiêu chí tra cứu thửa đất.']); exit; }
        $sql = 'SELECT ' . $selectFields . ' FROM ' . $table . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . fieldExpr('so_to') . ' ASC, ' . fieldExpr('so_thua') . ' ASC LIMIT 50';
        sendListResponse(@pg_query_params($connection, $sql, $params));
        break;

    case 'search_gcn':
        $conditions = []; $params = []; $idx = 1;
        $useCertificateTable = isset($certificateColumns['thua_id'])
            && (isset($certificateColumns['so_phat_hanh']) || isset($certificateColumns['so_vao_so']))
            && mappedColumns('gid');
        $certificateOverrides = [];
        if ($useCertificateTable) {
            foreach (['so_phat_hanh', 'so_vao_so'] as $field) {
                $value = requestString($_GET, $field);
                if ($value !== '' && isset($certificateColumns[$field])) {
                    $conditions[] = certificateFieldExpr($field) . ' ILIKE $' . $idx++;
                    $params[] = '%' . $value . '%';
                }
                if (isset($certificateColumns[$field])) $certificateOverrides[$field] = certificateFieldExpr($field);
            }
        } else {
            foreach (['so_phat_hanh' => requestString($_GET, 'so_phat_hanh'), 'so_vao_so' => requestString($_GET, 'so_vao_so')] as $field => $value) {
                if ($value !== '' && mappedColumns($field)) { $conditions[] = fieldExpr($field) . ' ILIKE $' . $idx++; $params[] = '%' . $value . '%'; }
            }
        }
        $status = requestString($_GET, 'trang_thai_gcn');
        if ($status !== '' && mappedColumns('trang_thai_gcn')) { $conditions[] = fieldExpr('trang_thai_gcn') . ' = $' . $idx++; $params[] = $status; }
        if (!$conditions) { echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tiêu chí GCN đã có trong bảng dữ liệu.']); exit; }
        $from = $useCertificateTable
            ? 'giay_chung_nhan g JOIN thua_dat_dong_thanh t ON g.' . quotedColumn('thua_id') . ' = ' . fieldExpr('gid', 't')
            : $table;
        $gcnSelectFields = $useCertificateTable ? selectedFields('t', $certificateOverrides) : $selectFields;
        $sql = 'SELECT ' . $gcnSelectFields . ' FROM ' . $from . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . fieldExpr('so_to') . ' ASC, ' . fieldExpr('so_thua') . ' ASC LIMIT 50';
        sendListResponse(@pg_query_params($connection, $sql, $params));
        break;

    case 'search_chu':
        $personForeignKey = $ownerForeignKeys['ca_nhan'] ?? null;
        $certificateForeignKey = $ownerForeignKeys['giay_chung_nhan'] ?? null;
        $bridgePersonColumn = $personForeignKey['local'] ?? firstAvailableColumn($certificatePersonColumns,
            ['ca_nhan_id', 'id_ca_nhan', 'person_id', 'ma_ca_nhan', 'chu_id']);
        $personKeyColumn = $personForeignKey['referenced'] ?? firstAvailableColumn($personColumns,
            ['ca_nhan_id', 'id_ca_nhan', 'person_id', 'ma_ca_nhan', 'chu_id', 'id']);
        $bridgeCertificateColumn = $certificateForeignKey['local'] ?? firstAvailableColumn($certificatePersonColumns,
            ['gcn_id', 'id_gcn', 'giay_chung_nhan_id', 'so_vao_so', 'so_phat_hanh', 'ma_vach', 'certificate_id', 'so_gcn', 'id_giay_chung_nhan', 'thua_id']);
        $certificateKeyColumn = $certificateForeignKey['referenced'] ?? firstAvailableColumn($certificateColumns,
            ['gcn_id', 'id_gcn', 'giay_chung_nhan_id', 'so_vao_so', 'so_phat_hanh', 'ma_vach', 'certificate_id', 'so_gcn', 'id_giay_chung_nhan', 'thua_id', 'id']);
        $certificateParcelColumn = firstAvailableColumn($certificateColumns, ['thua_id', 'gid', 'parcel_id', 'id_thua']);
        $hasOwnerData = ownerFieldExpr('ten_chu') !== 'NULL'
            || ownerFieldExpr('so_cccd') !== 'NULL'
            || ownerFieldExpr('dia_chi_thuong_tru') !== 'NULL'
            || ownerFieldExpr('so_dien_thoai') !== 'NULL';
        $useNormalizedOwnerTables = $hasOwnerData && $bridgePersonColumn && $personKeyColumn
            && $bridgeCertificateColumn && $certificateKeyColumn && $certificateParcelColumn
            && mappedColumns('gid') && isset($certificatePersonColumns[$bridgePersonColumn])
            && isset($personColumns[$personKeyColumn]) && isset($certificatePersonColumns[$bridgeCertificateColumn])
            && isset($certificateColumns[$certificateKeyColumn]);

        $conditions = []; $params = []; $idx = 1;
        foreach (['ten_chu' => requestString($_GET, 'ten_chu'), 'so_cccd' => requestString($_GET, 'so_cccd')] as $field => $value) {
            if ($value === '') continue;
            $expressions = $useNormalizedOwnerTables ? ownerParcelSearchExpr($field) : (mappedColumns($field) ? [fieldExpr($field)] : []);
            if ($expressions) {
                $conditions[] = '(' . implode(' OR ', array_map(function ($expression) use ($idx) { return $expression . ' ILIKE $' . $idx; }, $expressions)) . ')';
                $params[] = '%' . $value . '%'; $idx++;
            }
        }
        $address = requestString($_GET, 'dia_chi');
        if ($address !== '') {
            $ors = [];
            foreach (['dia_chi_thuong_tru', 'so_dien_thoai'] as $field) {
                $expressions = $useNormalizedOwnerTables ? ownerParcelSearchExpr($field) : (mappedColumns($field) ? [fieldExpr($field)] : []);
                foreach ($expressions as $expression) $ors[] = $expression . ' ILIKE $' . $idx;
            }
            if ($ors) { $conditions[] = '(' . implode(' OR ', $ors) . ')'; $params[] = '%' . $address . '%'; $idx++; }
        }
        if (!$conditions) { echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập tiêu chí chủ đất đã có trong bảng dữ liệu.']); exit; }
        $ownerOverrides = [];
        if ($useNormalizedOwnerTables) {
            foreach (['ten_chu', 'so_cccd', 'dia_chi_thuong_tru', 'so_dien_thoai'] as $field) {
                $ownerExpression = ownerFieldExpr($field);
                if ($ownerExpression !== 'NULL') $ownerOverrides[$field] = ownerResultExpr($field);
            }
            foreach (['so_phat_hanh', 'so_vao_so', 'ngay_cap'] as $field) {
                $certificateExpression = certificateFieldExpr($field);
                if ($certificateExpression !== 'NULL') $ownerOverrides[$field] = $certificateExpression;
            }
        }
        $ownerFrom = $useNormalizedOwnerTables
            ? 'giay_chung_nhan g JOIN thua_dat_dong_thanh t ON g.' . quotedColumn($certificateParcelColumn) . ' = ' . fieldExpr('gid', 't')
                . ' JOIN gcn_ca_nhan gc ON gc.' . quotedColumn($bridgeCertificateColumn) . ' = g.' . quotedColumn($certificateKeyColumn)
                . ' JOIN ca_nhan cn ON gc.' . quotedColumn($bridgePersonColumn) . ' = cn.' . quotedColumn($personKeyColumn)
            : $table;
        $ownerSelectFields = $useNormalizedOwnerTables ? selectedFields('t', $ownerOverrides) : $selectFields;
        $ownerSort = $useNormalizedOwnerTables && ownerFieldExpr('ten_chu') !== 'NULL' ? ownerFieldExpr('ten_chu') : fieldExpr('ten_chu');
        $sql = 'SELECT ' . $ownerSelectFields . ' FROM ' . $ownerFrom . ' WHERE ' . implode(' AND ', $conditions)
            . ' ORDER BY ' . $ownerSort . ' ASC, ' . fieldExpr('so_to') . ' ASC LIMIT 50';
        sendListResponse(@pg_query_params($connection, $sql, $params));
        break;

    case 'update_parcel':
        if (!isAdmin()) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Chỉ quản trị viên mới có quyền chỉnh sửa dữ liệu.']); exit; }
        $gid = filter_var($_POST['gid'] ?? null, FILTER_VALIDATE_INT);
        $toRaw = requestString($_POST, 'so_to'); $thuaRaw = requestString($_POST, 'so_thua'); $areaRaw = requestString($_POST, 'dien_tich');
        $to = $toRaw === '' ? null : filter_var($toRaw, FILTER_VALIDATE_INT);
        $thua = $thuaRaw === '' ? null : filter_var($thuaRaw, FILTER_VALIDATE_INT);
        $area = $areaRaw === '' ? null : (is_numeric($areaRaw) ? (float)$areaRaw : false);
        if (!$gid || ($toRaw !== '' && ($to === false || $to < 0)) || ($thuaRaw !== '' && ($thua === false || $thua < 0))
            || ($areaRaw !== '' && ($area === false || $area < 0))) {
            echo json_encode(['status' => 'error', 'message' => 'Dữ liệu cập nhật không hợp lệ.']); exit;
        }
        $values = ['so_to' => $to, 'so_thua' => $thua, 'dien_tich' => $area, 'ma_loai_dat' => requestString($_POST, 'ma_loai_dat'),
            'ten_chu' => requestString($_POST, 'ten_chu'), 'so_cccd' => requestString($_POST, 'so_cccd'),
            'dia_chi_thuong_tru' => requestString($_POST, 'dia_chi_thuong_tru'), 'so_dien_thoai' => requestString($_POST, 'so_dien_thoai'),
            'so_phat_hanh' => requestString($_POST, 'so_phat_hanh'), 'so_vao_so' => requestString($_POST, 'so_vao_so'),
            'ngay_cap' => requestString($_POST, 'ngay_cap') ?: null, 'trang_thai_gcn' => requestString($_POST, 'trang_thai_gcn') ?: 'Đã cấp GCN'];
        $assignments = []; $params = []; $idx = 1;
        foreach ($values as $field => $value) {
            $columns = mappedColumns($field);
            if (!$columns) continue;
            // Keep legacy and canonical values synchronized if both columns exist.
            foreach ($columns as $column) { $assignments[] = quotedColumn($column) . ' = $' . $idx++; $params[] = $value; }
        }
        if (!$assignments) { echo json_encode(['status' => 'error', 'message' => 'Không có cột nào cho phép cập nhật trong bảng.']); exit; }
        $keyColumn = mappedColumns('gid')[0];
        $params[] = $gid;
        $sql = 'UPDATE thua_dat_dong_thanh SET ' . implode(', ', $assignments) . ' WHERE ' . quotedColumn($keyColumn) . ' = $' . $idx;
        $res = @pg_query_params($connection, $sql, $params);
        if ($res && pg_affected_rows($res) > 0) echo json_encode(['status' => 'success', 'message' => 'Cập nhật hồ sơ địa chính thành công.']);
        else { if (!$res) error_log('WebGIS update_parcel error: ' . pg_last_error($connection)); echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật hồ sơ. Hãy kiểm tra dữ liệu hoặc mã thửa đất.']); }
        break;

    case 'delete_parcel':
        if (!isAdmin()) { http_response_code(403); echo json_encode(['status' => 'error', 'message' => 'Chỉ quản trị viên mới có quyền xóa dữ liệu.']); exit; }
        $gid = filter_var($_POST['gid'] ?? null, FILTER_VALIDATE_INT);
        if (!$gid || $gid < 1) { echo json_encode(['status' => 'error', 'message' => 'Mã thửa đất không hợp lệ.']); exit; }
        $keyColumn = mappedColumns('gid')[0];
        $res = @pg_query_params($connection, 'DELETE FROM thua_dat_dong_thanh WHERE ' . quotedColumn($keyColumn) . ' = $1', [$gid]);
        if ($res && pg_affected_rows($res) > 0) echo json_encode(['status' => 'success', 'message' => 'Đã xóa bản ghi thửa đất khỏi hệ thống.']);
        else { if (!$res) error_log('WebGIS delete_parcel error: ' . pg_last_error($connection)); echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy bản ghi cần xóa hoặc thao tác thất bại.']); }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ.']);
}

function sendListResponse($res): void
{
    if (!$res) { http_response_code(500); echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn cơ sở dữ liệu. Kiểm tra log máy chủ để biết cột gây lỗi.']); exit; }
    $rows = [];
    while ($row = pg_fetch_assoc($res)) { $row['geojson'] = json_decode($row['geojson']); $rows[] = $row; }
    echo json_encode(['status' => 'success', 'count' => count($rows), 'data' => $rows]);
}
