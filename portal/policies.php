<?php
// Policies Page - AI Use Policy and Billing Use Policy
?>

<div class="page-header">
  <h1>Platform Policies</h1>
  <p style="color: #64748b; margin-top: 0.5rem;">AI Use Policy and Billing Compliance Guidelines</p>
</div>

<div style="max-width: 900px; margin: 0 auto;">

  <!-- AI Use Policy -->
  <div style="background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h2 style="color: #059669; margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
      <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
      </svg>
      Artificial Intelligence Use Policy
    </h2>

    <div style="color: #475569; line-height: 1.7;">
      <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">1. Overview and Purpose</h3>
      <p>
        CollageDirect utilizes artificial intelligence (AI) technology to assist healthcare providers in generating
        clinical documentation for wound assessment and telehealth services. This policy outlines the proper use,
        limitations, and responsibilities associated with AI-generated content.
      </p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">2. AI-Assisted Clinical Documentation</h3>
      <p><strong>2.1 Scope of AI Use</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li>AI is used to <strong>assist</strong> in generating clinical note templates based on wound assessment data</li>
        <li>AI analyzes patient-submitted photos, wound location, and provider assessment to suggest documentation</li>
        <li>AI-generated content serves as a <strong>starting point</strong> only and requires provider review</li>
        <li>The system does NOT make diagnostic decisions or treatment recommendations independently</li>
      </ul>

      <p><strong>2.2 Provider Responsibility</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li><strong>Verification Required:</strong> Providers MUST review, verify, and edit all AI-generated clinical notes</li>
        <li><strong>Clinical Judgment:</strong> Final clinical decisions rest solely with the licensed healthcare provider</li>
        <li><strong>Individualization:</strong> Providers must individualize notes to reflect actual patient-specific findings</li>
        <li><strong>Accuracy:</strong> Providers are responsible for the accuracy and completeness of all documentation</li>
        <li><strong>Medical Necessity:</strong> Providers must ensure services rendered meet medical necessity criteria</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">3. Limitations of AI Technology</h3>
      <ul style="margin-left: 1.5rem;">
        <li>AI cannot replace clinical examination or provider judgment</li>
        <li>AI-generated content may contain inaccuracies or inappropriate suggestions</li>
        <li>AI does not have access to complete patient medical history or context</li>
        <li>AI cannot detect all wound complications or patient conditions</li>
        <li>AI recommendations must be validated against actual clinical findings</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">4. Data Privacy and Security</h3>
      <ul style="margin-left: 1.5rem;">
        <li>All patient data processed by AI systems is encrypted and HIPAA-compliant</li>
        <li>AI processing occurs on secure, SOC 2 certified infrastructure</li>
        <li>Patient data is not used to train AI models or shared with third parties</li>
        <li>AI-generated content is stored securely within the platform database</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">5. Liability and Indemnification</h3>
      <p>
        <strong>Provider Liability:</strong> The healthcare provider who reviews, modifies, and signs clinical
        documentation bears full professional liability for the content. CollageDirect provides AI technology
        as a documentation tool only and assumes no liability for clinical decisions made by providers.
      </p>
      <p>
        <strong>Platform Limitations:</strong> CollageDirect makes no warranties regarding the accuracy,
        completeness, or clinical appropriateness of AI-generated content. Providers use AI assistance at
        their own professional discretion and risk.
      </p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">6. Compliance and Best Practices</h3>
      <ul style="margin-left: 1.5rem;">
        <li>Providers must maintain current medical licensure and appropriate credentials</li>
        <li>AI-assisted documentation must comply with all applicable state and federal regulations</li>
        <li>Providers must adhere to specialty-specific documentation standards</li>
        <li>Any concerns about AI-generated content should be reported immediately</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">7. Updates to AI Systems</h3>
      <p>
        CollageDirect reserves the right to update, modify, or discontinue AI features at any time.
        Material changes to AI functionality will be communicated to users via platform notifications
        or email.
      </p>
    </div>
  </div>

  <!-- Billing Use Policy -->
  <div style="background: white; padding: 2rem; border-radius: 8px; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <h2 style="color: #059669; margin-top: 0; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
      <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
      </svg>
      Billing and Compliance Policy
    </h2>

    <div style="color: #475569; line-height: 1.7;">
      <p><strong>Last Updated:</strong> <?= date('F j, Y') ?></p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">1. Purpose and Scope</h3>
      <p>
        This policy establishes guidelines for proper billing and coding practices when using CollageDirect
        for telehealth wound assessment services. Compliance with Medicare, Medicaid, and commercial payer
        requirements is mandatory for all platform users.
      </p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">2. Billing Compliance Requirements</h3>

      <p><strong>2.1 Documentation Standards</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li><strong>Medical Necessity:</strong> All billable services must be medically necessary and documented</li>
        <li><strong>Individualized Notes:</strong> Clinical notes must be patient-specific and not appear templated</li>
        <li><strong>Accurate Coding:</strong> CPT and ICD-10 codes must accurately reflect services rendered</li>
        <li><strong>Time Documentation:</strong> Provider time must be documented accurately for time-based codes</li>
        <li><strong>Signature Requirement:</strong> All billable encounters must be electronically signed by the provider</li>
      </ul>

      <p><strong>2.2 Telehealth-Specific Requirements</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li>Telehealth modality (store-and-forward, synchronous) must be documented</li>
        <li>Appropriate modifiers (e.g., 95 for synchronous telehealth) must be applied</li>
        <li>Place of Service code 02 (Telehealth) must be used when applicable</li>
        <li>Patient consent for telehealth services must be obtained and documented</li>
        <li>Technology platform used must be HIPAA-compliant</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">3. Prohibited Billing Practices</h3>
      <p style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; border-radius: 4px;">
        <strong>The following practices are strictly prohibited:</strong>
      </p>
      <ul style="margin-left: 1.5rem;">
        <li><strong>Upcoding:</strong> Billing for a higher level of service than was provided</li>
        <li><strong>Duplicate Billing:</strong> Billing multiple times for the same service</li>
        <li><strong>Unbundling:</strong> Separately billing for services that should be billed together</li>
        <li><strong>Same-Day Multiple E/M:</strong> Billing multiple E/M codes for same patient on same day (unless distinct encounters)</li>
        <li><strong>False Documentation:</strong> Creating or altering documentation to support improper billing</li>
        <li><strong>Kickbacks:</strong> Accepting or providing remuneration for patient referrals</li>
        <li><strong>Billing Without Service:</strong> Billing for services not actually rendered</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">4. Medicare/Medicaid Compliance</h3>
      <ul style="margin-left: 1.5rem;">
        <li>Providers must verify patient eligibility before rendering services</li>
        <li>Services must comply with Local Coverage Determinations (LCDs)</li>
        <li>Documentation must support Medical Decision Making (MDM) complexity billed</li>
        <li>Anti-Kickback Statute and Stark Law requirements must be followed</li>
        <li>Claims must be submitted within timely filing limits</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">5. Audit Preparedness</h3>
      <p><strong>5.1 Documentation Retention</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li>All clinical documentation must be retained for minimum 7 years</li>
        <li>Digital images must be stored securely and remain accessible</li>
        <li>Billing records must be maintained per state and federal requirements</li>
      </ul>

      <p><strong>5.2 Audit Response</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li>Providers must respond promptly to payer audit requests</li>
        <li>CollageDirect will provide technical assistance in retrieving platform data</li>
        <li>Providers are responsible for defending their billing and documentation</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">6. CPT Codes and Modifiers</h3>
      <p><strong>Commonly Used Codes:</strong></p>
      <ul style="margin-left: 1.5rem;">
        <li><strong>99091:</strong> Remote monitoring/evaluation (20 min minimum per 30 days)</li>
        <li><strong>99457:</strong> Remote physiologic monitoring treatment services, first 20 minutes</li>
        <li><strong>99458:</strong> Each additional 20 minutes (add-on code)</li>
        <li><strong>99211-99215:</strong> Office/outpatient E/M visits (when applicable with telehealth)</li>
        <li><strong>Modifier 95:</strong> Synchronous telemedicine service via telecommunications</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">7. Credentialing Requirements</h3>
      <ul style="margin-left: 1.5rem;">
        <li>Providers must maintain active medical licensure in states where patients are located</li>
        <li>DEA registration required when prescribing controlled substances</li>
        <li>NPI (National Provider Identifier) must be valid and registered</li>
        <li>Malpractice insurance must be current and adequate</li>
        <li>Credentials must be re-verified periodically per payer requirements</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">8. Reporting Compliance Issues</h3>
      <p>
        Any suspected billing fraud, abuse, or compliance violations must be reported immediately to:
      </p>
      <ul style="margin-left: 1.5rem;">
        <li><strong>Platform Support:</strong> compliance@collagendirect.health</li>
        <li><strong>HHS OIG Hotline:</strong> 1-800-HHS-TIPS (1-800-447-8477)</li>
        <li><strong>CMS Fraud Hotline:</strong> 1-800-MEDICARE (1-800-633-4227)</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">9. Consequences of Non-Compliance</h3>
      <p style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; border-radius: 4px;">
        <strong>Violations of billing compliance may result in:</strong>
      </p>
      <ul style="margin-left: 1.5rem;">
        <li>Civil monetary penalties up to $11,000 per false claim</li>
        <li>Treble damages under False Claims Act (3x the amount billed)</li>
        <li>Exclusion from Medicare/Medicaid programs</li>
        <li>Criminal prosecution for healthcare fraud</li>
        <li>Loss of medical licensure</li>
        <li>Suspension or termination of platform access</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">10. Provider Acknowledgment</h3>
      <p>
        By using CollageDirect billing features, providers acknowledge that they:
      </p>
      <ul style="margin-left: 1.5rem;">
        <li>Have read and understand this Billing and Compliance Policy</li>
        <li>Agree to comply with all applicable federal and state laws</li>
        <li>Will maintain accurate and complete documentation</li>
        <li>Will bill only for services that are medically necessary and properly documented</li>
        <li>Understand their professional liability for all billing submitted</li>
      </ul>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">11. Platform Disclaimers</h3>
      <p>
        <strong>No Billing Advice:</strong> CollageDirect does not provide billing, coding, or reimbursement
        advice. Code suggestions are for informational purposes only. Providers should consult with qualified
        billing professionals and legal counsel regarding proper coding and billing practices.
      </p>
      <p>
        <strong>No Guarantee of Payment:</strong> CollageDirect makes no guarantees regarding reimbursement
        from any payer. Payment is determined by the payer based on medical necessity, coverage policies,
        and documentation quality.
      </p>

      <h3 style="color: #0f172a; margin-top: 1.5rem;">12. Policy Updates</h3>
      <p>
        This policy may be updated periodically to reflect changes in regulations, payer requirements, or
        industry best practices. Material changes will be communicated to users. Continued use of the platform
        constitutes acceptance of updated policies.
      </p>
    </div>
  </div>

  <!-- Acknowledgment Section -->
  <div style="background: #f0fdf4; border: 2px solid #059669; padding: 1.5rem; border-radius: 8px; margin-bottom: 2rem;">
    <h3 style="color: #065f46; margin-top: 0;">Provider Acknowledgment</h3>
    <p style="color: #065f46; margin-bottom: 1rem;">
      By using this platform, you acknowledge that you have read, understood, and agree to comply with both
      the AI Use Policy and the Billing and Compliance Policy outlined above.
    </p>
    <p style="color: #065f46; margin: 0; font-size: 0.875rem;">
      <strong>Questions?</strong> Contact support@collagendirect.health for clarification on any policy matter.
    </p>
  </div>

</div>

<style>
.page-header {
  margin-bottom: 2rem;
}

.page-header h1 {
  font-size: 2rem;
  color: #0f172a;
  margin: 0;
}

h3 {
  font-size: 1.1rem;
  font-weight: 600;
}

p {
  margin-bottom: 1rem;
}

ul {
  margin-bottom: 1rem;
}

li {
  margin-bottom: 0.5rem;
}

strong {
  font-weight: 600;
  color: #0f172a;
}
</style>
