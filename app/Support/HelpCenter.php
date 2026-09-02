<?php

namespace App\Support;

/**
 * Content for the public help centre. Articles are authored in Markdown and
 * rendered by HelpController. Add an article by adding an entry to articles();
 * category grouping and the index are derived automatically.
 */
class HelpCenter
{
    /** category key => label + icon. */
    public static function categories(): array
    {
        return [
            'basics' => ['label' => 'Getting started', 'icon' => 'rocket'],
            'attendance' => ['label' => 'Attendance & time', 'icon' => 'clock'],
            'documents' => ['label' => 'Documents & forms', 'icon' => 'file-text'],
            'people' => ['label' => 'People & org', 'icon' => 'users'],
            'admin' => ['label' => 'Admin & billing', 'icon' => 'settings'],
        ];
    }

    /** slug => [title, summary, category, body(markdown)]. */
    public static function articles(): array
    {
        return [
            'getting-started' => [
                'title' => 'Getting started',
                'summary' => 'Set up your workspace and invite your team in a few minutes.',
                'category' => 'basics',
                'body' => <<<MD
                When you create a workspace you become its **Super Admin**. Your dashboard's **Getting started** checklist walks you through the essentials, and it ticks each step off automatically as you go.

                ## The first five steps
                1. **Invite your team** — add employees so they can sign in and clock in.
                2. **Create departments** — group people into teams.
                3. **Add an office location** — used for attendance and reporting.
                4. **Publish company policies** — share handbooks for staff to acknowledge.
                5. **Brand your workspace** — add your logo and colour.

                ## Inviting people
                Go to **Employees → Invitations**. Each person gets an email link to set their password. Admin-created accounts are trusted, so they don't need to verify their email — only the workspace owner does, at signup.

                ## Roles
                Every person has a role: **Super Admin**, **HR Admin**, **Manager**, or **Employee**. Roles decide what each person can see and do — see *Roles & permissions*.
                MD,
            ],
            'clocking-in' => [
                'title' => 'Attendance & clocking in',
                'summary' => 'How employees clock in and out, and how remote/WFH works.',
                'category' => 'attendance',
                'body' => <<<MD
                Employees clock in and out from their dashboard. Attendance is recorded per day, with late arrivals, overtime and early departures calculated against their schedule.

                ## Working modes
                - **On-site / biometric** — clock in at the office (or via a connected device).
                - **Remote / work-from-home** — people working remotely show under **Working Remotely**, not "on leave". A person can be remote every day, on specific weekdays, or on a company-wide WFH day.

                ## Corrections
                If a clock-in is missing, an admin can add it from the employee's attendance — adding a clock-in clears an "absent" mark automatically. Employees can also request corrections, which managers approve.

                ## Reports
                Admins can schedule a **daily attendance report** and generate custom reports — see *Attendance reports*.
                MD,
            ],
            'requesting-time-off' => [
                'title' => 'Requesting time off',
                'summary' => 'Book leave, half-days and hourly time off, and get it approved.',
                'category' => 'attendance',
                'body' => <<<MD
                Employees request time off from the **Time Off** area. You can book a full day, a **half-day**, or **hourly** leave for appointments.

                ## Approvals
                Requests go to the person's manager (or HR). Approved leave shows on the calendar and the dashboard, with the duration and — for partial leave — the hours and time window.

                ## Policies & balances
                Leave types (annual, sick, WFH, etc.) are configured by admins under **Time Off Policies**, each with its own allowance. Balances renew on your configured leave-year schedule, with optional carry-over and encashment.
                MD,
            ],
            'documents-esign' => [
                'title' => 'Documents & e-signatures',
                'summary' => 'Share documents, collect acknowledgements, and sign online.',
                'category' => 'documents',
                'body' => <<<MD
                Trickle Hub has three document areas:

                - **Document Library** — share files with staff and require acknowledgement.
                - **Company Documents** — HR documents you send for **e-signature**: place signature fields, send, and track who has signed.
                - **HR Documents** — templated documents (offers, contracts) generated per employee, with signing.

                ## Signing
                When something needs your signature it appears under **To Sign** with a badge. Open it, review, and sign in the browser. Admins see the signing status of every recipient.

                ## Forms
                Use **Custom Forms** to collect structured responses (reviews, surveys, onboarding questionnaires), assign them to people, and review submissions.
                MD,
            ],
            'team-departments' => [
                'title' => 'Teams, departments & the org chart',
                'summary' => 'Structure your company and manage reporting lines.',
                'category' => 'people',
                'body' => <<<MD
                Organise your company under **Departments**, **Office Locations** and **Company Entities**. Assign each employee a department, a manager and a location.

                ## Managers & reporting lines
                A manager sees only their own team. Reporting lines drive who can view whose profile and approve whose requests — an employee can't view a colleague's profile, but their manager and HR can.

                ## Org chart & live board
                The **Org chart** visualises reporting lines. The **Team Management** live board shows who's in, out, remote or on leave right now.
                MD,
            ],
            'reports' => [
                'title' => 'Attendance reports & the report generator',
                'summary' => 'Scheduled reports and on-demand exports.',
                'category' => 'attendance',
                'body' => <<<MD
                ## Scheduled attendance report
                Under **Attendance Reports** you can enable a **daily report** that emails a summary on working days at a time you choose. It's guarded against duplicate sends.

                ## Report generator
                The **Report Generator** builds custom exports on demand — pick a date range and the data you need, generate, preview, and download. Past generations are kept in history so you can re-download them.
                MD,
            ],
            'roles-permissions' => [
                'title' => 'Roles & permissions',
                'summary' => 'What each role can do, and how to customise access.',
                'category' => 'admin',
                'body' => <<<MD
                Every workspace has four built-in roles:

                - **Super Admin** — full control of the company.
                - **HR Admin** — day-to-day HR across all staff.
                - **Manager** — their own team only.
                - **Employee** — self-service (their own profile, attendance, requests).

                ## Customising
                Under **Roles & Permissions** you can adjust exactly what each role can access. **Access follows the plan** too — modules that aren't in your subscription are hidden and blocked with an upgrade prompt.

                ## What a plan includes
                Your plan decides which modules are available. See the current tiers on the **Pricing** page, or **Billing & Plans** inside the app.
                MD,
            ],
            'billing-plans' => [
                'title' => 'Billing, plans & your free trial',
                'summary' => 'Trials, choosing a plan, and managing your subscription.',
                'category' => 'admin',
                'body' => <<<MD
                New workspaces start on a **free trial**. You'll see a banner counting down the days; when it ends without a plan, the workspace is limited until you choose one (your data is never deleted).

                ## Choosing a plan
                Go to **Billing & Plans**, pick a plan, and check out. Higher plans unlock more modules and seats. You can change plan at any time.

                ## Managing your subscription
                Once subscribed, use **Manage billing** to update your card or cancel. You can **export all your workspace data** as JSON from the Billing page at any time.
                MD,
            ],
            'two-factor' => [
                'title' => 'Securing your account with 2FA',
                'summary' => 'Turn on two-factor authentication for extra protection.',
                'category' => 'admin',
                'body' => <<<MD
                Two-factor authentication (2FA) adds a one-time code from an authenticator app to your login — strongly recommended for admins.

                ## Turning it on
                1. Go to **Settings → Security**.
                2. Choose **Enable two-factor** and scan the QR code with an authenticator app (Google Authenticator, 1Password, Authy…).
                3. Enter the 6-digit code to confirm.
                4. **Save your recovery codes** somewhere safe — each works once if you lose your device.

                To turn it off, enter your password under **Settings → Security**.
                MD,
            ],
        ];
    }

    public static function find(string $slug): ?array
    {
        $article = static::articles()[$slug] ?? null;

        return $article ? ['slug' => $slug] + $article : null;
    }

    /** Articles grouped by category, in category order. */
    public static function grouped(): array
    {
        $grouped = [];
        foreach (static::categories() as $key => $meta) {
            $grouped[$key] = ['meta' => $meta, 'articles' => []];
        }
        foreach (static::articles() as $slug => $a) {
            $cat = $a['category'] ?? 'basics';
            $grouped[$cat]['articles'][] = ['slug' => $slug] + $a;
        }

        return array_filter($grouped, fn ($g) => count($g['articles']) > 0);
    }
}
