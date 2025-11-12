<?php
/**
 * EMERGENCY PATCH: Add inline autocomplete directly to np-address field
 * This bypasses all the external script issues and adds autocomplete inline
 */

$portalFile = __DIR__ . '/../portal/index.php';

if (!file_exists($portalFile)) {
    die(json_encode(['ok' => false, 'error' => 'Portal file not found']));
}

$content = file_get_contents($portalFile);

// Check if patch already applied
if (strpos($content, '/* INLINE AUTOCOMPLETE PATCH */') !== false) {
    die(json_encode(['ok' => true, 'message' => 'Patch already applied', 'already_patched' => true]));
}

// Find the np-address input field
$searchPattern = '<input id="np-address" class="w-full" placeholder="Start typing full address...">';

if (strpos($content, $searchPattern) === false) {
    die(json_encode(['ok' => false, 'error' => 'np-address input not found']));
}

// Replace with input that has inline autocomplete
$patchedInput = <<<'HTML'
<input id="np-address" class="w-full" placeholder="Start typing full address...">
<script>
/* INLINE AUTOCOMPLETE PATCH */
(function() {
  const input = document.getElementById('np-address');
  if (!input) return;

  let timeout;
  let dropdown;

  input.addEventListener('input', async function(e) {
    clearTimeout(timeout);
    const query = e.target.value.trim();

    if (query.length < 3) {
      if (dropdown) dropdown.remove();
      return;
    }

    timeout = setTimeout(async () => {
      try {
        const res = await fetch('/api/portal/address-search.php?query=' + encodeURIComponent(query));
        const data = await res.json();

        if (!data.ok || !data.suggestions || data.suggestions.length === 0) {
          if (dropdown) dropdown.remove();
          return;
        }

        if (dropdown) dropdown.remove();

        dropdown = document.createElement('div');
        dropdown.style.cssText = 'position:absolute;z-index:9999;background:white;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 6px rgba(0,0,0,0.1);max-height:300px;overflow-y:auto;width:' + input.offsetWidth + 'px;';

        const rect = input.getBoundingClientRect();
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';
        dropdown.style.left = rect.left + 'px';

        data.suggestions.forEach(sugg => {
          const div = document.createElement('div');
          div.style.cssText = 'padding:0.75rem 1rem;cursor:pointer;border-bottom:1px solid #f1f5f9;';
          div.innerHTML = '<div style="font-weight:500;color:#1e293b;">' + sugg.main_text + '</div><div style="font-size:0.875rem;color:#64748b;">' + sugg.secondary_text + '</div>';

          div.addEventListener('mouseenter', () => div.style.background = '#f8fafc');
          div.addEventListener('mouseleave', () => div.style.background = 'white');

          div.addEventListener('click', async () => {
            input.value = sugg.description;
            dropdown.remove();

            try {
              const detailRes = await fetch('/api/portal/address-details.php?place_id=' + encodeURIComponent(sugg.place_id));
              const detailData = await detailRes.json();

              if (detailData.ok && detailData.address) {
                const addr = detailData.address;
                document.getElementById('np-address').value = addr.formatted || addr.street || '';
                document.getElementById('np-city').value = addr.city || '';
                document.getElementById('np-state').value = addr.state || '';
                document.getElementById('np-zip').value = addr.zip || '';
              }
            } catch (err) {
              console.error('Failed to fetch address details:', err);
            }
          });

          dropdown.appendChild(div);
        });

        document.body.appendChild(dropdown);

      } catch (err) {
        console.error('Address search failed:', err);
      }
    }, 300);
  });

  document.addEventListener('click', (e) => {
    if (dropdown && e.target !== input && !dropdown.contains(e.target)) {
      dropdown.remove();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && dropdown) {
      dropdown.remove();
    }
  });
})();
</script>
HTML;

$newContent = str_replace($searchPattern, $patchedInput, $content);

if ($newContent === $content) {
    die(json_encode(['ok' => false, 'error' => 'No replacement made']));
}

if (file_put_contents($portalFile, $newContent) === false) {
    die(json_encode(['ok' => false, 'error' => 'Failed to write file']));
}

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'message' => 'Inline autocomplete patch applied to np-address',
    'file_size' => filesize($portalFile)
]);
