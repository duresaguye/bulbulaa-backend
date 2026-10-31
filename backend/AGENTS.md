
# Reven Town Administration System

## Project purpose
Build a simple town administration platform for a small town in Ethiopia.

The system has three connected parts:
1. Public town website and content management.
2. Internal department planning and progress reporting.
3. HR employee attendance management.

The goal is to solve real administrative problems with a system that is easy for government staff to use and maintain.

## Current project status
- The public website already exists and uses Next.js.
- The internal administration backend has not been built yet.
- Requirements still need to be confirmed with the government contact.
- Do not assume that every proposed feature is approved.

## Proposed technology
- Backend: Laravel.
- Admin interface: Filament.
- Database: MySQL.
- Existing public website: Next.js and TypeScript.
- Target deployment: cPanel/shared hosting, subject to host compatibility.

Do not select framework versions until the installed PHP version and hosting requirements are checked.

## Core features

### Department planning and reporting
- Town leadership defines annual targets for departments.
- Departments create monthly plans aligned with annual targets.
- Authorized staff submit simple weekly progress reports.
- Monthly results are compared with monthly targets.
- Submitted internal reports are visible immediately to authorized users.
- Do not create an approval queue unless explicitly requested.

### HR attendance
- HR or an authorized person enters attendance from paper records.
- Attendance records are private.
- Support agreed statuses such as present, absent, late, and leave.
- Provide monthly attendance summaries.

### Public website and CMS
- Staff manage public news, announcements, office directories,
  services, and documents without editing source code.
- Support Amharic and Afaan Oromo.
- English may be added if confirmed.
- Only published public content is exposed to the public website.

## Development rules
- Build incrementally. Do not implement the entire system in one task.
- Before making large changes, explain the proposed changes.
- Do not overwrite or recreate the existing Next.js website.
- Do not add unnecessary features or dependencies.
- Do not use Docker, microservices, or complex infrastructure
  unless specifically requested.
- Keep code readable and suitable for a small team to maintain.
- Use migrations for database changes.
- Use seeders with fictional data for development and demos.
- Never commit passwords, API keys, or production credentials.
- Never use real employee or government data in demo seeders.

## Security requirements
- Enforce role-based permissions on the backend.
- Do not rely only on hiding buttons in the frontend.
- Restrict attendance and internal reports to authorized users.
- Keep private files outside publicly accessible directories.
- Validate user input.
- Keep an audit history for important changes.
- Do not expose unpublished CMS content through public APIs.

## Agent workflow
1. Inspect the existing files and environment before changing anything.
2. State what you plan to do.
3. Implement only the requested task.
4. Run relevant tests and checks.
5. Report files changed, commands run, and any remaining issues.
6. Do not claim something works unless it has been tested.

## First task
Inspect the development environment and help scaffold the Laravel
backend only after confirming PHP and Composer compatibility.

Do not implement reporting, attendance, CMS, or authentication
features until the backend foundation is verified.
