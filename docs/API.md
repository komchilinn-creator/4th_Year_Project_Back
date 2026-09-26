# API Usage

All API requests go to `public/index.php?action={action}`. Send JSON bodies and include browser cookies (`credentials: 'include'`) or a Bearer token returned from login.

`GET registration/subjects` lists the database subjects available during signup.

`POST register` accepts `full_name`, `username`, `password`, and `role`. Students send their roll number as `identifier` (for example, `4IT15`); the API derives class `4IT` and year `4`, which determine their available subjects. Teachers send one class or a comma-separated class list such as `3IT,4IT` plus `subject_codes` as an array. Every selected teacher subject must belong to one of those classes and is saved in `teacher_subjects`.

`POST login` accepts `username`, `password`, and a `device_uuid` for student accounts. Students are bound to their first registered device and a new student login replaces that device's previous token. Every teacher login creates an independent token, so teachers may stay signed in on multiple devices simultaneously. Logging out revokes only the token from the device making that request.

Teachers use `attendance/create` with `title`, `subject_id`, and `minutes` (1-240). For multi-class teachers, the API derives the session's single class and year from the selected assigned subject, then stores the class, year, and subject on the session. Creating a session deactivates that teacher's previous QR code. Students submit the returned `token` to `student/scan`; the API resolves the session context, validates the student's class/year, and prevents duplicate records.

`reports/monthly` returns attended sessions, total conducted sessions, and the calculated percentage. Teacher requests can include `subject_id` and `year_level`; the API accepts only assigned combinations and never combines different subjects or years.

Administrators may approve accounts with `admin/verify`, enable or disable non-admin accounts with `admin/status` (`user_id`, `status`), and reset a student's device with `admin/device/reset`.
