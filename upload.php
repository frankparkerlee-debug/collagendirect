<?php
/**
 * Web-based Wound Photo Upload
 * Patients access this via link in SMS: https://collagendirect.health/upload/{token}
 */

require_once __DIR__ . '/api/db.php';
require_once __DIR__ . '/api/lib/timezone.php';

// Get upload token from URL path
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$pathParts = explode('/', trim($requestUri, '/'));
$uploadToken = end($pathParts);

// Remove query string if present
$uploadToken = explode('?', $uploadToken)[0];

header('Content-Type: text/html; charset=utf-8');

// Verify token and get photo request
$photoRequest = null;
if ($uploadToken && strlen($uploadToken) === 32) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.id, pr.patient_id, pr.physician_id, pr.order_id, pr.wound_location,
                   pr.token_expires_at,
                   p.first_name, p.last_name, p.phone
            FROM photo_requests pr
            JOIN patients p ON p.id = pr.patient_id
            WHERE pr.upload_token = ?
              AND pr.token_expires_at > NOW()
        ");
        $stmt->execute([$uploadToken]);
        $photoRequest = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('[Upload] Database error: ' . $e->getMessage());
    }
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $photoRequest && isset($_FILES['photo'])) {
    try {
        $file = $_FILES['photo'];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Upload error: ' . $file['error']);
        }

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/heic', 'image/heif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array(strtolower($mimeType), $allowedTypes)) {
            throw new Exception('Invalid file type. Please upload a JPEG, PNG, or HEIC image.');
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File too large. Maximum size is 10MB.');
        }

        // Determine file extension
        $ext = 'jpg';
        if (strpos($mimeType, 'png') !== false) {
            $ext = 'png';
        } elseif (strpos($mimeType, 'heic') !== false || strpos($mimeType, 'heif') !== false) {
            $ext = 'heic';
        }

        // Generate filename
        $filename = 'wound-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $photoPath = '/uploads/wound_photos/' . $filename;
        $fullPath = __DIR__ . '/uploads/wound_photos/' . $filename;

        // Ensure directory exists
        $uploadDir = dirname($fullPath);
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new Exception('Failed to save photo');
        }

        // Save to database
        $photoId = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("
            INSERT INTO wound_photos
            (id, patient_id, photo_path, uploaded_via, uploaded_at)
            VALUES (?, ?, ?, 'portal', NOW())
        ");
        $stmt->execute([
            $photoId,
            $photoRequest['patient_id'],
            $photoPath
        ]);

        error_log('[Upload] Photo uploaded successfully: ' . $filename . ' for patient: ' . $photoRequest['patient_id']);

        // Redirect to success page
        header('Location: /upload/' . $uploadToken . '?success=1');
        exit;

    } catch (Exception $e) {
        error_log('[Upload] Upload failed: ' . $e->getMessage());
        $uploadError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Wound Photo - CollagenDirect</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 2rem;
        }
        h1 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        .subtitle {
            color: #666;
            font-size: 0.875rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .error {
            background: #fee;
            border: 1px solid #fcc;
            color: #c33;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        .success {
            background: #efe;
            border: 1px solid #cfc;
            color: #3c3;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #667eea;
            background: #f9f9ff;
        }
        .upload-area.dragover {
            border-color: #667eea;
            background: #f0f0ff;
        }
        input[type="file"] {
            display: none;
        }
        .file-label {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .file-label:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .preview {
            margin-top: 1.5rem;
            display: none;
        }
        .preview img {
            max-width: 100%;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.4);
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        .info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            color: #555;
        }
        .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$photoRequest): ?>
            <h1>❌ Invalid or Expired Link</h1>
            <p class="subtitle">This upload link is not valid or has expired.</p>
            <div class="error">
                <strong>Possible reasons:</strong>
                <ul style="margin-top: 0.5rem; margin-left: 1.5rem;">
                    <li>The link has expired (links are valid for 7 days)</li>
                    <li>The link was already used</li>
                    <li>The link is incorrect</li>
                </ul>
            </div>
            <p style="text-align: center; color: #666;">
                Please contact your doctor's office if you need a new upload link.
            </p>
        <?php elseif (isset($_GET['success'])): ?>
            <div class="success">
                <div class="icon">✅</div>
                <h1>Photo Uploaded Successfully!</h1>
                <p class="subtitle">Thank you, <?= htmlspecialchars($photoRequest['first_name']) ?>!</p>
                <p>Your doctor will review your photo shortly.</p>
            </div>
        <?php else: ?>
            <h1>📸 Upload Wound Photo</h1>
            <p class="subtitle">Hi <?= htmlspecialchars($photoRequest['first_name']) ?>!</p>

            <?php if (isset($uploadError)): ?>
                <div class="error">
                    <strong>Upload Error:</strong> <?= htmlspecialchars($uploadError) ?>
                </div>
            <?php endif; ?>

            <div class="info">
                Please take a clear photo of your wound. Make sure the area is well-lit and the photo is in focus.
            </div>

            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="uploadArea">
                    <div class="icon">📷</div>
                    <label for="photo" class="file-label">
                        Choose Photo
                    </label>
                    <input type="file" id="photo" name="photo" accept="image/*" required>
                    <p style="margin-top: 1rem; color: #999; font-size: 0.875rem;">
                        or drag and drop here
                    </p>
                </div>

                <div class="preview" id="preview">
                    <img id="previewImage" src="" alt="Preview">
                </div>

                <button type="submit" class="btn" id="submitBtn" disabled>
                    Upload Photo
                </button>
            </form>
        <?php endif; ?>
    </div>

    <script>
        const fileInput = document.getElementById('photo');
        const uploadArea = document.getElementById('uploadArea');
        const preview = document.getElementById('preview');
        const previewImage = document.getElementById('previewImage');
        const submitBtn = document.getElementById('submitBtn');

        if (fileInput) {
            fileInput.addEventListener('change', function(e) {
                if (e.target.files && e.target.files[0]) {
                    const file = e.target.files[0];

                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        preview.style.display = 'block';
                        submitBtn.disabled = false;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Drag and drop
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                uploadArea.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadArea.classList.remove('dragover');

                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    fileInput.files = e.dataTransfer.files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        }
    </script>
</body>
</html>
