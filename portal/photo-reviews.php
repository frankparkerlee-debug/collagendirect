<?php
// Photo Reviews Page - Included in portal/index.php when page=photo-reviews
// This page displays pending wound photos for physician review
?>

<div class="page-header">
  <h1>Wound Photo Reviews</h1>
  <p style="color: #64748b; margin-top: 0.5rem;">Review patient wound photos and generate billable E/M codes</p>
</div>

<!-- Filter Bar -->
<div style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
  <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
    <div style="flex: 1; min-width: 250px;">
      <input type="text" id="filter-patient-search" placeholder="Search patient name..." style="width: 100%; padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.875rem;">
    </div>
    <div>
      <select id="filter-status" style="padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.875rem; min-width: 150px;">
        <option value="all">All Photos</option>
        <option value="pending" selected>Pending Review</option>
        <option value="reviewed">Reviewed</option>
      </select>
    </div>
    <div>
      <select id="filter-assessment" style="padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.875rem; min-width: 150px;">
        <option value="all">All Assessments</option>
        <option value="improving">Improving</option>
        <option value="stable">Stable</option>
        <option value="concern">Concern</option>
        <option value="urgent">Urgent</option>
      </select>
    </div>
    <div>
      <select id="filter-date-range" style="padding: 0.5rem 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.875rem; min-width: 150px;">
        <option value="7">Last 7 days</option>
        <option value="30" selected>Last 30 days</option>
        <option value="90">Last 90 days</option>
        <option value="all">All time</option>
      </select>
    </div>
    <button onclick="applyFilters()" style="padding: 0.5rem 1rem; background: #3b82f6; color: white; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
      <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
      </svg>
      Apply
    </button>
    <button onclick="clearFilters()" style="padding: 0.5rem 1rem; background: #f1f5f9; color: #475569; border: none; border-radius: 6px; font-size: 0.875rem; font-weight: 500; cursor: pointer;">
      Clear
    </button>
  </div>
</div>

<!-- Billing Summary -->
<div class="billing-summary">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
    <h2 style="margin: 0;">Billing Summary - <span id="summary-month"></span></h2>
    <button class="export-btn" onclick="exportBilling()">
      <svg style="width: 20px; height: 20px; display: inline-block; vertical-align: middle; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      Export CSV
    </button>
  </div>

  <div class="billing-stats">
    <div class="stat-card">
      <div class="stat-value" id="total-encounters">0</div>
      <div class="stat-label">Reviews This Month</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" style="color: #059669;" id="total-charges">$0.00</div>
      <div class="stat-label">Total Charges</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="pending-count">0</div>
      <div class="stat-label">Pending Reviews</div>
    </div>
    <div class="stat-card">
      <div class="stat-value" id="exported-count">0</div>
      <div class="stat-label">Exported</div>
    </div>
  </div>
</div>

<!-- Photo Grid -->
<div id="photo-grid-container">
  <div class="photo-grid" id="photo-grid">
    <!-- Photos will be loaded here dynamically -->
  </div>

  <div class="empty-state" id="empty-state" style="display: none;">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    <h3>No Pending Photos</h3>
    <p>All wound photos have been reviewed. New photos will appear here when patients submit them.</p>
  </div>
</div>

<!-- Review Modal -->
<div class="review-modal" id="review-modal">
  <div class="review-modal-content">
    <div class="review-modal-header">
      <h2 id="modal-patient-name">Review Photo</h2>
      <button class="review-modal-close" onclick="closeReviewModal()">&times;</button>
    </div>
    <div class="review-modal-body">
      <img id="modal-photo" class="review-image-large" src="" alt="Wound photo">

      <div style="background: #f8fafc; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem;">
        <div style="font-size: 0.875rem; color: #64748b;">
          <strong>Patient:</strong> <span id="modal-patient-info"></span><br>
          <strong>Uploaded:</strong> <span id="modal-upload-date"></span><br>
          <strong>Location:</strong> <span id="modal-wound-location"></span>
        </div>
      </div>

      <form class="review-form" onsubmit="submitReview(event)">
        <input type="hidden" id="modal-photo-id" value="">

        <label style="display: block; font-weight: 500; margin-bottom: 0.5rem;">Additional Notes (Optional)</label>
        <textarea id="review-notes" placeholder="Enter any additional clinical observations..."></textarea>

        <label style="display: block; font-weight: 500; margin-bottom: 0.75rem;">Select Assessment:</label>
        <div class="assessment-buttons">
          <button type="button" class="assessment-btn btn-improving" onclick="selectAssessment('improving')">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Improving - 99213 ($92)
          </button>
          <button type="button" class="assessment-btn btn-stable" onclick="selectAssessment('stable')">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Stable - 99213 ($92)
          </button>
          <button type="button" class="assessment-btn btn-concern" onclick="selectAssessment('concern')">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            Concern - 99214 ($130)
          </button>
          <button type="button" class="assessment-btn btn-urgent" onclick="selectAssessment('urgent')">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Urgent - 99215 ($180)
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
.photo-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
  margin-top: 1.5rem;
}

.photo-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
}

.photo-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.photo-image {
  width: 100%;
  height: 250px;
  object-fit: cover;
  background: #f5f5f5;
}

.photo-details {
  padding: 1rem;
}

.photo-patient-name {
  font-size: 1.1rem;
  font-weight: 600;
  color: #059669;
  margin-bottom: 0.5rem;
}

.photo-meta {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.photo-notes {
  font-size: 0.875rem;
  padding: 0.75rem;
  background: #f8fafc;
  border-radius: 4px;
  margin-bottom: 1rem;
  font-style: italic;
  color: #475569;
}

.assessment-buttons {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.assessment-btn {
  padding: 0.75rem;
  border: none;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.assessment-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.btn-improving {
  background: #d1fae5;
  color: #065f46;
}

.btn-improving:hover {
  background: #a7f3d0;
}

.btn-stable {
  background: #dbeafe;
  color: #1e40af;
}

.btn-stable:hover {
  background: #bfdbfe;
}

.btn-concern {
  background: #fed7aa;
  color: #9a3412;
}

.btn-concern:hover {
  background: #fdba74;
}

.btn-urgent {
  background: #fecaca;
  color: #991b1b;
}

.btn-urgent:hover {
  background: #fca5a5;
}

.billing-summary {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
}

.billing-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1.5rem;
  margin-top: 1rem;
}

.stat-card {
  text-align: center;
  padding: 1rem;
  background: #f8fafc;
  border-radius: 6px;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #059669;
}

.stat-label {
  font-size: 0.875rem;
  color: #64748b;
  margin-top: 0.5rem;
}

.export-btn {
  background: #059669;
  color: white;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.export-btn:hover {
  background: #047857;
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #64748b;
}

.empty-state svg {
  width: 64px;
  height: 64px;
  margin: 0 auto 1rem;
  opacity: 0.3;
}

.review-modal {
  display: none;
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.7);
  z-index: 1000;
  overflow-y: auto;
}

.review-modal.active {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.review-modal-content {
  background: white;
  border-radius: 8px;
  max-width: 900px;
  width: 100%;
  max-height: 90vh;
  overflow-y: auto;
  position: relative;
}

.review-modal-header {
  padding: 1.5rem;
  border-bottom: 1px solid #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.review-modal-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  cursor: pointer;
  color: #64748b;
}

.review-modal-body {
  padding: 1.5rem;
}

.review-image-large {
  width: 100%;
  max-height: 500px;
  object-fit: contain;
  background: #f5f5f5;
  border-radius: 6px;
  margin-bottom: 1.5rem;
}

.review-form textarea {
  width: 100%;
  min-height: 100px;
  padding: 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  font-family: inherit;
  font-size: 0.875rem;
  resize: vertical;
  margin-bottom: 1rem;
}

.review-form textarea:focus {
  outline: none;
  border-color: #059669;
  box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.1);
}
</style>

<script>
let currentPhotoId = null;
let selectedAssessment = null;
let pendingPhotos = [];

// Load data on page load
document.addEventListener('DOMContentLoaded', function() {
  loadPendingPhotos();
  loadBillingSummary();

  // Update month display
  const now = new Date();
  const monthName = now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
  document.getElementById('summary-month').textContent = monthName;
});

// Load pending photos from API
async function loadPendingPhotos() {
  try {
    const response = await api('action=get_pending_photos');

    if (response.ok) {
      pendingPhotos = response.photos;
      renderPhotoGrid();

      // Update pending count
      document.getElementById('pending-count').textContent = response.count;
    } else {
      console.error('Failed to load photos:', response.error);
    }
  } catch (error) {
    console.error('Error loading photos:', error);
  }
}

// Render photo grid
function renderPhotoGrid() {
  const grid = document.getElementById('photo-grid');
  const emptyState = document.getElementById('empty-state');

  if (pendingPhotos.length === 0) {
    grid.style.display = 'none';
    emptyState.style.display = 'block';
    return;
  }

  grid.style.display = 'grid';
  emptyState.style.display = 'none';
  grid.innerHTML = '';

  pendingPhotos.forEach(photo => {
    const card = createPhotoCard(photo);
    grid.appendChild(card);
  });
}

// Create photo card element
function createPhotoCard(photo) {
  const card = document.createElement('div');
  card.className = 'photo-card';
  card.onclick = () => openReviewModal(photo);
  card.style.cursor = 'pointer';

  const uploadDate = new Date(photo.uploaded_at);
  const dateStr = uploadDate.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });

  card.innerHTML = `
    <img src="${photo.photo_path}" alt="Wound photo" class="photo-image" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><text x=%2250%%22 y=%2250%%22 text-anchor=%22middle%22 fill=%22%23999%22>Image unavailable</text></svg>'">
    <div class="photo-details">
      <div class="photo-patient-name">${photo.first_name} ${photo.last_name}</div>
      <div class="photo-meta">
        <strong>DOB:</strong> ${formatDate(photo.dob)}<br>
        <strong>MRN:</strong> ${photo.mrn || 'N/A'}<br>
        <strong>Uploaded:</strong> ${dateStr}<br>
        ${photo.wound_location ? `<strong>Location:</strong> ${photo.wound_location}<br>` : ''}
        <strong>Via:</strong> ${photo.uploaded_via || 'SMS'}
      </div>
      ${photo.patient_notes ? `<div class="photo-notes">"${photo.patient_notes}"</div>` : ''}
      <div style="text-align: center; padding: 0.75rem; background: #f0fdf4; border-radius: 6px; margin-top: 1rem; font-size: 0.875rem; color: #059669; font-weight: 500;">
        Click to Review & Bill
      </div>
    </div>
  `;

  return card;
}

// Open review modal
function openReviewModal(photo) {
  currentPhotoId = photo.id;
  selectedAssessment = null;

  // Populate modal
  document.getElementById('modal-photo-id').value = photo.id;
  document.getElementById('modal-photo').src = photo.photo_path;
  document.getElementById('modal-patient-name').textContent = `${photo.first_name} ${photo.last_name}`;
  document.getElementById('modal-patient-info').textContent = `${photo.first_name} ${photo.last_name} (DOB: ${formatDate(photo.dob)}, MRN: ${photo.mrn || 'N/A'})`;

  const uploadDate = new Date(photo.uploaded_at);
  document.getElementById('modal-upload-date').textContent = uploadDate.toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  });

  document.getElementById('modal-wound-location').textContent = photo.wound_location || 'Not specified';
  document.getElementById('review-notes').value = '';

  // Reset button states
  document.querySelectorAll('.assessment-btn').forEach(btn => {
    btn.style.opacity = '1';
    btn.style.transform = 'scale(1)';
  });

  // Show modal
  document.getElementById('review-modal').classList.add('active');
}

// Close review modal
function closeReviewModal() {
  document.getElementById('review-modal').classList.remove('active');
  currentPhotoId = null;
  selectedAssessment = null;
}

// Select assessment
function selectAssessment(assessment) {
  selectedAssessment = assessment;

  // Visual feedback
  document.querySelectorAll('.assessment-btn').forEach(btn => {
    btn.style.opacity = '0.5';
    btn.style.transform = 'scale(0.95)';
  });

  event.target.closest('.assessment-btn').style.opacity = '1';
  event.target.closest('.assessment-btn').style.transform = 'scale(1.05)';

  // Submit immediately
  setTimeout(() => submitReview(), 300);
}

// Submit review
async function submitReview(event) {
  if (event) {
    event.preventDefault();
  }

  if (!selectedAssessment) {
    alert('Please select an assessment');
    return;
  }

  const photoId = document.getElementById('modal-photo-id').value;
  const notes = document.getElementById('review-notes').value;

  try {
    const response = await api('action=review_wound_photo', {
      method: 'POST',
      body: fd({
        photo_id: photoId,
        assessment: selectedAssessment,
        notes: notes
      })
    });

    if (response.ok) {
      // Show success message
      alert(`Review saved! Billable charge: $${response.billed} (${response.cpt_code}-95)`);

      // Remove photo from grid
      pendingPhotos = pendingPhotos.filter(p => p.id !== photoId);
      renderPhotoGrid();

      // Reload billing summary
      loadBillingSummary();

      // Close modal
      closeReviewModal();
    } else {
      alert('Error: ' + response.error);
    }
  } catch (error) {
    console.error('Error submitting review:', error);
    alert('Failed to submit review. Please try again.');
  }
}

// Load billing summary
async function loadBillingSummary() {
  try {
    const now = new Date();
    const month = now.toISOString().substring(0, 7); // YYYY-MM format

    const response = await api(`action=get_billing_summary&month=${month}`);

    if (response.ok && response.summary) {
      const s = response.summary;
      document.getElementById('total-encounters').textContent = s.total_encounters || 0;
      document.getElementById('total-charges').textContent = '$' + (parseFloat(s.total_charges) || 0).toFixed(2);
      document.getElementById('exported-count').textContent = s.exported_count || 0;
    }
  } catch (error) {
    console.error('Error loading billing summary:', error);
  }
}

// Export billing CSV
function exportBilling() {
  const now = new Date();
  const month = now.toISOString().substring(0, 7); // YYYY-MM format

  // Open export URL in new window to trigger download
  window.location.href = `?action=export_billing&month=${month}`;
}

// Format date helper
function formatDate(dateStr) {
  if (!dateStr) return 'N/A';
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: '2-digit', day: '2-digit', year: 'numeric' });
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
  const modal = document.getElementById('review-modal');
  if (event.target === modal) {
    closeReviewModal();
  }
});

// Filter functionality
let allPhotos = []; // Store all photos for filtering

// Update loadPendingPhotos to store all photos
const originalLoadPendingPhotos = loadPendingPhotos;
loadPendingPhotos = async function() {
  try {
    const response = await api('action=get_pending_photos');

    if (response.ok) {
      allPhotos = response.photos; // Store all photos
      pendingPhotos = response.photos;
      renderPhotoGrid();

      // Update pending count
      document.getElementById('pending-count').textContent = response.count;
    } else {
      console.error('Failed to load photos:', response.error);
    }
  } catch (error) {
    console.error('Error loading photos:', error);
  }
};

function applyFilters() {
  const searchTerm = document.getElementById('filter-patient-search').value.toLowerCase();
  const status = document.getElementById('filter-status').value;
  const assessment = document.getElementById('filter-assessment').value;
  const dateRange = document.getElementById('filter-date-range').value;

  let filtered = [...allPhotos];

  // Filter by patient name
  if (searchTerm) {
    filtered = filtered.filter(photo => {
      const fullName = `${photo.first_name} ${photo.last_name}`.toLowerCase();
      return fullName.includes(searchTerm);
    });
  }

  // Filter by review status
  if (status === 'pending') {
    filtered = filtered.filter(photo => !photo.reviewed);
  } else if (status === 'reviewed') {
    filtered = filtered.filter(photo => photo.reviewed);
  }

  // Filter by assessment (only for reviewed photos)
  if (assessment !== 'all') {
    filtered = filtered.filter(photo => photo.assessment === assessment);
  }

  // Filter by date range
  if (dateRange !== 'all') {
    const days = parseInt(dateRange);
    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - days);

    filtered = filtered.filter(photo => {
      const uploadDate = new Date(photo.uploaded_at);
      return uploadDate >= cutoffDate;
    });
  }

  pendingPhotos = filtered;
  renderPhotoGrid();

  // Update count
  document.getElementById('pending-count').textContent = filtered.length;
}

function clearFilters() {
  document.getElementById('filter-patient-search').value = '';
  document.getElementById('filter-status').value = 'pending';
  document.getElementById('filter-assessment').value = 'all';
  document.getElementById('filter-date-range').value = '30';

  // Reset to show all pending photos
  pendingPhotos = allPhotos.filter(photo => !photo.reviewed);
  renderPhotoGrid();
  document.getElementById('pending-count').textContent = pendingPhotos.length;
}

// Auto-apply filters when Enter is pressed in search
document.addEventListener('DOMContentLoaded', function() {
  const searchInput = document.getElementById('filter-patient-search');
  if (searchInput) {
    searchInput.addEventListener('keyup', function(event) {
      if (event.key === 'Enter') {
        applyFilters();
      }
    });
  }
});
</script>
