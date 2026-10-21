@php
    $pricingUrl = route('product.template').'#pricing';
    $hasRegistration = \Illuminate\Support\Facades\Route::has('register') && config('app.system_setting.allow_registration', false);
    $hasEnquiry = \Illuminate\Support\Facades\Route::has('product.enterprise');
    $hasLogin = \Illuminate\Support\Facades\Route::has('login');
    $websitePlans = config('usage-pricing.hybrid.website_plans', []);
@endphp
@php($workspaceHome = true)
@extends('layouts.workspace-public')

@section('content')

    {{-- HERO: Left-Aligned Architectural Split --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="hero-title">
        <div class="hero-top">
            <div class="hero-main">
                <div class="index-kicker">Unified Digital Workspace</div>
                <h1 id="hero-title">
                    Every tool for your work.
                    <span class="blue-accent">One shared workspace.</span>
                </h1>
                <p class="hero-lead">
                    Projects to move forward. Documents and memos to get right. People to support. Public websites and forms to run. Bring your organisation’s everyday work into one connected place.
                </p>
                <div class="hero-actions">
                    @if ($hasRegistration)
                        <a class="btn btn-lg" href="{{ route('register') }}">Create your workspace</a>
                    @elseif ($hasEnquiry)
                        <a class="btn btn-lg" href="{{ route('product.enterprise') }}">Plan your workspace</a>
                    @elseif ($hasLogin)
                        <a class="btn btn-lg" href="{{ route('login') }}">Log in to your workspace</a>
                    @endif
                    <a class="btn btn-lg btn-outline" href="#tools">Explore the tools &rarr;</a>
                </div>
            </div>

            <aside class="hero-aside" aria-label="Workspace tool index">
                <div class="hero-aside-top">
                    <div class="hero-aside-label">Connected Workspace Tools</div>
                    <ul class="tool-index-list">
                        <li class="tool-index-item">
                            <span>Projects &amp; Tasks</span>
                            <span class="desc">Milestones &middot; Time</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Documents &amp; Memos</span>
                            <span class="desc">Versions &middot; Sign-offs</span>
                        </li>
                        <li class="tool-index-item">
                            <span>People &amp; HR</span>
                            <span class="desc">Records &middot; Leave</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Forms &amp; Surveys</span>
                            <span class="desc">Intake &middot; Routing</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Public Websites</span>
                            <span class="desc">CMS &middot; Domains</span>
                        </li>
                        <li class="tool-index-item">
                            <span>Facilities &amp; Assets</span>
                            <span class="desc">Leases &middot; Work orders</span>
                        </li>
                    </ul>
                </div>
                <div class="hero-aside-bottom">
                    <div class="stat-row">
                        <strong>One workspace</strong>
                        <span>Tenant-isolated</span>
                    </div>
                    <p>Enable only the tools your organisation needs today, under one login and one permission model.</p>
                </div>
            </aside>
        </div>

        {{-- THE ICONIC BLUE STAGE: Interactive Live Workbench --}}
        <div class="workbench-stage" id="workspace">
            <div class="stage-header">
                <div class="stage-header-left">
                    <h2>Inside the Unified Digital Workspace</h2>
                    <p>Switch between tools below to see how daily work stays connected in one interface.</p>
                </div>

                <div class="stage-tabs" role="tablist" aria-label="Workspace tool preview">
                    <button type="button" class="stage-tab" role="tab" id="tab-overview" aria-selected="true" aria-controls="view-overview" onclick="selectWorkspaceView('overview')">Overview</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-projects" aria-selected="false" aria-controls="view-projects" onclick="selectWorkspaceView('projects')">Projects</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-documents" aria-selected="false" aria-controls="view-documents" onclick="selectWorkspaceView('documents')">Documents &amp; Memos</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-people" aria-selected="false" aria-controls="view-people" onclick="selectWorkspaceView('people')">People &amp; HR</button>
                    <button type="button" class="stage-tab" role="tab" id="tab-sites" aria-selected="false" aria-controls="view-sites" onclick="selectWorkspaceView('sites')">Sites &amp; Forms</button>
                </div>
            </div>

            <figure class="app-window" aria-labelledby="workbench-caption">
                <div class="app-titlebar">
                    <div class="app-titlebar-left">
                        <span class="window-controls" aria-hidden="true"><i></i><i></i><i></i></span>
                        <span class="app-path">Example Organisation <span>/</span> <strong id="active-path-label">Workspace Overview</strong></span>
                    </div>
                    <span>Unified Digital Workspace</span>
                </div>

                <div class="app-body">
                    <aside class="app-sidebar" aria-label="Illustrative workspace navigation">
                        <div>
                            <div class="app-nav-group-label">Your Workspace</div>
                            <div class="app-nav">
                                <button type="button" class="app-nav-btn active" data-nav="overview" onclick="selectWorkspaceView('overview')">
                                    <span>Overview</span>
                                    <span class="count">04</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="projects" onclick="selectWorkspaceView('projects')">
                                    <span>Projects &amp; Tasks</span>
                                    <span class="count">12</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="documents" onclick="selectWorkspaceView('documents')">
                                    <span>Documents &amp; Memos</span>
                                    <span class="count">38</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="people" onclick="selectWorkspaceView('people')">
                                    <span>People &amp; Leave</span>
                                    <span class="count">48</span>
                                </button>
                                <button type="button" class="app-nav-btn" data-nav="sites" onclick="selectWorkspaceView('sites')">
                                    <span>Sites &amp; Forms</span>
                                    <span class="count">06</span>
                                </button>
                            </div>
                        </div>

                        <div class="app-sidebar-footer">
                            <strong>Connected Context</strong>
                            Shared roles, audit history, and directory across every tool.
                        </div>
                    </aside>

                    <div class="app-main">
                        {{-- View 1: Overview --}}
                        <div class="workspace-view active" id="view-overview" role="tabpanel" aria-labelledby="tab-overview">
                            <div class="view-header">
                                <div>
                                    <h3>A clearer working day.</h3>
                                    <p>Your projects, documents, approvals, and website updates in one shared queue.</p>
                                </div>
                                <div class="view-meta">TODAY'S FOCUS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Active Projects</div>
                                    <div class="m-val">12</div>
                                    <div class="m-sub">3 milestones due this week</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Documents &amp; Memos</div>
                                    <div class="m-val">38</div>
                                    <div class="m-sub">2 awaiting sign-off</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">People &amp; Requests</div>
                                    <div class="m-val">48</div>
                                    <div class="m-sub">1 leave approval pending</div>
                                </div>
                            </div>

                            <p class="table-scroll-hint">Scroll the table sideways to see all columns.</p>
                            <div class="ws-table-scroll" role="region" aria-label="Illustrative workspace records" tabindex="0">
                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Item in Focus</th>
                                        <th>Tool</th>
                                        <th>Owner</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Prepare the Q4 Website &amp; Campaign Launch</div>
                                            <div class="row-sub">Milestone deliverable and media review</div>
                                        </td>
                                        <td>Projects</td>
                                        <td>Operations Team</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Review Updated Staff Handbook (Version 3.2)</div>
                                            <div class="row-sub">Controlled document shared with department leads</div>
                                        </td>
                                        <td>Documents</td>
                                        <td>People &amp; Culture</td>
                                        <td><span class="ws-status warn">For review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Acknowledge Operations Circular #2026-04</div>
                                            <div class="row-sub">Internal memo distribution &amp; receipt log</div>
                                        </td>
                                        <td>Memos</td>
                                        <td>Executive Office</td>
                                        <td><span class="ws-status">Awaiting sign-off</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Publish Monthly Organisation Update</div>
                                            <div class="row-sub">Public newsroom post and intake form</div>
                                        </td>
                                        <td>Sites &amp; Forms</td>
                                        <td>Communications</td>
                                        <td><span class="ws-status done">Ready</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- View 2: Projects --}}
                        <div class="workspace-view" id="view-projects" role="tabpanel" aria-labelledby="tab-projects">
                            <div class="view-header">
                                <div>
                                    <h3>Projects, tasks, and milestones.</h3>
                                    <p>Organise deliverables, assign owners, and log time without leaving the workspace.</p>
                                </div>
                                <div class="view-meta">PROJECTS &amp; TASKS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Open Tasks</div>
                                    <div class="m-val">24</div>
                                    <div class="m-sub">Across 4 active workstreams</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Milestones on Track</div>
                                    <div class="m-val">9 / 9</div>
                                    <div class="m-sub">Current quarter schedule</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Logged Hours</div>
                                    <div class="m-val">142h</div>
                                    <div class="m-sub">Recorded on timesheets</div>
                                </div>
                            </div>

                            <p class="table-scroll-hint">Scroll the table sideways to see all columns.</p>
                            <div class="ws-table-scroll" role="region" aria-label="Illustrative workspace records" tabindex="0">
                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Task</th>
                                        <th>Project</th>
                                        <th>Logged</th>
                                        <th>Stage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Finalise service intake workflow</div>
                                            <div class="row-sub">Connect public form submissions to project tasks</div>
                                        </td>
                                        <td>Client Operations</td>
                                        <td>14.5 hrs</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Annual facility inspection checklist</div>
                                            <div class="row-sub">Coordinate maintenance work orders across offices</div>
                                        </td>
                                        <td>Facilities &amp; Assets</td>
                                        <td>8.0 hrs</td>
                                        <td><span class="ws-status warn">Under review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Publish updated partner directory page</div>
                                            <div class="row-sub">Approved copy and media assets attached</div>
                                        </td>
                                        <td>Website Launch</td>
                                        <td>6.0 hrs</td>
                                        <td><span class="ws-status done">Completed</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- View 3: Documents & Memos --}}
                        <div class="workspace-view" id="view-documents" role="tabpanel" aria-labelledby="tab-documents">
                            <div class="view-header">
                                <div>
                                    <h3>Controlled documents and official memos.</h3>
                                    <p>Keep policies versioned, share files with the right roles, and track memo sign-offs.</p>
                                </div>
                                <div class="view-meta">DOCUMENTS &amp; MEMOS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Controlled Files</div>
                                    <div class="m-val">182</div>
                                    <div class="m-sub">Version history preserved</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Official Memos</div>
                                    <div class="m-val">14</div>
                                    <div class="m-sub">With recipient acknowledgement</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Access Control</div>
                                    <div class="m-val">Role-Based</div>
                                    <div class="m-sub">Scoped by department &amp; team</div>
                                </div>
                            </div>

                            <p class="table-scroll-hint">Scroll the table sideways to see all columns.</p>
                            <div class="ws-table-scroll" role="region" aria-label="Illustrative workspace records" tabindex="0">
                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Document / Memo</th>
                                        <th>Version</th>
                                        <th>Access</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Staff Handbook &amp; Leave Policy</div>
                                            <div class="row-sub">Updated annual and study leave guidelines</div>
                                        </td>
                                        <td>Version 3.2</td>
                                        <td>All Staff</td>
                                        <td><span class="ws-status done">Published</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Executive Circular #2026-04: Q4 Budget</div>
                                            <div class="row-sub">Mandatory department head acknowledgement</div>
                                        </td>
                                        <td>Memo</td>
                                        <td>Department Heads</td>
                                        <td><span class="ws-status">4 of 6 signed</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Vendor Service Level Agreement Template</div>
                                            <div class="row-sub">Standard contract for marketplace suppliers</div>
                                        </td>
                                        <td>Version 2.0</td>
                                        <td>Operations &amp; Legal</td>
                                        <td><span class="ws-status done">Approved</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- View 4: People & HR --}}
                        <div class="workspace-view" id="view-people" role="tabpanel" aria-labelledby="tab-people">
                            <div class="view-header">
                                <div>
                                    <h3>Support your people and teams.</h3>
                                    <p>Maintain employee records, departments, leave requests, and performance appraisals.</p>
                                </div>
                                <div class="view-meta">PEOPLE &amp; HR</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Team Directory</div>
                                    <div class="m-val">48</div>
                                    <div class="m-sub">6 structured departments</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Leave Requests</div>
                                    <div class="m-val">2</div>
                                    <div class="m-sub">Awaiting manager approval</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Appraisal Cycle</div>
                                    <div class="m-val">Active</div>
                                    <div class="m-sub">Quarterly reviews in progress</div>
                                </div>
                            </div>

                            <p class="table-scroll-hint">Scroll the table sideways to see all columns.</p>
                            <div class="ws-table-scroll" role="region" aria-label="Illustrative workspace records" tabindex="0">
                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Employee / Request</th>
                                        <th>Department</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Evelyn Asare — Annual Leave Request</div>
                                            <div class="row-sub">5 working days requested &middot; 16 days balance remaining</div>
                                        </td>
                                        <td>People &amp; Culture</td>
                                        <td>Leave Approval</td>
                                        <td><span class="ws-status warn">Awaiting review</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Kwame Osei — Q4 Performance Appraisal</div>
                                            <div class="row-sub">Self-assessment submitted for manager review</div>
                                        </td>
                                        <td>Facilities &amp; Operations</td>
                                        <td>Appraisal</td>
                                        <td><span class="ws-status">In progress</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">New Staff Onboarding Checklist</div>
                                            <div class="row-sub">Workspace access, department assignment, and handbook</div>
                                        </td>
                                        <td>Communications</td>
                                        <td>Directory</td>
                                        <td><span class="ws-status done">Completed</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>

                        {{-- View 5: Sites & Forms --}}
                        <div class="workspace-view" id="view-sites" role="tabpanel" aria-labelledby="tab-sites">
                            <div class="view-header">
                                <div>
                                    <h3>Your public website and intake forms.</h3>
                                    <p>Publish pages, news posts, media, and forms right alongside your internal tools.</p>
                                </div>
                                <div class="view-meta">SITES &amp; FORMS</div>
                            </div>

                            <div class="metrics-strip">
                                <div class="metric-cell">
                                    <div class="m-label">Published Pages</div>
                                    <div class="m-val">18</div>
                                    <div class="m-sub">Custom domain connected</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Active Forms</div>
                                    <div class="m-val">6</div>
                                    <div class="m-sub">Public &amp; internal surveys</div>
                                </div>
                                <div class="metric-cell">
                                    <div class="m-label">Recent Submissions</div>
                                    <div class="m-val">37</div>
                                    <div class="m-sub">Routed to team queues</div>
                                </div>
                            </div>

                            <p class="table-scroll-hint">Scroll the table sideways to see all columns.</p>
                            <div class="ws-table-scroll" role="region" aria-label="Illustrative workspace records" tabindex="0">
                            <table class="ws-table">
                                <thead>
                                    <tr>
                                        <th>Page / Form</th>
                                        <th>Path</th>
                                        <th>Activity</th>
                                        <th>State</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>
                                            <div class="row-title">Organisation Home &amp; Services</div>
                                            <div class="row-sub">Primary public landing page and navigation</div>
                                        </td>
                                        <td><code>/</code></td>
                                        <td>Updated this week</td>
                                        <td><span class="ws-status done">Published</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Client &amp; Partner Enquiry Form</div>
                                            <div class="row-sub">Structured intake form with notification routing</div>
                                        </td>
                                        <td><code>/forms/enquiry</code></td>
                                        <td>14 submissions</td>
                                        <td><span class="ws-status done">Accepting</span></td>
                                    </tr>
                                    <tr>
                                        <td>
                                            <div class="row-title">Newsroom &amp; Public Announcements</div>
                                            <div class="row-sub">Articles, press updates, and media library assets</div>
                                        </td>
                                        <td><code>/news</code></td>
                                        <td>3scheduled posts</td>
                                        <td><span class="ws-status">Active</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            </div>
                        </div>
                    </div>
                </div>
            </figure>

            <div class="stage-caption" id="workbench-caption">
                <span>Illustrative workspace &middot; Sample content, not a live account</span>
                <span>Projects &middot; Documents &middot; Memos &middot; People &middot; Forms &middot; Sites</span>
            </div>
        </div>
    </section>

    {{-- CHAPTER 01 SEPARATION: Full-Bleed Interstitial Divider with Narrative Introduction --}}
    <div class="section-separator" id="tools" aria-label="Tools chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">01 / The Tools</span>
                <span class="sep-label">Connected Operations</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Purpose-built tools for everyday work, connected to one shared engine.</h2>
                <p class="sep-text">
                    Every organisation runs on projects, documents, decisions, and people. Instead of scattering your team across disconnected SaaS subscriptions with separate logins, Unified Digital Workspace provides dedicated operational tools that read and write to the same single source of truth.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 01: THE TOOLS — Left-Aligned Architectural Grid --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="tools-title">
        <div class="tools-spec-bar">
            <div class="tools-spec-left">
                <strong id="tools-title">6 Integrated Core Modules</strong>
                <span>Shared role permissions &middot; Common document store &middot; Full audit trail</span>
            </div>
            <div class="tools-spec-right">
                <span>Tenant-Isolated Architecture</span>
            </div>
        </div>

        <div class="tools-matrix">
            <article class="tool-cell">
                <div>
                    <div class="tool-num">01</div>
                    <h3>Move work forward.</h3>
                    <p>Organise projects and tasks, track milestones and timesheets, and keep every deliverable attached to its context.</p>
                </div>
                <div class="tool-spec">Projects &amp; Tasks &middot; Time Tracking &middot; Workflows</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">02</div>
                    <h3>Keep the knowledge.</h3>
                    <p>Manage controlled document versions, share folders with the right people, and circulate official memos with sign-offs.</p>
                </div>
                <div class="tool-spec">Document Management &middot; Memos &middot; Intranet</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">03</div>
                    <h3>Support your people.</h3>
                    <p>Maintain employee records, structure departments and designations, and manage leave requests and appraisals.</p>
                </div>
                <div class="tool-spec">Employee Directory &middot; Leave &middot; Appraisals</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">04</div>
                    <h3>Put your work out there.</h3>
                    <p>Create and manage your public website with pages, posts, and media libraries alongside your internal workspace.</p>
                </div>
                <div class="tool-spec">CMS &amp; Publishing &middot; Custom Domains &middot; Media</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">05</div>
                    <h3>Collect structured requests.</h3>
                    <p>Build forms and surveys for clients, staff, or partners so incoming requests arrive organised and ready to action.</p>
                </div>
                <div class="tool-spec">Forms &amp; Surveys &middot; Submissions &middot; CRM Leads</div>
            </article>

            <article class="tool-cell">
                <div>
                    <div class="tool-num">06</div>
                    <h3>Run facilities &amp; operations.</h3>
                    <p>Track properties, leases, maintenance work orders, incidents, and vendor contracts in the same operational system.</p>
                </div>
                <div class="tool-spec">Facilities &middot; Real Estate &middot; Quality &amp; Vendors</div>
            </article>
        </div>
    </section>

    {{-- CHAPTER 02 SEPARATION: Full-Bleed Interstitial Divider with Architecture Introduction --}}
    <div class="section-separator sep-alt" id="why-unified" aria-label="Architecture chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">02 / Why Unified</span>
                <span class="sep-label">Operational Architecture</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Too many tools fragment the work. One workspace keeps it whole.</h2>
                <p class="sep-text">
                    When projects live in one app, documents in another, HR in a third, and your website somewhere else, people spend their day copying context between tabs. Unifying your digital workspace restores operational speed, clarity, and control.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 02: WHY UNIFIED — Left-Aligned Architectural Comparison --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="why-title">
        <div class="ledger-grid">
            <div class="ledger-left">
                <div>
                    <div class="index-kicker">02 / The Comparison</div>
                    <h2 id="why-title">The Single-System Advantage</h2>
                    <p>
                        When teams buy point solutions in isolation, they create friction between departments. Here is how operating in a unified workspace changes daily execution.
                    </p>

                    <ul class="ledger-highlights">
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>Zero context switching</strong>
                                <span>Tasks, files, and sign-offs in one interface.</span>
                            </div>
                        </li>
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>One source of truth</strong>
                                <span>Shared staff directory with no duplicate profiles.</span>
                            </div>
                        </li>
                        <li>
                            <span class="lh-check" aria-hidden="true">&check;</span>
                            <div>
                                <strong>Predictable monthly billing</strong>
                                <span>One transparent statement with accountable usage.</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="ledger-callout">
                    <strong>Built as one coherent workspace</strong>
                    <span>One login, one role-based permission tree, and one monthly statement.</span>
                </div>
            </div>

            <div class="ledger-right">
                <div class="ledger-row ledger-row-header">
                    <div>Dimension</div>
                    <div>Disconnected Tools</div>
                    <div>Unified Digital Workspace</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Daily Work</div>
                    <div class="ledger-old">Tasks, files, and approvals scattered across separate subscriptions and inboxes.</div>
                    <div class="ledger-new">Projects, documents, memos, and requests connected in one shared workspace.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">People &amp; Access</div>
                    <div class="ledger-old">Duplicate user accounts and mismatched permissions across five different admin panels.</div>
                    <div class="ledger-new">One member directory and role-based access model across every enabled tool.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Documents</div>
                    <div class="ledger-old">Unclear file versions and attachments lost in chat threads with no audit trail.</div>
                    <div class="ledger-new">Version-controlled document folders, official memos, and clear activity records.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Public Web</div>
                    <div class="ledger-old">A separate CMS and form tool disconnected from the team handling the work.</div>
                    <div class="ledger-new">Your public website, media library, and forms managed right inside your workspace.</div>
                </div>

                <div class="ledger-row">
                    <div class="ledger-dim">Billing</div>
                    <div class="ledger-old">Multiple recurring software invoices in different currencies and renewal cycles.</div>
                    <div class="ledger-new">Transparent website plans with predictable member access, module, and usage billing.</div>
                </div>
            </div>
        </div>
    </section>

    {{-- CHAPTER 03 SEPARATION: Full-Bleed Interstitial Divider with Pricing Introduction --}}
    <div class="section-separator" id="pricing" aria-label="Pricing chapter introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">03 / Plans &amp; Pricing</span>
                <span class="sep-label">Predictable Growth</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">A website today. A wider workspace when you need it.</h2>
                <p class="sep-text">
                    Start with a reliable public website and intake forms today, then unlock internal workspace tools and member licences as your team expands. Predictable monthly billing with zero surprise fees and full data control.
                </p>
            </div>
        </div>
    </div>

    {{-- SECTION 03: THE ICONIC BLUE STATEMENT — Pricing --}}
    <section class="rail band crosshair-tl crosshair-tr" aria-labelledby="pricing-title">
        <div class="iconic-blue-band">
            <div class="iconic-blue-left">
                <div>
                    <div class="index-kicker">Transparent Pricing</div>
                    <h2 id="pricing-title">Simple, modular investment for growing organisations.</h2>
                    <p>
                        Start with an opt-in website plan, then see how member access, business modules, and usage shape your workspace estimate as your organisation grows.
                    </p>
                </div>

                <div class="iconic-blue-actions">
                    <a class="btn btn-lg btn-white" href="{{ $pricingUrl }}">See plans &amp; estimate costs &rarr;</a>
                </div>
            </div>

            <div class="iconic-blue-right">
                <div class="plan-ledger">
                    @foreach ($websitePlans as $plan)
                        <div class="plan-row">
                            <div>
                                <div class="plan-name">{{ $plan['name'] }}</div>
                                <div class="plan-sub">Public website, CMS publishing, forms &amp; workspace foundation</div>
                            </div>
                            <div class="plan-price">
                                GHS {{ number_format($plan['GHS'], 0) }} <small>/ month</small>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="plan-footnote">
                    Opt-in website plans. Setup, additional access and usage charges may apply. Business modules are priced separately. Existing agreements keep their agreed rates.
                </p>
            </div>
        </div>
    </section>

    {{-- CLOSING SEPARATION: Full-Bleed Interstitial Divider --}}
    <div class="section-separator sep-compact sep-alt" aria-label="Closing introduction">
        <div class="separator-inner">
            <div class="separator-meta">
                <span class="sep-num">Next Step</span>
                <span class="sep-label">Launch Workspace</span>
            </div>
            <div class="separator-body">
                <h2 class="sep-heading">Give good work a clean, connected place to happen.</h2>
                <p class="sep-text">
                    Bring your projects, documents, people, and public presence into one shared workspace.
                </p>
            </div>
        </div>
    </div>

    {{-- CLOSING SECTION: Left-Aligned Action Bar --}}
    <section class="rail band" aria-labelledby="closing-title">
        <div class="closing-grid">
            <div class="closing-left">
                <h2 id="closing-title">Ready to unify your organisation's work?</h2>
                <p>Start with the tools your team needs today. Keep the work connected.</p>
            </div>
            <div class="closing-right">
                @if ($hasRegistration)
                    <a class="btn btn-lg" href="{{ route('register') }}">Create your account &rarr;</a>
                @elseif ($hasEnquiry)
                    <a class="btn btn-lg" href="{{ route('product.enterprise') }}">Plan your workspace &rarr;</a>
                @endif
                @if ($hasLogin)
                    <a class="btn btn-lg btn-outline" href="{{ route('login') }}">Log in to workspace</a>
                @endif
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script>
    function selectWorkspaceView(viewKey) {
        const pathLabels = {
            overview: 'Workspace Overview',
            projects: 'Projects & Tasks',
            documents: 'Documents & Memos',
            people: 'People & Leave',
            sites: 'Sites & Forms'
        };

        document.querySelectorAll('.stage-tab').forEach(function (tab) {
            tab.setAttribute('aria-selected', tab.id === 'tab-' + viewKey ? 'true' : 'false');
        });

        document.querySelectorAll('.app-nav-btn').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-nav') === viewKey);
        });

        document.querySelectorAll('.workspace-view').forEach(function (view) {
            view.classList.toggle('active', view.id === 'view-' + viewKey);
        });

        const pathLabel = document.getElementById('active-path-label');
        if (pathLabel && pathLabels[viewKey]) {
            pathLabel.textContent = pathLabels[viewKey];
        }
    }
</script>
@endpush
