# API Usage

All API requests go to `public/index.php?action={action}`. Send JSON bodies and include browser cookies (`credentials: 'include'`) or a Bearer token returned from login.

`POST register` accepts `full_name`, `username`, `password`, `role`, and optional `identifier`.

`POST login` accepts `username`, `password`, and a `device_uuid` for student accounts.

Teachers use `attendance/create` with `title`, `subject_id`, and `minutes` (1-240). Creating a session deactivates that teacher's previous QR code. Students submit the returned `token` to `student/scan`; the API validates the active, unexpired token and prevents duplicate records.

`reports/monthly` returns attended sessions, total conducted sessions, and the calculated percentage. Teachers receive only their own sessions; students receive only their own report.

Administrators may approve accounts with `admin/verify`, enable or disable non-admin accounts with `admin/status` (`user_id`, `status`), and reset a student's device with `admin/device/reset`.
