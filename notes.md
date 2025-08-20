1. Specifications Field
Requirement
Add a “Specifications” field that summarizes service-specific details:
Roof: Replace/Repair, Asphalt/Metal/Tile/Flat
Windows: Replace/Repair, 3-5/6-9/10+
Bath: Replace bath/shower, Remove & install new bathroom, New walk-in tub
Siding: Replace/Remove/Add/Install, Wood composite/Aluminum/Fiber cement
Kitchen: Upgrade/Repair, Countertops/Cabinets/Appliances/Islands
Decks: Replace/Repair, Cedar/Redwood
Current State
Service-specific fields are captured and saved.
Emails and Google Sheets do not have a unified “Specifications” field; details are scattered.
Missing
No consolidated “Specifications” field in admin view, emails, or Google Sheets.
2. ZIP Code → City/State
Requirement
When user enters ZIP code, show city and state in Google Sheets.
Current State
Only ZIP code is saved and sent.
No city/state lookup or display.
Missing
No logic to convert ZIP code to city/state.
No city/state field in Google Sheets payload.
3. Date/Time Selection: 72-Hour Availability
Requirement
“Select a date and time” should only show slots for the next 72 hours.
Current State
Availability is shown for a wider date range (not limited to 72 hours).
Missing
No restriction to 72-hour window in frontend or backend availability logic.
4. Ad Tracking
Requirement
Correctly track ads based on the link you shared (UTM parameters, gclid, referrer).
Current State
UTM, gclid, and referrer are captured and saved.
Data is injected into the form and saved in post meta.
Data is sent to Google Sheets and shown in admin/email.
Missing
Tracking is implemented and appears correct, but needs validation against the actual ad link structure you provided.
5. Summary of Implementation Plan
Step 1: Specifications Field
Define a function to generate a “Specifications” string from service-specific fields.
Add this field to:
Admin booking view
Admin email template
Google Sheets payload
Step 2: ZIP Code → City/State
Use a ZIP code lookup (local JSON or API) to get city/state.
Add city/state to:
Admin booking view
Admin email template
Google Sheets payload
Step 3: 72-Hour Availability
Update backend availability logic to only return slots within the next 72 hours.
Update frontend to restrict date picker to next 3 days.
Step 4: Ad Tracking Validation
Review the ad link you provided.
Ensure all UTM/gclid/referrer fields are captured and sent correctly.
Test with a sample booking using your ad link.
Step 5: End-to-End Testing
Submit bookings with all combinations (multi-appointment, all services, ZIP codes).
Verify:
Admin area shows all required fields.
Admin email includes all details.
Google Sheet receives all fields in one row.
Let me know if you want to proceed with implementation or need a more detailed breakdown for any step.