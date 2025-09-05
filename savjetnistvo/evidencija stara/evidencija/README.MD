# Evidencija

WordPress plugin for managing care home locations and residents.

## Features

- Custom database tables for locations, rooms, users, family contacts, medical data, documents and audit log
- Custom user roles and capabilities ("Administracija Doma", "Medicinsko Osoblje")
- Admin pages for managing locations, rooms and users
- Audit log for actions performed in the plugin
- Daily e‑mail notifications about scheduled arrivals
- Reports (active users, occupancy, arrivals/departures, demographics, medical stats)
- Settings page to configure notification email and days prior to arrival

## Installation

1. Copy the `evidencija` directory into your WordPress `wp-content/plugins` folder.
2. Activate the plugin through the WordPress admin interface.
3. On activation the plugin creates its tables and schedules a daily cron event.
   Make sure WP‑Cron is running or set up a real system cron to trigger it.

## Usage

- Manage locations and rooms under **Evidencija → Lokacije Domova**.
- Manage residents under **Evidencija → Svi Korisnici**.
- Configure email notifications under **Evidencija → Postavke**.
- View reports under **Evidencija → Izvješća**.

## Development

This repository currently does not include unit tests. A stub class (`class-evidencija-reports.php`) and `uninstall.php` are empty.
Pull requests are welcome.