<?php
// Start/resume the administrator's login session before sending HTML.
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

$placeId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);
if (!$placeId) {
    header('Location: manage-places.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM places WHERE place_id = :id');
$stmt->execute(['id' => $placeId]);
$place = $stmt->fetch();
if (!$place) {
    http_response_code(404);
    exit('Place not found.');
}

$categories = $pdo->query(
    'SELECT category_id, category_name FROM categories ORDER BY category_name'
)->fetchAll();

// Escape database and form values whenever they are placed in HTML.
function escapeHtml($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// A CSRF token helps ensure updates originate from this session's form.
if (empty($_SESSION['edit_place_csrf'])) {
    $_SESSION['edit_place_csrf'] = bin2hex(random_bytes(32));
}

function saveGalleryPhoto(array $image): string
{
    if (
        !isset($image['error']) ||
        !is_int($image['error']) ||
        $image['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Gallery photo upload failed. Try a smaller image.'
        );
    }

    $temporaryFile = $image['tmp_name'] ?? '';

    if (!is_string($temporaryFile) || !is_uploaded_file($temporaryFile)) {
        throw new RuntimeException('Invalid gallery photo upload.');
    }

    $fileSize = filesize($temporaryFile);

    if ($fileSize === false || $fileSize > 2 * 1024 * 1024) {
        throw new RuntimeException(
            'Each gallery photo must be 2 MB or smaller.'
        );
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($temporaryFile);
    $imageInfo = @getimagesize($temporaryFile);

    if (
        !isset($allowedTypes[$mimeType]) ||
        $imageInfo === false ||
        ($imageInfo['mime'] ?? '') !== $mimeType
    ) {
        throw new RuntimeException(
            'Gallery photos must be valid JPG, PNG or WebP images.'
        );
    }

    $uploadFolder = __DIR__ . '/uploads/places';

    if (!is_dir($uploadFolder) || !is_writable($uploadFolder)) {
        throw new RuntimeException(
            'The uploads/places folder is missing or not writable.'
        );
    }

    $filename = bin2hex(random_bytes(16))
        . '.' . $allowedTypes[$mimeType];

    if (!move_uploaded_file($temporaryFile, $uploadFolder . '/' . $filename)) {
        throw new RuntimeException('Unable to save the gallery photo.');
    }

    return 'uploads/places/' . $filename;
}

$errors = [];
$success = '';


if (($_SESSION['updated_place_id'] ?? null) === $placeId) {
    $success = 'Place updated successfully.';
    unset($_SESSION['updated_place_id']);
}

$textFields = [
    'name' => ['Place Name', 150],
    'description' => ['Description', 5000],
    'opening_times' => ['Opening Time', 150],
    'travel_tips' => ['Travel Tips', 5000],
    'facilities' => ['Facilities', 5000],
    'safety_information' => ['Safety Information', 5000],
    'visitor_suitability' => ['Visitor Suitability', 255]
];
$fields = array_merge(array_keys($textFields),
    ['category_id', 'distance_km', 'latitude', 'longitude']);
$form = [];
foreach ($fields as $field) {
    $form[$field] = (string) ($place[$field] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['edit_place_csrf'], $token)) {
        http_response_code(403);
        exit('Your form session could not be verified. Reload the edit page and try again.');
    }

    foreach ($fields as $field) {
        if (!isset($_POST[$field]) || !is_string($_POST[$field])) {
            $errors[] = 'The form is incomplete or invalid. Reload the page and try again.';
            break;
        }
        $form[$field] = trim($_POST[$field]);
    }

    foreach ($textFields as $field => [$label, $limit]) {
        // Unicode-aware length validation, including Tamil text.
        $length = preg_match_all('/./us', $form[$field]);
        if ($length === false) {
            $errors[] = "$label contains invalid text.";
        } elseif ($length > $limit) {
            $errors[] = "$label must be $limit characters or fewer.";
        }
    }
    if ($form['name'] === '') {
        $errors[] = 'Place Name is required.';
    }

    $categoryId = filter_var($form['category_id'], FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]);
    $validCategoryIds = array_map('intval', array_column($categories, 'category_id'));
    if (!$categoryId || !in_array($categoryId, $validCategoryIds, true)) {
        $errors[] = 'Select a valid category.';
    }

    // DECIMAL(5,2) supports 0.00 to 999.99 for a non-negative distance.
    // Road distance is different from the project's 25 km geographic radius.
    if (!preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $form['distance_km'])) {
        $errors[] = 'Distance must be between 0 and 999.99 km, with up to 2 decimal places.';
    }

    foreach (['latitude' => 90, 'longitude' => 180] as $field => $limit) {
        $value = $form[$field];
        if ($value !== '' && (
            !preg_match('/^-?\d{1,3}(?:\.\d{1,7})?$/D', $value) ||
            (float) $value < -$limit || (float) $value > $limit
        )) {
            $errors[] = ucfirst($field) . " must be between -$limit and $limit, with up to 7 decimal places.";
        }
    }
    if (($form['latitude'] === '') !== ($form['longitude'] === '')) {
        $errors[] = 'Enter both latitude and longitude, or leave both blank.';
    }

    if (!$errors) {
        $params = ['id' => $placeId, 'category_id' => $categoryId,
            'distance_km' => $form['distance_km']];
        foreach ($textFields as $field => $rules) {
            // Name/description are NOT NULL columns. Other empty text fields can be NULL.
            $params[$field] = $form[$field] === '' && !in_array($field, ['name', 'description'], true)
                ? null : $form[$field];
        }
        $params['latitude'] = $form['latitude'] === '' ? null : $form['latitude'];
        $params['longitude'] = $form['longitude'] === '' ? null : $form['longitude'];

        // Keep the existing image unless a new one is uploaded.
$params['image_path'] = $place['image_path'];
$uploadDestination = null;

if (isset($_FILES['image'])) {
    $image = $_FILES['image'];

    if (!isset($image['error']) || !is_int($image['error'])) {
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

        $fileSize = filesize($temporaryFile);

        if ($fileSize === false || $fileSize > 2 * 1024 * 1024) {
            http_response_code(400);
            exit('The image must be 2 MB or smaller.');
        }

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

        $filename = bin2hex(random_bytes(16))
            . '.'
            . $allowedTypes[$mimeType];

        $uploadDestination = $uploadFolder . '/' . $filename;

        if (!move_uploaded_file($temporaryFile, $uploadDestination)) {
            http_response_code(500);
            exit('Unable to save the image.');
        }

        $params['image_path'] = 'uploads/places/' . $filename;
    }
}

          $galleryPaths = [];

        try {
            $pdo->beginTransaction();

            // Lock this place while checking and saving its gallery.
            $lock = $pdo->prepare(
                'SELECT place_id FROM places
                 WHERE place_id = :id FOR UPDATE'
            );
            $lock->execute(['id' => $placeId]);

            if (!$lock->fetchColumn()) {
                throw new RuntimeException('Place no longer exists.');
            }


            $removeIds = $_POST['remove_gallery'] ?? [];

            if (!is_array($removeIds) || count($removeIds) > 3) {
                throw new RuntimeException('Invalid photo selection.');
            }

            $validatedIds = [];

            foreach ($removeIds as $rawId) {
                if (!is_string($rawId)) {
                    throw new RuntimeException('Invalid photo selection.');
                }

                $imageId = filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                );

                if ($imageId === false) {
                    throw new RuntimeException('Invalid photo selection.');
                }

                $validatedIds[] = $imageId;
            }

            $validatedIds = array_unique($validatedIds);

            // Remove only photos belonging to the place being edited.
            $removePhoto = $pdo->prepare(
                'DELETE FROM place_images
                 WHERE image_id = :image_id AND place_id = :place_id'
            );

            foreach ($validatedIds as $imageId) {
                $removePhoto->execute([
                    'image_id' => $imageId,
                    'place_id' => $placeId
                ]);

                if ($removePhoto->rowCount() !== 1) {
                    throw new RuntimeException(
                        'A selected photo is no longer available. Reload and try again.'
                    );
                }
            }

            // Keep the remaining photo positions numbered from 1.
            $remaining = $pdo->prepare(
                'SELECT image_id FROM place_images
                 WHERE place_id = :id
                 ORDER BY sort_order, image_id'
            );
            $remaining->execute(['id' => $placeId]);
            $remainingIds = $remaining->fetchAll(PDO::FETCH_COLUMN);

            $reorder = $pdo->prepare(
                'UPDATE place_images SET sort_order = :position
                 WHERE image_id = :image_id AND place_id = :place_id'
            );

            foreach ($remainingIds as $index => $imageId) {
                $reorder->execute([
                    'position' => $index + 1,
                    'image_id' => $imageId,
                    'place_id' => $placeId
                ]);
            }

            $galleryFiles = [];

            if (isset($_FILES['gallery_images'])) {
                $gallery = $_FILES['gallery_images'];

                if (
                    !is_array($gallery) ||
                    !isset($gallery['error'], $gallery['tmp_name']) ||
                    !is_array($gallery['error']) ||
                    !is_array($gallery['tmp_name'])
                ) {
                    throw new RuntimeException('Invalid gallery upload.');
                }

                foreach ($gallery['error'] as $index => $uploadError) {
                    if (!is_int($uploadError)) {
                        throw new RuntimeException('Invalid gallery upload.');
                    }

                    if ($uploadError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    $galleryFiles[] = [
                        'error' => $uploadError,
                        'tmp_name' => $gallery['tmp_name'][$index] ?? ''
                    ];
                }
            }

            $galleryCheck = $pdo->prepare(
                'SELECT COUNT(*) AS total,
                        COALESCE(MAX(sort_order), 0) AS last_order
                 FROM place_images WHERE place_id = :id'
            );
            $galleryCheck->execute(['id' => $placeId]);
            $galleryState = $galleryCheck->fetch(PDO::FETCH_ASSOC);

            $existingCount = (int) $galleryState['total'];
            $nextOrder = (int) $galleryState['last_order'];

            if ($existingCount + count($galleryFiles) > 3) {
                throw new RuntimeException(
                    'Maximum 3 gallery photos per place. '
                    . 'Already saved: ' . $existingCount . '.'
                );
            }

            foreach ($galleryFiles as $galleryFile) {
                $galleryPaths[] = saveGalleryPhoto($galleryFile);
            }

            // Update this place, including the selected image path.
            $update = $pdo->prepare(
                'UPDATE places SET category_id = :category_id, name = :name,
                 description = :description, distance_km = :distance_km,
                 opening_times = :opening_times, travel_tips = :travel_tips,
                 facilities = :facilities, safety_information = :safety_information,
                 visitor_suitability = :visitor_suitability,
                 latitude = :latitude, longitude = :longitude,
image_path = :image_path
WHERE place_id = :id'
            );
            $update->execute($params);

            if ($galleryPaths) {
                $insertImage = $pdo->prepare(
                    'INSERT INTO place_images
                        (place_id, image_path, sort_order)
                     VALUES (:place_id, :image_path, :sort_order)'
                );

                foreach ($galleryPaths as $galleryPath) {
                    $nextOrder++;

                    $insertImage->execute([
                        'place_id' => $placeId,
                        'image_path' => $galleryPath,
                        'sort_order' => $nextOrder
                    ]);
                }
            }

            $pdo->commit();

            $_SESSION['updated_place_id'] = $placeId;
            // Redirect after POST so refreshing does not submit the update again.
            header('Location: edit-place.php?id=' . $placeId, true, 303);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            foreach ($galleryPaths as $galleryPath) {
                $savedFile = __DIR__ . '/' . $galleryPath;

                if (is_file($savedFile)) {
                    unlink($savedFile);
                }
            }

            if ($uploadDestination !== null && is_file($uploadDestination)) {
                unlink($uploadDestination);
            }

            error_log('Place update failed: ' . $e->getMessage());

            if ($e instanceof RuntimeException && !($e instanceof PDOException)) {
                http_response_code(422);
                $errors[] = $e->getMessage();
            } else {
                http_response_code(500);
                $errors[] = 'Unable to save changes. Please try again.';
            }
        }
    } else {
        http_response_code(422);
    }
}


$galleryList = $pdo->prepare(
    'SELECT image_id, image_path
     FROM place_images
     WHERE place_id = :id
     ORDER BY sort_order, image_id'
);
$galleryList->execute(['id' => $placeId]);
$currentGallery = $galleryList->fetchAll(PDO::FETCH_ASSOC);


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Place | Bogawantalawa Planner</title>
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
      <h1>Edit place</h1>
      <p class="muted">Update the selected place. Leave unknown optional information blank.</p>

      <?php if ($success !== ''): ?>
        <div role="status" class="side-card" style="margin-top:16px;border-left:4px solid #1f8a62;">
          <?= escapeHtml($success) ?>
        </div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div role="alert" class="side-card" style="margin-top:16px;border-left:4px solid #b23a2c;">
          <strong>Please correct the following:</strong>
          <ul style="padding-left:22px;">
            <?php foreach ($errors as $error): ?>
              <li><?= escapeHtml($error) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form
  class="side-card form-grid"
  method="POST"
  action="edit-place.php?id=<?= (int) $placeId ?>"
  enctype="multipart/form-data"
  style="margin-top:20px;"
>
          
        <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['edit_place_csrf']) ?>">

        <div class="form-group">
          <label for="name">Place Name</label>
          <input id="name" name="name" type="text" maxlength="150" required value="<?= escapeHtml($form['name']) ?>">
        </div>
        <div class="form-group">
          <label for="category">Category</label>
          <select id="category" name="category_id" required>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['category_id'] ?>" <?= (int) $category['category_id'] === (int) $form['category_id'] ? 'selected' : '' ?>><?= escapeHtml($category['category_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full">
          <label for="description">Description</label>
          <textarea id="description" name="description" maxlength="5000"><?= escapeHtml($form['description']) ?></textarea>
        </div>
        <div class="form-group">
          <label for="distance">Road Distance from Home (km)</label>
          <input id="distance" name="distance_km" type="number" min="0" max="999.99" step="0.01" required value="<?= escapeHtml($form['distance_km']) ?>">
        </div>
        <div class="form-group">
          <label for="opening">Opening Time</label>
          <input id="opening" name="opening_times" type="text" maxlength="150" value="<?= escapeHtml($form['opening_times']) ?>">
        </div>
        <div class="form-group">
          <label for="lat">Latitude</label>
          <input id="lat" name="latitude" type="number" min="-90" max="90" step="0.0000001" value="<?= escapeHtml($form['latitude']) ?>">
        </div>
        <div class="form-group">
          <label for="lng">Longitude</label>
          <input id="lng" name="longitude" type="number" min="-180" max="180" step="0.0000001" value="<?= escapeHtml($form['longitude']) ?>">
        </div>
        <div class="form-group full">
          <label for="tips">Travel Tips</label>
          <textarea id="tips" name="travel_tips" maxlength="5000"><?= escapeHtml($form['travel_tips']) ?></textarea>
        </div>
        <div class="form-group full">
          <label for="facilities">Facilities</label>
          <textarea id="facilities" name="facilities" maxlength="5000"><?= escapeHtml($form['facilities']) ?></textarea>
        </div>
        <div class="form-group full">
          <label for="safety">Safety Information</label>
          <textarea id="safety" name="safety_information" maxlength="5000"><?= escapeHtml($form['safety_information']) ?></textarea>
        </div>
        <div class="form-group full">
          <label for="suitability">Visitor Suitability</label>
          <input id="suitability" name="visitor_suitability" type="text" maxlength="255" value="<?= escapeHtml($form['visitor_suitability']) ?>">
        </div>
        <div class="form-group full">
  <label for="current-image">Current Image Path</label>

  <input
    id="current-image"
    type="text"
    readonly
    value="<?= escapeHtml($place['image_path']) ?>"
    placeholder="No image assigned"
  >

  <label for="image">Choose a New Image</label>

  <input
    id="image"
    name="image"
    type="file"
    accept="image/jpeg,image/png,image/webp"
  >

  <p class="muted">
    JPG, PNG or WebP, up to 2 MB.
    Leave this empty to keep the current image.
  </p>
</div>

<div class="form-group full">
  <strong>Current Gallery Photos</strong>

  <?php if (!$currentGallery): ?>
    <p class="muted">No gallery photos added yet.</p>
  <?php else: ?>
    <p class="muted">
      Tick the photos you want to remove, then click Update Place.
      You can also choose replacement photos below.
    </p>

    <div style="display:flex;flex-wrap:wrap;gap:16px;">
      <?php foreach ($currentGallery as $photo): ?>
        <div style="width:180px;">
          <img
            src="<?= escapeHtml($photo['image_path']) ?>"
            alt="Current gallery photo"
            style="display:block;width:180px;height:120px;
                   object-fit:cover;border-radius:12px;"
          >

          <label style="display:flex;align-items:center;
                        gap:8px;margin-top:10px;cursor:pointer;">
            <input
              type="checkbox"
              name="remove_gallery[]"
              value="<?= (int) $photo['image_id'] ?>"
              style="width:auto;min-width:0;margin:0;"
            >
            Remove this photo
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="form-group full">
  <label for="gallery-images">Additional Gallery Photos</label>

  <input
    id="gallery-images"
    name="gallery_images[]"
    type="file"
    accept="image/jpeg,image/png,image/webp"
    multiple
  >

  <p class="muted">
    Choose up to 3 additional photos.
    Each photo must be JPG, PNG or WebP, up to 2 MB.
  </p>
</div>

<div class="form-group full">
          <button class="btn btn-primary" type="submit">Update Place</button>
          <a class="btn btn-outline" href="manage-places.php">Back to Manage Places</a>
        </div>
      </form>
    </main>
  </div>
</body>
</html>
