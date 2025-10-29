<?php
/**
 * Screenshot Capture Tool
 * Instructions for capturing portal screenshots for documentation
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Screenshot Capture Instructions - CollagenDirect</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
  <div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-8">
      <h1 class="text-3xl font-bold text-gray-900 mb-6">📸 Portal Screenshot Guide</h1>

      <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
        <p class="text-sm text-blue-900">
          <strong>Purpose:</strong> Capture screenshots of the physician portal to enhance the user guide at /portal-guide.php
        </p>
      </div>

      <h2 class="text-2xl font-semibold text-gray-800 mb-4">Method 1: Browser Screenshots (Recommended)</h2>

      <div class="space-y-6 mb-8">
        <div class="bg-gray-50 p-4 rounded border">
          <h3 class="font-semibold text-lg mb-2">macOS:</h3>
          <ul class="list-disc ml-6 space-y-1 text-gray-700">
            <li><strong>Full screen:</strong> <code class="bg-gray-200 px-2 py-1 rounded">⌘ Cmd + Shift + 3</code></li>
            <li><strong>Selected area:</strong> <code class="bg-gray-200 px-2 py-1 rounded">⌘ Cmd + Shift + 4</code> (then drag to select)</li>
            <li><strong>Specific window:</strong> <code class="bg-gray-200 px-2 py-1 rounded">⌘ Cmd + Shift + 4</code> then press <code class="bg-gray-200 px-2 py-1 rounded">Space</code></li>
          </ul>
        </div>

        <div class="bg-gray-50 p-4 rounded border">
          <h3 class="font-semibold text-lg mb-2">Windows:</h3>
          <ul class="list-disc ml-6 space-y-1 text-gray-700">
            <li><strong>Full screen:</strong> <code class="bg-gray-200 px-2 py-1 rounded">PrtScn</code> (Print Screen)</li>
            <li><strong>Active window:</strong> <code class="bg-gray-200 px-2 py-1 rounded">Alt + PrtScn</code></li>
            <li><strong>Snipping Tool:</strong> <code class="bg-gray-200 px-2 py-1 rounded">Windows + Shift + S</code></li>
          </ul>
        </div>

        <div class="bg-gray-50 p-4 rounded border">
          <h3 class="font-semibold text-lg mb-2">Browser DevTools (Chrome/Edge):</h3>
          <ol class="list-decimal ml-6 space-y-2 text-gray-700">
            <li>Open DevTools: <code class="bg-gray-200 px-2 py-1 rounded">F12</code> or <code class="bg-gray-200 px-2 py-1 rounded">⌘ Cmd + Option + I</code></li>
            <li>Press <code class="bg-gray-200 px-2 py-1 rounded">⌘ Cmd + Shift + P</code> (Mac) or <code class="bg-gray-200 px-2 py-1 rounded">Ctrl + Shift + P</code> (Windows)</li>
            <li>Type "screenshot" and select:
              <ul class="list-disc ml-6 mt-2">
                <li><strong>Capture full size screenshot</strong> (entire page, even off-screen)</li>
                <li><strong>Capture screenshot</strong> (visible area only)</li>
              </ul>
            </li>
          </ol>
        </div>
      </div>

      <h2 class="text-2xl font-semibold text-gray-800 mb-4">📋 Screenshots Needed</h2>

      <div class="space-y-4">
        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss1">
            <label for="ss1" class="flex-1">
              <div class="font-semibold text-gray-900">1. Dashboard Overview</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/</code><br>
                Show: Main dashboard with metrics, recent patients, quick actions
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss2">
            <label for="ss2" class="flex-1">
              <div class="font-semibold text-gray-900">2. Patient List</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=patients</code><br>
                Show: Patient management interface with list of patients
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss3">
            <label for="ss3" class="flex-1">
              <div class="font-semibold text-gray-900">3. Add New Patient Form</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=patient-add</code><br>
                Show: Patient creation form with all fields
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss4">
            <label for="ss4" class="flex-1">
              <div class="font-semibold text-gray-900">4. Patient Detail Page</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=patient-detail&id=[patient-id]</code><br>
                Show: Patient information, order history, action buttons
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss5">
            <label for="ss5" class="flex-1">
              <div class="font-semibold text-gray-900">5. Create Order - Product Selection</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=order-add&patient_id=[id]</code><br>
                Show: Product selection dropdown and quantity input
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50 bg-yellow-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss6">
            <label for="ss6" class="flex-1">
              <div class="font-semibold text-gray-900">6. ICD-10 Autocomplete Search ⭐ NEW FEATURE</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=order-add&patient_id=[id]</code><br>
                Show: ICD-10 field with autocomplete dropdown showing search results<br>
                <strong>Action:</strong> Type "wound" or "ulcer" in ICD-10 field to show autocomplete
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss7">
            <label for="ss7" class="flex-1">
              <div class="font-semibold text-gray-900">7. Clinical Notes & Photo Upload</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=order-add&patient_id=[id]</code><br>
                Show: Clinical notes textarea and photo upload section
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss8">
            <label for="ss8" class="flex-1">
              <div class="font-semibold text-gray-900">8. Orders List</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=orders</code><br>
                Show: List of orders with different status indicators
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss9">
            <label for="ss9" class="flex-1">
              <div class="font-semibold text-gray-900">9. Documents Page</div>
              <div class="text-sm text-gray-600 mt-1">
                URL: <code class="bg-gray-100 px-2 py-0.5 rounded">https://collagendirect.health/portal/?page=documents</code><br>
                Show: Document management interface
              </div>
            </label>
          </div>
        </div>

        <div class="border rounded-lg p-4 hover:bg-gray-50">
          <div class="flex items-start">
            <input type="checkbox" class="mt-1 mr-3" id="ss10">
            <label for="ss10" class="flex-1">
              <div class="font-semibold text-gray-900">10. Mobile View (Optional)</div>
              <div class="text-sm text-gray-600 mt-1">
                Use browser DevTools responsive mode to capture mobile layout<br>
                <strong>Chrome:</strong> <code class="bg-gray-100 px-2 py-0.5 rounded">⌘ Cmd + Shift + M</code> to toggle device toolbar
              </div>
            </label>
          </div>
        </div>
      </div>

      <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-4">💾 Saving Screenshots</h2>

      <div class="bg-green-50 border-l-4 border-green-500 p-4 mb-6">
        <h3 class="font-semibold text-green-900 mb-2">Recommended File Naming:</h3>
        <ul class="list-disc ml-6 text-sm text-green-900 space-y-1">
          <li><code>portal-dashboard.png</code></li>
          <li><code>portal-patients-list.png</code></li>
          <li><code>portal-patient-add.png</code></li>
          <li><code>portal-patient-detail.png</code></li>
          <li><code>portal-order-product.png</code></li>
          <li><code>portal-order-icd10-autocomplete.png</code> ⭐</li>
          <li><code>portal-order-clinical-notes.png</code></li>
          <li><code>portal-orders-list.png</code></li>
          <li><code>portal-documents.png</code></li>
          <li><code>portal-mobile.png</code> (optional)</li>
        </ul>
      </div>

      <div class="bg-gray-100 border rounded p-4">
        <h3 class="font-semibold mb-2">Save Location:</h3>
        <p class="text-sm text-gray-700 mb-2">Save screenshots to:</p>
        <code class="block bg-white px-3 py-2 rounded border text-sm">/var/data/uploads/portal-screenshots/</code>
        <p class="text-xs text-gray-600 mt-2">Or save locally and upload via admin interface</p>
      </div>

      <h2 class="text-2xl font-semibold text-gray-800 mt-8 mb-4">🔧 Next Steps</h2>

      <ol class="list-decimal ml-6 space-y-2 text-gray-700">
        <li>Log into the physician portal as a test user</li>
        <li>Navigate to each page listed above</li>
        <li>Capture screenshots using your preferred method</li>
        <li>Save with recommended file names</li>
        <li>Upload screenshots to the server (or save locally for now)</li>
        <li>Update <code>/portal-guide.php</code> to reference the actual image files</li>
      </ol>

      <div class="mt-8 p-4 bg-blue-50 rounded border border-blue-200">
        <p class="text-sm text-blue-900">
          <strong>💡 Tip:</strong> For the ICD-10 autocomplete screenshot, make sure to type something in the search field
          (like "wound" or "ulcer") so the dropdown with suggestions is visible in the screenshot. This is the most
          important screenshot since it shows the new feature!
        </p>
      </div>

      <div class="mt-6 flex gap-4">
        <a href="/portal/" target="_blank" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
          Open Physician Portal →
        </a>
        <a href="/portal-guide.php" target="_blank" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition">
          View Current Guide
        </a>
      </div>
    </div>
  </div>
</body>
</html>
