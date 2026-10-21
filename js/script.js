let PLACES = [];



function escapeHTML(value) {
  return String(value ?? "").replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;"
  })[character]);
}

const PLAN_KEY = "bogawantalawaVisitPlan";

let currentPlan = [];
let planReady = false;
let planSaving = false;
let planCsrf = "";

function getPlan() {
  return [...currentPlan];
}

async function requestPlan(placeIds) {
  const options = {
    credentials: "same-origin",
    cache: "no-store"
  };

  if (placeIds !== undefined) {
    options.method = "POST";
    options.headers = {
      "Content-Type": "application/json",
      "X-CSRF-Token": planCsrf
    };
    options.body = JSON.stringify({
      place_ids: placeIds
    });
  }

  const response = await fetch("api/visit-plan.php", options);

  let result;

  try {
    result = await response.json();
  } catch {
    throw new Error("Unexpected server response. Please reload.");
  }

  if (!response.ok || result.success !== true) {
    throw new Error(result.message || "Unable to access your plan.");
  }

  if (!Array.isArray(result.place_ids)) {
    throw new Error("Invalid plan response. Please reload.");
  }

  return result;
}

async function loadVisitPlan() {
  planReady = false;

  try {
    let result = await requestPlan();
    planCsrf = result.csrf_token;

    /*
     * First-time migration:
     * copy the existing browser plan only when no DB plan exists.
     */
    if (!result.exists) {
      let oldPlan = [];

      try {
        const stored = JSON.parse(
          localStorage.getItem(PLAN_KEY) || "[]"
        );

        if (Array.isArray(stored)) {
          const availableIds = new Set(
            PLACES.map((place) => String(place.id))
          );

          oldPlan = [...new Set(stored.map(String))]
            .filter((id) => availableIds.has(id));
        }
      } catch {
        oldPlan = [];
      }

      if (oldPlan.length) {
        result = await requestPlan(oldPlan);
      }
    }

    currentPlan = result.place_ids.map(String);
    planReady = true;

    // Remove the old copy only after a successful load/migration.
    try {
      localStorage.removeItem(PLAN_KEY);
    } catch {
      // Browser storage may be unavailable.
    }

    renderVisitPlan();
    updatePlanCount();
  } catch (error) {
    console.error(error);
    showToast(error.message || "Unable to load your visit plan.");

    const list = document.querySelector("#plan-list");

    if (list) {
      list.textContent =
        "Unable to load your saved plan. Please refresh and try again.";
    }

    document.querySelectorAll("[data-plan-count]").forEach((el) => {
      el.hidden = true;
    });
  }
}

async function savePlan(plan) {
  if (!planReady) {
    showToast("Your plan is not ready. Please reload the page.");
    return false;
  }

  if (planSaving) {
    showToast("Please wait until your plan finishes saving.");
    return false;
  }

  planSaving = true;

  try {
    const result = await requestPlan(plan);

    // Update the screen only after the database confirms the save.
    currentPlan = result.place_ids.map(String);

    renderVisitPlan();
    updatePlanCount();

    return true;
  } catch (error) {
    console.error(error);
    showToast(error.message || "Unable to save. Please try again.");
    return false;
  } finally {
    planSaving = false;
  }
}

function showToast(message) {
  let toast = document.querySelector(".toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "toast";
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.add("show");
  setTimeout(() => toast.classList.remove("show"), 2200);
}

async function addToPlan(id) {
  id = String(id);

  if (!planReady) {
    showToast("Please wait for your plan to load, or reload the page.");
    return;
  }

  const plan = getPlan();

  if (plan.includes(id)) {
    showToast("This place is already in your visit plan.");
    return;
  }

  if (!PLACES.some((place) => String(place.id) === id)) {
    showToast("This place is no longer available.");
    return;
  }

  plan.push(id);

  if (await savePlan(plan)) {
    showToast("Added to your saved visit plan.");
  }
}

async function removeFromPlan(id) {
  const plan = getPlan().filter((item) => item !== String(id));

  if (await savePlan(plan)) {
    showToast("Removed from your saved visit plan.");
  }
}

async function movePlace(id, direction) {
  const plan = getPlan();
  const index = plan.indexOf(String(id));
  const next = index + direction;

  if (index < 0 || next < 0 || next >= plan.length) return;

  [plan[index], plan[next]] = [plan[next], plan[index]];

  if (await savePlan(plan)) {
    showToast("Visit order saved.");
  }
}

async function clearPlan() {
  if (await savePlan([])) {
    showToast("Visit plan cleared.");
  }
}

function printPlan() {
  window.print();
}

function updatePlanCount() {
  const count = getPlan().length;

  document.querySelectorAll("[data-plan-count]").forEach((el) => {
    el.textContent = count;
    el.hidden = count === 0;
    el.setAttribute("aria-label", `${count} places in your plan`);
  });
}

function categoryClass(category) {
  const classes = {
    Nature: "nature",
    Religious: "religious",
    Cultural: "cultural",
    Sightseeing: "sightseeing",
    Accommodation: "accommodation"
  };

  return classes[category] || "other";
}

function placeCard(place) {
  return `
    <article class="card" data-category="${escapeHTML(place.category)}">
      <div class="card-media">
        <img src="${escapeHTML(place.image)}" alt="${escapeHTML(place.name)}">
        <span class="badge ${categoryClass(place.category)}">${escapeHTML(place.category)}</span>
      </div>
      <div class="card-body">
        <h3>${escapeHTML(place.name)}</h3>
        <p class="muted">${escapeHTML(place.description)}</p>
        <div class="meta">
          <span>Distance: ${place.distance} km</span>
        </div>
        <div class="card-actions">
          <a class="btn btn-primary btn-small" href="place-details.html?id=${escapeHTML(place.id)}">View Details</a>
          <button class="btn btn-outline btn-small" data-add="${escapeHTML(place.id)}">Add to Plan</button>
        </div>
      </div>
    </article>
  `;
}

function renderPlaceGrid(target, filter) 
{
  const grid = document.querySelector(target);

  if (!grid) return;

 const firstIds = ["11", "12", "13"];
const lastIds = ["2", "3", "4"];

const getPriority = (id) => {
  id = String(id);

  const firstIndex = firstIds.indexOf(id);
  if (firstIndex !== -1) return firstIndex;

  const lastIndex = lastIds.indexOf(id);
  if (lastIndex !== -1) return 100 + lastIndex;

  return 50;
};

const orderedPlaces = [...PLACES].sort(
  (a, b) => getPriority(a.id) - getPriority(b.id)
);

const list = filter && filter !== "All"
  ? orderedPlaces.filter((place) => place.category === filter)
  : orderedPlaces;

  if (list.length === 0) {
    grid.innerHTML = `
      <div class="empty-state" style="grid-column: 1 / -1;">
        <h3> No places available </h3>
        <p class="muted">
          No places available in this category. Please try another category.
        </p>
      </div>
    `;

    return;
  }

  grid.innerHTML = list.map(placeCard).join("");
}


function renderPlaceMap(place) {
  const parseCoordinate = (value) => {
    if (
      value === null ||
      value === undefined ||
      String(value).trim() === ""
    ) {
      return NaN;
    }

    return Number(value);
  };

  const lat = parseCoordinate(place.lat);
  const lng = parseCoordinate(place.lng);

  const valid =
    Number.isFinite(lat) &&
    Number.isFinite(lng) &&
    lat >= -90 &&
    lat <= 90 &&
    lng >= -180 &&
    lng <= 180;

  if (!valid) {
    return `
      <section class="place-map">
        <h3>Location</h3>
        <p class="muted">
          Map location has not been added for this place yet.
        </p>
      </section>
    `;
  }

  const bbox = [
    Math.max(-180, lng - 0.012),
    Math.max(-90, lat - 0.008),
    Math.min(180, lng + 0.012),
    Math.min(90, lat + 0.008)
  ].join(",");

  const embedUrl =
    "https://www.openstreetmap.org/export/embed.html" +
    "?bbox=" + encodeURIComponent(bbox) +
    "&layer=mapnik" +
    "&marker=" + encodeURIComponent(`${lat},${lng}`);

  const fullMapUrl =
    `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lng}` +
    `#map=16/${lat}/${lng}`;

  return `
    <section class="place-map">
      <h3>Location</h3>

      <iframe
        class="place-map-frame"
        title="Selected place location map"
        src="${embedUrl}"
        loading="lazy"
      ></iframe>

      <a
        class="btn btn-outline btn-small"
        href="${fullMapUrl}"
        target="_blank"
        rel="noopener noreferrer"
      >
        Open larger map
      </a>
    </section>
  `;
}

function renderPlaceDetails() {
  const root = document.querySelector("#place-details");
  if (!root) return;
const id = new URLSearchParams(window.location.search).get("id");

const place = PLACES.find(
  (item) => String(item.id) === id
);

if (!place) {
  root.innerHTML = `
    <div class="empty-state">
      <h2>Place not found</h2>
      <p class="muted">
        This place may have been removed or the link is incorrect.
      </p>
      <a class="btn btn-primary" href="places.html">
        Explore Places
      </a>
    </div>
  `;

  return;
}
  root.innerHTML = `



    <div id="place-slider" class="place-slider"
         role="region" aria-label="Place photos">
    </div>


    <aside class="side-card">
      <span class="badge ${categoryClass(place.category)}">${escapeHTML(place.category)}</span>
      <h1 style="font-family:var(--font-display);font-size:2.2rem;margin:12px 0;">${escapeHTML(place.name)}</h1>
      <p class="muted">${escapeHTML(place.description)}</p>
      <div class="info-grid">
        <div class="info-box"><span>Distance</span>${place.distance} km</div>
        <div class="info-box"><span>Opening time</span>${escapeHTML(place.opening)}</div>
        <div class="info-box"><span>Latitude</span>${escapeHTML(place.lat)}</div>
        <div class="info-box"><span>Longitude</span>${escapeHTML(place.lng)}</div>
      </div>
      <button class="btn btn-primary" data-add="${escapeHTML(place.id)}">Add to Visit Plan</button>
      <a class="btn btn-outline" href="places.html" style="margin-top:10px;">Back to Places</a>
    </aside>
    <div class="info-panel">
      <h2>Visitor information</h2>
      <p><strong>Facilities:</strong> ${escapeHTML(place.facilities)}</p>
      <p><strong>Travel tips:</strong> ${escapeHTML(place.tips)}</p>
      <p><strong>Safety information:</strong> ${escapeHTML(place.safety)}</p>
      <p><strong>Visitor suitability:</strong> ${escapeHTML(place.suitability)}</p>
      ${renderPlaceMap(place)}
    </div>
  `;
}

function renderVisitPlan() {
  const list = document.querySelector("#plan-list");
  if (!list) return;
  const plan = getPlan();
  const selected = plan.map((id) => PLACES.find((place) => place.id === id)).filter(Boolean);
  if (!selected.length) {
    list.innerHTML = `
      <div class="empty-state">
        <h3>Your plan is empty</h3>
        <p class="muted">Browse places and tap Add to Plan to build a one-day visit.</p>
        <a class="btn btn-primary" href="places.html">Explore Places</a>
      </div>
    `;
  } else {
    list.innerHTML = selected.map((place, index) => `
      <article class="plan-item">
        <img src="${escapeHTML(place.image)}" alt="">
        <div>
          <span class="order">${index + 1}</span>
          <strong>${escapeHTML(place.name)}</strong>
          <p class="muted">${escapeHTML(place.category)} · ${place.distance} km</p>
        </div>
        <div class="item-actions">
          <button class="btn btn-outline btn-small" data-up="${escapeHTML(place.id)}">Up</button>
          <button class="btn btn-outline btn-small" data-down="${escapeHTML(place.id)}">Down</button>
          <button class="btn btn-danger btn-small" data-remove="${escapeHTML(place.id)}">Remove</button>
        </div>
      </article>
    `).join("");
  }

const setSummaryText = (selector, value) => {
  const element = document.querySelector(selector);

  if (element) {
    element.textContent = value;
  }
};

setSummaryText("#total-places", selected.length);

const categories = [
  ...new Set(
    selected
      .map((place) => place.category)
      .filter(Boolean)
  )
];

setSummaryText(
  "#plan-categories",
  categories.length ? categories.join(", ") : "—"
);

const placesWithDistance = selected.filter((place) => {
  return (
    place.distance !== null &&
    place.distance !== undefined &&
    String(place.distance).trim() !== "" &&
    Number.isFinite(Number(place.distance)) &&
    Number(place.distance) >= 0
  );
});

if (placesWithDistance.length) {
  const distances = placesWithDistance.map(
    (place) => Number(place.distance)
  );

  const nearestDistance = Math.min(...distances);
  const farthestDistance = Math.max(...distances);

  const describePlaces = (distance) => {
    const names = placesWithDistance
      .filter((place) => Number(place.distance) === distance)
      .map((place) => place.name)
      .join(", ");

    return `${names} · ${Number(distance.toFixed(2))} km`;
  };

  setSummaryText(
    "#plan-nearest",
    describePlaces(nearestDistance)
  );

  setSummaryText(
    "#plan-farthest",
    describePlaces(farthestDistance)
  );
} else {
  setSummaryText("#plan-nearest", "—");
  setSummaryText("#plan-farthest", "—");
}
} // renderVisitPlan function முடிவு
function setupFilters() {
  const grid = document.querySelector("#places-grid");
  const filters = document.querySelector(".filters");

  if (!grid || !filters) return;

 const categories = [
  "All",
  "Nature",
  "Religious",
  "Cultural",
  "Sightseeing",
  "Accommodation"
];

  // Build one consistent set of category buttons.
  filters.replaceChildren();

  categories.forEach((category) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = "filter-btn";
    button.dataset.filter = category;
    button.textContent = category;
    filters.appendChild(button);
  });

  function applyCategory(category) {
    const selected = categories.includes(category) ? category : "All";

    filters.querySelectorAll(".filter-btn").forEach((button) => {
      const active = button.dataset.filter === selected;
      button.classList.toggle("active", active);
      button.setAttribute("aria-pressed", String(active));
    });

    renderPlaceGrid("#places-grid", selected);
  }

  function readCategory() {
    const category = new URLSearchParams(window.location.search)
      .get("category");

    applyCategory(category || "All");
  }

  filters.addEventListener("click", (event) => {
    const button = event.target.closest(".filter-btn");
    if (!button) return;

    const category = button.dataset.filter;
    const url = new URL(window.location.href);

    if (category === "All") {
      url.searchParams.delete("category");
    } else {
      url.searchParams.set("category", category);
    }

    history.pushState(null, "", url);
    applyCategory(category);
  });

  window.addEventListener("popstate", readCategory);
  readCategory();
}

function setupNav() {
  const toggle = document.querySelector(".menu-toggle");
  const links = document.querySelector(".nav-links");

  if (!toggle || !links) return;

  let currentPage =
    window.location.pathname.split("/").pop() || "index.html";

  if (currentPage === "place-details.html") {
    currentPage = "places.html";
  }

  links.querySelectorAll("a").forEach((link) => {
    const active = link.getAttribute("href") === currentPage;

    link.classList.toggle("active", active);

    if (active) {
      link.setAttribute("aria-current", "page");
    } else {
      link.removeAttribute("aria-current");
    }
  });

  function setMenu(open) {
    links.classList.toggle("open", open);
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
  }

  toggle.addEventListener("click", () => {
    setMenu(!links.classList.contains("open"));
  });

  links.addEventListener("click", (event) => {
    if (event.target.closest("a")) setMenu(false);
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && links.classList.contains("open")) {
      setMenu(false);
      toggle.focus();
    }
  });
}

function setupClicks() {
  document.body.addEventListener("click", (event) => {
    const add = event.target.closest("[data-add]");
    const remove = event.target.closest("[data-remove]");
    const up = event.target.closest("[data-up]");
    const down = event.target.closest("[data-down]");
    if (add) addToPlan(add.dataset.add);
    if (remove) removeFromPlan(remove.dataset.remove);
    if (up) movePlace(up.dataset.up, -1);
    if (down) movePlace(down.dataset.down, 1);
  });
}



document.addEventListener("DOMContentLoaded", async () => {
  setupNav();
  setupClicks();
  
 

  try {
    const response = await fetch("api/places.php");

    if (!response.ok) {
      throw new Error("Could not load places.");
    }

    const data = await response.json();

    PLACES = data.map((place) => ({
      ...place,
      id: String(place.id),
      distance: Number(place.distance),
      opening: place.opening || "Not available",
      tips: place.tips || "Not available",
      facilities: place.facilities || "Not available",
      safety: place.safety || "No additional information available",
      suitability: place.suitability || "No additional information available",
      lat: place.lat ?? "Not available",
      lng: place.lng ?? "Not available"
    }));

  

   setupFilters();
    renderPlaceDetails();

     setupPlaceSlider();

await loadVisitPlan();
  } catch (error) {
    console.error(error);
    showToast("Unable to load places. Please refresh and try again.");
  }
});


function setupPlaceSlider() {
  const slider = document.querySelector("#place-slider");
  if (!slider) return;

  // Clean up an earlier slideshow if this function runs again.
  if (slider.cleanup) slider.cleanup();

  const id = new URLSearchParams(window.location.search).get("id");
  const place = PLACES.find((item) => String(item.id) === id);
  if (!place) return;

  const photos = [
    place.image,
    ...(Array.isArray(place.gallery) ? place.gallery.slice(0, 3) : [])
  ].filter((photo) => typeof photo === "string" && photo.trim() !== "");

  slider.replaceChildren();

  if (!photos.length) {
    slider.textContent = "No photos available.";
    return;
  }

  const track = document.createElement("div");
  track.className = "place-slider-track";

  photos.forEach((photo, index) => {
    const img = document.createElement("img");
    img.src = photo;
    img.alt = `${place.name} — photo ${index + 1}`;
    img.draggable = false;
    track.appendChild(img);
  });

  slider.appendChild(track);

  if (photos.length === 1) return;

  const makeButton = (className, label, text) => {
    const button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.textContent = text;
    slider.appendChild(button);
    return button;
  };

  const previous = makeButton("slider-arrow slider-prev", "Previous photo", "❮");
  const next = makeButton("slider-arrow slider-next", "Next photo", "❯");
  const pause = makeButton("slider-pause", "Pause slideshow", "Pause");

  const counter = document.createElement("span");
  counter.className = "slider-counter";
  slider.appendChild(counter);

  let current = 0;
  let timer = null;
  let hovered = false;
  let touchStart = null;
  let paused = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function show(index) {
    current = (index + photos.length) % photos.length;
    track.style.transform = `translateX(-${current * 100}%)`;
    counter.textContent = `${current + 1} / ${photos.length}`;

    [...track.children].forEach((img, index) => {
      img.setAttribute("aria-hidden", String(index !== current));
    });
  }

  function stop() {
    clearInterval(timer);
    timer = null;
  }

  function start() {
    stop();
    if (!paused && !hovered && !document.hidden &&
        !slider.contains(document.activeElement)) {
      timer = setInterval(() => show(current + 1), 4000);
    }
  }

  function move(direction) {
    show(current + direction);
    start();
  }

  function updatePauseButton() {
    pause.textContent = paused ? "Play" : "Pause";
    pause.setAttribute("aria-label", paused ? "Play slideshow" : "Pause slideshow");
  }

  previous.addEventListener("click", () => move(-1));
  next.addEventListener("click", () => move(1));

  pause.addEventListener("click", () => {
    paused = !paused;
    updatePauseButton();
    start();
  });

  slider.addEventListener("mouseenter", () => {
    hovered = true;
    stop();
  });

  slider.addEventListener("mouseleave", () => {
    hovered = false;
    start();
  });

  slider.addEventListener("focusin", stop);
  slider.addEventListener("focusout", () => setTimeout(start, 0));

  slider.addEventListener("keydown", (event) => {
    if (event.key === "ArrowLeft" || event.key === "ArrowRight") {
      event.preventDefault();
      move(event.key === "ArrowLeft" ? -1 : 1);
    }
  });

  slider.addEventListener("touchstart", (event) => {
    touchStart = {
      x: event.touches[0].clientX,
      y: event.touches[0].clientY
    };
    stop();
  }, { passive: true });

  slider.addEventListener("touchend", (event) => {
    if (!touchStart) return;

    const dx = event.changedTouches[0].clientX - touchStart.x;
    const dy = event.changedTouches[0].clientY - touchStart.y;

    if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
      show(current + (dx < 0 ? 1 : -1));
    }

    touchStart = null;
    start();
  }, { passive: true });

  slider.addEventListener("touchcancel", () => {
    touchStart = null;
    start();
  });

  document.addEventListener("visibilitychange", start);

  slider.cleanup = () => {
    stop();
    document.removeEventListener("visibilitychange", start);
  };

  show(0);
  updatePauseButton();
  start();
}

function setupHeaderScroll() {
  if (!document.body.classList.contains("home-page")) return;

  const header = document.querySelector(".site-header");
  if (!header) return;

  function updateHeader() {
    header.classList.toggle("is-scrolled", window.scrollY > 40);
  }

  window.addEventListener("scroll", updateHeader, { passive: true });
  window.addEventListener("pageshow", updateHeader);
  updateHeader();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", setupHeaderScroll);
} else {
  setupHeaderScroll();
}


window.addEventListener("beforeunload", (event) => {
  if (!planSaving) return;

  event.preventDefault();
  event.returnValue = "";
});