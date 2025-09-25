# Trust Badge Add-on for MyListing

This repository contains a WordPress plugin that implements the Trust Badge workflow for the MyListing directory theme. The plugin ships with:

- A custom post type to track badge requests and their lifecycle (pending, approved, rejected, expired).
- REST API endpoints for listing owners and administrators to submit, review, approve, reject, and extend requests.
- MyListing dashboard integrations that let listing owners request badges and copy embed codes directly from their account area.
- Admin pages for reviewing requests and configuring global badge settings such as default validity periods, reminder timing, and allowed document MIME types.
- A dynamic badge renderer with both script and iframe delivery options backed by a secure token.
- Cron automation to expire badges automatically when they reach their validity limit.

To install, copy the `trust-badge` directory into your WordPress installation's `wp-content/plugins/` folder and activate **Trust Badge for MyListing** from the Plugins screen.
