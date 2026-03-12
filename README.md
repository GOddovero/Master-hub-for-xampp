# Master Hub

Master Hub is a local dashboard for listing and quickly opening the projects you keep inside XAMPP's `htdocs`. It detects folders, lets you hide system entries, reads metadata from a JSON file, and assigns custom logos to each project.

## Requirements

- XAMPP with Apache running.
- PHP 8 or later recommended.
- The project located at `C:/xampp/htdocs/master-hub`.

## Installation

1. Copy this repository into `htdocs` using the `master-hub` folder name.
2. Open `http://localhost/master-hub/`.
3. If you want to customize names, groups, descriptions, or logos, create your local `projects.json` from `projects.example.json`.

## Redirect From The htdocs Root

If you want `http://localhost/` to open this hub directly, replace the contents of `C:/xampp/htdocs/index.php` with the following:

```php
<?php
// Redirect "/" to the local project hub.
// Keep XAMPP's dashboard reachable at "/dashboard/".
if (!empty($_SERVER['HTTPS']) && ('on' === $_SERVER['HTTPS'])) {
    $uri = 'https://';
} else {
    $uri = 'http://';
}
$uri .= $_SERVER['HTTP_HOST'];
header('Location: ' . $uri . '/master-hub/');
exit;
```

## Project Configuration

The `projects.json` file is optional. If it does not exist, the hub still works and lists folders from `htdocs`.

Example entry:

```json
{
  "my_php_app": {
    "title": "My PHP App",
    "description": "Internal application built with PHP and MySQL",
    "logo": "hub-mark.svg",
    "group": "Work",
    "tags": ["php", "mysql"]
  }
}
```

Available fields:

- `title`: visible name shown on the card.
- `description`: short descriptive text.
- `logo`: file inside `logos/`, a relative path, or a URL.
- `group`: group used to organize cards.
- `tags`: list of labels.
- `hidden`: if `true`, the project is not shown.


## Structure

```text
master-hub/
├─ index.php
├─ README.md
├─ projects.example.json
├─ projects.json        # local only, ignored by git
└─ logos/
  ├─ hub-mark.svg
  ├─ goh1.png          # versioned for the footer
  └─ *.png             # remaining files are local only, ignored by git
```