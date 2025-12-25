<?php
require_once '../../helper/pdo_connection.php';
require_once '../../helper/auth.php';
require_auth();

header('Content-Type: application/json');

$memberId = $_SESSION['member']['id'];
$method = $_SERVER['REQUEST_METHOD'];

function respond($ok, $data = [], $status = 200) {
  http_response_code($status);
  echo json_encode(array_merge(['ok' => $ok], $data));
  exit;
}

if ($method === 'GET') {
  // List addresses
  $stmt = $pdo->prepare("SELECT shipping_address_id, address_line1, address_line2, city, state, postal_code, country, created_date FROM Shipping_Address WHERE member_id = ? ORDER BY created_date DESC, shipping_address_id DESC");
  $stmt->execute([$memberId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  respond(true, ['addresses' => $rows]);
}

// POST actions
$action = $_POST['action'] ?? '';
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
  respond(false, ['error' => 'Invalid CSRF token'], 403);
}

$addrId = isset($_POST['address_id']) ? (int)$_POST['address_id'] : 0;
$line1 = trim($_POST['address_street'] ?? '');
$line2 = trim($_POST['address_line2'] ?? ''); // optional hidden field if added later
$city  = trim($_POST['address_city'] ?? '');
$state = trim($_POST['address_state'] ?? '');
$postcode = trim($_POST['address_postcode'] ?? '');
$country  = trim($_POST['address_country'] ?? 'Malaysia');

// Basic validation for create/update
function validate_fields($line1, $city, $postcode) {
  $errors = [];
  if ($line1 === '' || strlen($line1) < 5) $errors[] = 'Street address is required (min 5 chars).';
  if ($city === '') $errors[] = 'City is required.';
  if ($postcode !== '' && !preg_match('/^\d{5}$/', $postcode)) $errors[] = 'Postcode must be exactly 5 digits.';
  return $errors;
}

// Check if the same address already exists for this member (case-insensitive via collation)
function address_exists($pdo, $memberId, $line1, $line2, $city, $state, $postcode, $country, $excludeId = null) {
  $sql = "SELECT 1 FROM Shipping_Address WHERE member_id = ? AND COALESCE(address_line1, '') = ? AND COALESCE(address_line2, '') = ? AND COALESCE(city, '') = ? AND COALESCE(state, '') = ? AND COALESCE(postal_code, '') = ? AND COALESCE(country, '') = ?";
  $params = [$memberId, $line1, $line2, $city, $state, $postcode, $country];
  if ($excludeId !== null) {
    $sql .= " AND shipping_address_id <> ?";
    $params[] = $excludeId;
  }
  $sql .= " LIMIT 1";
  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  return (bool)$stmt->fetchColumn();
}

switch ($action) {
  case 'create':
    $errs = validate_fields($line1, $city, $postcode);
    if ($errs) respond(false, ['error' => implode(' ', $errs)], 422);

    $countryToSave = $country ?: 'Malaysia';
    if (address_exists($pdo, $memberId, $line1, $line2 ?: '', $city, $state ?: '', $postcode ?: '', $countryToSave)) {
      respond(false, ['error' => 'This is a duplicate address. You already saved this exact address.'], 409);
    }

    $ins = $pdo->prepare("INSERT INTO Shipping_Address (member_id, address_line1, address_line2, city, state, postal_code, country, created_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->execute([$memberId, $line1, $line2 ?: null, $city, $state ?: null, $postcode ?: null, $countryToSave]);
    respond(true, ['message' => 'Address added']);

  case 'update':
    if ($addrId <= 0) respond(false, ['error' => 'Missing address ID'], 400);

    // Ownership check
    $own = $pdo->prepare('SELECT member_id FROM Shipping_Address WHERE shipping_address_id = ?');
    $own->execute([$addrId]);
    $row = $own->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['member_id'] !== (int)$memberId) respond(false, ['error' => 'Address not found'], 404);

    $errs = validate_fields($line1, $city, $postcode);
    if ($errs) respond(false, ['error' => implode(' ', $errs)], 422);

    $countryToSave = $country ?: 'Malaysia';
    if (address_exists($pdo, $memberId, $line1, $line2 ?: '', $city, $state ?: '', $postcode ?: '', $countryToSave, $addrId)) {
      respond(false, ['error' => 'This is a duplicate address. You already saved this exact address.'], 409);
    }

    $upd = $pdo->prepare("UPDATE Shipping_Address SET address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ? WHERE shipping_address_id = ?");
    $upd->execute([$line1, $line2 ?: null, $city, $state ?: null, $postcode ?: null, $countryToSave, $addrId]);
    respond(true, ['message' => 'Address updated']);

  case 'delete':
    if ($addrId <= 0) respond(false, ['error' => 'Missing address ID'], 400);

    $own = $pdo->prepare('SELECT member_id FROM Shipping_Address WHERE shipping_address_id = ?');
    $own->execute([$addrId]);
    $row = $own->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['member_id'] !== (int)$memberId) respond(false, ['error' => 'Address not found'], 404);

    // Prevent deleting last address? Allow deletion; default will just be empty.
    $del = $pdo->prepare('DELETE FROM Shipping_Address WHERE shipping_address_id = ?');
    $del->execute([$addrId]);
    respond(true, ['message' => 'Address deleted']);

  case 'set_default':
    if ($addrId <= 0) respond(false, ['error' => 'Missing address ID'], 400);

    $own = $pdo->prepare('SELECT member_id FROM Shipping_Address WHERE shipping_address_id = ?');
    $own->execute([$addrId]);
    $row = $own->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['member_id'] !== (int)$memberId) respond(false, ['error' => 'Address not found'], 404);

    // Without an is_default column, make this the most recent to treat as default
    $upd = $pdo->prepare('UPDATE Shipping_Address SET created_date = NOW() WHERE shipping_address_id = ?');
    $upd->execute([$addrId]);
    respond(true, ['message' => 'Default address updated']);

  default:
    respond(false, ['error' => 'Unsupported action'], 400);
}
