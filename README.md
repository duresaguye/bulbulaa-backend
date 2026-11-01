# Reven Town System

A town administration platform built by Reven Labs.

## Modules
- Department planning and reporting
- Public website CMS (later)

## Development
See `AGENTS.md` for project rules and current scope.

## Status
Initial project setup. Requirements and hosting compatibility
are being confirmed.
## Free demo on Render

`render.yaml` sets up a free demo of the admin and the public API on [Render](https://render.com):

1. Sign in to Render with GitHub, choose **New → Blueprint** and pick this repository.
2. Render builds `backend/Dockerfile.demo` and gives the service an address such as `https://bulbulaa-demo.onrender.com`.
3. Sign in at `/admin` as `admin@demo.test` with the password `demo-password`.

The demo runs on SQLite and loads the fictional demo data every time it starts. Nothing is kept: Render's free plan
stops the service after 15 minutes without visitors, and the next visit starts it again with fresh demo data (the
first page then takes about a minute). Never put real staff or citizen information in it.

To connect the public website, set `CMS_API_URL` and `NEXT_PUBLIC_CMS_API_URL` to the demo address followed by
`/api/public` in the website's hosting settings (for example on Vercel).
