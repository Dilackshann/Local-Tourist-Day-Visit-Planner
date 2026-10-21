# Bogo DayTrails
## Local Tourist Day-Visit Planner and Information System

A web application that helps visitors explore places around
Bogawantalawa and create a simple one-day visit plan.

Developed as a BIT undergraduate campus project.

## Features

- Browse local places and filter by category.
- View descriptions, photos, distances and opening information.
- Read travel tips, facilities, safety and visitor suitability.
- View place locations using OpenStreetMap.
- Add, remove and reorder places in a visit plan.
- Save visit plans and print an itinerary.
- Send messages through the contact form.
- Manage places, photos and messages through the admin panel.

Booking, payments and public account registration are outside
the scope of this project.

## Technologies

- HTML, CSS and JavaScript
- PHP with PDO
- MySQL / MariaDB
- XAMPP
- OpenStreetMap

## Local Setup

These steps are for a fresh installation.

1. Install XAMPP and start Apache and MySQL.
2. Copy the project folder into:

   C:\xampp\htdocs\Local-Tourist-Day-Visit-Planner

3. Open http://localhost/phpmyadmin/
4. Create a database named tourist_planner using utf8mb4.
5. Select that database and import these files in order:

   - database/schema.sql
   - database/seed.sql

6. Copy config/database.example.php to config/database.php.
   Update the host, database name, username and password
   to match your local MySQL configuration.

7. Keep the images and uploads folders in the project.
   The SQL files contain image paths, not the image files.

8. Open the website:

   http://localhost/Local-Tourist-Day-Visit-Planner/

Do not open index.html by double-clicking it.
PHP and database features require the Apache server.

Do not import the SQL files again into an existing populated
database without first backing it up.

## Administrator Access

Open admin-login.html through localhost, or use the Admin Login
link in the website footer.

Use the administrator account configured in the local database.

The supplied schema and seed files do not include an administrator
account. A fresh installation requires administrator provisioning
before the admin panel can be used.

There is no public administrator registration page.

## Visit Plans

Visit plans are associated with a browser cookie and stored in
the database. Use the same browser to return to a saved plan.
Clearing the cookie or changing browsers may prevent access to
the previous plan.

## Distance Information

Place distances represent road distances from the project's
reference home location. They are not distances between successive
stops and should not be treated as a total itinerary distance.

## External Services

An internet connection is needed for online maps and Google Fonts.

## Current Limitations

- The Chair-Shaped Large Stone photograph is pending.
- Social media links are demonstration links.
- Opening times and access conditions should be confirmed before travel.