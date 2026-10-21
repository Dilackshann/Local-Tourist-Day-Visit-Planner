<?php

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$sql = 'SELECT
            places.place_id AS id,
            places.name,
            categories.category_name AS category,
            places.description,
            places.distance_km AS distance,
            places.opening_times AS opening,
            places.travel_tips AS tips,
            places.facilities,
            places.safety_information AS safety,
            places.visitor_suitability AS suitability,
            places.latitude AS lat,
            places.longitude AS lng,
            places.image_path AS image
        FROM places
        INNER JOIN categories
            ON places.category_id = categories.category_id
        ORDER BY places.place_id';

$stmt = $pdo->query($sql);
$places = $stmt->fetchAll();


// Get gallery photos in their display order.
$galleryStmt = $pdo->query(
    'SELECT place_id, image_path
     FROM place_images
     ORDER BY place_id, sort_order, image_id'
);

$galleryByPlace = [];

foreach ($galleryStmt->fetchAll(PDO::FETCH_ASSOC) as $photo) {
    $placeId = (int) $photo['place_id'];
    $galleryByPlace[$placeId][] = $photo['image_path'];
}

// Attach each place's gallery to its existing details.
foreach ($places as &$place) {
    $place['gallery'] = $galleryByPlace[(int) $place['id']] ?? [];
}
unset($place);

echo json_encode(
    $places,
    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
);