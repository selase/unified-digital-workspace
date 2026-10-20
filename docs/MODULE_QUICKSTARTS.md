# Workspace module quickstarts

Verified against the local source on 20 October 2026. This guide describes implemented web workflows; availability in your workspace depends on enabled modules, permissions, configuration and the version installed. It is not a package catalogue or a promise that every module is included. Ask your workspace administrator which modules are available.

## Getting started: your workspace and account

1. Open your organisation's workspace address and sign in using the account supplied by your administrator. Complete any prompted account verification or two-factor challenge.
2. If the onboarding wizard appears, complete the branding details and finish setup. Administrators can review organisation settings afterwards.
3. Open Dashboard for the workspace overview. Use the navigation to open enabled modules; use links in the application to open individual records rather than guessing their addresses.
4. Open **Help → Module Help** for this guide. Module home screens also show a **Module quickstarts** link, including when you have no records yet.
5. Administrators: open Users to add colleagues and assign appropriate roles. Use Roles to review permissions. An employee record in HRMS and a sign-in account are separate records; link them where needed.
6. Before inviting a whole team, check that a colleague with the intended role can open the required module and perform their task. A view permission does not necessarily allow creation, approval, export or management.

**Module setup:** an administrator with `core.settings.manage` can use Modules to enable an available module. Enable any dependency requested by the catalogue first. Several modules explicitly depend on Core; the organisation-specific Thyroid site extension depends on CMS Core. Enabling a module must also finish its database setup. If setup fails, ask support to investigate before using the module. Disabling a module can be blocked while another enabled module depends on it.

**Permissions:** role membership, module permissions, organisation membership, departmental audience and record ownership can all affect access. Org Admin and Org Superadmin are administrative roles, but do not assume a role label bypasses every record-level restriction. Ask an administrator to check the actual assigned permissions if access fails. Do not grant platform-wide administrator access as a workaround.

**Connected features:** enable and configure each participating module. Reports needs its source module and source-data permission; Workflow Automation needs a real event from its source module; facilities/vendor and real-estate/vendor tasks need Vendor Marketplace. The intranet's CMS news and controlled-document panels need the corresponding content modules and visible published content. Not all of these practical dependencies are declared as catalogue dependencies.

**Payments and notifications:** administrators must configure the appropriate payment provider before gateway checkout can work. A pending payment is not a confirmed payment. Notification delivery can depend on background processing and delivery configuration; ask support if processing stalls. This guide does not specify subscription prices, usage rates, tax treatment or backup guarantees.

_Source: `routes/subdomain.php`; `app/Http/Controllers/Admin/OnboardingController.php`; `app/Http/Controllers/Tenant/{UserController,RoleController,ModuleCatalogController}.php`; `app/Services/ModuleManager.php`; module `Config/module.php` files._

## 1. CMS Core: publish website content

1. Open CMS, then Posts, and create a post or page with a title and content. Choose the audience carefully: internal content is not intended for the public website.
2. Save a draft, review it, then publish with the appropriate permission. The page editor supports content blocks. Upload assets through Media and use Categories and Tags to organise content.
3. Use Menus to connect published content to navigation. Check the resulting page through the website link appropriate to your workspace. Archive content that should no longer be current.

**Limits:** publishing does not itself configure a custom domain or install an organisation-specific site design. Public presentation depends on the active theme, site module and domain routing. The Thyroid Ghana Foundation extension is an organisation-specific integration, not a general-purpose customer module.

_Source: `app/Modules/CmsCore/Routes/{web,public}.php`; `Http/Controllers/Web/{CmsPostLibraryController,CmsMediaLibraryController,CmsMenuLibraryController,CmsPublicPageController}.php`; `Views/partials/post-form.blade.php`._

## 2. CRM: move an inquiry into a client relationship

1. Open CRM and capture a walk-in or online inquiry. Record the prospect's contact details and source.
2. Open the lead, log interactions and update its status. Convert a qualified lead to a client account when appropriate.
3. Open Accounts to maintain client details and contacts. Create a deal, associate it with the account and use the pipeline or deal detail page to update its stage and activity history.

**Limits:** CRM records interactions and deal progress; a won deal is not proof that money has been received. Online inquiry capture depends on an implemented public form or integration.

_Source: `app/Modules/Crm/Routes/web.php`; `Http/Controllers/Web/CrmWebController.php`; `Views/{index,leads/show,accounts/show,deals/show}.blade.php`._

## 3. Document Management: control a document and its revisions

1. Open Documents and create a controlled draft with its identifying details and file where required.
2. Submit it for review. An authorised reviewer approves or returns it for revision; an authorised publisher publishes the approved document. Some creation options allow direct review or publication when permitted.
3. Readers open the published document, download the available version and acknowledge it when required. Complete an attached quiz if one has been assigned.
4. For a change, initiate revision and upload the next version. Use compliance, quiz analytics and audit screens to inspect acknowledgements and activity. Mark superseded content obsolete or archive it using the available lifecycle controls.

**Limits:** allowed transitions depend on current state and permissions. These records support your organisation's controls; they do not by themselves certify compliance with any external standard.

_Source: `app/Modules/DocumentManagement/Routes/web.php`; `Http/Controllers/Web/DocumentWebController.php`; `Views/{create,show}.blade.php`._

## 4. Memos: circulate and acknowledge correspondence

1. Open Memos and create a memorandum with its subject, body and recipients. Choose To, CC and BCC deliberately, and add attachments if needed.
2. Use the available submission/dispatch option. Open the memo to follow circulation and minutes.
3. Recipients acknowledge when required. Authorised participants can add minutes or sign using the controls shown on the detail page.

**Limits:** access and actions depend on your relationship to the memo. BCC recipients are confidential; do not manually copy their details into the memo body. An application signature is not a promise of recognition by an external signing authority.

_Source: `app/Modules/Memos/Routes/web.php`; `Http/Controllers/Web/MemoWebController.php`; `Views/{create,show}.blade.php`._

## 5. Incident Management: report, investigate and close

1. Open Incidents and create an incident with the available classification, impact and reporting details.
2. An authorised handler assigns or escalates it, records comments and creates corrective tasks. Use Tasks to follow outstanding work.
3. Record resolution details, complete the required tasks and close the incident when its state permits. Use Reports to review recorded incident activity.

**Limits:** closing a record does not establish that an external investigation or regulatory obligation has been completed. Follow your organisation's incident procedures.

_Source: `app/Modules/IncidentManagement/Routes/web.php`; `Http/Controllers/Web/{IncidentListController,IncidentTaskBoardController,IncidentReportController}.php`; `Views/{incidents,incidents-create,incidents-show,tasks,reports}.blade.php`._

## 6. Quality Monitoring: workplans, indicators and evidence

1. Open Workplans and initialise a workplan. Submit it for review; an authorised reviewer approves or rejects it.
2. Open the workplan to record updates against its available KPIs, log variances and corrective actions, and upload supporting evidence.
3. Download evidence from the workplan when permitted. Review the Alerts list and acknowledge alerts you have assessed.

**Limits:** full clinical audit authoring is not complete. Do not treat this module as a complete clinical accreditation or audit-management suite. KPI updates require indicators already available on the workplan.

_Source: `app/Modules/QualityMonitoring/Routes/web.php`; `Http/Controllers/Web/{WorkplanWebController,AlertListController}.php`; `Views/{create,show}.blade.php`; `conductor/tracks.md`._

## 7. Project Management: deliver tasks and track execution

1. Open Projects and create a project. Open its detail page to add members, milestones, tasks and risks.
2. Assign deliverables and update their status. Record time entries and maintain the project's budget and actual spend using the available controls.
3. Use stage-gate reviews when authorised. Review milestones, risk scores, logged hours and budget variance before progressing the project.

**Limits:** task dependencies, resource allocation, attachments and the interactive Gantt capability are API-only; there is no complete web workflow for them. Time entries and budget records do not run payroll or make payments.

_Source: `app/Modules/ProjectManagement/Routes/{web,api}.php`; `Http/Controllers/Web/ProjectWebController.php`; `Views/show.blade.php`; `conductor/tracks.md`._

## 8. HRMS Core: employee records, leave and HR reviews

1. HR administrators set up the organisation's directorates, departments, units and leadership in Departments. Create employee records and maintain dossier details such as emergency contacts, education and qualifications. Restrict sensitive personal and compensation information to the appropriate roles.
2. For leave, ensure the employee is linked to their account and the relevant leave categories, balances and holidays are configured. Submit through Leave Requests; authorised reviewers approve or reject requests.
3. Recruitment staff manage requisitions, internal posting records, candidate applications, interviews and offers. Record the offer decision and follow the supported status transitions.
4. Set up appraisal periods, templates and rating scales, then create appraisals and complete the available goals, competencies and reviews. Access depends on the participant's role and relationship to the appraisal.
5. HR staff maintain salary levels, steps and allowances, assign compensation, and submit promotions for supervisor and HR review.

**Limits:** there is no payroll processing, attendance tracking or public recruitment/careers application page. Marking a candidate hired creates onboarding tasks, but does not automatically create their employee record; HR must create it separately. Salary structures are records, not a payroll run.

_Source: `app/Modules/HrmsCore/Routes/web.php`; `Http/Controllers/Web/Hrms{EmployeeDirectory,EmployeeDossierWeb,DepartmentDirectory,LeaveRequest,Recruitment,AppraisalWeb,SalaryWeb,PromotionWeb}Controller.php`; corresponding employee, recruitment, appraisal and salary views; `conductor/tracks.md`._

## 9. Forums: discussions and private messages

1. Open Channels and choose an accessible channel, or create one when permitted.
2. Create a thread, open it and reply. Flag content that needs moderator attention.
3. Moderators use thread pin/lock controls and inspect the moderation queue. Use Messages to compose and read direct correspondence available to your account.

**Limits:** channel visibility and moderation permissions apply. A forum thread and a private message have different audiences; choose the appropriate screen before sharing sensitive information.

_Source: `app/Modules/Forums/Routes/web.php`; `Http/Controllers/Web/{ForumChannelDirectoryController,ForumThreadWebController,ForumMessageCenterController,ForumModerationQueueController}.php`; corresponding channel/thread/message views._

## 10. Intranet: internal news, boards and events

1. Open Intranet to read visible announcements and published internal news. Use search and filters to find relevant items.
2. Open a message board to post or reply. Managers can create boards, maintain posts and pin important items.
3. Review events and RSVP as attending, tentative or declined. Event managers can create and update events.

**Limits:** departmental content requires the correct organisational membership. News and controlled-policy panels depend on CMS and Document Management content; an empty panel is not necessarily an error. Board audiences include public options: check the selected audience before posting confidential material.

_Source: `app/Modules/Intranet/Routes/web.php`; `Http/Controllers/Web/IntranetWebController.php`; `Views/index.blade.php`._

## 11. Forms & Surveys: collect and review responses

1. Open Forms and create a form, survey or poll. Set the audience and add the required fields/questions.
2. Save a draft and review the questions before publishing. Use the form's supplied response link for the intended audience; restricted forms remain subject to access checks.
3. Open the form to inspect submissions, review individual responses and view results. Export CSV when permitted. Close submissions when collection ends.

**Limits:** only drafts can be edited or deleted. Published forms cannot be freely rewritten; prepare a new form when the questions need changing. A form labelled as an HR request does not itself perform the HRMS leave approval workflow. Poll response controls are not an identity-verification guarantee.

_Source: `app/Modules/Forms/Routes/web.php`; `Http/Controllers/Web/FormsWebController.php`; `Views/{create,show}.blade.php`._

## 12. Reports: preview and export allowed source data

1. Open Reports, create a report and choose an available source, columns and optional date range.
2. Save and open it to preview the result. Export CSV or PDF using the detail-page controls.
3. Keep sensitive definitions private. A private report is limited to its author or report managers; shared visibility does not remove source-data permission checks.

**Limits:** the registered sources are incidents, CRM inquiries, facilities work orders, store orders and real-estate listings. This is not an arbitrary SQL builder, an all-module join tool or a scheduled report-delivery service. Source permissions are required in addition to report permissions. Check column units: some monetary fields are stored/exported in minor units.

_Source: `app/Modules/Reports/Routes/web.php`; `Http/Controllers/Web/ReportsWebController.php`; `Services/{ReportDataSourceRegistry,ReportGeneratorService}.php`; `Views/{create,show}.blade.php`._

## 13. Workflow Automation: respond to implemented events

1. Open Workflow Automation and create a rule with a name and one of the offered events. The source module must be enabled and must actually perform that event.
2. Optionally add a condition using a field present in that event's payload. The web form offers equals, not equals, greater than, less than and text contains.
3. Choose **Dispatch Topbar In-App Notification** or **Log Institutional Audit Alert**. For a notification, enter an exact role name held by intended users in this organisation; the default `Super Admin` may match nobody. Templates can use `{rule_name}`, `{event}` and scalar event-payload fields.
4. Inspect the rule's execution logs and recipient count. Toggle the rule off when it should stop running.

**Offered events with real dispatch points:** incident logged (`incident.logged`); HR leave submitted (`leave.submitted`); CRM inquiry created (`crm.inquiry.created`); CRM lead converted (`crm.lead.converted`); store order placed and paid (`store.order.placed`, `store.order.paid`); facilities request created (`facilities.request.created`); memo dispatched (`memo.dispatched`); controlled document published (`document.published`); form submitted (`form.submitted`).

**Limits:** the web builder does not automate approvals, assign tasks, send arbitrary external webhooks or transfer money. Audit alerts write to the application log; they are not a new task or approval. Delivery uses queued event handling. **Test Run is a real execution, not a harmless preview:** it evaluates all active rules for the selected event in the organisation and can send actual notifications. Test only with deliberate payloads and recipients. A successful execution with zero recipients does not mean anyone was notified. Other events/actions present in engine code are not offered as customer web-builder workflows here.

_Source: `app/Modules/WorkflowAutomation/Routes/web.php`; `Http/Controllers/Web/WorkflowAutomationWebController.php`; `Services/WorkflowEngineService.php`; `Listeners/WorkflowDomainEventListener.php`; `Views/{create,show}.blade.php`. Dispatch points are in the corresponding source-module web controllers and `app/Modules/Store/Services/StoreInventoryService.php`._

## 14. Real Estate: parcels, listings and client allocations

1. Open Real Estate, create a project/enclave and register its parcels with the required boundaries and identifying details.
2. Maintain the parcel's compliance details and supporting documents. Advance through the available stages only when the required records have been supplied.
3. Create a public listing with its marketing details/images. Allocate an eligible parcel to a client and maintain its installment schedule.
4. Clients use Client Portal to review their allocations and initiate supported gateway payments. Authorised staff record offline receipts with a reference where offered. Open the receipt for a recorded paid installment.
5. Where Vendor Marketplace is configured, dispatch a related expert/material service job from the parcel controls.

**Limits:** recorded compliance stages and uploaded documents are not an independent legal title verification or a guarantee from a public authority. A payment initiation is not a receipt; wait for confirmed fulfillment. The client portal shows records associated with the signed-in client, not every allocation.

_Source: `app/Modules/RealEstate/Routes/{web,public}.php`; `Http/Controllers/Web/{RealEstateWebController,ClientPortalWebController,PublicRealEstateWebController}.php`; `Services/LandAcquisitionStateMachine.php`; `Views/index.blade.php`._

## 15. Vendor Marketplace: service jobs and escrow records

1. Open Vendor Marketplace. Authorised managers configure category rate cards and register service providers before dispatching a job.
2. Create the service job and issue its proforma invoice. Review the disclosed fee split before funding escrow through the configured gateway or the authorised offline-receipt process.
3. The awarded vendor records completion when the job and funding state allow it. Authorised staff review completion and release/settle escrow using the available controls.
4. Open a dispute when necessary; authorised staff record its resolution. Finance can record an external payout reference for a payout held for disbursement.

**Limits:** escrow release, fee allocation and payout status are application records. They do not guarantee an external bank transfer or automatic payout by the gateway. Finance must verify actual disbursement and retain the external reference. Do not treat a pending checkout as funded escrow or a dispute record as an automatic refund.

_Source: `app/Modules/VendorMarketplace/Routes/web.php`; `Http/Controllers/Web/VendorMarketplaceWebController.php`; `Services/EscrowSettlementService.php`; `Views/index.blade.php`._

## 16. Facilities Management: assets and maintenance requests

1. Register buildings and spaces, then add institutional assets with their location and lifecycle information.
2. Open an asset and create a preventive-maintenance schedule, or raise an institutional work order. Maintain schedules as equipment requirements change.
3. Assign and update work orders, then complete or cancel them using the permitted transitions. Automatic scheduled work-order generation requires the installation's background scheduler to be operational.
4. For occupant service requests, create the request with the appropriate building/space details. Vendor Marketplace handles the connected service-job and escrow workflow where configured.

**Limits:** completion can be blocked by assignment, service-job and funding state. Registering an asset or maintenance schedule does not itself procure parts or pay a contractor.

_Source: `app/Modules/FacilitiesManagement/Routes/web.php`; `Http/Controllers/Web/FacilitiesManagementWebController.php`; `Console/Commands/GeneratePreventiveWorkOrdersCommand.php`; `Views/index.blade.php`._

## 17. Store: products, orders and configured credit packages

1. Open Products to register products when you have management permission. Use the available store checkout controls to place an order.
2. Complete checkout through the configured payment gateway. Open Orders to inspect payment state. Authorised staff can confirm an offline payment with its required reference or cancel an eligible order.
3. Open Platform packages to purchase an available service-credit package. Credits are applied after verified payment fulfillment.

**Limits:** checkout creates a pending order/purchase; a redirect alone does not prove payment succeeded. Credit packages must be configured before they can be bought; an empty package list is not a free-credit entitlement. This guide does not promise shipping, returns management, recurring orders or a complete public storefront editor.

_Source: `app/Modules/Store/Routes/web.php`; `Http/Controllers/Web/StoreWebController.php`; `Services/StoreInventoryService.php`; store product, order and platform-package views._

## When something does not work

- **403 / forbidden:** confirm you are signed in to the correct organisation. Ask an administrator to check the action permission, role assignment, employee/account link and department, ownership or participant restrictions. Report the module and action that failed. A hidden approval button may reflect permissions or lifecycle state.
- **404 / not found:** reopen the record from its current module list. Confirm the correct workspace and that the module is enabled; the record may have been removed, unpublished or intentionally hidden from your account. Public pages also depend on published state, audience and domain routing. Do not change identifiers to look for another organisation's records.
- **No navigation entry / empty list:** confirm enablement and role permissions, then clear search/status/date filters. Your audience or record relationship may legitimately show fewer entries than an administrator sees.
- **Validation error / blocked transition:** read the field errors, supply required information and check the record's current state. Draft-only editing, prerequisite reviews, required references and escrow funding can prevent an action.
- **Payment pending / missing notification:** avoid repeating payments to force a status change. Provide support with the order/proforma reference and time; ask them to inspect payment fulfillment or background delivery. Never send passwords, gateway secrets or full card details.
- **500 / server error:** report the screen, action, approximate time and a non-sensitive record reference to support. This is not normally fixed by granting yourself more permissions.

## Source verification and release limits (for administrators and support)

Source paths in module sections are relative to the named module directory unless prefixed with `app/`, `routes/` or `conductor/`. They are evidence pointers for maintainers, not addresses to paste into the browser. Use installed navigation and generated record links for customer access.

This guide was checked against routes, controller actions, representative views, module manifests and the report/workflow service implementations. It was not a live browser acceptance test, payment test or production verification. Existing uncommitted implementation work was present during review; deployment parity has not been established.

Operator checklist before customer rollout: verify intranet with a published controlled document; incident task/report screens with real assignments; memo detail with To/CC/BCC recipients; document detail in a dedicated tenant database; newly created organisation administrators' module permissions; HRMS enablement and cross-tenant setup; invoice/newsletter routes. Release approval remains pending acceptance of these checks on the installed version.

In-app access is available through the authenticated `help.modules` route, the workspace Help menu and shared module-home help prompts. The guide is bundled from `docs/MODULE_QUICKSTARTS.md`; a missing bundle shows a support message instead of the guide. Module bundles, usage billing configuration, tax configuration and the separate pricing sheet are outside this quickstart. No pricing or tax figures are asserted here. Backup configuration and restore verification are operational work outside this guide.
