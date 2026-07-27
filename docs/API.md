# API Usage

All API requests go to `public/index.php?action={action}`. Send JSON bodies for POST requests and include browser cookies (`credentials: 'include'`).

`POST register` accepts `full_name`, `username`, `password`, `role`, and optional `identifier`.

`POST login` accepts `username`, `password`, and a `device_uuid` for student accounts.

Teachers use `attendance/create` with `title`, `subject_id`, and `minutes`; students submit the returned `token` to `student/scan`.

All protected actions use the PHP session created by login.
