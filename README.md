<img src=".github/banner.png" width="400">
# McLogCleaner (by JuggleGaming)

McLogCleaner automatically deletes all `.log.gz` files from the server’s `logs` folder.

> **Note:** `latest.log` will always remain intact and is never deleted.

## Setup
You install the latest version of McLogCleaner via the [Pelican Hub](https://hub.pelican.dev/plugins/mclogcleaner).

If you want to install McLogCleaner manually, just click [here](https://github.com/JuggleGaming/mclogcleaner/releases/latest/download/mclogcleaner.zip) to download the latest version.

To use this plugin, add `mclogcleaner` as a _feature_ to the egg you want to run it with.

## Log Deletion Options
When you click **Delete logs**, a dropdown menu appears where you can choose the **minimum age (in days)** of log files to delete:
- Logs older than 7 days
- Logs older than 30 days
- All logs (regardless of age)
- A custom age in days
