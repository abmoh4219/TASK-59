# questions.md — Workforce & Operations Hub

## 1. What Are "Business Hours" for SLA Calculation?

**Question:** The prompt says "SLA of 24 business hours per step" but does not define what business hours means — Monday to Friday only? What hours of the day count?

**My Understanding:** A US-based organization typically operates Monday through Friday, 8 AM to 6 PM local time. Business hours exclude weekends and outside those hours. The prompt says this is a US-based organization with no further specification, so standard US business hours are the safest default.

**Solution Implemented:** SLA clock counts only Mon–Fri 8:00 AM–6:00 PM. These hours are stored as a configurable system setting (System Administrator can adjust start/end time via System Config page). The SlaService.calculateDeadline() method advances time by business hours only, skipping weekends and outside-window hours.

---

## 2. "Approved Offsite" — When Is It Automatically Resolved?

**Question:** The prompt lists "approved offsite time" as one of the exception flags shown on the attendance card. When does this flag auto-resolve versus require employee action?

**My Understanding:** If an employee has an approved business trip or outing request that covers the date in question, the attendance exception should be automatically flagged as APPROVED_OFFSITE rather than requiring the employee to file a new correction. The approval of the trip/outing request itself serves as the resolution.

**Solution Implemented:** AttendanceEngineService checks ExceptionRequest records with status=APPROVED and type IN (BUSINESS_TRIP, OUTING) that cover the attendance date. If found, the exception is classified as APPROVED_OFFSITE (shown in green) rather than ABSENCE or MISSED_PUNCH. No further employee action required for that day.

---

## 3. How Are "Split Issuance" and "Merged Allocation" Defined?

**Question:** The prompt mentions "split issuance when a booking includes multiple travelers" and "merged allocations across multiple bookings." These two concepts need precise definitions.

**My Understanding:** Split issuance means: one booking with multiple travelers creates separate allocation records for each traveler (one allocation per person), so each person's cost-center charge is tracked individually. Merged allocation means: if the same cost center has multiple separate bookings, their charges are combined into a single allocation record for billing efficiency.

**Solution Implemented:** BookingService.createBooking() with travelers array creates one BookingAllocation per traveler (split issuance). A separate mergeAllocations() process groups all allocations for the same cost center within a billing period into one combined record. Both are tracked separately in the booking_allocations table with an allocation_group_id linking merged records.

---

## 4. What Exactly Is a "Bookable Internal Resource"?

**Question:** The prompt says "bookable internal resources tied to trips or outings" without specifying what these resources are — vehicles, conference rooms, accommodation, equipment?

**My Understanding:** In the context of a US-based organization managing business trips and outings, internal bookable resources most likely include: company vehicles, meeting rooms, accommodation units (for dormitory context), and shared equipment. The prompt does not restrict the types, so a general resource catalog with configurable types is most appropriate.

**Solution Implemented:** Resource entity has a type field (VEHICLE, ROOM, ACCOMMODATION, EQUIPMENT, OTHER — configurable). System Administrator manages the resource catalog. When employees create a business trip or outing request, they can optionally book an available resource. Cost center is the only "payment" mechanism — no money changes hands.

---

## 5. What Is the Filing Window Enforcement Rule Exactly?

**Question:** The prompt says "requests must be filed within 7 calendar days." Does this mean 7 days from when the exception occurred, or 7 days from when the employee noticed it?

**My Understanding:** "7 calendar days" most naturally means from the date the attendance exception was detected (i.e., the date of the attendance record). The exception is detected the morning after the event, so the 7-day clock starts from the attendance record date.

**Solution Implemented:** ExceptionRequest creation validates: request.filedAt <= attendanceRecord.recordDate + 7 calendar days. The UI shows the remaining days to file ("5 days remaining to file this exception") as a countdown. After the window closes, the submit button is disabled with message "Filing window closed — this exception can no longer be disputed."

---

## 6. What Does "Withdraw Before the First Approver Acts" Mean Precisely?

**Question:** The prompt says employees "can withdraw a pending request before the first approver acts." Does "acts" mean viewed the request, or actually approved/rejected it?

**My Understanding:** "Acts" means the approver has taken an action (approved, rejected, or escalated) — not merely viewed the request. A request that has been seen but not yet approved/rejected is still withdrawable.

**Solution Implemented:** Withdraw is permitted when: ApprovalStep for stepNumber=1 has status=PENDING (no approve/reject action recorded). As soon as an ApprovalAction record exists for step 1, withdrawal is blocked. The withdraw button is shown/hidden dynamically based on this condition.

---

## 7. What Is "Reassignment If an Approver Is Out"?

**Question:** The prompt says users can "request reassignment if an approver is out." Who initiates the reassignment — the employee submitting the request, or a manager?

**My Understanding:** Both parties should have this ability. The employee can flag "my approver is out" to trigger reassignment. The approver themselves can also mark themselves as "out" system-wide (setting isOut=true on their user account), which automatically triggers reassignment of pending items.

**Solution Implemented:** Users have an isOut boolean flag settable by themselves or Admin. ApprovalWorkflowService checks isOut flag for current step's approver. If true, the step is automatically reassigned to the backup approver. Employees can also manually click "Request Reassignment" on a pending request, which calls the same reassign logic.

---

## 8. How Are "Anomaly Alerts" for Repeated Failed Logins Surfaced?

**Question:** The prompt says the system "raises local anomaly alerts for repeated failed logins." Where and how are these alerts shown — email, in-app notification, or admin dashboard?

**My Understanding:** Since the system is completely offline with no external network services, email is not an option. The alerts must be in-system. The most practical approach is an in-app notification visible to System Administrator users, plus an entry in the audit log.

**Solution Implemented:** When an account is locked (5th failure), AnomalyDetectionService creates an AnomalyAlert record. System Administrators see a notification bell badge in the top bar showing unread anomaly count. Clicking shows a list of recent anomalies (account locked events, unusual activity). The alert is also written to the immutable audit log.

---

## 9. What Does "Approved Offsite" in the Attendance Context Mean?

**Question:** The attendance card shows flags for "approved offsite time" — is this a separate exception type, or is it the absence of an exception because offsite work was pre-approved?

**My Understanding:** "Approved offsite" is shown on the card as context — it explains why the employee has no punches (they were working offsite on an approved basis). It is displayed as a green informational badge, not a red exception requiring action. It confirms to the employee (and their supervisor) that the day is accounted for.

**Solution Implemented:** APPROVED_OFFSITE is a special exception type that renders as a green badge on the attendance card with text "Offsite — Approved" rather than a red exception. It does not create an ExceptionRequest requirement. The card still shows the approved trip/outing details (type, dates, approved by) so the record is complete.

---

## 10. How Should the 72-Hour Rating Window Be Enforced?

**Question:** The prompt says employees "can rate the work within 72 hours." 72 hours from what exact moment — when the technician marks it complete, or when the status changes in the system?

**My Understanding:** The most defensible and auditable definition is 72 hours from the timestamp when the status was officially changed to "completed" in the system (WorkOrder.completedAt). This is a system-recorded timestamp, not subject to interpretation.

**Solution Implemented:** WorkOrder.completedAt is set automatically when a Technician transitions status to COMPLETED. The rating endpoint validates: NOW() <= completedAt + 72 hours. The rating button on the frontend shows a live countdown ("Rate by: 04/15/2024 3:45 PM — 18h 23m remaining"). After window closes, button is replaced with "Rating window closed."

---

## 11. What CSV Format Is Expected for Punch File Import?

**Question:** The prompt says attendance is "generated nightly at 2:00 AM from locally imported punch files (CSV)" but does not specify the CSV column format.

**My Understanding:** Punch file systems commonly export: employee ID, date, event type (IN/OUT), and timestamp. These four fields are the minimum needed for the attendance engine to function. Additional fields (if present) should be ignored.

**Solution Implemented:** CSV format: employee_id, date (MM/DD/YYYY), event_type (IN or OUT), time (HH:MM:SS). Header row is optional (auto-detected). Extra columns are silently ignored. Rows with missing required fields generate a validation error in the import summary. Duplicate punch events (same employee + date + type + time) are skipped with a warning.

---

## 12. What Happens When Escalation Backup Approver Is Also Unavailable?

**Question:** The prompt says escalation assigns to "a designated backup approver." What if the backup approver is also marked as out or unavailable?

**My Understanding:** There must be an ultimate fallback to prevent requests from being permanently stuck. HR Admin is the natural organizational fallback for attendance matters since they handle policy overrides.

**Solution Implemented:** Escalation chain: (1) check user.backupApproverId → if not out, assign there. (2) If backup is also out, assign to the HR Admin user. (3) If no HR Admin is available, assign to System Administrator. This chain is tried in order and the first available non-out user receives the escalation. The audit log records the full escalation chain.