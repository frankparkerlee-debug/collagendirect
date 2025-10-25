# collagendirect
CollagenDirect Physician Portal

CollagenDirect is a PHP-based physician portal used by clinicians and administrators to manage orders and patients for CollagenDirect products.

This repository contains the public-facing portal, API endpoints, and admin tools. The project is intended to run on a standard LAMP/LEMP stack and is shipped as application code (no container or package manager is bundled).

Quick start
-----------

1. Install PHP (>=7.4 or 8.x), MySQL/MariaDB, and a web server (Apache or nginx). For quick local testing you can use PHP's built-in server.

2. From the project root (this directory), start the PHP built-in server:

```bash
php -S 0.0.0.0:8000 -t .
```

Then open http://localhost:8000 in your browser.

3. Create a MySQL database and import schema files found under `admin/sql/` and `api/sql/` as needed.

4. Configure database and environment settings in:

- `admin/config.php` (admin UI configuration)
- `api/lib/env.php` (API environment/database settings)

Security & sensitive files
--------------------------

- Do NOT commit secrets, API keys or .env files. If you have sensitive files, add them to `.gitignore` and remove them from history (use `git filter-repo` or the BFG repo cleaner) before sharing publicly.
- The `uploads/` directory contains user uploads and should generally be excluded from version control. It's already listed in `.gitignore`.

Project structure (high level)
-----------------------------

- `api/` — API endpoints and helpers
- `admin/` — admin UI and admin-only scripts
- `portal/` — main portal app and pages
- `uploads/` — uploaded documents and data (not tracked)
- `assets/` — images and static assets

Contributing
------------

If you want help cleaning this repository (removing sensitive history, splitting large uploads, adding CI), I can help with a step-by-step plan.

License
-------

Please add a LICENSE file if you intend to publish this repository under an open-source license.

Contact
-------

Owner: frankparkerlee-debug

--
Generated README: concise instructions for local testing and notes about sensitive data.
