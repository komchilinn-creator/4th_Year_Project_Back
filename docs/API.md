# API Usage

All API requests go to `public/index.php?action={action}`. Send JSON bodies and include browser cookies (`credentials: 'include'`) or a Bearer token returned from login.

`GET registration/subjects` lists the database subjects available during signup.

`POST register` accepts `full_name`, `username`, `password`, `role`, and optional `identifier`. Teacher registration also accepts `subject_codes` as an array. Every code is validated and saved in `teacher_subjects`; its year level always comes from `subjects`.

`POST login` accepts `username`, `password`, and a `device_uuid` for student accounts. Students are bound to their first registered device and a new student login replaces that device's previous token. Every teacher login creates an independent token, so teachers may stay signed in on multiple devices simultaneously. Logging out revokes only the token from the device making that request.

Teachers use `attendance/create` with `title`, `year_level`, `subject_id`, and `minutes` (1-240). The API verifies that the subject belongs to both the teacher and selected year, then stores that context on the session. Creating a session deactivates that teacher's previous QR code. Students submit the returned `token` to `student/scan`; the API resolves the session context, validates the student's year level, and prevents duplicate records.

`reports/monthly` returns attended sessions, total conducted sessions, and the calculated percentage. Teacher requests can include `subject_id` and `year_level`; the API accepts only assigned combinations and never combines different subjects or years.

Administrators may approve accounts with `admin/verify`, enable or disable non-admin accounts with `admin/status` (`user_id`, `status`), and reset a student's device with `admin/device/reset`.
