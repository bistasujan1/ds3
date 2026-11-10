---
name: project-ds3-phase1
description: DS3 Phase 1 scope, deadline, key facts from the kickoff meeting (Nov 9 2026)
metadata:
  type: project
---

Phase 1 target is mid-December 2026 (Mihir's target, more aggressive than Ashwin's "first week of January").

**Why:** Ashwin has been managing all workflows via email/WhatsApp/phone. ~350 pharmacy clients. Two warehouses (Irving TX, LSL Indiana). The Rx ERP system tried for 1.5 years failed to implement — building in-house.

**Phase 1 scope:** Order Management only — receive orders from clients, allocate stock, ship, track delivery. Both admin portal AND client ordering portal are in scope.

**Explicitly deferred:** vendor portal, email/SMS notifications (but `reunite/email` and `reunite/sms` must be pulled into Composer now per Mihir's instruction).

**How to apply:** Prioritize Order workflow (orders, quotes, invoices, clients module) for all sprint/task planning. Do not delay by building non-Phase-1 modules first. When estimating, mid-December 2026 is the hard target.

**Naming:** Do not use "RxVanta" anywhere — use "DS3" in namespaces, routes, constants, and UI text.

**Warehouses (named):**
- Irving, TX — DS3-owned, most active, day-to-day fulfillment
- LSL (Larry's / 3PL), Indiana — bulk storage, can ship direct-to-pharmacy

**Clients:** ~350 pharmacies. Each client's pricing is individually negotiated.

**Products:** Dynamic catalog — new ingredients added as market demand changes (recent: peptides, GLP-1 analogues). Not a fixed list.

**Vendor geography:** ~90% China, ~10% India.

**Key schema gaps added from this meeting:**
- `inventory_lots.status` now includes `Expired` and `Written_off`
- `client_allocations` table added (standing weekly reservations per client/product)

**Gaps found from workflow diagram v5 (Nov 10 2026) — corrected in schema.md + architecture.md:**
- `users.role` ENUM now includes `Manager` (approval authority for POs, credit memos, escalations)
- `orders.status` expanded to 14 states matching the warehouse pick→pack→ship flow
- `orders` table: added `tracking_number`, `carrier`, `pick_list_approved_by/at`, `picked_at`, `packed_at`, `documents_verified_at`, `client_confirmed_at/by`
- `inventory_lots.status` added `Picked`, `In Transit`, `Rejected`; FEFO rule documented
- `invoices.status` added `Posted` (Hardshells AR ledger) and `Closed`
- `purchase_orders.status` added `Approved`
- New tables: `pick_list_items`, `client_users`, `invoice_reminders`, `document_requirements`, `capa_log`
- `notifications.type` expanded to 12 types

**Open items requiring DS3 decision:**
- Hardshells handoff: format (CSV/PDF?) and email address — ask Ashwin/Finance
- Carrier API: UPS/FedEx account credentials for label generation — manual entry for Phase 1 until provided
- `Manager` role: confirm whether a named Manager role is distinct from Admin, or whether Admin covers it

**Phase 1 priority gap — client portal:**
- `/v2/portals/client/` — zero files exist. Phase 1 scope requires pharmacy clients to log in and place/view orders. `client_users` table now in schema. Must be built before Phase 1 deadline (mid-Dec 2026).

**Key contacts:**
- Ashwin Prabhakar — business owner, point of contact for architecture/product decisions
- Mihir Patel — technical lead / CTO equivalent
- Manish B — developer (Nepal, joining soon)
- Sujan Bista — developer (you)
- Stephanie — System Manager/Director (heavy warehouse workflow lifting)
- Ross — COO
