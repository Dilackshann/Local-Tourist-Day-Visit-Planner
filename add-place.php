<?php

session_start([
    'use_strict_mode' => true,
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax'
]);

header('Cache-Control: no-store');

if (empty($_SESSION['admin_id'])) {
    header('Location: admin-login.html');
    exit;
}

require_once __DIR__ . '/config/database.php';

$categoryStmt = $pdo->query(
    'SELECT category_id, category_name
     FROM categories
     ORDER BY category_name'
);

$categories = $categoryStmt->fetchAll();

if (empty($_SESSION['add_place_csrf'])) {
    $_SESSION['add_place_csrf'] = bin2hex(random_bytes(32));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verify that the request contains this session's form token.
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['add_place_csrf'], $token)
    ) {
        http_response_code(403);
        exit('Form verification failed. Reload the Add Place page.');
    }

    // 2. Read the text fields and check their maximum lengths.
    $textFields = [
        'name' => 150,
        'description' => 5000,
        'opening_times' => 150,
        'travel_tips' => 5000,
        'facilities' => 5000,
        'safety_information' => 5000,
        'visitor_suitability' => 255
    ];

    $data = [];
    $errors = [];

    foreach ($textFields as $field => $limit) {
        $value = $_POST[$field] ?? null;

        if (!is_string($value)) {
            $errors[] = "Missing or invalid field: $field";
            continue;
        }

        $value = trim($value);
        $length = preg_match_all('/./us', $value);

        if ($length === false || $length > $limit) {
            $errors[] = "$field must contain valid text of at most $limit characters.";
        }

        $data[$field] = $value;
    }

    if (($data['name'] ?? '') === '') {
        $errors[] = 'Place Name is required.';
    }

    // 3. Check that the selected category exists.
    $categoryId = filter_input(
        INPUT_POST,
        'category_id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $validCategoryIds = array_map(
        'intval',
        array_column($categories, 'category_id')
    );

    if (!$categoryId || !in_array($categoryId, $validCategoryIds, true)) {
        $errors[] = 'Please select a valid category.';
    }

    // 4. Validate road distance.
    $distance = $_POST['distance_km'] ?? '';

    if (
        !is_string($distance) ||
        !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $distance)
    ) {
        $errors[] = 'Distance must be 0 to 999.99 km, with up to 2 decimal places.';
    }

    // 5. Coordinates are optional, but must be supplied together.
    foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
        $value = $_POST[$field] ?? '';

        if (!is_string($value)) {
            $errors[] = "Invalid $field.";
            $data[$field] = null;
            continue;
        }

        $value = trim($value);
        $data[$field] = $value === '' ? null : $value;

        if ($value !== '' && (
            !preg_match('/^-?\d{1,3}(?:\.\d{1,7})?$/D', $value) ||
            (float) $value < -$limit ||
            (float) $value > $limit
        )) {
            $errors[] = "$field must be between -$limit and $limit, with up to 7 decimal places.";
        }
    }

    if (($data['latitude'] === null) !== ($data['longitude'] === null)) {
        $errors[] = 'Enter both coordinates, or leave both blank.';
    }

    // Stop here if any input is invalid.
    if ($errors) {
        http_response_code(422);

        echo '<h2>Please correct the following</h2>';

        foreach ($errors as $error) {
            echo '<p>'
                . htmlspecialchars($error, ENT_QUOTES, 'UTF-8')
                . '</p>';
        }

        echo '<p>Use your browser Back button to return to the form.</p>';
        exit;
    }

    // 6. Store unknown optional text as NULL.
    foreach ([
        'opening_times',
        'travel_tips',
        'facilities',
        'safety_information',
        'visitor_suitability'
    ] as $field) {
        if ($data[$field] === '') {
            $data[$field] = null;
        }
    }

// No image is also allowed.
$imagePath = null;
$uploadDestination = null;

if (isset($_FILES['image'])) {
    $image = $_FILES['image'];

    if (
        !isset($image['error']) ||
        !is_int($image['error'])
    ) {
        http_response_code(400);
        exit('Invalid image upload.');
    }

    if ($image['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($image['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            exit('Image upload failed. Try a smaller image.');
        }

        $temporaryFile = $image['tmp_name'];

        if (!is_uploaded_file($temporaryFile)) {
            http_response_code(400);
            exit('Invalid uploaded file.');
        }

        // Maximum size: 2 MB.
        $fileSize = filesize($temporaryFile);

        if ($fileSize === false || $fileSize > 2 * 1024 * 1024) {
            http_response_code(400);
            exit('The image must be 2 MB or smaller.');
        }

        // Check the actual file content, not just its filename.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($temporaryFile);

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $imageInfo = @getimagesize($temporaryFile);

        if (
            !isset($allowedTypes[$mimeType]) ||
            $imageInfo === false ||
            ($imageInfo['mime'] ?? '') !== $mimeType
        ) {
            http_response_code(400);
            exit('Please upload a valid JPG, PNG or WebP image.');
        }

        $uploadFolder = __DIR__ . '/uploads/places';

        if (!is_dir($uploadFolder) || !is_writable($uploadFolder)) {
            http_response_code(500);
            exit('The uploads/places folder is missing or not writable.');
        }

        // Generate a unique filename.
        $filename = bin2hex(random_bytes(16))
            . '.'
            . $allowedTypes[$mimeType];

        $uploadDestination = $uploadFolder . '/' . $filename;

        if (!move_uploaded_file($temporaryFile, $uploadDestination)) {
            http_response_code(500);
            exit('Unable to save the image.');
        }

        $imagePath = 'uploads/places/' . $filename;
    }
}

$data['image_path'] = $imagePath;

    $data['category_id'] = $categoryId;
    $data['distance_km'] = $distance;

    // 7. Insert a new place using a prepared statement.
    $sql = 'INSERT INTO places (
                category_id,
                name,
                description,
                distance_km,
                opening_times,
                travel_tips,
                facilities,
                safety_information,
                visitor_suitability,
                latitude,
longitude,
image_path
            ) VALUES (
                :category_id,
                :name,
                :description,
                :distance_km,
                :opening_times,
                :travel_tips,
                :facilities,
                :safety_information,
                :visitor_suitability,
                :latitude,
:longitude,
:image_path
            )';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
    } catch (PDOException $e) {
    if ($uploadDestination !== null && is_file($uploadDestination)) {
        unlink($uploadDestination);
    }

    error_log('Add place failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to save the place. Please try again.');
}

    // 8. Prevent reuse of this submitted form and redirect.
    unset($_SESSION['add_place_csrf']);

    header('Location: manage-places.php', true, 303);
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Place</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
 <link
  href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700&display=swap"
  rel="stylesheet"
>
  <link rel="stylesheet" href="css/style.css?v=2">
</head>
<body>
  <div class="admin-shell">
    <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>

    <main class="admin-main">
      <h1>Add New Place</h1>
     
  <form
  class="side-card form-grid"
  action="add-place.php"
  method="POST"
  enctype="multipart/form-data"
  style="margin-top:20px;"
>
<input
  type="hidden"
  name="csrf_token"
  value="<?= htmlspecialchars(
      $_SESSION['add_place_csrf'],
      ENT_QUOTES,
      'UTF-8'
  ) ?>"
>


        <div class="form-group">
          <label for="name">Place Name</label>
          <input
  id="name"
  name="name"
  type="text"
  placeholder="Place Name"
  maxlength="150"
  required

  
  
>
        </div>
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category_id" required>
  <option value="" selected disabled>Select a category</option>

  <?php foreach ($categories as $category): ?>
    <option value="<?= (int) $category['category_id'] ?>">
      <?= htmlspecialchars(
          $category['category_name'],
          ENT_QUOTES,
          'UTF-8'
      ) ?>
    </option>
  <?php endforeach; ?>
</select>
        </div>
        <div class="form-group full">
          <label for="description">Description</label>
          <textarea
  id="description"
  name="description"
  placeholder="Place description"
  maxlength="5000"
></textarea>
        </div>
        <div class="form-group">
          <label for="distance">Road Distance from Home (km)</label>
          <input
  id="distance"
  name="distance_km"
  type="number"
  placeholder="Example: 9.20"
  min="0"
  max="999.99"
  step="0.01"
  required
>
        </div>
        <div class="form-group">
          <label for="opening">Opening Time</label>
          <input
  id="opening"
  name="opening_times"
  type="text"
  placeholder="Example: 8:00 AM - 5:00 PM"
  maxlength="150"
>
        </div>
        <div class="form-group">
          <label for="lat">Latitude</label>
          <input
  id="lat"
  name="latitude"
  type="number"
  placeholder="Latitude"
  min="-90"
  max="90"
  step="0.0000001"
>
        </div>
        <div class="form-group">
          <label for="lng">Longitude</label>
          <input
  id="lng"
  name="longitude"
  type="number"
  placeholder="Longitude"
  min="-180"
  max="180"
  step="0.0000001"
>
        </div>
        <div class="form-group full">
          <label for="tips">Travel Tips</label>
          <textarea
  id="tips"
  name="travel_tips"
  placeholder="Travel tips"
  maxlength="5000"
></textarea>
        </div>
        <div class="form-group full">
          <label for="facilities">Facilities</label>
          <textarea
  id="facilities"
  name="facilities"
  placeholder="Available facilities"
  maxlength="5000"
></textarea>
        </div>
        <div class="form-group full">
          <label for="safety">Safety Information</label>
          <textarea
  id="safety"
  name="safety_information"
  placeholder="Safety information"
  maxlength="5000"
></textarea>
        </div>
        <div class="form-group full">
          <label for="suitability">Visitor Suitability</label>
          <input
  id="suitability"
  name="visitor_suitability"
  type="text"
  placeholder="Example: Suitable for families"
  maxlength="255"
>
        </div>
        <div class="form-group full">
          <label for="image">Image</label>
          <input
  id="image"
  name="image"
  type="file"
  accept="image/jpeg,image/png,image/webp"
>
<p class="muted">
  Optional: choose a JPG, PNG or WebP image, up to 2 MB.
</p>
        </div>
        <div class="form-group full">
          <button class="btn btn-primary" type="submit">Save Place</button>
        </div>
      </form>
    </main>
  </div>
  <script src="js/script.js"></script>
</body>
</html>
