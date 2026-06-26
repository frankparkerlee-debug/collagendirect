# HealKit — How It Works & How to Order

_Last updated: 2026-06-26_

HealKit is CollagenDirect's **simplified wound-supply program**. Unlike insurance
(referral) orders, HealKit needs **no insurance documentation** and is **billed to the
practice at wholesale pricing, by the actual piece** (not by whole boxes).

- **No required docs.** Patient ID, insurance card, and clinical notes are all optional.
- **Ships to your office by default.** You can pick another delivery location per order.
- **Priced per piece at your wholesale rate.** If your practice has negotiated wholesale
  pricing, that price is used; otherwise the standard wholesale price (per box ÷ pieces
  per box) applies.

---

## Part 1 — Placing a HealKit order (provider / practice staff)

1. **Open the portal** and click **HealKit Order** in the left sidebar
   (or go to `…/portal/?page=healkit`).

2. **Choose the patient.**
   - Start typing a name to search existing patients, **or**
   - Click **+ New Patient**. Only **First Name** and **Last Name** are required —
     everything else (DOB, phone, address, insurance) is optional.

3. **Confirm the delivery location.**
   - Orders default to your **office** (your practice's primary location).
   - To ship elsewhere, choose **Another location** and enter a **Location Name** and
     **Address**.

4. **Add the wound(s) and products.**
   - For each wound, pick the **Product Type**, then the **Size**.
   - Set **Quantity per change**, **Frequency / week** (defaults to **1**), and
     **Duration in days** (defaults to **7**). The up/down arrows are always visible.
   - Add additional wounds/products as needed.

5. **(Optional) Attach documents or notes.** Upload an ID, insurance card, or paste
   clinical notes only if you'd like us to review notes or verify benefits. Not required.

6. **Sign and submit.** Your name and credentials are pre-filled — confirm them and
   click **Submit**.

That's it. The order is created and sent to CollagenDirect for fulfillment.

> **How many pieces ship?** Pieces = ⌈ (Duration ÷ 7) × Frequency/week × Qty per change ⌉.
> HealKit ships and bills in **pieces**, so a 7-day, 1×/week, 1-per-change order is **1 piece**.

---

## Part 2 — What happens next (CollagenDirect admin)

HealKit orders appear in the admin portal under **HealKit Orders**
(`…/admin/healkit-orders.php`), separate from referral and wholesale orders.

1. **Review** the order (patient, practice, products, pieces).
2. **Approve** or **Reject**.
3. **Mark Shipped** and (optionally) add a **tracking number**.

Statuses: Pending → Approved → Shipped → Delivered (or Rejected).

---

## Part 3 — Pricing & revenue

- **Unit:** HealKit bills **per piece** (referral is per piece at the Medicare rate;
  wholesale is per box). HealKit uses the **wholesale price, per piece**.
- **Rate per piece** = practice wholesale custom price if one is set, otherwise
  `price_wholesale ÷ pieces_per_box`.
- **Order revenue** = pieces shipped × per-piece wholesale rate.
- **Practice pricing is shared across a practice's logins.** If a practice has more than
  one user account, a wholesale price set on any one of them applies to all of them.

**Example** — CollaHeal Collagen Dressing, wholesale **$900 / box of 10** → **$90 / piece**.
An order needing 13 pieces = **13 × $90 = $1,170**.

---

## Part 4 — Where HealKit shows up

- **Revenue Report** (`…/admin/revenue-report.php`): its own **HealKit** KPI card, a
  **HealKit Only** filter, a **HealKit** column in the per-rep and per-physician
  breakdowns, and a **HealKit** tag on each order in the detailed table. CSV export
  includes HealKit totals.
- **Admin → HealKit Orders**: the working queue for approving and shipping.

---

## Notes for staff

- HealKit is for **practice-pay (cash) wholesale supply**, not insurance billing. If a
  patient's supplies should be billed to insurance, use a **Referral** order instead.
- Two practices (Richard S. Cohen, DPM, PA and Med Effects) placed orders through the
  referral section before HealKit was working; those historical orders have been
  **reclassified to HealKit** and repriced to wholesale-per-piece.
