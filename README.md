<img src=".github/pictures/banner.png">

McLogCleaner automatically deletes all `.log.gz` files and crash reports from the server’s `logs` folder.

> **Note:** `latest.log` will always remain intact and is never deleted.

## Setup

You install the latest version of McLogCleaner via the [Pelican Hub](https://hub.pelican.dev/plugins/mclogcleaner).

If you want to install McLogCleaner manually, you can [download the latest release](https://github.com/JuggleGaming/mclogcleaner/releases/latest/download/mclogcleaner.zip) directly.

To use this plugin, add `mclogcleaner` as a _feature_ to the egg you want to run it with.

## Log Deletion Options

When you click **Delete logs**, a dropdown menu appears where you can choose the **minimum age (in days)** of log files to delete:

- Logs older than 7 days
- Logs older than 30 days
- All logs (regardless of age)
- A custom age in days

## Command usage

This command allows server administrators to clean up old log files and crash reports directly from the terminal or via scheduled tasks (cronjobs).

```bash
php artisan mclogcleaner:clean {server_uuid} [--days=7] [--logs] [--crashes] [--dry-run]
```


| Argument    | Description                                                                                         | Default value                                 |
| ----------- | --------------------------------------------------------------------------------------------------- | --------------------------------------------- |
| server_uuid | **Required.** The UUID of the server you want to clean up.                                          | none                                          |
| --days      | Specify how many days of history you want to keep. Files older than this threshold will be deleted. | 7                                             |
| --logs      | Only clean up compressed log files (`.log.gz`).                                                     | both logs and crash reports if none specified |
| --crashes   | Only clean up crash reports (`crash-*.txt`).                                                       | both logs and crash reports if none specified |
| --dry-run   | Simulates the cleanup process. It will list all target files without actually deleting them.        | false                                         |
