<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'ZenonERP') }} Installer</title>
    <style>
        /*
         * Deliberately no framework, no icon font, no web font (CLAUDE.md §7 Phase 8 Task 7 —
         * this view must render before any build step or database exists: zero external
         * assets, inline only). System-font stack, light theme only.
         */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f4f5f7;
            color: #1f2430;
            line-height: 1.5;
        }

        header.top {
            padding: 1.25rem 2rem;
            background: #1f2430;
            color: #fff;
        }

        header.top h1 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        header.top p {
            margin: 0.15rem 0 0;
            font-size: 0.85rem;
            color: #b7bcc9;
        }

        .layout {
            display: flex;
            max-width: 960px;
            margin: 2rem auto;
            gap: 1.5rem;
            padding: 0 1rem;
            align-items: flex-start;
        }

        nav.steps {
            flex: 0 0 220px;
            background: #fff;
            border: 1px solid #dde0e6;
            border-radius: 6px;
            overflow: hidden;
        }

        nav.steps ol {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        nav.steps li {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #eef0f4;
            cursor: pointer;
            font-size: 0.9rem;
        }

        nav.steps li:last-child { border-bottom: none; }

        nav.steps li .step-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: #e5e7eb;
            color: #4b5160;
            font-size: 0.75rem;
            flex: 0 0 auto;
        }

        nav.steps li.step-active { background: #eef2ff; }
        nav.steps li.step-active .step-index { background: #3949ab; color: #fff; }
        nav.steps li.step-done .step-index { background: #2e7d32; color: #fff; }

        .step-label { flex: 1 1 auto; }
        .step-state { font-size: 0.75rem; color: #6b7280; }

        main.panel {
            flex: 1 1 auto;
            background: #fff;
            border: 1px solid #dde0e6;
            border-radius: 6px;
            padding: 1.5rem 1.75rem;
            min-width: 0;
        }

        section.step-panel h2 {
            margin-top: 0;
            font-size: 1.1rem;
        }

        .hint {
            font-size: 0.85rem;
            color: #5b6072;
        }

        .banner {
            border: 1px solid #d33;
            background: #fdecec;
            color: #7a1717;
            padding: 0.65rem 0.85rem;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            white-space: pre-wrap;
        }

        table.requirements {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        table.requirements th,
        table.requirements td {
            text-align: left;
            padding: 0.4rem 0.5rem;
            border-bottom: 1px solid #eef0f4;
        }

        .status-pill {
            display: inline-block;
            padding: 0.1rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pass { background: #e3f3e5; color: #2e7d32; }
        .status-warn { background: #fdf3d8; color: #8a6d1a; }
        .status-fail { background: #fbe0e0; color: #a02121; }

        fieldset {
            border: 1px solid #e2e4ea;
            border-radius: 5px;
            margin: 0 0 1rem;
            padding: 0.75rem 1rem 1rem;
        }

        legend {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4b5160;
            padding: 0 0.35rem;
        }

        .field {
            display: block;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }

        .field span.field-label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        .field input,
        .field select {
            width: 100%;
            padding: 0.4rem 0.5rem;
            border: 1px solid #ccced6;
            border-radius: 4px;
            font-size: 0.9rem;
            font-family: inherit;
        }

        .field-error {
            display: block;
            color: #a02121;
            font-size: 0.75rem;
            min-height: 1em;
            margin-top: 0.2rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 1rem;
        }

        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.6rem;
            margin-top: 0.5rem;
        }

        button {
            font-family: inherit;
            font-size: 0.9rem;
            padding: 0.5rem 1.1rem;
            border-radius: 4px;
            border: 1px solid #ccced6;
            background: #fff;
            color: #1f2430;
            cursor: pointer;
        }

        button.btn-primary {
            background: #3949ab;
            border-color: #3949ab;
            color: #fff;
        }

        button:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        [hidden] { display: none !important; }
    </style>
</head>
<body>
    <header class="top">
        <h1>{{ config('app.name', 'ZenonERP') }}</h1>
        <p>Standalone installer</p>
    </header>

    <div class="layout">
        <nav class="steps" aria-label="Installation steps">
            <ol id="step-list">
                <li data-step="requirements"><span class="step-index">1</span><span class="step-label">Requirements</span><span class="step-state" data-step-state></span></li>
                <li data-step="database"><span class="step-index">2</span><span class="step-label">Database</span><span class="step-state" data-step-state></span></li>
                <li data-step="migrate"><span class="step-index">3</span><span class="step-label">Migrate</span><span class="step-state" data-step-state></span></li>
                <li data-step="tenant"><span class="step-index">4</span><span class="step-label">Tenant</span><span class="step-state" data-step-state></span></li>
                <li data-step="admin"><span class="step-index">5</span><span class="step-label">Admin</span><span class="step-state" data-step-state></span></li>
                <li data-step="finalize"><span class="step-index">6</span><span class="step-label">Finish</span><span class="step-state" data-step-state></span></li>
            </ol>
        </nav>

        <main class="panel">
            <div id="banner-error" class="banner" role="alert" hidden></div>

            <section class="step-panel" data-panel="requirements" id="panel-requirements">
                <h2>Requirements</h2>
                <p class="hint">Preflight checks the wizard needs to pass (or at least not fail) before it can write anything.</p>
                <table class="requirements">
                    <thead>
                        <tr><th>Check</th><th>Status</th><th>Detail</th></tr>
                    </thead>
                    <tbody id="requirements-body"></tbody>
                </table>
                <div class="actions">
                    <button type="button" id="requirements-recheck">Re-check</button>
                    <button type="button" id="requirements-next" class="btn-primary" disabled>Next</button>
                </div>
            </section>

            <section class="step-panel" data-panel="database" id="panel-database" hidden>
                <h2>Database</h2>
                <form id="form-database" novalidate>
                    <fieldset>
                        <legend>Application</legend>
                        <label class="field">
                            <span class="field-label">Application name</span>
                            <input name="app_name" type="text" maxlength="100" required>
                            <small class="field-error" data-error-for="app_name"></small>
                        </label>
                        <label class="field">
                            <span class="field-label">Site URL</span>
                            <input name="app_url" type="text" maxlength="255" required>
                            <small class="field-error" data-error-for="app_url"></small>
                        </label>
                        <label class="field">
                            <span class="field-label">Database driver</span>
                            <select name="driver">
                                <option value="mysql">MySQL</option>
                                <option value="mariadb">MariaDB</option>
                            </select>
                            <small class="field-error" data-error-for="driver"></small>
                        </label>
                    </fieldset>

                    <fieldset>
                        <legend>Central database</legend>
                        <p class="hint">The pre-created database that stores platform-wide data (tenants, installed modules).</p>
                        <div class="grid-2">
                            <label class="field">
                                <span class="field-label">Host</span>
                                <input name="central.host" type="text" maxlength="255" placeholder="127.0.0.1">
                                <small class="field-error" data-error-for="central.host"></small>
                            </label>
                            <label class="field">
                                <span class="field-label">Port</span>
                                <input name="central.port" type="text" maxlength="10" placeholder="3306">
                                <small class="field-error" data-error-for="central.port"></small>
                            </label>
                        </div>
                        <label class="field">
                            <span class="field-label">Database name</span>
                            <input name="central.database" type="text" maxlength="255" required>
                            <small class="field-error" data-error-for="central.database"></small>
                        </label>
                        <div class="grid-2">
                            <label class="field">
                                <span class="field-label">Username</span>
                                <input name="central.username" type="text" maxlength="255">
                                <small class="field-error" data-error-for="central.username"></small>
                            </label>
                            <label class="field">
                                <span class="field-label">Password</span>
                                <input name="central.password" type="password" maxlength="255">
                                <small class="field-error" data-error-for="central.password"></small>
                            </label>
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend>Tenant database</legend>
                        <p class="hint">The pre-created database for this install's single tenant. Leave host/username/password blank to reuse the central database's values (the common case of one DB user granted on both).</p>
                        <div class="grid-2">
                            <label class="field">
                                <span class="field-label">Host</span>
                                <input name="tenant.host" type="text" maxlength="255" placeholder="same as central">
                                <small class="field-error" data-error-for="tenant.host"></small>
                            </label>
                            <label class="field">
                                <span class="field-label">Port</span>
                                <input name="tenant.port" type="text" maxlength="10" placeholder="same as central">
                                <small class="field-error" data-error-for="tenant.port"></small>
                            </label>
                        </div>
                        <label class="field">
                            <span class="field-label">Database name</span>
                            <input name="tenant.database" type="text" maxlength="255" required>
                            <small class="field-error" data-error-for="tenant.database"></small>
                        </label>
                        <div class="grid-2">
                            <label class="field">
                                <span class="field-label">Username</span>
                                <input name="tenant.username" type="text" maxlength="255" placeholder="same as central">
                                <small class="field-error" data-error-for="tenant.username"></small>
                            </label>
                            <label class="field">
                                <span class="field-label">Password</span>
                                <input name="tenant.password" type="password" maxlength="255" placeholder="same as central">
                                <small class="field-error" data-error-for="tenant.password"></small>
                            </label>
                        </div>
                    </fieldset>

                    <div class="actions">
                        <button type="submit" class="btn-primary">Next</button>
                    </div>
                </form>
            </section>

            <section class="step-panel" data-panel="migrate" id="panel-migrate" hidden>
                <h2>Migrate</h2>
                <p class="hint">Creates the central tables (tenants, domains, modules, tenant_modules, jobs, cache, sessions) on the database configured in the previous step. Safe to run again.</p>
                <div class="actions">
                    <button type="button" id="migrate-run" class="btn-primary">Run migrations</button>
                </div>
            </section>

            <section class="step-panel" data-panel="tenant" id="panel-tenant" hidden>
                <h2>Tenant</h2>
                <p class="hint">A standalone install has exactly one tenant. Give it a display name.</p>
                <form id="form-tenant" novalidate>
                    <label class="field">
                        <span class="field-label">Company / tenant name</span>
                        <input name="name" type="text" maxlength="100" required>
                        <small class="field-error" data-error-for="name"></small>
                    </label>
                    <div class="actions">
                        <button type="submit" class="btn-primary">Next</button>
                    </div>
                </form>
            </section>

            <section class="step-panel" data-panel="admin" id="panel-admin" hidden>
                <h2>Administrator account</h2>
                <p class="hint">This account gets full access to the tenant once installation finishes.</p>
                <form id="form-admin" novalidate>
                    <label class="field">
                        <span class="field-label">Name</span>
                        <input name="name" type="text" maxlength="100" required>
                        <small class="field-error" data-error-for="name"></small>
                    </label>
                    <label class="field">
                        <span class="field-label">Email</span>
                        <input name="email" type="email" maxlength="255" required>
                        <small class="field-error" data-error-for="email"></small>
                    </label>
                    <label class="field">
                        <span class="field-label">Password</span>
                        <input name="password" type="password" minlength="8" required>
                        <small class="field-error" data-error-for="password"></small>
                    </label>
                    <div class="actions">
                        <button type="submit" class="btn-primary">Next</button>
                    </div>
                </form>
            </section>

            <section class="step-panel" data-panel="finalize" id="panel-finalize" hidden>
                <h2>Finish</h2>
                <p class="hint">Everything is provisioned. Finishing locks the installer (it will no longer be reachable) and opens the application.</p>
                <div class="actions">
                    <button type="button" id="finalize-run" class="btn-primary">Finish</button>
                </div>
            </section>
        </main>
    </div>

    <script>
    (function () {
        'use strict';

        // Step -> endpoint contract (routes/installer.php + InstallerController). Kept as a
        // plain comment, not code, because each step's request shape differs too much to
        // generalize usefully:
        //   requirements  GET  /install/api/requirements  -> { data: { ok, items[] } }        (not tracked by status; pure preflight, re-run any time)
        //   database      POST /install/api/database      { app_name, app_url, driver?, central{database,host?,port?,username?,password?}, tenant{database,host?,port?,username?,password?} }
        //   migrate       POST /install/api/migrate        {}                                  (no payload)
        //   tenant        POST /install/api/tenant         { name }
        //   admin         POST /install/api/admin          { name, email, password }
        //   finalize      POST /install/api/finalize       {}  -> { data: { redirect } }
        //   status        GET  /install/api/status         -> { data: { steps: { database, migrate, tenant, admin, finalize } } }
        //                 drives the on-load "resume" jump to the first incomplete step below.
        var STEP_ORDER = ['requirements', 'database', 'migrate', 'tenant', 'admin', 'finalize'];
        var STATUS_KEYS = ['database', 'migrate', 'tenant', 'admin', 'finalize'];

        var state = {
            active: 'requirements',
            completed: {},
        };

        function byId(id) { return document.getElementById(id); }

        var banner = byId('banner-error');
        var stepList = byId('step-list');

        // --- fetch helper --------------------------------------------------------------
        // A network-level failure (fetch() itself rejects — e.g. the server process dies
        // mid-request, the classic shared-hosting execution-time kill during the migrate
        // step) is shaped into the SAME { error: { message } } result shape a normal
        // non-2xx response produces, rather than left to reject and bubble past every
        // caller. That keeps exactly one catch site: every caller already awaits api()
        // and always reaches its own setBusy(button, false)/handleFailure() afterward, so
        // a rejected fetch can no longer leave a button stuck disabled with no message.
        async function api(method, path, body) {
            var opts = { method: method, headers: { Accept: 'application/json' } };

            if (body !== undefined) {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(body);
            }

            var response;

            try {
                response = await fetch(path, opts);
            } catch (networkError) {
                return {
                    ok: false,
                    status: 0,
                    json: { error: { message: 'Network error — the server did not respond. Check the server logs, then retry.' } },
                };
            }

            var json = null;

            try {
                json = await response.json();
            } catch (parseError) {
                json = null;
            }

            return { ok: response.ok, status: response.status, json: json };
        }

        // --- error / banner surfaces -----------------------------------------------------
        function clearBanner() {
            banner.hidden = true;
            banner.textContent = '';
        }

        function showBanner(message) {
            banner.textContent = message;
            banner.hidden = false;
        }

        function clearFieldErrors(form) {
            var nodes = form.querySelectorAll('.field-error');
            for (var i = 0; i < nodes.length; i++) {
                nodes[i].textContent = '';
            }
        }

        function applyFieldErrors(form, errors) {
            Object.keys(errors).forEach(function (field) {
                var target = form.querySelector('[data-error-for="' + field + '"]');
                var messages = errors[field];

                if (target && Array.isArray(messages) && messages.length > 0) {
                    // textContent only — these are server-provided strings (validation
                    // messages), never inserted as markup.
                    target.textContent = messages[0];
                }
            });
        }

        // Two distinct 422 shapes reach this wizard (bootstrap/app.php explicitly does NOT
        // wrap install/api/* in the api/* error envelope): the framework's default
        // FormRequest validation failure ({ message, errors: { field: [...] } }) and each
        // controller action's own flat business-error shape ({ error: { type, message, ... } },
        // e.g. InstallerController::database()'s database_connection_failed). Handle both;
        // never innerHTML server text.
        function handleFailure(form, result) {
            var json = result.json;

            if (!json) {
                showBanner('Unexpected error (HTTP ' + result.status + ').');
                return;
            }

            if (json.errors) {
                if (form) {
                    applyFieldErrors(form, json.errors);
                }

                showBanner(json.message || 'Please fix the highlighted fields.');
                return;
            }

            if (json.error) {
                var parts = [json.error.message || 'Something went wrong.'];

                if (json.error.central) {
                    parts.push('Central database: ' + json.error.central);
                }

                if (json.error.tenant) {
                    parts.push('Tenant database: ' + json.error.tenant);
                }

                showBanner(parts.join(' '));
                return;
            }

            showBanner(json.message || ('Unexpected error (HTTP ' + result.status + ').'));
        }

        // --- step navigation ---------------------------------------------------------------
        function go(stepId) {
            state.active = stepId;

            STEP_ORDER.forEach(function (id) {
                var panel = byId('panel-' + id);

                if (panel) {
                    panel.hidden = id !== stepId;
                }
            });

            renderStepList();
            clearBanner();
        }

        function nextStep(stepId) {
            var index = STEP_ORDER.indexOf(stepId);

            return index === -1 || index === STEP_ORDER.length - 1 ? stepId : STEP_ORDER[index + 1];
        }

        function renderStepList() {
            var items = stepList.querySelectorAll('li[data-step]');

            for (var i = 0; i < items.length; i++) {
                var li = items[i];
                var id = li.getAttribute('data-step');
                var stateEl = li.querySelector('[data-step-state]');
                var isActive = id === state.active;
                var isDone = !! state.completed[id];

                li.className = (isActive ? 'step-active ' : '') + (isDone ? 'step-done' : '');

                if (stateEl) {
                    stateEl.textContent = isDone ? 'Done' : (isActive ? 'In progress' : '');
                }
            }
        }

        stepList.addEventListener('click', function (event) {
            var li = event.target.closest('li[data-step]');

            if (li) {
                go(li.getAttribute('data-step'));
            }
        });

        // --- form payload helper (dot-path names -> nested JSON, e.g. "central.database") ---
        function formToPayload(form) {
            var payload = {};
            var data = new FormData(form);

            data.forEach(function (value, key) {
                var parts = key.split('.');
                var cursor = payload;

                for (var i = 0; i < parts.length - 1; i++) {
                    if (typeof cursor[parts[i]] !== 'object' || cursor[parts[i]] === null) {
                        cursor[parts[i]] = {};
                    }

                    cursor = cursor[parts[i]];
                }

                cursor[parts[parts.length - 1]] = value;
            });

            return payload;
        }

        function setBusy(button, busy) {
            button.disabled = busy;
        }

        // --- requirements step -------------------------------------------------------------
        function renderRequirements(items) {
            var tbody = byId('requirements-body');

            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }

            items.forEach(function (item) {
                var tr = document.createElement('tr');

                var labelCell = document.createElement('td');
                labelCell.textContent = item.label;

                var statusCell = document.createElement('td');
                var pill = document.createElement('span');
                pill.className = 'status-pill status-' + item.status;
                pill.textContent = item.status;
                statusCell.appendChild(pill);

                var detailCell = document.createElement('td');
                detailCell.textContent = item.detail || '';

                tr.appendChild(labelCell);
                tr.appendChild(statusCell);
                tr.appendChild(detailCell);
                tbody.appendChild(tr);
            });
        }

        async function loadRequirements() {
            var result = await api('GET', '/install/api/requirements');

            if (!result.ok || !result.json || !result.json.data) {
                showBanner('Could not load the requirements check (HTTP ' + result.status + ').');
                return false;
            }

            var data = result.json.data;
            renderRequirements(data.items);
            byId('requirements-next').disabled = !data.ok;

            if (!data.ok) {
                showBanner('Resolve the failing requirements below before continuing.');
            }

            return !!data.ok;
        }

        byId('requirements-recheck').addEventListener('click', async function () {
            clearBanner();
            var ok = await loadRequirements();
            state.completed.requirements = ok;
            renderStepList();
        });

        byId('requirements-next').addEventListener('click', function () {
            go('database');
        });

        // --- generic form-step binder --------------------------------------------------
        function bindFormStep(formId, stepId, endpoint) {
            var form = byId(formId);

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                clearBanner();
                clearFieldErrors(form);

                var submitButton = form.querySelector('button[type="submit"]');
                setBusy(submitButton, true);

                var payload = formToPayload(form);
                var result = await api('POST', endpoint, payload);

                setBusy(submitButton, false);

                if (result.ok) {
                    state.completed[stepId] = true;
                    go(nextStep(stepId));
                } else {
                    handleFailure(form, result);
                }
            });
        }

        bindFormStep('form-database', 'database', '/install/api/database');
        bindFormStep('form-tenant', 'tenant', '/install/api/tenant');
        bindFormStep('form-admin', 'admin', '/install/api/admin');

        // --- plain-button steps (no fields to submit) -----------------------------------
        byId('migrate-run').addEventListener('click', async function () {
            clearBanner();
            setBusy(this, true);
            var result = await api('POST', '/install/api/migrate', {});
            setBusy(this, false);

            if (result.ok) {
                state.completed.migrate = true;
                go(nextStep('migrate'));
            } else {
                handleFailure(null, result);
            }
        });

        byId('finalize-run').addEventListener('click', async function () {
            clearBanner();
            setBusy(this, true);
            var result = await api('POST', '/install/api/finalize', {});

            if (result.ok) {
                var redirect = (result.json && result.json.data && result.json.data.redirect) || '/';
                window.location.href = redirect;
                return;
            }

            setBusy(this, false);
            handleFailure(null, result);
        });

        // --- boot: prefill + status-driven resume ---------------------------------------
        function firstIncompleteStep(steps) {
            if (!steps) {
                return 'database';
            }

            for (var i = 0; i < STATUS_KEYS.length; i++) {
                if (!steps[STATUS_KEYS[i]]) {
                    return STATUS_KEYS[i];
                }
            }

            return 'finalize';
        }

        async function init() {
            var appUrlInput = document.querySelector('#form-database [name="app_url"]');

            if (appUrlInput && !appUrlInput.value) {
                appUrlInput.value = window.location.origin;
            }

            var requirementsOk = await loadRequirements();
            state.completed.requirements = requirementsOk;

            var statusResult = await api('GET', '/install/api/status');
            var steps = (statusResult.ok && statusResult.json && statusResult.json.data)
                ? statusResult.json.data.steps
                : null;

            if (steps) {
                STATUS_KEYS.forEach(function (key) {
                    if (steps[key]) {
                        state.completed[key] = true;
                    }
                });
            }

            if (!requirementsOk) {
                go('requirements');
                return;
            }

            go(firstIncompleteStep(steps));
        }

        init();
    })();
    </script>
</body>
</html>
