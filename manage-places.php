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

if (empty($_SESSION['delete_place_csrf'])) {
    $_SESSION['delete_place_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/config/database.php';

$sql = 'SELECT
            places.place_id,
            places.name,
            places.distance_km,
            categories.category_name
        FROM places
        INNER JOIN categories
            ON places.category_id = categories.category_id
        ORDER BY places.place_id';

$stmt = $pdo->query($sql);
$places = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Places</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css?v=2">

<style>
  .manage-content {
    min-width: 0;
  }

  .manage-content .manage-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
  }

  .manage-content h1 {
    margin: 0 0 6px;
    font-family: var(--font-body);
    font-size: 28px;
    line-height: 1.25;
  }

  .manage-heading p,
  .manage-results {
    margin: 0;
    color: var(--muted);
    font-size: 13px;
  }

  .manage-heading .btn {
    padding: 11px 18px;
    font-size: 13px;
  }

  .manage-tools {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 220px auto;
    align-items: end;
    gap: 14px;
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
  }

  .manage-tools label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 700;
    color: var(--green-800);
  }

  .manage-tools input,
  .manage-tools select {
    box-sizing: border-box;
    width: 100%;
    height: 42px;
    margin: 0;
    padding: 0 12px;
    border: 1px solid #cbdad2;
    border-radius: 8px;
    background: #fff;
    color: #203c32;
    font: inherit;
    font-size: 14px;
  }

  .manage-reset {
    height: 42px;
    padding: 0 16px;
    border: 1px solid #cbdad2;
    border-radius: 8px;
    background: #f2f7f4;
    color: #245443;
    font: inherit;
    font-size: 13px;
    cursor: pointer;
  }

  .manage-results {
    margin: 16px 0 10px;
  }

  .manage-content .manage-table-wrap {
    margin: 0;
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: #fff;
    box-shadow: none;
  }

  .manage-table {
    width: 100%;
    min-width: 720px;
    border-collapse: collapse;
  }

  .manage-table th {
    padding: 13px 16px;
    background: #eaf3ee;
    color: #426354;
    font-size: 11px;
    letter-spacing: 0.04em;
    text-align: left;
  }

  .manage-table td {
    padding: 13px 16px;
    border-bottom: 1px solid #e4ece7;
    font-size: 13px;
    vertical-align: middle;
  }

  .manage-table tbody tr:last-child td {
    border-bottom: 0;
  }

  .manage-table tbody tr:hover {
    background: #f7faf8;
  }

  .manage-table tr[hidden] {
    display: none !important;
  }

  .manage-place-name {
    color: #173f31;
    font-weight: 700;
  }

  .manage-place-name:hover {
    text-decoration: underline;
  }

  .manage-category {
    display: inline-block;
    padding: 4px 9px;
    border-radius: 6px;
    background: #eef5f0;
    color: #426354;
    font-size: 12px;
  }

  .manage-distance {
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }

  .manage-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    white-space: nowrap;
  }

  .manage-actions form {
    margin: 0;
  }

  .manage-edit,
  .manage-view,
  .manage-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 36px;
    padding: 7px 12px;
    border: 1px solid transparent;
    border-radius: 7px;
    font: inherit;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
  }

  .manage-edit {
    background: #185542;
    color: #fff;
  }

  .manage-edit:hover {
    background: #103e30;
  }

  .manage-view {
    border-color: #d6e2da;
    background: #fff;
    color: #245443;
  }

  .manage-delete {
    background: #fff0ed;
    color: #ae3428;
  }

  .manage-content :is(a, button, input, select):focus-visible {
    outline: 3px solid #c58b16;
    outline-offset: 3px;
  }

  #no-place-results td {
    padding: 30px 16px;
    text-align: center;
    color: var(--muted);
  }

  @media (max-width: 700px) {
    .manage-tools {
      grid-template-columns: 1fr;
    }

    .manage-content h1 {
      font-size: 24px;
    }
  }
</style>


</head>
<body>
  <div class="admin-shell">

  <?php require __DIR__ . '/includes/admin-sidebar.php'; ?>

    <main class="admin-main manage-content">
  <div class="manage-heading">
    <div>
      <h1>Manage Places</h1>
      <p>Find a place and update its visitor information.</p>
    </div>

    <a class="btn btn-primary" href="add-place.php">
      + Add New Place
    </a>
  </div>

  <div class="manage-tools">
    <div>
      <label for="place-search">Search places</label>
      <input
        id="place-search"
        type="search"
        placeholder="Type a place name..."
        autocomplete="off"
      >
    </div>

    <div>
      <label for="category-filter">Category</label>
      <select id="category-filter">
        <option value="">All categories</option>

        <?php
        $filterCategories = array_unique(
            array_column($places, 'category_name')
        );
        sort($filterCategories);
        ?>

        <?php foreach ($filterCategories as $category): ?>
          <option value="<?= htmlspecialchars(
              $category,
              ENT_QUOTES,
              'UTF-8'
          ) ?>">
            <?= htmlspecialchars(
                $category,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <button id="reset-filters" class="manage-reset" type="button">
      Reset
    </button>
  </div>

  <p id="place-results" class="manage-results" role="status">
    <?= count($places) ?> places
  </p>

  <div class="table-wrap manage-table-wrap">
    <table class="manage-table">
      <thead>
        <tr>
          <th scope="col">Place name</th>
          <th scope="col">Category</th>
          <th scope="col">Distance from home</th>
          <th scope="col">Actions</th>
        </tr>
      </thead>

      <tbody>
        <?php foreach ($places as $place): ?>
          <tr
            data-place-row
            data-name="<?= htmlspecialchars(
                $place['name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            data-category="<?= htmlspecialchars(
                $place['category_name'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
          >
            <td>
              <a
                class="manage-place-name"
                href="edit-place.php?id=<?= (int) $place['place_id'] ?>"
              >
                <?= htmlspecialchars(
                    $place['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
              </a>
            </td>

            <td>
              <span class="manage-category">
                <?= htmlspecialchars(
                    $place['category_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
              </span>
            </td>

            <td class="manage-distance">
              <?= number_format(
                  (float) $place['distance_km'],
                  2
              ) ?> km
            </td>

            <td>
              <div class="manage-actions">
                <a
                  class="manage-edit"
                  href="edit-place.php?id=<?= (int) $place['place_id'] ?>"
                >Edit</a>

                <a
                  class="manage-view"
                  href="place-details.html?id=<?= (int) $place['place_id'] ?>"
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="View <?= htmlspecialchars(
                      $place['name'],
                      ENT_QUOTES,
                      'UTF-8'
                  ) ?> (opens in a new tab)"
                >View ↗</a>

                <form
                  action="api/delete-place.php"
                  method="POST"
                  onsubmit="return confirm('Delete this place permanently?');"
                >
                  <input
                    type="hidden"
                    name="place_id"
                    value="<?= (int) $place['place_id'] ?>"
                  >

                  <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['delete_place_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                  >

                  <button class="manage-delete" type="submit">
                    Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>

        <tr id="no-place-results" <?= $places ? 'hidden' : '' ?>>
          <td colspan="4">
            No places found. Try another name or category.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</main>
  </div>
  <script>
  (() => {
    const search = document.querySelector("#place-search");
    const category = document.querySelector("#category-filter");
    const reset = document.querySelector("#reset-filters");
    const result = document.querySelector("#place-results");
    const empty = document.querySelector("#no-place-results");
    const rows = [...document.querySelectorAll("[data-place-row]")];

    function filterPlaces() {
      const query = search.value.trim().toLocaleLowerCase();
      const selectedCategory = category.value;
      let visible = 0;

      rows.forEach((row) => {
        const matchesName = row.dataset.name
          .toLocaleLowerCase()
          .includes(query);

        const matchesCategory =
          !selectedCategory ||
          row.dataset.category === selectedCategory;

        const show = matchesName && matchesCategory;

        row.hidden = !show;

        if (show) visible++;
      });

      result.textContent =
        `Showing ${visible} of ${rows.length} places`;

      empty.hidden = visible !== 0;
    }

    search.addEventListener("input", filterPlaces);
    category.addEventListener("change", filterPlaces);

    reset.addEventListener("click", () => {
      search.value = "";
      category.value = "";
      filterPlaces();
      search.focus();
    });

    filterPlaces();
  })();
  
</script>
</body>
</html>
