<?php
/**
 * File utilities for handling uploads across different environments
 */

/**
 * Get the absolute path for an uploaded file
 * Handles different deployment environments (local dev vs Render)
 *
 * @param string $relativePath Path stored in database (e.g., "/uploads/ids/file.pdf")
 * @return string Absolute filesystem path
 */
function getUploadAbsolutePath(string $relativePath): string {
  // On Render, uploads are mounted at /opt/render/project/src/uploads
  // On local dev, they're at $_SERVER['DOCUMENT_ROOT'] . '/uploads'

  // If the path already starts with an absolute path, return as-is
  if (strpos($relativePath, '/opt/render/') === 0 || strpos($relativePath, '/var/www/') === 0) {
    return $relativePath;
  }

  // Try Render mount path first
  $renderPath = '/opt/render/project/src' . $relativePath;
  if (file_exists($renderPath)) {
    return $renderPath;
  }

  // Fall back to DOCUMENT_ROOT (for local dev)
  $docRootPath = $_SERVER['DOCUMENT_ROOT'] . $relativePath;
  if (file_exists($docRootPath)) {
    return $docRootPath;
  }

  // Try relative to project root (api parent directory)
  $projectRoot = dirname(__DIR__, 2); // Go up from api/lib to project root
  $projectPath = $projectRoot . $relativePath;
  if (file_exists($projectPath)) {
    return $projectPath;
  }

  // If nothing exists, prefer Render path if we're on Render, otherwise DOCUMENT_ROOT
  if (file_exists('/opt/render/project/src')) {
    return $renderPath;
  }

  return $docRootPath;
}

/**
 * Get the base directory for uploads
 * Used when saving new files
 *
 * @param string $subdir Subdirectory like '/uploads/ids'
 * @return string Absolute path to the upload directory
 */
function getUploadBaseDir(string $subdir = '/uploads'): string {
  // On Render, use the persistent mount
  if (file_exists('/opt/render/project/src/uploads')) {
    $path = '/opt/render/project/src' . $subdir;
    if (!is_dir($path)) {
      mkdir($path, 0775, true);
    }
    return $path;
  }

  // On local dev, use DOCUMENT_ROOT
  $path = $_SERVER['DOCUMENT_ROOT'] . $subdir;
  if (!is_dir($path)) {
    mkdir($path, 0775, true);
  }
  return $path;
}
